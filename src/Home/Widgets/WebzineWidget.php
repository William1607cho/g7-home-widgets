<?php

namespace Plugins\G7\Home\Widgets\Home\Widgets;

use Plugins\G7\Home\Widgets\Home\BoardGallerySource;
use Plugins\G7\Home\Widgets\Home\HomeWidget;
use Plugins\G7\Home\Widgets\Home\PostLists;
use Plugins\G7\Home\Widgets\Home\WebzineAddon;
use Plugins\G7\Home\Widgets\Home\WebzineItems;
use Plugins\G7\Home\Widgets\Home\WidgetCache;
use Plugins\G7\Home\Widgets\Home\WikiBoards;

/**
 * 웹진 스타일 최근글 — 썸네일 + 제목 + 요약 + 게시판 이름·분류 + 날짜(0.4.0 웹진 묶음).
 *
 * 글 목록은 갤러리와 같은 코어 경로({@see BoardGallerySource}: `PostService`·`PostResource`, 열람 가능
 * 게시판)로 얻고, 요약·썸네일만 g7-webzine-addon 공개 계약으로 받는다({@see WebzineAddon}).
 * 비밀글은 제목만 보이고 썸네일 자리는 자물쇠, 요약은 없다. 썸네일 없는 일반 글은 애드온 설정의 대체
 * 이미지가 있으면 그것, 없으면 대체 틀. 위키 게시판 글은 분류를 뺀다.
 *
 * 애드온이 없거나 꺼지면 쓸 수 없는 종류가 되고, 조립기가 최근글로 대신 그린다(설정은 그대로).
 */
final class WebzineWidget implements HomeWidget
{
    /** 응답에 싣는 항목 키 */
    public const ITEM_KEYS = ['id', 'board_slug', 'board_name', 'title', 'category', 'summary', 'thumbnail',
        'has_thumbnail', 'fallback_image', 'is_secret', 'created_at', 'created_at_formatted'];

    public function __construct(
        private readonly BoardGallerySource $source,
        private readonly WebzineAddon $addon,
        private readonly WidgetCache $cache,
    ) {}

    public function id(): string
    {
        return 'webzine';
    }

    public function defaults(): array
    {
        return ['limit' => 5, 'boards' => ['mode' => 'exclude', 'ids' => []]];
    }

    public function titleKey(): string
    {
        return 'messages.home.titles.webzine';
    }

    public function defaultIcon(): string
    {
        return 'newspaper';
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
        return $this->addon->available();
    }

    public function fallback(): ?string
    {
        return 'recent';
    }

    public function data(array $col, array $boards): array
    {
        $perBoard = [];
        foreach ($boards as $board) {
            $perBoard[] = ['board' => $board, 'items' => $this->source->ofBoard($board)];
        }
        $items = PostLists::mergeRecent($perBoard, (int) $col['limit'], false);
        $items = PostLists::withCategory($items, null, WikiBoards::slugsIn($boards));

        $input = WebzineItems::contractInput($items);
        // 카드 값은 사용자와 무관하다(비밀글은 누구에게나 비운다) → 계약 입력 묶음으로 짧게 캐시한다.
        $cards = $input === [] ? [] : $this->cache->remember('webzine:'.sha1(json_encode($input)), fn () => $this->addon->cards($input));

        return ['items' => PostLists::pick(WebzineItems::apply($items, is_array($cards) ? $cards : []), self::ITEM_KEYS)];
    }
}
