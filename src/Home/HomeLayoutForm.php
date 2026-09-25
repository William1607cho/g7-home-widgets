<?php

namespace Plugins\G7\Home\Widgets\Home;

use Closure;

/**
 * 관리자 설정 화면의 평면 폼 ↔ `home_layout` 구조 변환·검증 규칙(0.4.0). 순수 클래스.
 *
 * 화면 상태는 평면 키(`s1_enabled`, `s1_columns`, `s1c1_type`, `s1c1_ids` …)로 둔다. 코어 setState 가
 * 키 안의 표현식을 해석하지 않고, 배열 첨자 점 경로(`sections.0.cols.1`)는 객체로 바뀔 위험이 있어서다.
 * 저장은 코어 설정 저장 경로(`PUT /api/admin/plugins/{id}/settings`)에 `home_layout_form` 으로 보내고,
 * 저장 전 필터가 {@see self::toStructure()} → {@see HomeLayoutSettings::normalize()} 로 바꿔 넣는다.
 *
 * 칸마다 두 칸(c1·c2)을 항상 싣는다 — 1단으로 바꿔도 두 번째 칸 값이 남아 있다가 2단으로 돌아오면 쓰인다.
 */
final class HomeLayoutForm
{
    /** 저장 요청의 평면 폼 키 */
    public const INPUT_KEY = 'home_layout_form';

    /** 칸 평면 필드(접미사) */
    public const COL_FIELDS = ['type', 'title', 'icon', 'limit', 'mode', 'ids', 'board', 'period', 'html'];

    /**
     * 정리된 구조 → 평면 폼.
     *
     * @param  array<string, mixed>  $layout  {@see HomeLayoutSettings::normalize()} 결과
     * @param  array<string, mixed>  $defaultCol  두 번째 칸이 없을 때 채울 기본 칸
     * @return array<string, mixed>
     */
    public static function toFlat(array $layout, array $defaultCol): array
    {
        $flat = [];
        foreach ($layout['sections'] as $i => $section) {
            $s = 's'.($i + 1);
            $flat[$s.'_enabled'] = (bool) $section['enabled'];
            $flat[$s.'_columns'] = (int) $section['columns'];
            for ($c = 0; $c < 2; $c++) {
                $col = $section['cols'][$c] ?? $defaultCol;
                $k = $s.'c'.($c + 1);
                $ids = array_values($col['boards']['ids'] ?? []);
                $flat[$k.'_type'] = $col['type'];
                $flat[$k.'_title'] = (string) ($col['title'] ?? '');
                $flat[$k.'_icon'] = (string) ($col['icon'] ?? '');
                $flat[$k.'_limit'] = (int) $col['limit'];
                $flat[$k.'_mode'] = $col['boards']['mode'] ?? 'all';
                $flat[$k.'_ids'] = $ids;
                $flat[$k.'_board'] = $col['type'] === 'ticker' && $ids !== [] ? (int) $ids[0] : null;
                $flat[$k.'_period'] = $col['period'] ?? 'week';
                $flat[$k.'_html'] = (string) ($col['html'] ?? '');
            }
        }

        return $flat;
    }

    /**
     * 평면 폼 → 구조(정리 전 원값). 정리는 {@see HomeLayoutSettings::normalize()} 가 한다.
     *
     * @param  array<string, mixed>  $flat
     * @return array<string, mixed>
     */
    public static function toStructure(array $flat): array
    {
        $sections = [];
        for ($i = 1; $i <= HomeLayoutSettings::SECTION_COUNT; $i++) {
            $s = 's'.$i;
            $columns = (int) ($flat[$s.'_columns'] ?? 1) === 2 ? 2 : 1;
            $cols = [];
            for ($c = 1; $c <= $columns; $c++) {
                $k = $s.'c'.$c;
                $type = (string) ($flat[$k.'_type'] ?? 'recent');
                $board = $flat[$k.'_board'] ?? null;
                $cols[] = [
                    'type' => $type,
                    'title' => $flat[$k.'_title'] ?? '',
                    'icon' => $flat[$k.'_icon'] ?? '',
                    'limit' => $flat[$k.'_limit'] ?? null,
                    'boards' => $type === 'ticker'
                        ? ['mode' => 'only', 'ids' => $board === null || $board === '' ? [] : [$board]]
                        : ['mode' => $flat[$k.'_mode'] ?? 'all', 'ids' => $flat[$k.'_ids'] ?? []],
                    'period' => $flat[$k.'_period'] ?? 'week',
                    'html' => $flat[$k.'_html'] ?? '',
                ];
            }
            $sections[] = ['enabled' => $flat[$s.'_enabled'] ?? false, 'columns' => $columns, 'cols' => $cols];
        }

        return ['_meta' => ['version' => HomeLayoutSettings::VERSION], 'sections' => $sections];
    }

