<?php

namespace Plugins\G7\Home\Widgets\Tests\Unit\Home;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Home\Widgets\Home\BoardPostsSource;
use Plugins\G7\Home\Widgets\Home\HomeLayoutSettings;
use Plugins\G7\Home\Widgets\Home\WidgetRegistry;
use Plugins\G7\Home\Widgets\Home\Widgets\PopularWidget;
use Plugins\G7\Home\Widgets\Home\Widgets\RecentWidget;
use Plugins\G7\Home\Widgets\Home\Widgets\TickerWidget;
use ReflectionClass;

/**
 * 홈 섹션 설정 기본값·정리 (0.4.0) — 순수 클래스, DB·앱 컨테이너 없음.
 *
 * 위젯의 기본값·정리는 데이터 소스를 쓰지 않으므로 소스는 생성자 없이 만든 빈 인스턴스를 넘긴다.
 */
class HomeLayoutSettingsTest extends TestCase
{
    private function settings(): HomeLayoutSettings
    {
        $source = (new ReflectionClass(BoardPostsSource::class))->newInstanceWithoutConstructor();

        return new HomeLayoutSettings(new WidgetRegistry([
            new RecentWidget($source),
            new PopularWidget($source),
            new TickerWidget($source),
        ]));
    }

    public function test_defaults_match_the_current_home(): void
    {
        $d = $this->settings()->defaults(7);

        $this->assertSame(HomeLayoutSettings::VERSION, $d['_meta']['version']);
        $this->assertCount(5, $d['sections']);
        $this->assertSame([true, true, false, false, false], array_column($d['sections'], 'enabled'));
        $this->assertSame(2, $d['sections'][0]['columns']);
        $this->assertSame(['recent', 'popular'], array_column($d['sections'][0]['cols'], 'type'));
        $this->assertSame(1, $d['sections'][1]['columns']);
        $this->assertSame('ticker', $d['sections'][1]['cols'][0]['type']);
        $this->assertSame(['mode' => 'only', 'ids' => [7]], $d['sections'][1]['cols'][0]['boards']);
        $this->assertSame(5, $d['sections'][1]['cols'][0]['limit']);
        $this->assertSame('week', $d['sections'][0]['cols'][1]['period']);
        $this->assertSame(['mode' => 'all', 'ids' => []], $d['sections'][0]['cols'][0]['boards']);
        $this->assertSame('', $d['sections'][0]['cols'][0]['title']);
        $this->assertSame('', $d['sections'][0]['cols'][0]['icon']);
    }

    public function test_ticker_default_has_no_board_when_notice_board_is_missing(): void
    {
        $d = $this->settings()->defaults(null);

        $this->assertSame(['mode' => 'only', 'ids' => []], $d['sections'][1]['cols'][0]['boards']);
    }

    public function test_absent_or_malformed_value_falls_back_to_defaults(): void
    {
        $s = $this->settings();

        $this->assertSame($s->defaults(3), $s->normalize(null, 3));
        $this->assertSame($s->defaults(3), $s->normalize('x', 3));
        $this->assertSame($s->defaults(3), $s->normalize(['sections' => 'x'], 3));
    }

    public function test_sections_are_always_five_and_missing_ones_use_defaults(): void
    {
        $s = $this->settings();
        $out = $s->normalize(['sections' => [['enabled' => false, 'columns' => 1, 'cols' => [['type' => 'popular']]]]], 3);

        $this->assertCount(5, $out['sections']);
        $this->assertFalse($out['sections'][0]['enabled']);
        $this->assertSame('popular', $out['sections'][0]['cols'][0]['type']);
        $this->assertSame($s->defaults(3)['sections'][1], $out['sections'][1]);
    }

    public function test_columns_are_one_or_two_and_cols_follow_columns(): void
    {
        $s = $this->settings();
        $out = $s->normalize(['sections' => [
            ['enabled' => true, 'columns' => 3, 'cols' => []],
            ['enabled' => true, 'columns' => 2, 'cols' => [['type' => 'ticker']]],
            ['enabled' => '1', 'columns' => '1', 'cols' => [['type' => 'recent'], ['type' => 'popular']]],
        ]], null);

        $this->assertSame(2, $out['sections'][0]['columns']);
        $this->assertCount(2, $out['sections'][0]['cols']);
        $this->assertSame(2, $out['sections'][1]['columns']);
        $this->assertSame(['ticker', 'recent'], array_column($out['sections'][1]['cols'], 'type'));
        $this->assertTrue($out['sections'][2]['enabled']);
        $this->assertCount(1, $out['sections'][2]['cols']);
    }

    public function test_column_fields_are_cleaned(): void
    {
        $out = $this->settings()->normalize(['sections' => [[
            'enabled' => true,
            'columns' => 2,
            'cols' => [
                ['type' => 'nope', 'title' => '  a   b  ', 'icon' => 'Bad Icon', 'limit' => 999,
                    'boards' => ['mode' => 'weird', 'ids' => [3, '2', 3, -1, 'x', 0]]],
                ['type' => 'popular', 'title' => str_repeat('가', 80), 'icon' => 'fire', 'limit' => '0', 'period' => 'today',
                    'boards' => ['mode' => 'only', 'ids' => [9]]],
            ],
        ]]], null);

        [$a, $b] = $out['sections'][0]['cols'];
        $this->assertSame('recent', $a['type']);
        $this->assertSame('a b', $a['title']);
        $this->assertSame('', $a['icon']);
        $this->assertSame(20, $a['limit']);
        $this->assertSame(['mode' => 'all', 'ids' => [2, 3]], $a['boards']);

        $this->assertSame(60, mb_strlen($b['title']));
        $this->assertSame('fire', $b['icon']);
        $this->assertSame(1, $b['limit']);
        $this->assertSame('week', $b['period']);
        $this->assertSame(['mode' => 'only', 'ids' => [9]], $b['boards']);
    }

    public function test_changing_type_takes_that_type_defaults(): void
    {
        $out = $this->settings()->normalize(['sections' => [[
            'enabled' => true, 'columns' => 1, 'cols' => [['type' => 'ticker']],
        ]]], null);

        $col = $out['sections'][0]['cols'][0];
        $this->assertSame('ticker', $col['type']);
        $this->assertSame(5, $col['limit']);
        $this->assertArrayNotHasKey('period', $col);
    }

    public function test_ticker_keeps_one_board_in_only_mode(): void
    {
        $out = $this->settings()->normalize(['sections' => [[
            'enabled' => true, 'columns' => 1,
            'cols' => [['type' => 'ticker', 'boards' => ['mode' => 'all', 'ids' => [9, 3, 5]]]],
        ]]], null);

        $this->assertSame(['mode' => 'only', 'ids' => [3]], $out['sections'][0]['cols'][0]['boards']);
    }

    public function test_board_ids_are_capped(): void
    {
        $ids = HomeLayoutSettings::normalizeIds(range(1, HomeLayoutSettings::MAX_BOARD_IDS + 10));

        $this->assertCount(HomeLayoutSettings::MAX_BOARD_IDS, $ids);
    }
}
