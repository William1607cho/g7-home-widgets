<?php

namespace Plugins\G7\Home\Widgets\Tests\Unit\Home;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Home\Widgets\Listeners\HomeCacheGenerationListener;

/**
 * 홈 캐시 무효화 훅 구독(릴리스 전 정리) — 웹진 애드온 활성·비활성 뒤 봇 홈이 옛 화면으로 남지 않게.
 */
class HomeCacheHooksTest extends TestCase
{
    public function test_addon_toggle_hooks_are_subscribed(): void
    {
        $hooks = HomeCacheGenerationListener::getSubscribedHooks();

        foreach (['core.plugins.activated', 'core.plugins.after_deactivate'] as $hook) {
            $this->assertArrayHasKey($hook, $hooks);
            $this->assertSame('onPluginToggled', $hooks[$hook]['method']);
        }
    }

    public function test_settings_and_board_hooks_are_still_subscribed(): void
    {
        $hooks = HomeCacheGenerationListener::getSubscribedHooks();

        $this->assertSame('onSettingsSaved', $hooks['core.plugin_settings.after_save']['method']);
        foreach (HomeCacheGenerationListener::BOARD_HOOKS as $hook) {
            $this->assertSame('onBoardDataChanged', $hooks[$hook]['method']);
        }
    }

    public function test_other_plugins_are_ignored_without_touching_the_container(): void
    {
        // 식별자가 다르면 캐시·컨테이너에 손대기 전에 돌아간다(예외 없음).
        (new HomeCacheGenerationListener)->onPluginToggled('some-other-plugin');
        (new HomeCacheGenerationListener)->onPluginToggled(null);

        $this->assertTrue(true);
    }
}
