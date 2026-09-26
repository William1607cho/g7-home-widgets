<?php

namespace Plugins\G7\Home\Widgets\Home\Widgets;

use Plugins\G7\Home\Widgets\Home\BoardGallerySource;
use Plugins\G7\Home\Widgets\Home\HomeWidget;
use Plugins\G7\Home\Widgets\Home\PostLists;

/**
 * 갤러리 위젯 — 고른 게시판들의 최근글을 썸네일 카드로 보여 준다(0.4.0).
 *
 * 썸네일은 코어 `PostResource` 규칙({@see BoardGallerySource}). 썸네일이 없는 글은 빼지 않고
 * 화면에서 대체 틀(아이콘 칸)로 그린다(설계서 6-4, 갤러리 스킨과 같은 동작).
 */
final class GalleryWidget implements HomeWidget
{
    /** 응답에 싣는 항목 키 */
    public const ITEM_KEYS = ['id', 'board_slug', 'board_name', 'title', 'thumbnail', 'is_secret',
        'created_at', 'created_at_formatted'];

    public function __construct(private readonly BoardGallerySource $source) {}

    public function id(): string
    {
        return 'gallery';
    }

    public function defaults(): array
    {
        return ['limit' => 8, 'boards' => ['mode' => 'exclude', 'ids' => []]];
    }

    public function titleKey(): string
    {
        return 'messages.home.titles.gallery';
    }

    public function defaultIcon(): string
    {
        return 'images';
    }

    public function limitRange(): array
    {
        return [1, 20];
    }

    public function normalize(array $col, array $raw): array
    {
        return $col;
    }

    public function rules(string $prefix): array
    {
        return ["{$prefix}.limit" => ['integer', 'min:1', 'max:20']];
    }

    public function boardSelection(): string
    {
        return 'many';
    }

    public function available(): bool
    {
        return true;
    }

    public function fallback(): ?string
    {
        return null;
    }

    public function data(array $col, array $boards): array
    {
        $perBoard = [];
        foreach ($boards as $board) {
            $perBoard[] = ['board' => $board, 'items' => $this->source->ofBoard($board)];
        }
        $items = PostLists::pick(PostLists::mergeRecent($perBoard, (int) $col['limit'], false), self::ITEM_KEYS);
        foreach ($items as $i => $item) {
            $items[$i]['has_thumbnail'] = is_string($item['thumbnail']) && $item['thumbnail'] !== '';
        }

        return ['items' => $items];
    }
}
