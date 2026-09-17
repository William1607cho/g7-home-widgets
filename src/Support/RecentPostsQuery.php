<?php

namespace Plugins\G7\Home\Widgets\Support;

use App\Helpers\PermissionHelper;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Sirsoft\Board\Models\Board;
use Modules\Sirsoft\Board\Models\Post;
use Modules\Sirsoft\Board\Traits\FormatsBoardDate;
use Plugins\G7\Home\Widgets\Http\Requests\RecentPostsRequest;

/**
 * 게시판 무관 "전체 최근글" 통합 조회.
 *
 * `sirsoft-board` 코어의 `BoardRepository::getRecentPosts()`와 완전히 같은 계약— 활성
 * 게시판을 대상으로 게시판별 서브쿼리를 `UNION ALL`로 합쳐 전체 최근글을 뽑는다(단일
 * `WHERE board_id IN (...) ORDER BY created_at DESC LIMIT N` 은 게시판 여럿에 걸친
 * 정렬이라 인덱스를 못 타 풀스캔이 난다 — 코어가 이미 겪은 문제, 코드 주석에 남아있다).
 * 각 서브쿼리는 코어 마이그레이션이 이미 만들어 둔
 * `idx_board_posts_board_status_created (board_id, status, created_at)` 를 그대로 탄다
 * — 이 애드온은 새 인덱스를 요구하지 않는다.
 *
 * 코어와 다른 점은 둘뿐이다: ① `category` 필드를 추가로 select 한다(코어 응답엔 없음).
 * ② 권한 필터링을 코어의 `BoardService::filterItemsByBoardReadPermission()`(private)을
 * 호출하는 대신, 그 메서드가 쓰는 것과 동일한 공개 유틸(`App\Helpers\PermissionHelper::check()`)
 * 로 이 클래스 안에 다시 구현한다 — "안전집합 캐시 + 응답시점 권한필터" 패턴은 동일하게
 * 유지한다: 캐시엔 게시판 활성 여부만 반영된 안전집합을 담고, 게시판별
 * `sirsoft-board.{slug}.posts.read` 권한은 캐시가 아니라 매 요청 시점에 현재 사용자
 * 기준으로 적용한다(고권한 사용자의 결과가 캐시에 남아 저권한/비회원에게 새는 것을 방지).
 *
 * 0.3.0: 관리자가 고른 제외 게시판({@see BoardFilterSettings})을 안전집합 조회 단계에서 뺀다
 * — 제외된 게시판 글이 풀 자리를 차지하지 않아 요청 limit 이 그대로 채워진다. 캐시 키에는
 * 제외 목록 지문이 들어간다.
 */
class RecentPostsQuery
{
    use FormatsBoardDate;

    /** 안전집합 캐시 TTL(초) — 짧게 두어 이벤트 기반 무효화 없이도 신선도를 보장한다 */
    private const CACHE_TTL_SECONDS = 90;

    /** 안전집합 풀 최소 크기 — 권한 필터링 후에도 요청 limit 만큼 남도록 여유를 둔다 */
    private const MIN_POOL_SIZE = 50;

