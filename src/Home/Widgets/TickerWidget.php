<?php

namespace Plugins\G7\Home\Widgets\Home\Widgets;

use Plugins\G7\Home\Widgets\Home\BoardPostsSource;
use Plugins\G7\Home\Widgets\Home\HomeWidget;
use Plugins\G7\Home\Widgets\Home\PostLists;

/**
 * 뉴스(공지) 티커 위젯 — 고른 게시판들의 최신글을 한 줄씩 넘긴다(0.4.0).
 *
 * 기본 게시판은 0.3.0 템플릿이 고정으로 쓰던 slug `notice` 게시판이다
 * ({@see \Plugins\G7\Home\Widgets\Home\HomeLayoutSettings::defaults()}). 비밀글은 뺀다(0.3.0
 * 공지 티커 정책). 움직임은 플러그인 JS 핸들러(`g7-home-widgets.ticker`)가 맡고, 봇 화면에는
 * 같은 목록이 정적 링크로 나간다.
 */
final class TickerWidget implements HomeWidget
{
    /** 응답에 싣는 항목 키(0.3.0 공지 티커와 같은 칸) */
    public const ITEM_KEYS = ['id', 'board_slug', 'title', 'created_at', 'created_at_formatted'];

    public function __construct(private readonly BoardPostsSource $source) {}

    public function id(): string
    {
        return 'ticker';
    }

    public function defaults(): array
    {
        return ['limit' => 5, 'boards' => ['mode' => 'only', 'ids' => []]];
    }

    public function titleKey(): string
    {
        return 'messages.home.titles.ticker';
    }

    public function defaultIcon(): string
    {
        return 'bullhorn';
    }

    public function limitRange(): array
    {
        return [1, 20];
    }

    /**
     * 티커는 게시판 1개만 고른다: 방식은 항상 `only`, id 는 첫 번째 하나만 남긴다.
     * 다른 종류의 선택(포함 안 함 목록 등)이 남아 있으면 쓰지 않는다(빈 선택).
     */
    public function normalize(array $col, array $raw): array
    {
        $ids = ($col['boards']['mode'] ?? '') === 'only' ? ($col['boards']['ids'] ?? []) : [];
        $col['boards'] = ['mode' => 'only', 'ids' => $ids === [] ? [] : [(int) $ids[0]]];

        return $col;
    }

    public function rules(string $prefix): array
    {
        return [
            "{$prefix}.limit" => ['integer', 'min:1', 'max:20'],
            "{$prefix}.boards.ids" => ['array', 'max:1'],
        ];
    }

    /**
     * 게시판 1개(확정 사항). 열람 가능 판정은
     * {@see \Plugins\G7\Home\Widgets\Home\BoardScope} 가 그대로 적용한다.
     */
    public function boardSelection(): string
    {
        return 'one';
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

        return ['items' => PostLists::pick(PostLists::mergeRecent($perBoard, (int) $col['limit'], true), self::ITEM_KEYS)];
    }
}
