<?php

namespace Plugins\G7\Home\Widgets\Home;

/**
 * 홈 섹션 설정(`home_layout`)의 기본값·정리(0.4.0). 순수 클래스 — 저장소·DB 를 모른다.
 *
 * 구조: `{_meta: {version}, sections: [5개 {enabled, columns(1|2), cols: [columns 개]}]}`.
 * 칸 공통 키는 `type`·`title`·`icon`·`limit`·`boards{mode, ids}` 이고 종류 전용 키는 위젯이
 * 정리한다({@see HomeWidget::normalize()}).
 *
 * - 저장 파일에 `home_layout` 이 없으면 기본값을 쓴다. 기본값 = 0.3.0 홈 모양
 *   (1번 섹션 2단 최근글·인기글, 2번 섹션 1단 공지 티커, 3~5번 끔).
 * - 읽을 때마다 정리한다. 코어 경로 밖에서 파일이 바뀌어도 화면이 깨지지 않게 하려는 것이다.
 *   알 수 없는 값은 버리지 않고 그 칸의 기본값으로 되돌린다.
 */
final class HomeLayoutSettings
{
    /** 설정 키 */
    public const KEY = 'home_layout';

    /** 구조 판본 */
    public const VERSION = 1;

    /** 섹션 수(고정) */
    public const SECTION_COUNT = 5;

    /** 게시판 선택 방식 */
    public const MODES = ['all', 'only'];

    /** 칸 하나가 고를 수 있는 게시판 수 상한 */
    public const MAX_BOARD_IDS = 500;

    /** 제목 최대 글자 수 */
    public const TITLE_MAX = 60;

    public function __construct(private readonly WidgetRegistry $registry) {}

    /**
     * 기본 설정(5개 섹션).
     *
     * @param  int|null  $noticeBoardId  공지 티커 기본 게시판(slug `notice`) id. 없으면 빈 선택
     * @return array<string, mixed>
     */
    public function defaults(?int $noticeBoardId): array
    {
        $ticker = $this->defaultCol('ticker');
        $ticker['boards'] = ['mode' => 'only', 'ids' => $noticeBoardId !== null ? [$noticeBoardId] : []];

        $off = ['enabled' => false, 'columns' => 1, 'cols' => [$this->defaultCol('recent')]];

        return [
            '_meta' => ['version' => self::VERSION],
            'sections' => [
                ['enabled' => true, 'columns' => 2, 'cols' => [$this->defaultCol('recent'), $this->defaultCol('popular')]],
                ['enabled' => true, 'columns' => 1, 'cols' => [$ticker]],
                $off,
                $off,
                $off,
            ],
        ];
    }

    /**
     * 저장값(또는 임의 입력)을 현재 판본 구조로 정리한다.
     *
     * @param  mixed  $raw  저장된 `home_layout` 값(없으면 null)
     * @param  int|null  $noticeBoardId  기본값 계산용 공지 게시판 id
     * @return array<string, mixed>
     */
    public function normalize(mixed $raw, ?int $noticeBoardId): array
    {
        $defaults = $this->defaults($noticeBoardId);
        if (! is_array($raw) || ! is_array($raw['sections'] ?? null)) {
            return $defaults;
        }

        $sections = [];
        for ($i = 0; $i < self::SECTION_COUNT; $i++) {
            $in = $raw['sections'][$i] ?? null;
            $sections[] = is_array($in)
                ? $this->normalizeSection($in, $defaults['sections'][$i])
                : $defaults['sections'][$i];
        }

        return ['_meta' => ['version' => self::VERSION], 'sections' => $sections];
    }

    /**
     * @param  array<string, mixed>  $in  입력 섹션
     * @param  array<string, mixed>  $default  같은 자리의 기본 섹션
     * @return array<string, mixed>
     */
    private function normalizeSection(array $in, array $default): array
    {
        $columns = self::toInt($in['columns'] ?? null);
        $columns = in_array($columns, [1, 2], true) ? $columns : $default['columns'];

        $cols = [];
        for ($c = 0; $c < $columns; $c++) {
            $rawCol = $in['cols'][$c] ?? null;
            $fallback = $default['cols'][$c] ?? $this->defaultCol('recent');
            $cols[] = is_array($rawCol) ? $this->normalizeCol($rawCol, $fallback) : $fallback;
        }

        return [
            'enabled' => self::toBool($in['enabled'] ?? null, $default['enabled']),
            'columns' => $columns,
            'cols' => $cols,
        ];
    }

    /**
     * @param  array<string, mixed>  $in  입력 칸
     * @param  array<string, mixed>  $default  같은 자리의 기본 칸
     * @return array<string, mixed>
     */
    private function normalizeCol(array $in, array $default): array
    {
        $type = is_string($in['type'] ?? null) && $this->registry->has($in['type']) ? $in['type'] : $default['type'];
        $widget = $this->registry->get($type);
        $base = $type === $default['type'] ? $default : $this->defaultCol($type);
        [$min, $max] = $widget->limitRange();

        $limit = self::toInt($in['limit'] ?? null);
        $col = [
            'type' => $type,
            'title' => self::cleanTitle($in['title'] ?? ''),
            'icon' => self::cleanIcon($in['icon'] ?? ''),
            'limit' => $limit === null ? $base['limit'] : max($min, min($max, $limit)),
            'boards' => self::normalizeBoards($in['boards'] ?? null, $base['boards']),
        ];

        return $widget->normalize($col + $base, $in);
    }

    /**
     * 종류의 기본 칸.
     *
     * @return array<string, mixed>
     */
    public function defaultCol(string $type): array
    {
        return ['type' => $type, 'title' => '', 'icon' => ''] + $this->registry->get($type)->defaults();
    }

    /**
     * @param  mixed  $in  입력 `boards`
     * @param  array{mode: string, ids: array<int, int>}  $default  기본값
     * @return array{mode: string, ids: array<int, int>}
     */
    public static function normalizeBoards(mixed $in, array $default): array
    {
        if (! is_array($in)) {
            return $default;
        }
        $mode = in_array($in['mode'] ?? null, self::MODES, true) ? $in['mode'] : $default['mode'];

        return ['mode' => $mode, 'ids' => self::normalizeIds($in['ids'] ?? [])];
    }

    /**
     * 정수·양수·중복 없음·오름차순 id 목록(상한 적용).
     *
     * @return array<int, int>
     */
    public static function normalizeIds(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $ids = [];
        foreach ($value as $item) {
            $id = self::toInt($item);
            if ($id !== null && $id >= 1) {
                $ids[$id] = $id;
            }
        }
        ksort($ids);

        return array_slice(array_values($ids), 0, self::MAX_BOARD_IDS);
    }

    private static function cleanTitle(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return mb_substr($value, 0, self::TITLE_MAX);
    }

    private static function cleanIcon(mixed $value): string
    {
        return is_string($value) && preg_match('/^[a-z0-9-]{1,40}$/', $value) === 1 ? $value : '';
    }

    private static function toInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d{1,9}$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private static function toBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === '1' || $value === 'true') {
            return true;
        }
        if ($value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        return $default;
    }
}
