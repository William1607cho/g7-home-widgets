<?php

namespace Plugins\G7\Home\Widgets\Tests\Unit\Home;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Home\Widgets\Home\PostLists;

/**
 * 게시글 목록 합치기·자르기 (0.4.0) — 순수 함수.
 */
class PostListsTest extends TestCase
{
    public function test_merge_sorts_by_time_then_id_and_tags_boards(): void
    {
        $out = PostLists::mergeRecent([
            ['board' => ['id' => 1, 'slug' => 'a', 'name' => 'A'], 'items' => [
                ['id' => 10, 'created_at' => '2026-09-25 10:00:00'],
                ['id' => 3, 'created_at' => '2026-09-20 10:00:00'],
            ]],
            ['board' => ['id' => 2, 'slug' => 'b', 'name' => 'B'], 'items' => [
                ['id' => 11, 'created_at' => '2026-09-25 10:00:00'],
                ['id' => 7, 'created_at' => '2026-09-24 10:00:00'],
            ]],
        ], 3, false);

        $this->assertSame([11, 10, 7], array_column($out, 'id'));
        $this->assertSame(['b', 'a', 'b'], array_column($out, 'board_slug'));
        $this->assertSame('B', $out[0]['board_name']);
    }

    public function test_merge_can_drop_secret_posts(): void
    {
        $perBoard = [['board' => ['id' => 1, 'slug' => 'n', 'name' => 'N'], 'items' => [
            ['id' => 2, 'created_at' => '2026-09-25', 'is_secret' => true],
            ['id' => 1, 'created_at' => '2026-09-24', 'is_secret' => false],
        ]]];

        $this->assertSame([1], array_column(PostLists::mergeRecent($perBoard, 5, true), 'id'));
        $this->assertSame([2, 1], array_column(PostLists::mergeRecent($perBoard, 5, false), 'id'));
        $this->assertSame([], PostLists::mergeRecent($perBoard, 0, false));
    }

    public function test_keep_boards_filters_in_order_and_stops_at_limit(): void
    {
        $items = [
            ['id' => 1, 'board_slug' => 'x'],
            ['id' => 2, 'board_slug' => 'a'],
            ['id' => 3, 'board_slug' => 'b'],
            ['id' => 4, 'board_slug' => 'a'],
        ];

        $this->assertSame([2, 3], array_column(PostLists::keepBoards($items, ['a', 'b'], 2), 'id'));
        $this->assertSame([], PostLists::keepBoards($items, [], 5));
    }

    public function test_fill_board_names_uses_known_boards_only(): void
    {
        $out = PostLists::fillBoardNames([
            ['board_slug' => 'a', 'board_name' => null],
            ['board_slug' => 'b', 'board_name' => 'Kept'],
            ['board_slug' => 'z', 'board_name' => ''],
        ], ['a' => 'Alpha', 'b' => 'Beta']);

        $this->assertSame(['Alpha', 'Kept', ''], array_column($out, 'board_name'));
    }

    public function test_pick_keeps_only_listed_keys(): void
    {
        $out = PostLists::pick([['id' => 1, 'author' => ['email' => 'x'], 'title' => 't']], ['id', 'title', 'missing']);

        $this->assertSame([['id' => 1, 'title' => 't', 'missing' => null]], $out);
    }
}
