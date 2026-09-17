<?php

namespace Plugins\G7\Home\Widgets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Plugins\G7\Home\Widgets\Support\BoardFilterSettings;

/**
 * 위젯 게시판 제외 목록 저장 요청을 검증합니다 (0.3.0).
 *
 * 권한은 라우트 미들웨어(`permission:admin,core.plugins.update`)가 검증한다.
 * 중복 ID 는 거부하지 않고 저장 전에 제거한다(`distinct` 규칙을 쓰지 않는 이유).
 * 존재하지 않는 게시판 ID 도 거부하지 않고 컨트롤러가 걸러낸다.
 */
class UpdateBoardFilterRequest extends FormRequest
{
    /**
     * 요청 권한 — 라우트 미들웨어가 검사하므로 true.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 검증 규칙.
     *
     * 빈 배열(= 모두 포함)을 받아야 하므로 `required` 대신 `present` 를 쓴다.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'excluded_board_ids' => ['present', 'array', 'max:'.BoardFilterSettings::MAX_IDS],
            'excluded_board_ids.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * 정리된 제외 ID 목록.
     *
     * @return array<int, int>
     */
    public function excludedBoardIds(): array
    {
        return BoardFilterSettings::normalize($this->validated('excluded_board_ids', []));
    }
}
