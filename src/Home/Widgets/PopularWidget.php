<?php

namespace Plugins\G7\Home\Widgets\Home\Widgets;

use Plugins\G7\Home\Widgets\Home\BoardPostsSource;
use Plugins\G7\Home\Widgets\Home\HomeWidget;
use Plugins\G7\Home\Widgets\Home\PostLists;

/**
 * 인기글 위젯 — 기간 탭(이번주·이번달·1년)을 한 응답에 함께 싣는다(0.4.0).
 *
 * 코어 인기글 캐시(`getCachedPopularPosts`, 비밀글 제외·열람 권한 적용)의 풀에서 고른 게시판
 * 글만 남긴다. 게시판 단위 코어 인기글 API 가 없어서, 고른 게시판이 적으면 개수보다 적게 나올
 * 수 있다(확정 사항: 부족 허용). 작성자 칸은 응답에 싣지 않는다.
 *
 * 탭 전환은 브라우저 상태만 바꾼다(재호출 없음). 봇 화면은 `active` 기간 목록만 그린다.
 */
final class PopularWidget implements HomeWidget
{
    /** 탭 순서 = 화면 순서 */
    public const PERIODS = ['week', 'month', 'year'];

    /** 응답에 싣는 항목 키 */
    public const ITEM_KEYS = ['id', 'board_slug', 'board_name', 'title', 'created_at',
        'created_at_formatted', 'view_count', 'comment_count'];

    public function __construct(private readonly BoardPostsSource $source) {}

    public function id(): string
    {
        return 'popular';
    }

    public function defaults(): array
    {
        return ['limit' => 10, 'boards' => ['mode' => 'all', 'ids' => []], 'period' => 'week'];
    }

    public function titleKey(): string
    {
        return 'messages.home.titles.popular';
    }

    public function defaultIcon(): string
    {
        return 'fire';
    }

    public function limitRange(): array
    {
        return [1, 20];
    }

    public function normalize(array $col, array $raw): array
    {
        $period = $raw['period'] ?? null;
        $col['period'] = in_array($period, self::PERIODS, true) ? $period : ($col['period'] ?? 'week');

        return $col;
    }

    public function rules(string $prefix): array
    {
        return [
            "{$prefix}.limit" => ['integer', 'min:1', 'max:20'],
            "{$prefix}.period" => ['string', 'in:'.implode(',', self::PERIODS)],
        ];
    }

    public function appliesCommonExclusion(): bool
    {
        return true;
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
        $slugs = array_column($boards, 'slug');
        $names = array_column($boards, 'name', 'slug');
        $limit = (int) $col['limit'];
        $periods = [];
        foreach (self::PERIODS as $period) {
            $items = $slugs === [] ? [] : PostLists::keepBoards($this->source->popular($period, $limit), $slugs, $limit);
            $items = PostLists::fillBoardNames($items, $names);
            $periods[] = [
                'key' => $period,
                'label' => __('g7-home-widgets::messages.home.periods.'.$period),
                'active' => $period === $col['period'],
                'items' => PostLists::pick($items, self::ITEM_KEYS),
            ];
        }

        return ['period' => $col['period'], 'periods' => $periods];
    }
}
