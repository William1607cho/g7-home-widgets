<?php

namespace Plugins\G7\Home\Widgets\Tests\Unit\Home;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Home\Widgets\Home\BoardScope;

/**
 * 칸 게시판 집합 = 열람 가능 ∩ 그 칸의 선택 (0.4.0, 0.5.0 공통 제외 없음) — 순수 클래스.
 */
class BoardScopeTest extends TestCase
{
    /**
     * @return array<int, array{id: int, slug: string, name: string}>
     */
    private function readable(): array
    {
        return [
            ['id' => 5, 'slug' => 'free', 'name' => 'Free'],
            ['id' => 1, 'slug' => 'notice', 'name' => 'Notice'],
            ['id' => 9, 'slug' => 'qna', 'name' => 'QnA'],
        ];
    }

    public function test_all_mode_is_every_readable_board_in_core_order(): void
    {
        $scope = new BoardScope($this->readable());

        $this->assertSame([5, 1, 9], array_column($scope->resolve(['mode' => 'all', 'ids' => []]), 'id'));
        $this->assertSame([5, 1, 9], $scope->ids());
    }

    public function test_exclude_mode_drops_listed_boards_and_keeps_new_ones(): void
    {
        $scope = new BoardScope($this->readable());

        // 9 를 뺐다. 목록에 없는 새 게시판(5·1)은 자동 포함, 열람 불가(42)는 목록에 있어도 무관
        $this->assertSame([5, 1], array_column($scope->resolve(['mode' => 'exclude', 'ids' => [9, 42]]), 'id'));
        $this->assertSame([5, 1, 9], array_column($scope->resolve(['mode' => 'exclude', 'ids' => []]), 'id'));
    }

    public function test_only_mode_intersects_with_readable(): void
    {
        $scope = new BoardScope($this->readable());

        // 42 는 열람 불가(목록에 없음) — 설정에 넣어도 나오지 않는다
        $this->assertSame([1, 9], array_column($scope->resolve(['mode' => 'only', 'ids' => [42, 9, 1]]), 'id'));
        $this->assertSame([], $scope->resolve(['mode' => 'only', 'ids' => [42]]));
    }

    public function test_empty_selection_in_only_mode_gives_nothing(): void
    {
        $scope = new BoardScope($this->readable());

        $this->assertSame([], $scope->resolve(['mode' => 'only', 'ids' => []]));
    }

    public function test_id_by_slug_looks_only_at_readable_boards(): void
    {
        $scope = new BoardScope($this->readable());

        $this->assertSame(1, $scope->idBySlug('notice'));
        $this->assertNull($scope->idBySlug('private'));
    }
}
