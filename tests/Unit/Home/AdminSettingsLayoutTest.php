<?php

namespace Plugins\G7\Home\Widgets\Tests\Unit\Home;

use PHPUnit\Framework\TestCase;

/**
 * 관리자 설정 화면 생성물(resources/layouts/admin/plugin_settings.json) 구조 (관리자 화면 2차 보완).
 *
 * - 안내 이미지 흔적 없음, 섹션 박스 5개(켜기·단 수·강조 속성), 섹션 탭 폭 100%·안내 칸 없음
 * - 2단 칸 하위 탭 이름이 종류를 따라감, 아이콘 격자 첫 칸 = 기본 아이콘
 * - 칩 = 손잡이 + 이름 + 슬러그, [전체 선택]·[전체 해제] 버튼
 * - 폼 초기화는 기존 _local.form 과 병합하지 않는다(다른 화면 키 잔존 방지)
 */
class AdminSettingsLayoutTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $layout;

    protected function setUp(): void
    {
        $path = dirname(__DIR__, 3).'/resources/layouts/admin/plugin_settings.json';
        $this->layout = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_no_guide_image_traces(): void
    {
        $json = json_encode($this->layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->assertStringNotContainsString('admin-guide', $json);
        $this->assertStringNotContainsString('home.guide.', $json);
        $this->assertStringNotContainsString('g7hw-admin-guide', $json);
    }

    public function test_five_preview_boxes_follow_form(): void
    {
        for ($s = 1; $s <= 5; $s++) {
            $box = $this->byTestId("g7hw-admin-box-s{$s}");
            $this->assertNotNull($box, "box s{$s}");
            $this->assertStringContainsString("_local.form?.s{$s}_enabled", $box['props']['data-enabled']);
            $this->assertStringContainsString("_local.form?.s{$s}_columns", $box['props']['data-columns']);
            $this->assertStringContainsString('_local.hwHover ?? _local.hwFocus', $box['props']['data-active']);
            $this->assertNotNull($this->byTestId("g7hw-admin-box-s{$s}-c2"));
        }
    }

    public function test_section_rows_set_hover_and_focus(): void
    {
        for ($s = 1; $s <= 5; $s++) {
            $row = $this->byTestId("g7hw-admin-row-s{$s}");
            $types = array_column($row['actions'] ?? [], 'type');
            $this->assertSame(['mouseenter', 'mouseleave', 'focus'], $types);
            $this->assertNotContains('click', $types, '행 click 액션은 체크박스 기본 동작을 막는다');
        }
    }

    public function test_section_panels_are_full_width_with_subtabs(): void
    {
        for ($s = 1; $s <= 5; $s++) {
            $panel = $this->byTestId("g7hw-admin-panel-s{$s}");
            $this->assertSame('100%', $panel['props']['style']['width']);
            $this->assertNotNull($this->byTestId("g7hw-admin-subtabs-s{$s}"));
            foreach ([1, 2] as $c) {
                $tab = $this->byTestId("g7hw-admin-subtab-s{$s}c{$c}");
                $ifs = array_filter(array_map(fn ($n) => $n['if'] ?? null, $tab['children']));
                $this->assertContains("{{_local.form?.s{$s}c{$c}_type === 'recent'}}", $ifs);
                $this->assertSame('$t:g7-home-widgets.home.col.card'.$c, $tab['children'][0]['text']);
            }
        }
    }

    public function test_icon_grid_first_cell_is_default_icon(): void
    {
        $grid = $this->byTestId('g7hw-admin-s1c1-icon-grid');
        $cells = $grid['children'][1]['children'];

        $this->assertSame('', $cells[0]['props']['data-hw-icon-value']);
        $this->assertStringContainsString('type_icons', $cells[0]['children'][0]['props']['name']);
        $this->assertStringContainsString('homeMeta?.data?.icons', $cells[1]['iteration']['source']);
        $this->assertSame('{{iconName}}', $cells[1]['props']['title']);
    }

    public function test_chips_show_handle_name_slug_and_bulk_buttons(): void
    {
        $zone = $this->byTestId('g7hw-admin-s1c1-included');
        $chip = $zone['children'][1]['children'][0];
        $this->assertSame('grip-vertical', $chip['children'][0]['props']['name']);
        $this->assertSame('{{board.name}}', $chip['children'][1]['text']);
        $this->assertSame('{{board.slug}}', $chip['children'][2]['text']);

        $this->assertNotNull($this->byTestId('g7hw-admin-s1c1-all-in'));
        $this->assertNotNull($this->byTestId('g7hw-admin-s1c1-all-out'));
    }

    public function test_form_init_replaces_instead_of_merging(): void
    {
        $sources = array_values(array_filter($this->layout['data_sources'], fn ($d) => $d['id'] === 'homeForm'));

        $this->assertCount(1, $sources);
        $this->assertSame(['_merge' => 'replace', 'form' => '{{data}}'], $sources[0]['initLocal']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function byTestId(string $id): ?array
    {
        $found = null;
        $walk = function ($node) use (&$walk, &$found, $id): void {
            if ($found !== null || ! is_array($node)) {
                return;
            }
            if (($node['props']['data-testid'] ?? null) === $id) {
                $found = $node;

                return;
            }
            foreach ($node as $child) {
                $walk($child);
            }
        };
        $walk($this->layout);

        return $found;
    }
}