    /**
     * 현재 사용자 기준으로 권한 필터링된 전체 최근글을 반환한다.
     *
     * @param  int  $limit  반환할 최대 건수
     * @return array<int, array<string, mixed>>
     */
    public function forCurrentUser(int $limit): array
    {
        $poolLimit = self::poolSizeFor($limit);
        $pool = $this->cachedSafePool($poolLimit, BoardFilterSettings::excludedIds());

        $user = Auth::user();

        return collect($pool)
            ->filter(fn (array $post) => $this->canReadBoard($post['board_slug'], $user))
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * 요청 limit 에 대한 안전집합 풀 크기.
     *
     * @param  int  $limit  요청 limit
     */
    private static function poolSizeFor(int $limit): int
    {
        return max(self::MIN_POOL_SIZE, $limit * 5);
    }

    /**
     * 안전집합 캐시 키.
     *
     * @param  string  $fingerprint  제외 목록 지문
     * @param  int  $poolLimit  풀 크기
     */
    private static function cacheKey(string $fingerprint, int $poolLimit): string
    {
        return "g7_home_widgets_recent_posts_pool_{$fingerprint}_{$poolLimit}";
    }

    /**
     * 지정한 지문으로 만들어질 수 있는 캐시 키 전체 (limit 0 ~ 상한).
     *
     * @param  string  $fingerprint  제외 목록 지문
     * @return array<int, string>
     */
    public static function cacheKeysFor(string $fingerprint): array
    {
        $keys = [];
        for ($limit = 0; $limit <= RecentPostsRequest::MAX_LIMIT; $limit++) {
            $keys[] = self::cacheKey($fingerprint, self::poolSizeFor($limit));
        }

        return array_values(array_unique($keys));
    }

    /**
     * 게시판별 읽기 권한을 확인한다.
     *
     * `sirsoft-board.{slug}.posts.read` — 코어의 `BoardService::filterItemsByBoardReadPermission()`,
     * `SearchPostsListener::searchPosts()` 가 쓰는 것과 동일한 권한 식별자.
     *
     * @param  string|null  $boardSlug  게시판 슬러그
     * @param  Authenticatable|null  $user  기준 사용자 (null 이면 비회원)
     */
    private function canReadBoard(?string $boardSlug, ?Authenticatable $user): bool
    {
        if ($boardSlug === null) {
            return false;
        }

        return PermissionHelper::check("sirsoft-board.{$boardSlug}.posts.read", $user);
    }

    /**
     * 안전집합(사용자 무관, 활성 게시판 + 발행 상태만) 캐시 조회.
     *
     * 권한은 절대 이 단계에서 걸지 않는다 — 캐시는 모든 사용자가 공유하므로, 여기서
     * 권한을 걸면 고권한 요청이 채운 캐시를 저권한/비회원 요청이 그대로 받아 정보가 샌다.
     *
     * @param  int  $poolLimit  풀 크기
     * @param  array<int, int>  $excludedBoardIds  제외 게시판 ID (정리된 목록)
     * @return array<int, array<string, mixed>>
     */
    private function cachedSafePool(int $poolLimit, array $excludedBoardIds): array
    {
        return Cache::remember(
            self::cacheKey(BoardFilterSettings::fingerprint($excludedBoardIds), $poolLimit),
            self::CACHE_TTL_SECONDS,
            fn () => $this->querySafePool($poolLimit, $excludedBoardIds)
        );
    }

    /**
     * 안전집합을 실제로 조회한다 — `BoardRepository::getRecentPosts()`와 동일한
     * UNION ALL 구조(게시판별 서브쿼리 → ID 합집합 정렬·상한 → 최종 재조회).
     *
     * @param  int  $limit  풀 크기
     * @param  array<int, int>  $excludedBoardIds  제외 게시판 ID
     * @return array<int, array<string, mixed>>
     */
    private function querySafePool(int $limit, array $excludedBoardIds = []): array
    {
        // audit:allow query-unbounded-get reason: boards 는 운영자 등록 설정성 테이블 — 행 수가 운영자 행위에 묶여 데이터 증가에 비례하지 않는다 (코어 BoardRepository::getRecentPosts()와 동일 근거)
        $activeBoardIds = Board::where('is_active', true)
            ->when($excludedBoardIds !== [], fn ($query) => $query->whereNotIn('id', $excludedBoardIds))
            ->pluck('id');

        if ($activeBoardIds->isEmpty()) {
            return [];
        }

        $columns = ['id', 'board_id', 'title', 'category', 'author_name', 'created_at', 'view_count', 'is_secret', 'comments_count'];

        // board_id IN (...) + ORDER BY created_at DESC 는 게시판 여럿에 걸친 정렬이라
        // 단일 인덱스로 못 커버한다 — 게시판별 서브쿼리(각각 idx_board_posts_board_status_created
        // 를 탐)를 UNION ALL 로 합쳐 우회한다. 코어 BoardRepository::getRecentPosts()와 동일 기법.
        $subQueries = $activeBoardIds->map(
            fn ($boardId) => Post::query()
                ->select($columns)
                ->where('board_id', $boardId)
                ->whereNull('deleted_at')
                ->whereNull('parent_id')
                ->where('status', 'published')
                // 비밀글도 제목은 공개한다(본문만 보호) — 2026-02-04 확정 정책, 목록/검색/홈과 동일.
                // 게시판별 열람 권한은 이 클래스의 forCurrentUser() 가 응답 시점에 적용한다.
                ->orderBy('created_at', 'desc')
                ->limit($limit)
        );

        $unionQuery = $subQueries->shift();
        foreach ($subQueries as $sub) {
            $unionQuery = $unionQuery->unionAll($sub);
        }

        $postIds = DB::query()
            ->fromSub($unionQuery, 'sub')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->pluck('id');

        $posts = Post::query()
            ->with('board:id,slug,name')
            ->select($columns)
            ->whereIn('id', $postIds)
            ->orderBy('created_at', 'desc')
            ->get();

        $newHours = g7_module_settings('sirsoft-board', 'basic_defaults.new_display_hours', 24);

        return $posts->map(fn ($post) => [
            'id' => $post->id,
            'board_slug' => $post->board?->slug,
            'board_name' => $post->board?->getLocalizedName(),
            'title' => $post->title,
            'category' => $post->category,
            'author_name' => $post->author_name,
            'created_at' => $this->formatCreatedAt($post->created_at),
            'created_at_formatted' => $this->formatCreatedAtFormat($post->created_at, g7_module_settings('sirsoft-board', 'display.date_display_format', 'standard')),
            'view_count' => $post->view_count,
            'comment_count' => $post->comments_count ?? 0,
            'is_secret' => (bool) $post->is_secret,
            'is_new' => $post->created_at && $post->created_at->diffInHours(now()) < $newHours,
        ])->toArray();
    }
}
