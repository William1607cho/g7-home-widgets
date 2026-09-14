<?php

namespace Plugins\G7\Home\Widgets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 전체 최근글 위젯 조회 요청을 검증합니다.
 *
 * sirsoft-board 코어의 `RecentPostsRequest`(기본 5, 상한 20, 초과 시 거부 대신 클램프)와
 * 동일한 계약을 따른다 — 이 위젯은 "최근글 10개"가 기본 용도라 기본값만 10으로 둔다.
 */
class RecentPostsRequest extends FormRequest
{
    /** 조회 상한 */
    public const MAX_LIMIT = 20;

    /** 기본 조회 개수 */
    public const DEFAULT_LIMIT = 10;

    /**
     * 요청 권한 — 공개 엔드포인트이므로 true 고정.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 검증 규칙.
     *
     * 상한은 규칙이 아니라 접근자에서 클램프한다 — 공개 API 이므로 상한 초과 요청을
     * 거부하지 않고 상한까지만 돌려준다(코어 RecentPostsRequest와 동일 계약).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'limit' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * 클램프된 조회 개수를 반환한다 (코어 RecentPostsRequest::limit()과 동일 계약 —
     * 하한 강제 없음, limit=0 요청은 그대로 0건 응답).
     */
    public function limit(): int
    {
        return min((int) $this->validated('limit', self::DEFAULT_LIMIT), self::MAX_LIMIT);
    }
}
