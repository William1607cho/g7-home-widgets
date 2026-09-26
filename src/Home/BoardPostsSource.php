<?php

namespace Plugins\G7\Home\Widgets\Home;

use Modules\Sirsoft\Board\Models\Post;
use Modules\Sirsoft\Board\Services\BoardService;

/**
 * 코어 게시판 서비스 어댑터(0.4.0). 홈 위젯이 게시판 데이터를 얻는 유일한 통로다.
 *
 * 판정은 모두 코어가 한다(재구현하지 않는다).
 * - 열람 가능 게시판: `getReadableActiveBoards()` (활성 + `sirsoft-board.{slug}.posts.read`)
 * - 게시판별 최근글: `getBoardRecentPostsById()` (삭제·답글·발행 상태 제외, `is_secret` 값 제공)
 * - 인기글: `getCachedPopularPosts()` (코어 캐시, 비밀글 제외·기간·정렬·열람 권한 적용)
 *
 * 게시판별 최근글은 사용자와 무관한 안전집합이라 세대 키 캐시({@see WidgetCache})에 담는다.
 * 인기글은 코어 캐시에 맡긴다(이 클래스는 캐시하지 않는다).
 */
final class BoardPostsSource
{
    /** 게시판별 최근글 풀 크기 — 최근글(최대 20)·티커(최대 10)가 함께 쓴다 */
    public const RECENT_POOL = 20;

    /** 인기글 코어 풀 최솟값 */
    public const POPULAR_MIN_POOL = 50;

    /** 인기글 풀 = max(최솟값, 개수 × 배수) */
    public const POPULAR_POOL_FACTOR = 5;

    public function __construct(
        private readonly BoardService $boards,
        private readonly WidgetCache $cache,
    ) {}

    /**
     * 호출자가 열람할 수 있는 활성 게시판(코어 판정).
     *
     * @return array<int, array{id: int, slug: string, name: string}>
     */
    public function readableBoards(): array
    {
        return $this->boards->getReadableActiveBoards()
            ->map(fn ($board) => [
                'id' => (int) $board->id,
                'slug' => (string) $board->slug,
                'name' => (string) $board->getLocalizedName(),
            ])
            ->values()
            ->all();
    }

    /**
     * 게시판 하나의 최근글 안전집합(최신순, 최대 {@see self::RECENT_POOL}건).
     *
     * @return array<int, array<string, mixed>>
     */
    public function recentOfBoard(int $boardId): array
    {
        return $this->cache->remember(
            'recent:'.$boardId.':'.self::RECENT_POOL,
            fn () => $this->boards->getBoardRecentPostsById($boardId, self::RECENT_POOL),
        );
    }

    /**
     * 기간별 인기글(코어 캐시 + 코어 열람 권한 필터 적용 결과).
     *
     * @param  string  $period  week|month|year
     * @param  int  $limit  칸이 보여 줄 개수
     * @return array<int, array<string, mixed>>
     */
    public function popular(string $period, int $limit): array
    {
        $pool = max(self::POPULAR_MIN_POOL, $limit * self::POPULAR_POOL_FACTOR);

        return $this->boards->getCachedPopularPosts($period, $pool);
    }

    /**
     * 게시글 id 묶음의 분류(category)를 한 번에 읽는다 — 코어 인기글 결과에 분류가 없어서다.
     * 권한 판정 없는 데이터 조회 1회(id 는 코어가 이미 걸러 준 목록). 세대 키 캐시에 담는다.
     *
     * @param  array<int, int>  $ids
     * @return array<int, string|null> id => 분류
     */
    public function categoriesOf(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        sort($ids);

        return $this->cache->remember('cat:'.sha1(implode(',', $ids)), fn () => Post::query()
            ->whereIn('id', $ids)
            ->pluck('category', 'id')
            ->map(fn ($c) => is_string($c) && $c !== '' ? $c : null)
            ->all());
    }
}
