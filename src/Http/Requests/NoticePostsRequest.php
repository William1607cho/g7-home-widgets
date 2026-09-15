<?php

namespace Plugins\G7\Home\Widgets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 공지 티커 위젯 조회 요청을 검증합니다.
 *
 * {@see RecentPostsRequest} 와 같은 계약(상한 초과는 거부 대신 클램프)에 게시판 슬러그를 더한다
 * — 티커는 "공지 5건 롤링"이 기본 용도라 기본 5, 상한 10.
 */
class NoticePostsRequest extends FormRequest
{
    /** 조회 상한 */
    public const MAX_LIMIT = 10;

    /** 기본 조회 개수 */
    public const DEFAULT_LIMIT = 5;

    /** 게시판 슬러그 형식 — sirsoft-board StoreBoardRequest 의 slug 규칙과 동일 */
    private const SLUG_PATTERN = '/^[a-z][a-z0-9-]*$/';

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
     * 슬러그 형식은 규칙으로 거부하지 않는다 — 존재할 수 없는 슬러그도 "없는 게시판"과 같이
     * 빈 배열로 응답한다({@see boardSlug()}).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'board' => ['required', 'string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * 게시판 슬러그를 반환한다 — 코어 슬러그 형식이 아니면 null(= 빈 배열 응답, 캐시 키 미생성).
     */
    public function boardSlug(): ?string
    {
        $slug = (string) $this->validated('board');

        return preg_match(self::SLUG_PATTERN, $slug) === 1 ? $slug : null;
    }

    /**
     * 클램프된 조회 개수를 반환한다 (상한 초과 요청은 상한까지).
     */
    public function limit(): int
    {
        return min((int) $this->validated('limit', self::DEFAULT_LIMIT), self::MAX_LIMIT);
    }
}
