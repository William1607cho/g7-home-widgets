<?php

namespace Plugins\G7\Home\Widgets\Tests\Unit\Home;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Home\Widgets\Home\BoardPostsSource;
use Plugins\G7\Home\Widgets\Home\BoardScope;
use Plugins\G7\Home\Widgets\Home\HomeLayoutSettings;
use Plugins\G7\Home\Widgets\Home\StoredHomeLayout;
use Plugins\G7\Home\Widgets\Home\WidgetRegistry;
use Plugins\G7\Home\Widgets\Home\Widgets\PopularWidget;
use Plugins\G7\Home\Widgets\Home\Widgets\RecentWidget;
use Plugins\G7\Home\Widgets\Home\Widgets\TickerWidget;
use ReflectionClass;

require_once dirname(__DIR__, 2).'/stubs/plugin_settings.php';

/**
 * 칸의 게시판 선택 상태별 실효 게시판 집합 (0.5.0) — 순수 클래스.
 *
 * 목록 위젯 칸의 `boards` 가 없음·null·빈 배열·값 있음일 때 {@see HomeLayoutSettings::normalize()} →
 * {@see BoardScope::resolve()} 결과를 본다. 0.4.x 의 옛 공통 제외 키가 설정 파일에 남아 있어도 결과가
 * 같아야 한다(0.5.0 은 그 키를 읽지 않는다).
 */
class UnsavedBoardSelectionTest extends TestCase
{
    private function settings(): HomeLayoutSettings
    {
        $source = (new ReflectionClass(BoardPostsSource::class))->newInstanceWithoutConstructor();

        return new HomeLayoutSettings(new WidgetRegistry([
            new RecentWidget($source), new PopularWidget($source), new TickerWidget($source),
        ]));
    }

    private function scope(): BoardScope
    {
        return new BoardScope([
            ['id' => 5, 'slug' => 'free', 'name' => 'Free'],
            ['id' => 1, 'slug' => 'notice', 'name' => 'Notice'],
            ['id' => 9, 'slug' => 'qna', 'name' => 'QnA'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $col  칸 원값(type 제외)
     * @return array<int, int>
     */
    private function effectiveIds(HomeLayoutSettings $s, array $col): array
    {
        $layout = $s->normalize(['sections' => [['enabled' => true, 'columns' => 1,
            'cols' => [['type' => 'recent'] + $col]]]], null);

        return array_column($this->scope()->resolve($layout['sections'][0]['cols'][0]['boards']), 'id');
    }

    public function test_each_selection_state_resolves_as_documented(): void
    {
        $s = $this->settings();

        $this->assertSame([5, 1, 9], $this->effectiveIds($s, []), '키 없음');
        $this->assertSame([5, 1, 9], $this->effectiveIds($s, ['boards' => null]), 'null');
        $this->assertSame([5, 1, 9], $this->effectiveIds($s, ['boards' => []]), '빈 배열');
        $this->assertSame([5, 1, 9], $this->effectiveIds($s, ['boards' => ['mode' => 'exclude', 'ids' => []]]), '빈 목록');
        $this->assertSame([5, 1], $this->effectiveIds($s, ['boards' => ['mode' => 'exclude', 'ids' => [9]]]), '값 있음');
    }

    public function test_stored_layout_hands_over_the_normalizer_unchanged(): void
    {
        $s = $this->settings();

        $this->assertSame($s, (new StoredHomeLayout($s))->settings());
    }

    public function test_a_leftover_legacy_key_in_the_settings_file_changes_nothing(): void
    {
        if (! defined('G7HW_TEST_PLUGIN_SETTINGS_STUB')) {
            $this->markTestSkipped('코어 plugin_settings() 가 있는 환경 — 저장소 설정 파일을 건드리지 않으려고 건너뛴다.');
        }

        $layout = ['sections' => [['enabled' => true, 'columns' => 2,
            'cols' => [['type' => 'recent'], ['type' => 'popular', 'boards' => null]]]]];

        $results = [];
        foreach ([['home_layout' => $layout], ['home_layout' => $layout, 'excluded_board_ids' => [9, 1]]] as $file) {
            $GLOBALS['g7hw_test_plugin_settings'] = $file;
            $stored = new StoredHomeLayout($this->settings());
            $normalized = $stored->settings()->normalize($stored->raw(), null);
            $results[] = array_map(
                fn (array $col) => array_column($this->scope()->resolve($col['boards']), 'id'),
                $normalized['sections'][0]['cols'],
            );
        }
        unset($GLOBALS['g7hw_test_plugin_settings']);

        $this->assertSame([[5, 1, 9], [5, 1, 9]], $results[0]);
        $this->assertSame($results[0], $results[1]);
    }
}
