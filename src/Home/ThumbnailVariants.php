<?php

namespace Plugins\G7\Home\Widgets\Home;

/**
 * 갤러리·웹진 칸 썸네일을 image-delivery 변환본으로 바꾼다(0.4.2). 순수 함수 모음.
 *
 * 조회는 응답 전체에서 한 번이다 — {@see self::urls()} 로 모든 칸의 썸네일 주소를 모아 계약을 한 번 부르고,
 * {@see self::apply()} 로 결과를 입힌다(칸·글마다 조회하지 않는다). 캐시 뒤에서 요청마다 하므로 변환본이 생기면
 * 다음 요청부터 바로 쓰인다(위젯 목록 캐시에 옛 주소가 남지 않는다).
 *
 * 규칙
 * - 기본 `thumbnail` = 240 변환본(없으면 있는 것 중 가장 작은 것). `thumbnail_srcset` = 240·960 중 있는 것.
 *   960 이 없고(원본이 960 이하) 원본이 240 보다 넓은 JPEG·WebP 면 원본을 제 폭으로 후보에 더한다 —
 *   고밀도 화면에서 240 을 늘려 그리면 0.4.1(원본)보다 흐려지기 때문이다. PNG 원본은 넣지 않는다(용량).
 * - 변환본이 없으면(생성 전·대상 아님·플러그인 없음) 값을 그대로 둔다 — 원본 주소, `thumbnail_srcset` 없음.
 *   게시판 목록과 같은 동작이고, 스케줄러가 변환본을 만들면 다음 요청부터 바뀐다.
 * - 비밀글·썸네일 없는 글(`thumbnail` null)과 웹진 대체 이미지(`fallback_image`)는 건드리지 않는다.
 */
final class ThumbnailVariants
{
    /** 변환본을 입히는 위젯 종류 */
    public const TYPES = ['gallery', 'webzine'];

    /** 기본 src 폭 */
    public const BASE_WIDTH = 240;

    /** srcset 후보 폭 */
    public const SRCSET_WIDTHS = [240, 960];

    /** 원본을 후보로 더할 수 있는 형식 */
    public const ORIGINAL_MIMES = ['image/jpeg', 'image/webp'];

    /**
     * 섹션들에서 변환본을 물어볼 썸네일 주소(중복 없음).
     *
     * @param  array<int, array<string, mixed>>  $sections  {@see HomeLayoutService::build()} 의 sections
     * @return array<int, string>
     */
    public static function urls(array $sections): array
    {
        $urls = [];
        foreach (self::items($sections) as $item) {
            $url = self::target($item);
            if ($url !== null) {
                $urls[$url] = true;
            }
        }

        return array_keys($urls);
    }

    /**
     * 조회 결과를 섹션들에 입힌다.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array<string, mixed>  $found  주소 => 계약 결과
     * @return array<int, array<string, mixed>>
     */
    public static function apply(array $sections, array $found): array
    {
        foreach ($sections as $s => $section) {
            foreach ($section['cols'] ?? [] as $c => $col) {
                if (! in_array($col['type'] ?? null, self::TYPES, true) || ! is_array($col['items'] ?? null)) {
                    continue;
                }
                foreach ($col['items'] as $i => $item) {
                    $sections[$s]['cols'][$c]['items'][$i] = self::applyItem($item, $found);
                }
            }
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $found
     * @return array<string, mixed>
     */
    public static function applyItem(array $item, array $found): array
    {
        $item['thumbnail_srcset'] = null;
        $url = self::target($item);
        $hit = $url !== null && is_array($found[$url] ?? null) ? $found[$url] : null;
        $variants = is_array($hit['variants'] ?? null) && ! empty($hit['available']) ? $hit['variants'] : [];
        $variants = array_filter($variants, static fn ($v): bool => is_array($v) && is_string($v['url'] ?? null) && $v['url'] !== '');
        if ($variants === []) {
            return $item;
        }
        ksort($variants);

        $base = $variants[self::BASE_WIDTH] ?? reset($variants);
        $candidates = [];
        foreach (self::SRCSET_WIDTHS as $w) {
            if (isset($variants[$w])) {
                $candidates[] = $variants[$w]['url'].' '.((int) $variants[$w]['width'] ?: $w).'w';
            }
        }
        $source = is_array($hit['source'] ?? null) ? $hit['source'] : [];
        $srcWidth = (int) ($source['width'] ?? 0);
        if (! isset($variants[960]) && $srcWidth > self::BASE_WIDTH
            && in_array(strtolower((string) ($source['mime'] ?? '')), self::ORIGINAL_MIMES, true)) {
            $candidates[] = $url.' '.$srcWidth.'w';
        }

        $item['thumbnail'] = $base['url'];
        $item['thumbnail_srcset'] = count($candidates) > 1 ? implode(', ', $candidates) : null;

        return $item;
    }

    /**
     * 변환본을 물어볼 주소 — 비밀글이 아니고 썸네일이 문자열일 때만.
     *
     * @param  mixed  $item
     */
    private static function target(mixed $item): ?string
    {
        if (! is_array($item) || ! empty($item['is_secret'])) {
            return null;
        }
        $url = $item['thumbnail'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return iterable<mixed>
     */
    private static function items(array $sections): iterable
    {
        foreach ($sections as $section) {
            foreach ($section['cols'] ?? [] as $col) {
                if (in_array($col['type'] ?? null, self::TYPES, true) && is_array($col['items'] ?? null)) {
                    yield from $col['items'];
                }
            }
        }
    }
}
