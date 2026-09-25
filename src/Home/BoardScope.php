<?php

namespace Plugins\G7\Home\Widgets\Home;

/**
 * 칸 하나가 쓸 게시판 집합 계산(0.4.0). 순수 클래스.
 *
 * `쓰는 게시판 = 코어 열람 가능 게시판(호출자 기준) ∩ (mode=all ? 전체 : 선택 ids) − 공통 제외 목록`
 *
 * 열람 가능 목록은 코어 `BoardService::getReadableActiveBoards()` 결과를 그대로 받는다 —
 * 권한 판정은 이 클래스가 하지 않는다. 설정은 "표시 여부"만 좁힐 수 있고 권한을 넓힐 수 없다.
 */
final class BoardScope
{
    /** @var array<int, array{id: int, slug: string, name: string}> id => 게시판 */
    private array $readable = [];

    /** @var array<int, true> */
    private array $excluded = [];

    /**
     * @param  iterable<array{id: int, slug: string, name: string}>  $readable  열람 가능 활성 게시판(코어 정렬 순서)
     * @param  array<int, int>  $excludedIds  공통 제외 게시판 id
     */
    public function __construct(iterable $readable, array $excludedIds)
    {
        foreach ($readable as $board) {
            $this->readable[(int) $board['id']] = [
                'id' => (int) $board['id'],
                'slug' => (string) $board['slug'],
                'name' => (string) $board['name'],
            ];
        }
        foreach ($excludedIds as $id) {
            $this->excluded[(int) $id] = true;
        }
    }

    /**
     * 칸 설정의 `boards` 로 실제 쓸 게시판 목록을 정한다(코어 정렬 순서 유지).
     *
     * @param  array{mode: string, ids: array<int, int>}  $boards  정리된 선택
     * @return array<int, array{id: int, slug: string, name: string}>
     */
    public function resolve(array $boards): array
    {
        $only = null;
        if (($boards['mode'] ?? 'all') === 'only') {
            $only = array_fill_keys(array_map('intval', $boards['ids'] ?? []), true);
        }

        $out = [];
        foreach ($this->readable as $id => $board) {
            if (isset($this->excluded[$id])) {
                continue;
            }
            if ($only !== null && ! isset($only[$id])) {
                continue;
            }
            $out[] = $board;
        }

        return $out;
    }

    /**
     * 열람 가능 게시판 가운데 slug 가 일치하는 것의 id.
     */
    public function idBySlug(string $slug): ?int
    {
        foreach ($this->readable as $id => $board) {
            if ($board['slug'] === $slug) {
                return $id;
            }
        }

        return null;
    }
}
