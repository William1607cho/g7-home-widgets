<?php

namespace Plugins\G7\Home\Widgets\Home;

/**
 * 제목 아이콘 허용 목록(0.4.0) — `resources/home/icons.json`(wc-community Font Awesome 서브셋 141종 사본).
 *
 * 같은 파일을 overlay·관리자 화면 생성기(scripts/build-home.sh)도 읽는다. 서브셋 밖 아이콘은 wc-community
 * 에서 빈칸이 되므로 저장 검증과 읽기 정리에서 막는다.
 */
final class HomeIcons
{
    /**
     * @return array<int, string>
     */
    public static function load(): array
    {
        $path = dirname(__DIR__, 2).'/resources/home/icons.json';
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $icons = is_array($data['icons'] ?? null) ? $data['icons'] : [];

        return array_values(array_filter($icons, fn ($name) => is_string($name) && preg_match('/^[a-z0-9-]{1,40}$/', $name) === 1));
    }
}
