<?php

namespace Plugins\G7\Home\Widgets\Home\Widgets;

use Plugins\G7\Home\Widgets\Home\BoardPostsSource;
use Plugins\G7\Home\Widgets\Home\HomeWidget;
use Plugins\G7\Home\Widgets\Home\PostLists;
use Plugins\G7\Home\Widgets\Home\WikiBoards;

/**
 * 최근글 위젯 — 고른 게시판들의 글을 작성일 순으로 합친다(0.4.0).
 *
 * 게시판별 최근글은 코어 `getBoardRecentPostsById()` 결과(안전집합)를 쓴다. 비밀글은 코어
 * 최근글과 같은 "제목 공개" 정책이라 빼지 않고 `is_secret` 을 그대로 싣는다.
 */
final class RecentWidget implements HomeWidget
{
    /** 응답에 싣는 항목 키(0.3.0 최근글 위젯 표시 칸과 같다) */
    public const ITEM_KEYS = ['id', 'board_slug', 'board_name', 'title', 'category', 'created_at',
        'created_at_formatted', 'view_count', 'comment_count', 'is_new', 'is_secret'];

    public function __construct(private readonly BoardPostsSource $source) {}

    public function id(): string
    {
        return 'recent';
    }

    public function defaults(): array
    {
        return ['limit' => 10, 'boards' => ['mode' => 'exclude', 'ids' => []]];
    }

    public function titleKey(): string
    {
        return 'messages.home.titles.recent';
    }

    public function defaultIcon(): string
    {
        return 'clock';
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
            $perBoard[] = ['board' => $board, 'items' => $this->source->recentOfBoard($board['id'])];
        }

        $items = PostLists::mergeRecent($perBoard, (int) $col['limit'], false);
        $items = PostLists::withCategory($items, null, WikiBoards::slugsIn($boards));

        return ['items' => PostLists::pick($items, self::ITEM_KEYS)];
    }
}
