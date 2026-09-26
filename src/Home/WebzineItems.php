<?php

namespace Plugins\G7\Home\Widgets\Home;

/**
 * 웹진 위젯 항목에 애드온 카드 값(요약·썸네일)을 입힌다(0.4.0 웹진 묶음). 순수 함수 모음.
 *
 * 비밀글은 애드온 결과와 무관하게 요약·썸네일을 비운다(애드온도 비우지만 여기서 한 번 더 막는다).
 * 썸네일이 없는 글은 갤러리처럼 대체 틀로 그리므로 `has_thumbnail` 만 거짓으로 둔다 — 애드온의
 * `fallback_image` 는 쓰지 않는다(명령서: 갤러리와 같은 대체 틀).
 */
final class WebzineItems
{
    /**
     * 애드온에 넘길 입력(공개 계약 입력 키만). 코어 목록에서 발행·미삭제 글만 온다.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{id: int, is_secret: bool, status: string, deleted_at: null, thumbnail: ?string}>
     */
    public static function contractInput(array $items): array
    {
        return array_map(static fn (array $item): array => [
            'id' => (int) ($item['id'] ?? 0),
            'is_secret' => ! empty($item['is_secret']),
            'status' => 'published',
            'deleted_at' => null,
            'thumbnail' => ! empty($item['is_secret']) ? null : self::str($item['thumbnail'] ?? null),
        ], $items);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, mixed>  $cards  id => 애드온 카드 값
     * @return array<int, array<string, mixed>>
     */
    public static function apply(array $items, array $cards): array
    {
        foreach ($items as $i => $item) {
            $card = $cards[(int) ($item['id'] ?? 0)] ?? null;
            $card = is_array($card) ? $card : [];
            if (! empty($item['is_secret'])) {
                $summary = null;
                $thumbnail = null;
            } else {
                $summary = self::str($card['summary'] ?? null);
                $thumbnail = array_key_exists('thumbnail', $card) ? self::str($card['thumbnail']) : self::str($item['thumbnail'] ?? null);
            }
            $items[$i]['summary'] = $summary;
            $items[$i]['thumbnail'] = $thumbnail;
            $items[$i]['has_thumbnail'] = $thumbnail !== null;
        }

        return $items;
    }

    private static function str(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
