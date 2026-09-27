<?php

namespace Plugins\G7\Home\Widgets\Home;

/**
 * 홈 섹션 응답 조립(0.4.0). 브라우저 API 와 봇 컨텍스트 필터가 같은 이 메서드를 부른다.
 *
 * 응답 모양(`data`):
 * `{has_sections, sections: [{key, row_class, cols: [{key, type, title, icon, ...위젯 데이터}]}], fallbacks: []}`
 * 꺼진 섹션은 빠진다. overlay 는 `has_sections` 가 참일 때만 그린다 — 이 값이 없으면(원복 뒤
 * 404 fallback 등) 아무것도 그리지 않는다.
 *
 * 권한: 호출자 기준 열람 가능 게시판은 코어가 정하고({@see BoardPostsSource::readableBoards()}),
 * 칸마다 {@see BoardScope} 로 그 칸의 게시판 선택을 적용한다.
 */
final class HomeLayoutService
{
    /** 티커 기본 게시판 slug — 0.3.0 템플릿 고정값 */
    public const DEFAULT_TICKER_SLUG = 'notice';

    public function __construct(
        private readonly StoredHomeLayout $stored,
        private readonly WidgetRegistry $registry,
        private readonly BoardPostsSource $source,
        private readonly ImageDelivery $images,
    ) {}

    /**
     * 현재 호출자 기준 홈 섹션 데이터.
     *
     * @return array{has_sections: bool, sections: array<int, array<string, mixed>>, fallbacks: array<int, array<string, string>>}
     */
    public function build(): array
    {
        // 게시판 선택은 칸마다 따로 적용한다. 자기 선택이 없는 목록 위젯은 모든 게시판을 포함한다(0.5.0).
        $scope = new BoardScope($this->source->readableBoards());
        $settings = $this->stored->settings();
        $layout = $settings->normalize($this->stored->raw(), $scope->idBySlug(self::DEFAULT_TICKER_SLUG));

        $sections = [];
        $fallbacks = [];
        foreach ($layout['sections'] as $i => $section) {
            if (! $section['enabled']) {
                continue;
            }
            $sKey = 's'.($i + 1);
            $cols = [];
            foreach ($section['cols'] as $c => $col) {
                $cKey = $sKey.'c'.($c + 1);
                $widget = $this->registry->resolveAvailable($col['type']);
                if ($widget === null) {
                    continue;
                }
                if ($widget->id() !== $col['type']) {
                    // 대체 지점은 여기 한 곳 — 제목·아이콘·개수·게시판 선택은 사용자 값을 유지한다(설정은 그대로).
                    $fallbacks[] = ['key' => $cKey, 'from' => $col['type'], 'to' => $widget->id()];
                    $col = $settings->fallbackCol($col, $widget->id());
                }
                $cols[] = $this->buildCol($cKey, $widget, $col, $scope);
            }
            if ($cols !== []) {
                $sections[] = ['key' => $sKey, 'row_class' => 'g7hw-section g7hw-cols-'.$section['columns'], 'cols' => $cols];
            }
        }

        // 갤러리·웹진 썸네일 → image-delivery 변환본(0.4.2). 모든 칸을 모아 계약을 한 번만 부른다.
        // 위젯 목록 캐시 뒤에서 하므로 변환본이 생기면 다음 요청부터 바로 쓰인다.
        $sections = ThumbnailVariants::apply($sections, $this->images->lookup(ThumbnailVariants::urls($sections)));

        return ['has_sections' => $sections !== [], 'sections' => $sections, 'fallbacks' => $fallbacks];
    }

    /**
     * @param  array<string, mixed>  $col  정리된 칸 설정
     * @return array<string, mixed>
     */
    private function buildCol(string $key, HomeWidget $widget, array $col, BoardScope $scope): array
    {
        $title = $col['title'] !== '' ? $col['title'] : __('g7-home-widgets::'.$widget->titleKey());

        return [
            'key' => $key,
            'type' => $widget->id(),
            'title' => $title,
            'icon' => $col['icon'] !== '' ? $col['icon'] : $widget->defaultIcon(),
            'empty_text' => __('g7-home-widgets::messages.home.empty.'.$widget->id()),
        ] + $widget->data($col, $widget->boardSelection() === 'none' ? [] : $scope->resolve($col['boards']));
    }
}
