<?php

namespace Plugins\G7\Home\Widgets\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Plugins\G7\Home\Widgets\Home\HomeIcons;
use Plugins\G7\Home\Widgets\Home\HomeLayoutForm;
use Plugins\G7\Home\Widgets\Home\HomeSettingsAdmin;
use Plugins\G7\Home\Widgets\Home\WidgetRegistry;
use Plugins\G7\Home\Widgets\Support\BoardFilterSettings;

/**
 * 코어 설정 저장 경로에 홈 섹션 폼을 끼운다(0.4.0).
 *
 * - `core.plugin_settings.update_validation_rules` (rules, identifier, input): 평면 폼 검증 규칙 추가
 * - `core.plugin_settings.filter_save_data` (settings, identifier): 평면 폼 → `home_layout` 변환·정리
 * - 저장 후(캐시 세대·봇 홈 캐시)는 {@see HomeCacheGenerationListener} 가 맡는다.
 *
 * 이 플러그인 식별자가 아닐 때는 값을 그대로 돌려준다.
 */
class HomeSettingsFormListener implements HookListenerInterface
{
    public static function getSubscribedHooks(): array
    {
        return [
            'core.plugin_settings.update_validation_rules' => ['method' => 'rules', 'type' => 'filter', 'priority' => 20],
            'core.plugin_settings.filter_save_data' => ['method' => 'prepare', 'type' => 'filter', 'priority' => 20],
        ];
    }

    public function handle(...$args): void {}

    /**
     * @param  array<string, mixed>  $rules
     * @param  mixed  $identifier
     * @param  mixed  $input
     * @return array<string, mixed>
     */
    public function rules(array $rules, mixed $identifier = null, mixed $input = []): array
    {
        if ($identifier !== BoardFilterSettings::IDENTIFIER) {
            return $rules;
        }

        return $rules + HomeLayoutForm::rules(app(WidgetRegistry::class)->ids(), is_array($input) ? $input : [], HomeIcons::load());
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  mixed  $identifier
     * @return array<string, mixed>
     */
    public function prepare(array $settings, mixed $identifier = null): array
    {
        if ($identifier !== BoardFilterSettings::IDENTIFIER) {
            return $settings;
        }

        return app(HomeSettingsAdmin::class)->prepareForSave($settings);
    }
}
