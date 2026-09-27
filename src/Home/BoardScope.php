<?php

namespace Plugins\G7\Home\Widgets\Home;

/**
 * 칸 하나가 쓸 게시판 집합 계산(0.4.0). 순수 클래스.
 *
 * 위젯마다 자기 게시판 선택을 가진다(사이트 공통 제외 목록은 없다).
 * - `exclude`(목록 위젯 기본): 열람 가능 − 포함 안 함 목록. 새로 만든 게시판은 자동으로 포함된다.
 * - `only`(티커, 옛 저장값): 열람 가능 ∩ 고른 목록
 * - `all`(옛 저장값): 열람 가능 전체
 *
 * 열람 가능 목록은 코어 `BoardService::getReadableActiveBoards()` 결과를 그대로 받는다 —
 * 권한 판정은 이 클래스가 하지 않는다. 설정은 "표시 여부"만 좁힐 수 있고 권한을 넓힐 수 없다.
 */
final class BoardScope
{
    /** @var array<int, array{id: int, slug: string, name: string}> id => 게시판 */
    private array $readable = [];

    /**
     * @param  iterable<array{id: int, slug: string, name: string}>  $readable  열람 가능 활성 게시판(코어 정렬 순서)
     */
    public function __construct(iterable $readable)
    {
        foreach ($readable as $board) {
            $this->readable[(int) $board['id']] = [
                'id' => (int) $board['id'],
                'slug' => (string) $board['slug'],
                'name' => (string) $board['name'],
            ];
        }
    }

    /**
     * 칸 설정의 `boards` 로 실제 쓸 게시판 목록을 정한다(코어 정렬 순서 유지).
     *
     * 열람 가능 목록 밖의 id 는 어떤 방식에서도 나오지 않는다.
     *
     * @param  array{mode: string, ids: array<int, int>}  $boards  정리된 선택
     * @return array<int, array{id: int, slug: string, name: string}>
     */
    public function resolve(array $boards): array
    {
        $mode = $boards['mode'] ?? 'all';
        $ids = array_fill_keys(array_map('intval', $boards['ids'] ?? []), true);

        $out = [];
        foreach ($this->readable as $id => $board) {
            if ($mode === 'only' && ! isset($ids[$id])) {
                continue;
            }
            if ($mode === 'exclude' && isset($ids[$id])) {
                continue;
            }
            $out[] = $board;
        }

        return $out;
    }

    /**
     * 열람 가능 게시판 id 목록(코어 정렬 순서).
     *
     * @return array<int, int>
     */
    public function ids(): array
    {
        return array_keys($this->readable);
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
