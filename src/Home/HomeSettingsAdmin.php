<?php

namespace Plugins\G7\Home\Widgets\Home;

use Modules\Sirsoft\Board\Services\BoardService;
use Plugins\G7\Home\Widgets\Support\BoardFilterSettings;

/**
 * 관리자 설정 화면용 서비스(0.4.0) — 평면 폼 조회와 저장 전 변환.
 *
 * - {@see self::form()}: 현재 저장값(없으면 기본값)을 평면 폼으로. `GET admin/home-form` 이 쓴다.
 * - {@see self::prepareForSave()}: 코어 설정 저장 경로의 저장 전 필터(`core.plugin_settings.filter_save_data`)가
 *   부른다. 요청의 `home_layout_form` 을 `home_layout` 구조로 바꾸고 정리한 뒤 평면 키는 버린다.
 *   고를 수 있는 게시판 id 는 활성 게시판으로 좁힌다(없는 id 제거). 공통 제외 목록은 0.3.0 과 같은
 *   정리만 한다.
 *
 * 게시판 목록은 코어 `BoardService::getActiveBoards()` 로만 얻는다(이 클래스는 쿼리를 쓰지 않는다).
 */
final class HomeSettingsAdmin
{
    public function __construct(
        private readonly HomeLayoutSettings $settings,
        private readonly BoardService $boards,
    ) {}

    /**
     * 현재 설정의 평면 폼 + 공통 제외 목록.
     *
     * @return array<string, mixed>
     */
    public function form(): array
    {
        $all = function_exists('plugin_settings') ? plugin_settings(BoardFilterSettings::IDENTIFIER) : [];
        $raw = is_array($all) ? ($all[HomeLayoutSettings::KEY] ?? null) : null;
        $layout = $this->settings->normalize($raw, $this->activeIdBySlug(HomeLayoutService::DEFAULT_TICKER_SLUG));

        return HomeLayoutForm::toFlat($layout, $this->settings->defaultCol('recent'))
            + ['excluded_board_ids' => BoardFilterSettings::excludedIds()];
    }

    /**
     * 저장 전 변환. 이 플러그인 설정 저장에만 부른다.
     *
     * @param  array<string, mixed>  $settings  저장할 값(검증 통과분)
     * @return array<string, mixed>
     */
    public function prepareForSave(array $settings): array
    {
        if (array_key_exists(BoardFilterSettings::KEY, $settings)) {
            $settings[BoardFilterSettings::KEY] = BoardFilterSettings::normalize($settings[BoardFilterSettings::KEY]);
        }
        if (! is_array($settings[HomeLayoutForm::INPUT_KEY] ?? null)) {
            unset($settings[HomeLayoutForm::INPUT_KEY]);

            return $settings;
        }

        $layout = $this->settings->normalize(HomeLayoutForm::toStructure($settings[HomeLayoutForm::INPUT_KEY]), null);
        $active = array_fill_keys($this->activeIds(), true);
        foreach ($layout['sections'] as $i => $section) {
            foreach ($section['cols'] as $c => $col) {
                $ids = array_values(array_filter($col['boards']['ids'], fn (int $id) => isset($active[$id])));
                $layout['sections'][$i]['cols'][$c]['boards']['ids'] = $ids;
                if (array_key_exists('html', $col)) {
                    // 저장 시 정제(코어 HtmlSanitizer). 표시 때도 다시 정제된다.
                    $layout['sections'][$i]['cols'][$c]['html'] = HomeHtml::sanitize((string) $col['html']);
                }
            }
        }
        unset($settings[HomeLayoutForm::INPUT_KEY]);
        $settings[HomeLayoutSettings::KEY] = $layout;

        return $settings;
    }

    /**
     * @return array<int, int>
     */
    private function activeIds(): array
    {
        return $this->boards->getActiveBoards()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function activeIdBySlug(string $slug): ?int
    {
        $board = $this->boards->getActiveBoards()->firstWhere('slug', $slug);

        return $board ? (int) $board->id : null;
    }
}
