<?php

namespace Plugins\G7\Home\Widgets\Tests\Unit\Home;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Home\Widgets\Home\WikiBoards;

/**
 * g7-light-wiki 설정 키 `wiki_boards` 에서 위키 게시판 id 뽑기 — 순수 함수.
 */
class WikiBoardsTest extends TestCase
{
    public function test_ids_are_taken_from_well_formed_rows_only(): void
    {
        $rows = [
            ['board_id' => 8, 'front_post_id' => 3],
            ['board_id' => '9', 'front_post_id' => null],
            ['board_id' => 'x'],
            'garbage',
            ['front_post_id' => 1],
            ['board_id' => 8],
        ];

        $this->assertSame([8, 9], WikiBoards::idsFrom($rows));
        $this->assertSame([], WikiBoards::idsFrom(null));
    }
}
