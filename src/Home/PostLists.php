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
