<?php

namespace Plugins\G7\Home\Widgets\Support;

use App\Helpers\PermissionHelper;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Modules\Sirsoft\Board\Models\Board;
use Modules\Sirsoft\Board\Models\Post;
use Modules\Sirsoft\Board\Traits\FormatsBoardDate;
use Plugins\G7\Home\Widgets\Http\Requests\PopularPostsRequest;

/**
 * 게시판 무관 "인기글" 통합 조회 (카테고리 포함).
 *
 * `sirsoft-board` 코어의 `BoardRepository::getPopularPosts()`와 같은 정렬·필터 계약을 그대로
 * 재현한다 — 활성 게시판, 삭제 제외, 답글 제외(`parent_id IS NULL`), 발행 상태, 비밀글 제외,
 * 기간 필터(today=오늘 0시 / week / month / year, 그 밖은 year), 조회수 내림차순 → 동률 시
 * 댓글수 내림차순. 코어가 `idx_board_posts_board_status_created` 로 기간 범위를 좁힌 뒤
 * filesort 하는 단일 쿼리 구조도 같다 — 이 애드온은 새 인덱스를 요구하지 않는다.
 *
 * 코어와 다른 점:
 * ① `category` 필드를 추가로 select 한다(코어 응답엔 없음).
 * ② 작성자 정보(users JOIN — 이름/이메일/상태/아바타)와 본문 발췌를 아예 조회하지 않는다.
 *    홈 위젯은 작성자를 표시하지 않고, 코어 응답의 `author.email` 노출을 새 코드에서
 *    반복하지 않기 위해서다.
 * ③ 권한 필터링을 코어의 `BoardService::filterItemsByBoardReadPermission()`(private) 대신
 *    동일한 공개 유틸(`PermissionHelper::check()`)로 이 클래스 안에 다시 구현한다. "안전집합
 *    캐시 + 응답시점 권한필터" 패턴은 코어·{@see RecentPostsQuery} 와 동일하다.
 * ④ 캐시는 {@see RecentPostsQuery} 방식(짧은 TTL, 사용자 무관 풀)을 따르며, 권한 필터링 후에도
 *    요청 limit 만큼 남도록 풀을 limit 보다 크게 잡는다(코어는 limit 만큼만 뽑은 뒤 필터링해
 *    권한 없는 글이 섞이면 limit 보다 적게 돌려준다). 정렬 기준이 같으므로 필터링 결과의 앞
 *    limit 건은 코어 결과와 순서가 같다.
 * ⑤ 0.3.0: 관리자가 고른 제외 게시판({@see BoardFilterSettings})을 안전집합 조회 단계에서 뺀다.
 *    캐시 키에는 제외 목록 지문이 들어간다.
 */
class PopularPostsQuery
{
    use FormatsBoardDate;

    /** 안전집합 캐시 TTL(초) — RecentPostsQuery 와 동일 */
    private const CACHE_TTL_SECONDS = 90;

    /** 안전집합 풀 최소 크기 — RecentPostsQuery 와 동일 */
    private const MIN_POOL_SIZE = 50;

