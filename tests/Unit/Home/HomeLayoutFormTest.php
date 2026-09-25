<?php

namespace Plugins\G7\Home\Widgets\Tests\Unit\Home;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Home\Widgets\Home\BoardPostsSource;
use Plugins\G7\Home\Widgets\Home\HomeLayoutForm;
use Plugins\G7\Home\Widgets\Home\HomeLayoutSettings;
use Plugins\G7\Home\Widgets\Home\WidgetRegistry;
use Plugins\G7\Home\Widgets\Home\Widgets\PopularWidget;
use Plugins\G7\Home\Widgets\Home\Widgets\RecentWidget;
use Plugins\G7\Home\Widgets\Home\Widgets\TickerWidget;
use ReflectionClass;

/**
 * 관리자 평면 폼 ↔ home_layout 구조, 검증 규칙 모양 (0.4.0) — 순수 클래스.
 */
class HomeLayoutFormTest extends TestCase
{
    private function settings(): HomeLayoutSettings
    {
        $source = (new ReflectionClass(BoardPostsSource::class))->newInstanceWithoutConstructor();

        return new HomeLayoutSettings(new WidgetRegistry([
            new RecentWidget($source), new PopularWidget($source), new TickerWidget($source),
        ]));
    }

    public function test_round_trip_of_defaults_is_lossless(): void
    {
        $s = $this->settings();
        $defaults = $s->defaults(4);
        $flat = HomeLayoutForm::toFlat($defaults, $s->defaultCol('recent'));

        $this->assertTrue($flat['s1_enabled']);
        $this->assertSame(2, $flat['s1_columns']);
        $this->assertSame('ticker', $flat['s2c1_type']);
        $this->assertSame(4, $flat['s2c1_board']);
        $this->assertSame('recent', $flat['s2c2_type']);
        $this->assertSame($defaults, $s->normalize(HomeLayoutForm::toStructure($flat), 4));
    }

    public function test_one_column_drops_the_second_col_but_flat_keeps_it(): void
    {
        $s = $this->settings();
        $flat = HomeLayoutForm::toFlat($s->defaults(null), $s->defaultCol('recent'));
        $flat['s1_columns'] = '1';

        $out = $s->normalize(HomeLayoutForm::toStructure($flat), null);
        $this->assertSame(1, $out['sections'][0]['columns']);
        $this->assertCount(1, $out['sections'][0]['cols']);
        $this->assertArrayHasKey('s1c2_type', $flat);
    }

    public function test_ticker_uses_board_field_not_ids(): void
    {
        $s = $this->settings();
        $flat = HomeLayoutForm::toFlat($s->defaults(null), $s->defaultCol('recent'));
        $flat['s1c1_type'] = 'ticker';
        $flat['s1c1_ids'] = [3, 4, 5];
        $flat['s1c1_board'] = '9';

        $col = $s->normalize(HomeLayoutForm::toStructure($flat), null)['sections'][0]['cols'][0];
        $this->assertSame(['mode' => 'only', 'ids' => [9]], $col['boards']);
    }

    public function test_rules_cover_every_field_and_ticker_limit(): void
    {
        $rules = HomeLayoutForm::rules(['recent', 'popular', 'ticker'], [
            HomeLayoutForm::INPUT_KEY => ['s1c1_type' => 'ticker', 's1c2_type' => 'recent'],
        ]);

        $this->assertArrayHasKey('home_layout_form.s5c2_period', $rules);
        $this->assertContains('in:recent,popular,ticker', $rules['home_layout_form.s3c1_type']);
        $this->assertContains('max:10', $rules['home_layout_form.s1c1_limit']);
        $this->assertContains('max:20', $rules['home_layout_form.s1c2_limit']);
        // 폼 1 + 섹션 5×2(enabled·columns) + 칸 10×10(type·limit·mode·ids·ids.*·board·period·title·icon·html)
        $this->assertSame(1 + 5 * 2 + 10 * 10, count($rules));
    }

    public function test_icon_rule_uses_the_allow_list_when_given(): void
    {
        $rules = HomeLayoutForm::rules(['recent'], [], ['clock', 'fire']);

        $this->assertContains('in:clock,fire', $rules['home_layout_form.s1c1_icon']);
    }

    public function test_html_round_trips_through_the_flat_form(): void
    {
        $flat = ['s1_enabled' => true, 's1_columns' => 1, 's1c1_type' => 'html', 's1c1_limit' => 1, 's1c1_html' => '<p>x</p>'];
        $structure = HomeLayoutForm::toStructure($flat);

        $this->assertSame('<p>x</p>', $structure['sections'][0]['cols'][0]['html']);
    }
}
