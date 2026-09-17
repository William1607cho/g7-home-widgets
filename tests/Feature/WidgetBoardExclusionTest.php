<?php

namespace Plugins\G7\Home\Widgets\Tests\Feature;

use Plugins\G7\Home\Widgets\Support\NoticePostsQuery;
use Plugins\G7\Home\Widgets\Support\PopularPostsQuery;
use Plugins\G7\Home\Widgets\Support\RecentPostsQuery;
use Plugins\G7\Home\Widgets\Tests\PluginTestCase;

/**
 * 최근글·인기글 게시판 제외 (0.3.0)
 *
 * - 제외 게시판 글은 두 위젯에서 빠지고, 빠진 만큼 다른 게시판 글로 limit 이 채워진다.
 * - 인기글은 기간 4종 모두 같은 규칙.
 * - 열람 권한 필터는 제외 설정과 무관하게 그대로 적용된다.
 * - 공지 티커는 영향을 받지 않는다.
 */
class WidgetBoardExclusionTest extends PluginTestCase
{
    /**
     * @return array<int, int>
     */
    private function recentIds(int $limit = 10): array
    {
        return array_column(app(RecentPostsQuery::class)->forCurrentUser($limit), 'id');
    }

    /**
     * @return array<int, int>
     */
    private function popularIds(string $period, int $limit = 10): array
    {
        return array_column(app(PopularPostsQuery::class)->forCurrentUser($period, $limit), 'id');
    }

    public function test_recent_posts_drop_excluded_board_and_still_fill_limit(): void
    {
        $this->actAs(null);
        $kept = $this->createBoard();
        $hidden = $this->createBoard();

        $keptIds = [];
        for ($i = 0; $i < 12; $i++) {
            $keptIds[] = $this->createPost($kept, ['created_at' => now()->addMinutes(5)->addSeconds($i)]);
        }
        $hiddenIds = [];
        for ($i = 0; $i < 12; $i++) {
            $hiddenIds[] = $this->createPost($hidden, ['created_at' => now()->addMinutes(20)->addSeconds($i)]);
        }

        $this->assertSame(10, count(array_intersect($this->recentIds(), $hiddenIds)), '제외 전에는 더 최근인 게시판 글이 위를 차지한다');

        $this->setExcluded([$hidden->id]);

        $ids = $this->recentIds();
        $this->assertCount(10, $ids);
        $this->assertSame([], array_values(array_intersect($ids, $hiddenIds)));
        $this->assertCount(10, array_intersect($ids, $keptIds));
    }

    public function test_popular_posts_drop_excluded_board_for_every_period(): void
    {
        $this->actAs(null);
        $kept = $this->createBoard();
        $hidden = $this->createBoard();

        $keptIds = [];
        for ($i = 0; $i < 12; $i++) {
            $keptIds[] = $this->createPost($kept, ['created_at' => now()->subMinutes(1), 'view_count' => 2000000 + $i]);
        }
        $hiddenIds = [];
        for ($i = 0; $i < 12; $i++) {
            $hiddenIds[] = $this->createPost($hidden, ['created_at' => now()->subMinutes(1), 'view_count' => 3000000 + $i]);
        }

        foreach (['today', 'week', 'month', 'year'] as $period) {
            $this->assertNotSame([], array_intersect($this->popularIds($period), $hiddenIds), "{$period}: 제외 전");
        }

        $this->setExcluded([$hidden->id]);

        foreach (['today', 'week', 'month', 'year'] as $period) {
            $ids = $this->popularIds($period);
            $this->assertCount(10, $ids, $period);
            $this->assertSame([], array_values(array_intersect($ids, $hiddenIds)), $period);
            $this->assertCount(10, array_intersect($ids, $keptIds), $period);
        }
    }

    public function test_change_applies_immediately_without_waiting_for_ttl(): void
    {
        $this->actAs(null);
        $board = $this->createBoard();
        $postId = $this->createPost($board);

        // 캐시를 채운다
        $this->assertContains($postId, $this->recentIds());
        $this->assertContains($postId, $this->popularIds('week'));

        $this->setExcluded([$board->id]);
        $this->assertNotContains($postId, $this->recentIds());
        $this->assertNotContains($postId, $this->popularIds('week'));

        $this->setExcluded([]);
        $this->assertContains($postId, $this->recentIds());
        $this->assertContains($postId, $this->popularIds('week'));
    }

    public function test_read_permission_filter_still_applies_when_board_is_included(): void
    {
        $private = $this->createBoard(active: true, guestReadable: false);
        $postId = $this->createPost($private);

        $this->setExcluded([]);

        $this->actAs(null);
        $this->assertNotContains($postId, $this->recentIds());

        $this->actAs($this->createUserWithRole('user'));
        $this->assertNotContains($postId, $this->recentIds());
        $this->assertNotContains($postId, $this->popularIds('week'));
    }

    public function test_deleted_board_id_in_settings_is_ignored(): void
    {
        $this->actAs(null);
        $board = $this->createBoard();
        $postId = $this->createPost($board);

        $this->setExcluded([999999999]);

        $this->assertContains($postId, $this->recentIds());
        $this->assertContains($postId, $this->popularIds('week'));
    }

    public function test_notice_posts_are_not_affected(): void
    {
        $this->actAs(null);
        $board = $this->createBoard();
        $postId = $this->createPost($board, ['created_at' => now()->subMinute()]);

        $before = array_column(app(NoticePostsQuery::class)->forCurrentUser($board->slug, 5), 'id');
        $this->setExcluded([$board->id]);
        $after = array_column(app(NoticePostsQuery::class)->forCurrentUser($board->slug, 5), 'id');

        $this->assertContains($postId, $before);
        $this->assertSame($before, $after);
    }
}
