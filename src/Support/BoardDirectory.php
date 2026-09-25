<?php

namespace Plugins\G7\Home\Widgets\Support;

use Modules\Sirsoft\Board\Models\Board;

/**
 * 관리자 게시판 목록 조회(정리 A, 0.4.0) — 0.3.0 `BoardFilterAdminController` 안에 있던 조회 2가지를
 * 컨트롤러 밖으로 옮긴 것. 동작은 바꾸지 않았다(같은 쿼리·같은 응답 모양).
 */
final class BoardDirectory
{
    /**
     * 모든 게시판(비활성 포함, id 순) — 설정 화면 카드 목록.
     *
     * @return array<int, array{id: int, name: string, slug: string, is_active: bool}>
     */
    public function all(): array
    {
        // audit:allow query-unbounded-get reason: boards 는 운영자 등록 설정성 테이블 — 행 수가 운영자 행위에 묶여 데이터 증가에 비례하지 않는다
        return Board::query()
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'is_active'])
            ->map(fn (Board $board) => [
                'id' => (int) $board->id,
                'name' => $board->getLocalizedName(),
                'slug' => (string) $board->slug,
                'is_active' => (bool) $board->is_active,
            ])->values()->all();
    }

    /**
     * 주어진 id 가운데 실제로 있는 게시판 id.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    public function existingIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Board::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
