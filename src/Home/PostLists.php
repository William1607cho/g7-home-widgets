<?php

namespace Plugins\G7\Home\Widgets\Home;

/**
 * 게시글 목록 합치기·자르기(0.4.0). 순수 함수 모음.
 */
final class PostLists
{
    /**
     * 게시판별 목록을 하나로 합쳐 최신순 상위 n건을 만든다.
     *
     * 각 항목에 게시판 slug·이름을 붙인다(코어 게시판별 조회 결과에는 없다). 정렬은 작성
     * 시각 문자열(같은 요청 안에서 같은 형식) 내림차순, 같으면 id 내림차순.
     *
     * @param  array<int, array{board: array{id: int, slug: string, name: string}, items: array<int, array<string, mixed>>}>  $perBoard
     * @param  int  $limit  상위 개수
     * @param  bool  $dropSecret  비밀글(`is_secret`)을 뺄지
     * @return array<int, array<string, mixed>>
     */
    public static function mergeRecent(array $perBoard, int $limit, bool $dropSecret): array
    {
        $all = [];
        foreach ($perBoard as $entry) {
            foreach ($entry['items'] as $item) {
                if ($dropSecret && ! empty($item['is_secret'])) {
                    continue;
                }
                $item['board_slug'] = $entry['board']['slug'];
                $item['board_name'] = $entry['board']['name'];
                $all[] = $item;
            }
        }

        usort($all, static function (array $a, array $b): int {
            $byTime = strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));

            return $byTime !== 0 ? $byTime : ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
        });

        return array_slice($all, 0, max(0, $limit));
    }

    /**
     * 게시판 slug 집합에 든 항목만 남기고 상위 n건(원래 순서 유지).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, string>  $slugs
     * @return array<int, array<string, mixed>>
     */
    public static function keepBoards(array $items, array $slugs, int $limit): array
    {
        $allowed = array_fill_keys($slugs, true);
        $out = [];
        foreach ($items as $item) {
            if (isset($allowed[$item['board_slug'] ?? ''])) {
                $out[] = $item;
                if (count($out) >= $limit) {
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * 게시판 이름이 비어 있는 항목을 이미 가진 열람 가능 게시판 목록(slug → 이름)으로 채운다.
     * 추가 조회는 하지 않는다. 대응이 없으면 빈 이름 그대로 둔다.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, string>  $namesBySlug
     * @return array<int, array<string, mixed>>
     */
    public static function fillBoardNames(array $items, array $namesBySlug): array
    {
        foreach ($items as $i => $item) {
            if (($item['board_name'] ?? '') === '' || $item['board_name'] === null) {
                $items[$i]['board_name'] = $namesBySlug[$item['board_slug'] ?? ''] ?? '';
            }
        }

        return $items;
    }

    /**
     * 분류를 붙이고(있으면) 위키 게시판 글은 분류를 뺀다 — 화면은 "게시판 이름 · 분류", 위키는 이름만.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, string|null>|null  $categories  id => 분류 (null 이면 항목의 category 를 그대로 쓴다)
     * @param  array<int, string>  $wikiSlugs  위키 게시판 slug
     * @return array<int, array<string, mixed>>
     */
    public static function withCategory(array $items, ?array $categories, array $wikiSlugs): array
    {
        $wiki = array_fill_keys($wikiSlugs, true);
        foreach ($items as $i => $item) {
            $category = $categories === null ? ($item['category'] ?? null) : ($categories[(int) ($item['id'] ?? 0)] ?? null);
            $category = is_string($category) && $category !== '' ? $category : null;
            $items[$i]['category'] = isset($wiki[$item['board_slug'] ?? '']) ? null : $category;
        }

        return $items;
    }

    /**
     * 항목에서 지정한 키만 남긴다(응답 크기·노출 칸 고정).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, string>  $keys
     * @return array<int, array<string, mixed>>
     */
    public static function pick(array $items, array $keys): array
    {
        return array_map(static function (array $item) use ($keys): array {
            $row = [];
            foreach ($keys as $key) {
                $row[$key] = $item[$key] ?? null;
            }

            return $row;
        }, $items);
    }
}