    /**
     * 현재 사용자 기준으로 권한 필터링된 기간별 인기글을 반환한다.
     *
     * @param  string  $period  기간 키 (today|week|month|year — 요청 접근자가 닫힌 집합으로 정규화)
     * @param  int  $limit  반환할 최대 건수
     * @return array<int, array<string, mixed>>
     */
    public function forCurrentUser(string $period, int $limit): array
    {
        $poolLimit = self::poolSizeFor($limit);
        $pool = $this->cachedSafePool($period, $poolLimit, BoardFilterSettings::excludedIds());

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
     * @param  string  $period  기간 키
     * @param  string  $fingerprint  제외 목록 지문
     * @param  int  $poolLimit  풀 크기
     */
    private static function cacheKey(string $period, string $fingerprint, int $poolLimit): string
    {
        return "g7_home_widgets_popular_posts_pool_{$period}_{$fingerprint}_{$poolLimit}";
    }

    /**
     * 지정한 지문으로 만들어질 수 있는 캐시 키 전체 (기간 4종 × limit 0 ~ 상한).
     *
     * @param  string  $fingerprint  제외 목록 지문
     * @return array<int, string>
     */
    public static function cacheKeysFor(string $fingerprint): array
    {
        $keys = [];
        foreach (PopularPostsRequest::RESOLVED_PERIODS as $period) {
            for ($limit = 0; $limit <= PopularPostsRequest::MAX_LIMIT; $limit++) {
                $keys[] = self::cacheKey($period, $fingerprint, self::poolSizeFor($limit));
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * 게시판별 읽기 권한을 확인한다 — 코어 `filterItemsByBoardReadPermission()` 과 동일한
     * `sirsoft-board.{slug}.posts.read` 식별자, 슬러그 없는 항목은 제외.
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
     * 안전집합(사용자 무관) 캐시 조회 — 키에 기간을 넣어 today/week/month/year 가 섞이지 않게 한다.
     * 권한은 절대 이 단계에서 걸지 않는다(캐시 공유로 인한 권한 누수 방지).
     *
     * @param  string  $period  기간 키
     * @param  int  $poolLimit  풀 크기
     * @param  array<int, int>  $excludedBoardIds  제외 게시판 ID (정리된 목록)
     * @return array<int, array<string, mixed>>
     */
    private function cachedSafePool(string $period, int $poolLimit, array $excludedBoardIds): array
    {
        return Cache::remember(
            self::cacheKey($period, BoardFilterSettings::fingerprint($excludedBoardIds), $poolLimit),
            self::CACHE_TTL_SECONDS,
            fn () => $this->querySafePool($period, $poolLimit, $excludedBoardIds)
        );
    }

    /**
     * 안전집합을 실제로 조회한다 — `BoardRepository::getPopularPosts()` 의 WHERE/ORDER BY 를
     * 그대로 옮기고, JOIN(users/attachments)과 작성자·본문 컬럼만 뺀다.
     *
     * @param  string  $period  기간 키
     * @param  int  $limit  풀 크기
     * @param  array<int, int>  $excludedBoardIds  제외 게시판 ID
     * @return array<int, array<string, mixed>>
     */
    private function querySafePool(string $period, int $limit, array $excludedBoardIds = []): array
    {
        // audit:allow query-unbounded-get reason: boards 는 운영자 등록 설정성 테이블 — 행 수가 운영자 행위에 묶여 데이터 증가에 비례하지 않는다 (코어 BoardRepository::getPopularPosts()와 동일 근거)
        $activeBoardIds = Board::where('is_active', true)
            ->when($excludedBoardIds !== [], fn ($query) => $query->whereNotIn('id', $excludedBoardIds))
            ->pluck('id');

        if ($activeBoardIds->isEmpty()) {
            return [];
        }

        // 코어와 동일한 기간 필터 (default 는 코어 match 의 폴백과 같게 year)
        $dateFilter = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'year' => now()->subYear(),
            default => now()->subYear(),
        };

        $posts = Post::query()
            ->with('board:id,slug,name')
            ->select(['id', 'board_id', 'title', 'category', 'view_count', 'comments_count', 'created_at'])
            ->whereIn('board_id', $activeBoardIds)
            ->whereNull('deleted_at')
            ->whereNull('parent_id')
            ->where('status', 'published')
            ->where('is_secret', false)
            ->where('created_at', '>=', $dateFilter)
            ->orderBy('view_count', 'desc')
            ->orderBy('comments_count', 'desc')
            ->limit($limit)
            ->get();

        $dateFormat = g7_module_settings('sirsoft-board', 'display.date_display_format', 'standard');

        return $posts->map(fn ($post) => [
            'id' => $post->id,
            'board_slug' => $post->board?->slug,
            'board_name' => $post->board?->getLocalizedName(),
            'title' => $post->title,
            'category' => $post->category,
            'view_count' => $post->view_count ?? 0,
            'comment_count' => $post->comments_count ?? 0,
            'created_at' => $this->formatCreatedAt($post->created_at),
            'created_at_formatted' => $this->formatCreatedAtFormat($post->created_at, $dateFormat),
        ])->toArray();
    }
}
