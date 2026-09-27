<?php

namespace Plugins\G7\Home\Widgets\Home;

use Plugins\G7\Home\Widgets\Support\BoardFilterSettings;

/**
 * 저장된 홈 섹션 설정을 읽는 한 곳(0.4.0 릴리스 전 구조 정리).
 *
 * 홈 조립({@see HomeLayoutService})과 관리자 화면({@see HomeSettingsAdmin})이 같은 두 가지를 각자 읽고
 * 있었다 — 플러그인 설정의 `home_layout` 원값과 정리기. 읽는 곳을 여기 하나로 모았다(코어 설정 헬퍼를 그대로 부른다).
 *
 * 0.5.0: 0.4.x 의 옛 공통 제외 설정은 읽지 않는다. 자기 선택이 없는 목록 위젯은 모든 게시판을 포함한다.
 */
final class StoredHomeLayout
{
    public function __construct(private readonly HomeLayoutSettings $settings) {}

    /**
     * 저장된 `home_layout` 원값(없으면 null). 정리는 하지 않는다.
     */
    public function raw(): mixed
    {
        $all = function_exists('plugin_settings') ? plugin_settings(BoardFilterSettings::IDENTIFIER) : [];

        return is_array($all) ? ($all[HomeLayoutSettings::KEY] ?? null) : null;
    }

    /**
     * 설정 정리기.
     */
    public function settings(): HomeLayoutSettings
    {
        return $this->settings;
    }
}
