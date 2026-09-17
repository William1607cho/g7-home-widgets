<?php

namespace Plugins\G7\Home\Widgets\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Modules\Sirsoft\Board\Models\Board;
use Plugins\G7\Home\Widgets\Http\Requests\UpdateBoardFilterRequest;
use Plugins\G7\Home\Widgets\Support\BoardFilterSettings;

/**
 * 위젯 게시판 제외 설정 컨트롤러 (관리자, 0.3.0).
 *
 * GET/PUT /api/plugins/g7-home-widgets/admin/board-filter
 *
 * 권한은 라우트 미들웨어가 검증한다 — 조회는 `core.plugins.read`, 저장은
 * `core.plugins.update`. 이 설정은 "플러그인 설정 변경" 의 일부라 코어 권한을 그대로 쓰고
 * 별도 권한을 만들지 않는다(g7-webzine-addon 과 같은 방침).
 *
 * 응답은 조회·저장 모두 같은 형식이다:
 * `{ boards: [{id, name, slug, is_active}], excluded_board_ids: [...] }` —
 * `excluded_board_ids` 는 현재 존재하는 게시판과 교집합한 값이다.
 */
class BoardFilterAdminController extends AdminBaseController
{
    private const DOMAIN = 'g7-home-widgets';

    /**
     * 게시판 목록과 현재 제외 목록을 반환합니다.
     */
    public function show(): JsonResponse
    {
        return ResponseHelper::success('common.success', $this->payload());
    }

    /**
     * 제외 목록을 저장합니다 ('포함안함' 영역의 게시판 ID 만).
     *
     * 존재하지 않는 게시판 ID 는 버리고 저장한다. 저장에 성공하면 이전 제외 목록 지문의
     * 위젯 풀 캐시를 모두 지운다(새 목록은 지문이 달라 새 키로 조회된다).
     */
    public function update(UpdateBoardFilterRequest $request): JsonResponse
    {
        $requested = $request->excludedBoardIds();

        $existing = $requested === []
            ? []
            : Board::query()->whereIn('id', $requested)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $previousFingerprint = BoardFilterSettings::fingerprint(BoardFilterSettings::excludedIds());

        $failureReason = null;
        if (! BoardFilterSettings::save($existing, $failureReason)) {
            Log::error('[g7-home-widgets] 게시판 제외 설정 저장 실패', ['reason' => $failureReason]);

            return ResponseHelper::error('messages.board_filter.save_failed', 500, domain: self::DOMAIN);
        }

        BoardFilterSettings::forgetPoolsFor($previousFingerprint);

        return ResponseHelper::success('messages.board_filter.saved', $this->payload(), domain: self::DOMAIN);
    }

    /**
     * 조회·저장 공통 응답.
     *
     * @return array{boards: array<int, array<string, mixed>>, excluded_board_ids: array<int, int>}
     */
    private function payload(): array
    {
        // audit:allow query-unbounded-get reason: boards 는 운영자 등록 설정성 테이블 — 행 수가 운영자 행위에 묶여 데이터 증가에 비례하지 않는다
        $boards = Board::query()
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'is_active']);

        $boardIds = $boards->pluck('id')->map(fn ($id) => (int) $id)->all();

        return [
            'boards' => $boards->map(fn (Board $board) => [
                'id' => (int) $board->id,
                'name' => $board->getLocalizedName(),
                'slug' => (string) $board->slug,
                'is_active' => (bool) $board->is_active,
            ])->values()->all(),
            'excluded_board_ids' => array_values(array_intersect(BoardFilterSettings::excludedIds(), $boardIds)),
        ];
    }
}