    /**
     * 저장 검증 규칙(코어 `core.plugin_settings.update_validation_rules` 필터에 합친다).
     *
     * @param  array<int, string>  $typeIds  고를 수 있는 종류 id
     * @param  array<string, mixed>  $input  요청 입력(티커 판정용)
     * @param  array<int, string>  $icons  제목 아이콘 허용 목록(비면 이름 형식만)
     * @return array<string, array<int, mixed>>
     */
    public static function rules(array $typeIds, array $input, array $icons = []): array
    {
        $p = self::INPUT_KEY;
        $form = is_array($input[$p] ?? null) ? $input[$p] : [];
        $rules = [$p => ['sometimes', 'array']];
        for ($i = 1; $i <= HomeLayoutSettings::SECTION_COUNT; $i++) {
            $s = "{$p}.s{$i}";
            $rules[$s.'_enabled'] = ['required_with:'.$p, 'boolean'];
            $rules[$s.'_columns'] = ['required_with:'.$p, 'in:1,2'];
            for ($c = 1; $c <= 2; $c++) {
                $key = "s{$i}c{$c}";
                $k = "{$p}.{$key}";
                $isTicker = ($form[$key.'_type'] ?? null) === 'ticker';
                $rules[$k.'_type'] = ['required_with:'.$p, 'string', 'in:'.implode(',', $typeIds)];
                $rules[$k.'_limit'] = ['required_with:'.$p, 'integer', 'min:1', 'max:'.($isTicker ? 10 : 20)];
                $rules[$k.'_mode'] = ['nullable', 'in:'.implode(',', HomeLayoutSettings::MODES)];
                $rules[$k.'_ids'] = ['nullable', 'array', 'max:'.HomeLayoutSettings::MAX_BOARD_IDS];
                $rules[$k.'_ids.*'] = ['integer', 'min:1'];
                // 티커 게시판은 한 개(정수 하나). 목록이 오면 거부한다. 티커가 아닌 칸의 _ids 는 티커에서 쓰지 않는다.
                $rules[$k.'_board'] = ['nullable', self::tickerSingleBoard($isTicker), 'integer', 'min:1'];
                $rules[$k.'_period'] = ['nullable', 'in:week,month,year'];
                $rules[$k.'_title'] = ['nullable', 'string', 'max:'.HomeLayoutSettings::TITLE_MAX];
                $rules[$k.'_icon'] = $icons === []
                    ? ['nullable', 'string', 'max:40']
                    : ['nullable', 'string', 'in:'.implode(',', $icons)];
                $rules[$k.'_html'] = ['nullable', 'string', 'max:'.HomeHtml::MAX_LENGTH, self::sanitizerPresent()];
            }
        }

        return $rules;
    }

    /**
     * HTML 을 저장하려면 코어 정제기가 있어야 한다(없으면 빈 값 저장이 아니라 거부).
     */
    private static function sanitizerPresent(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && $value !== '' && ! HomeHtml::available()) {
                $fail(__('g7-home-widgets::messages.home.validation.html_unavailable'));
            }
        };
    }

    /**
     * 티커 칸의 게시판은 1개여야 한다(목록으로 여럿이 오면 거부).
     */
    private static function tickerSingleBoard(bool $isTicker): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($isTicker): void {
            if ($isTicker && is_array($value) && count($value) > 1) {
                $fail(__('g7-home-widgets::messages.home.validation.ticker_single_board'));
            }
        };
    }
}
