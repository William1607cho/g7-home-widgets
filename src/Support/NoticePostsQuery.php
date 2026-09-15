<?php

namespace Plugins\G7\Home\Widgets\Support;

use App\Helpers\PermissionHelper;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Modules\Sirsoft\Board\Models\Board;
use Modules\Sirsoft\Board\Models\Post;
use Modules\Sirsoft\Board\Traits\FormatsBoardDate;

/**
 * 지정 게시판 1곳의 "공지 티커" 조회 (홈 하단 한 줄 롤링용).
 *
 * {@see RecentPostsQuery} 와 같은 필터 계약을 한 게시판에 적용한다 — 활성 게시판, 삭제 제외,
 * 답글 제외(`parent_id IS NULL`), 발행 상태(`published` — 블라인드 `blinded` 제외), 작성일
 * 내림차순. 단일 게시판 조회라 UNION ALL 우회 없이 코어 인덱스
 * `idx_board_posts_board_status_created (board_id, status, created_at)` 를 그대로 탄다.
 *
 * 최근글과 다른 점:
 * ① 비밀글을 제외한다(`is_secret = false`) — 티커는 제목만 노출하는 공개 한 줄 영역이라
 *    {@see PopularPostsQuery} 와 같은 쪽을 따른다.
 * ② 응답은 제목·게시판 슬러그·글 ID·작성일뿐이다. 작성자(이름/이메일)·게시판명·조회수는
 *    조회하지도 응답하지도 않는다.
 * ③ 게시판이 없거나(비활성 포함) 읽기 권한이 없으면 에러가 아니라 빈 배열을 돌려준다.
 *
 * "안전집합 캐시 + 응답시점 권한필터" 패턴은 동일하다: 캐시엔 사용자 무관 결과만 담고,
 * `sirsoft-board.{slug}.posts.read` 권한은 매 요청 시점에 현재 사용자 기준으로 적용한다.
 * 한 게시판이라 권한은 전부/전무이므로 풀을 limit 보다 크게 잡을 필요가 없다.
 */
class NoticePostsQuery
{
    use FormatsBoardDate;

    /** 안전집합 캐시 TTL(초) — RecentPostsQuery 와 동일 */
    private const CACHE_TTL_SECONDS = 90;

    /**
     * 현재 사용자 기준으로 권한 필터링된 게시판 공지 목록을 반환한다.
     *
     * @param  string|null  $boardSlug  게시판 슬러그 (형식 불일치로 null 이면 빈 배열)
     * @param  int  $limit  반환할 최대 건수
     * @return array<int, array<string, mixed>>
     */
    public function forCurrentUser(?string $boardSlug, int $limit): array
    {
        if ($boardSlug === null || $limit <= 0) {
            return [];
        }

        // 권한은 캐시 밖에서 먼저 확인한다 — 권한 없는 요청은 캐시를 채우지도 읽지도 않는다.
        if (! $this->canReadBoard($boardSlug, Auth::user())) {
            return [];
        }

        // 존재하지 않는 슬러그로 캐시 키가 늘어나지 않도록 게시판 확인은 캐시 밖에서 한다.
        $boardId = Board::where('slug', $boardSlug)->where('is_active', true)->value('id');

        if ($boardId === null) {
            return [];
        }

        return Cache::remember(
            "g7_home_widgets_notice_posts_pool_{$boardSlug}_{$limit}",
            self::CACHE_TTL_SECONDS,
            fn () => $this->querySafePool((int) $boardId, $boardSlug, $limit)
        );
    }

    /**
     * 게시판 읽기 권한을 확인한다 — {@see RecentPostsQuery} 와 동일한
     * `sirsoft-board.{slug}.posts.read` 식별자.
     *
     * @param  string  $boardSlug  게시판 슬러그
     * @param  Authenticatable|null  $user  기준 사용자 (null 이면 비회원)
     */
    private function canReadBoard(string $boardSlug, ?Authenticatable $user): bool
    {
        return PermissionHelper::check("sirsoft-board.{$boardSlug}.posts.read", $user);
    }

    /**
     * 안전집합(사용자 무관)을 실제로 조회한다.
     *
     * @param  int  $boardId  게시판 ID
     * @param  string  $boardSlug  게시판 슬러그 (링크 생성용으로 응답에 담는다)
     * @param  int  $limit  조회 건수
     * @return array<int, array<string, mixed>>
     */
    private function querySafePool(int $boardId, string $boardSlug, int $limit): array
    {
        $posts = Post::query()
            ->select(['id', 'title', 'created_at'])
            ->where('board_id', $boardId)
            ->whereNull('deleted_at')
            ->whereNull('parent_id')
            ->where('status', 'published')
            ->where('is_secret', false)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $dateFormat = g7_module_settings('sirsoft-board', 'display.date_display_format', 'standard');

        return $posts->map(fn ($post) => [
            'id' => $post->id,
            'board_slug' => $boardSlug,
            'title' => $post->title,
            'created_at' => $this->formatCreatedAt($post->created_at),
            'created_at_formatted' => $this->formatCreatedAtFormat($post->created_at, $dateFormat),
        ])->toArray();
    }
}
