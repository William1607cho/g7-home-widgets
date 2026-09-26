<?php

namespace Plugins\G7\Home\Widgets\Home;

/**
 * 위키 게시판 판별(관리자 화면 보완 묶음) — 최근글·인기글에서 위키 게시판 글은 분류를 빼고 게시판 이름만 보인다.
 *
 * 코어 게시판 속성(유형 `type`)으로는 위키를 구별할 수 없다(위키 게시판도 `basic`). g7-light-wiki 가 공개한
 * 설정 키 `wiki_boards`(`[{board_id, front_post_id}]`, g7-light-wiki CHANGELOG 에 적힌 설정)를 **코어 설정 헬퍼**
 * `plugin_settings('g7-light-wiki')` 로 읽는다. g7-light-wiki 의 PHP 클래스는 부르지 않는다(코드 의존 없음).
 * 플러그인이 없거나 꺼져 있으면 빈 목록 — 위키 게시판이 없는 것으로 본다.
 */
final class WikiBoards
{
    public const PLUGIN = 'g7-light-wiki';

    public const KEY = 'wiki_boards';

    /**
     * 위키 게시판 id 목록.
     *
     * @return array<int, int>
     */
    public static function ids(): array
    {
        $all = function_exists('plugin_settings') ? plugin_settings(self::PLUGIN) : [];

        return self::idsFrom(is_array($all) ? ($all[self::KEY] ?? []) : []);
    }

    /**
     * 주어진 게시판 가운데 위키 게시판의 slug.
     *
     * @param  array<int, array{id: int, slug: string, name: string}>  $boards
     * @return array<int, string>
     */
    public static function slugsIn(array $boards): array
    {
        $wiki = array_fill_keys(self::ids(), true);

        return array_values(array_map(fn (array $b) => $b['slug'], array_filter($boards, fn (array $b) => isset($wiki[(int) $b['id']]))));
    }

    /**
     * 설정 값에서 게시판 id 만 뽑는다(형태가 어긋난 줄은 버린다). 순수 함수.
     *
     * @return array<int, int>
     */
    public static function idsFrom(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }
        $ids = [];
        foreach ($rows as $row) {
            $id = is_array($row) ? ($row['board_id'] ?? null) : null;
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $ids[(int) $id] = (int) $id;
            }
        }

        return array_values($ids);
    }
}
