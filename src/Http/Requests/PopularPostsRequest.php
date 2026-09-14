<?php

namespace Plugins\G7\Home\Widgets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 인기글 위젯 조회 요청을 검증합니다.
 *
 * sirsoft-board 코어의 `PopularPostsRequest`(기본 기간 week, `all`·미지원 값은 year, 상한 50
 * 클램프)와 동일한 계약을 따른다 — 이 위젯은 "인기글 10개"가 기본 용도라 기본 개수만 10으로 둔다.
 */
class PopularPostsRequest extends FormRequest
{
    /** 조회 상한 */
    public const MAX_LIMIT = 50;

    /** 기본 조회 개수 */
    public const DEFAULT_LIMIT = 10;

    /** 기본 기간 */
    public const DEFAULT_PERIOD = 'week';

    /**
     * 해석되는 기간 어휘 (코어 PopularPostsRequest::RESOLVED_PERIODS 와 동일).
     *
     * @var array<int, string>
     */
    public const RESOLVED_PERIODS = ['today', 'week', 'month', 'year'];

    /**
     * 요청 권한 — 공개 엔드포인트이므로 true 고정.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 검증 규칙 — 상한·어휘는 거부하지 않고 접근자에서 정규화한다(코어와 동일 계약).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * 조회 기간을 닫힌 집합 안의 값으로 반환한다 — 미지정은 week, 그 밖의 미지원 값(`all` 포함)은
     * year. 반환값이 닫혀 있어 이 값을 쓰는 캐시 키도 임의 문자열로 늘어나지 않는다.
     */
    public function period(): string
    {
        $period = $this->validated('period');

        if ($period === null || $period === '') {
            return self::DEFAULT_PERIOD;
        }

        return in_array((string) $period, self::RESOLVED_PERIODS, true)
            ? (string) $period
            : 'year';
    }

    /**
     * 클램프된 조회 개수를 반환한다 (상한 초과 요청은 상한까지).
     */
    public function limit(): int
    {
        return min((int) $this->validated('limit', self::DEFAULT_LIMIT), self::MAX_LIMIT);
    }
}
