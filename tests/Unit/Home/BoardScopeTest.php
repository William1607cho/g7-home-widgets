<?php

namespace Plugins\G7\Home\Widgets\Tests\Unit\Home;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Home\Widgets\Home\BoardScope;

/**
 * 칸 게시판 집합 = 열람 가능 ∩ 선택 − 공통 제외 (0.4.0) — 순수 클래스.
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

    public function test_all_mode_is_readable_minus_excluded_in_core_order(): void
    {
        $scope = new BoardScope($this->readable(), [9]);

        $this->assertSame([5, 1], array_column($scope->resolve(['mode' => 'all', 'ids' => []]), 'id'));
    }

    public function test_only_mode_intersects_with_readable(): void
    {
        $scope = new BoardScope($this->readable(), []);

        // 42 는 열람 불가(목록에 없음) — 설정에 넣어도 나오지 않는다
        $this->assertSame([1, 9], array_column($scope->resolve(['mode' => 'only', 'ids' => [42, 9, 1]]), 'id'));
    }

    public function test_excluded_list_applies_to_selected_boards_too(): void
    {
        $scope = new BoardScope($this->readable(), [1]);

        $this->assertSame([], $scope->resolve(['mode' => 'only', 'ids' => [1]]));
    }

    public function test_empty_selection_in_only_mode_gives_nothing(): void
    {
        $scope = new BoardScope($this->readable(), []);

        $this->assertSame([], $scope->resolve(['mode' => 'only', 'ids' => []]));
    }

    public function test_id_by_slug_looks_only_at_readable_boards(): void
    {
        $scope = new BoardScope($this->readable(), []);

        $this->assertSame(1, $scope->idBySlug('notice'));
        $this->assertNull($scope->idBySlug('private'));
    }
}
