<?php

namespace Plugins\G7\Home\Widgets\Home;

use Modules\Sirsoft\Board\Services\BoardService;

/**
 * 관리자 설정 화면용 서비스(0.4.0) — 평면 폼 조회와 저장 전 변환.
 *
 * - {@see self::form()}: 현재 저장값(없으면 기본값)을 평면 폼으로. `GET admin/home-form` 이 쓴다.
 * - {@see self::prepareForSave()}: 코어 설정 저장 경로의 저장 전 필터(`core.plugin_settings.filter_save_data`)가
 *   부른다. 요청의 `home_layout_form` 을 `home_layout` 구조로 바꾸고 정리한 뒤 평면 키는 버린다.
 *   고를 수 있는 게시판 id 는 활성 게시판으로 좁힌다(없는 id 제거).
 * - 0.5.0: 0.4.x 의 옛 공통 제외 키는 스키마에 없어 코어가 요청에서 버리고, 파일에 남은 값은 병합 저장으로
 *   그대로 남는다(이 클래스는 그 키를 다루지 않는다).
 * - {@see self::meta()}: 화면용 활성 게시판 목록, 제목 아이콘 허용 목록, 종류별 기본 아이콘. `GET admin/home-meta`.
 *
 * 게시판 목록은 코어 `BoardService::getActiveBoards()` 로만 얻는다(이 클래스는 쿼리를 쓰지 않는다).
 */
final class HomeSettingsAdmin
{
    public function __construct(
        private readonly StoredHomeLayout $stored,
        private readonly BoardService $boards,
        private readonly WidgetRegistry $registry,
    ) {}

    /**
     * 현재 설정의 평면 폼.
     *
     * @return array<string, mixed>
     */
    public function form(): array
    {
        $settings = $this->stored->settings();
        $layout = $settings->normalize($this->stored->raw(), $this->activeIdBySlug(HomeLayoutService::DEFAULT_TICKER_SLUG));

        return HomeLayoutForm::toFlat($layout, $settings->defaultCol('recent'), $this->activeIds());
    }

    /**
     * 화면용 메타: 활성 게시판(칩·티커 선택지), 제목 아이콘 허용 목록(격자 선택기), 종류별 기본 아이콘(격자 첫 칸),
     * 종류별 사용 가능 여부(애드온 의존 종류 — 웹진 — 는 없으면 비활성 선택지), 웹진 애드온 저장소 주소(안내 링크).
     *
     * @return array{boards: array<int, array{id: int, name: string, slug: string}>, icons: array<int, string>, type_icons: array<string, string>, type_available: array<string, bool>, webzine_repository: string}
     */
    public function meta(): array
    {
        $boards = $this->boards->getActiveBoards('id', 'asc')->map(fn ($b) => [
            'id' => (int) $b->id,
            'name' => (string) $b->getLocalizedName(),
            'slug' => (string) $b->slug,
        ])->values()->all();

        $typeIcons = [];
        $typeAvailable = [];
        foreach ($this->registry->ids() as $id) {
            $widget = $this->registry->get($id);
            $typeIcons[$id] = $widget->defaultIcon();
            $typeAvailable[$id] = $widget->available();
        }

        return ['boards' => $boards, 'icons' => HomeIcons::load(), 'type_icons' => $typeIcons,
            'type_available' => $typeAvailable, 'webzine_repository' => WebzineAddon::REPOSITORY_URL];
    }

    /**
     * 저장 전 변환. 이 플러그인 설정 저장에만 부른다.
     *
     * @param  array<string, mixed>  $settings  저장할 값(검증 통과분)
     * @return array<string, mixed>
     */
    public function prepareForSave(array $settings): array
    {
        if (! is_array($settings[HomeLayoutForm::INPUT_KEY] ?? null)) {
            unset($settings[HomeLayoutForm::INPUT_KEY]);

            return $settings;
        }

        $normalizer = $this->stored->settings();
        $layout = $normalizer->normalize(HomeLayoutForm::toStructure($settings[HomeLayoutForm::INPUT_KEY]), null);
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
        return $this->boards->getActiveBoards('id', 'asc')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function activeIdBySlug(string $slug): ?int
    {
        $board = $this->boards->getActiveBoards()->firstWhere('slug', $slug);

        return $board ? (int) $board->id : null;
    }
}
