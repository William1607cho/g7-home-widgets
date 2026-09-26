<?php

namespace Plugins\G7\Home\Widgets\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use App\Seo\Contracts\SeoCacheManagerInterface;
use Illuminate\Support\Facades\Log;
use Plugins\G7\Home\Widgets\Home\WebzineAddon;
use Plugins\G7\Home\Widgets\Home\WidgetCache;
use Plugins\G7\Home\Widgets\Support\BoardFilterSettings;

/**
 * 홈 섹션 데이터 캐시 세대 올리기(0.4.0).
 *
 * - 글 작성·수정·삭제·블라인드·복원, 게시판 수정: 세대만 올린다. 봇 홈 캐시는 게시판 모듈이
 *   같은 이벤트에서 지운다(SeoBoardCacheListener). 세대를 함께 올려야 그 직후의 봇 재렌더가
 *   옛 위젯 데이터를 읽지 않는다({@see WidgetCache}).
 * - 이 플러그인 설정 저장(`core.plugin_settings.after_save`): 세대를 올리고 봇 홈 캐시도 지운다
 *   (섹션 구성이 바뀌므로).
 * - g7-webzine-addon 활성·비활성(`core.plugins.activated`·`core.plugins.after_deactivate`): 웹진 칸이 웹진 ↔
 *   최근글 대체로 바뀌므로 세대를 올리고 봇 홈 캐시를 지운다. 코어는 활성화 때만 SEO 캐시를 비우고
 *   비활성화 때는 비우지 않아, 이것이 없으면 봇 홈이 캐시 수명(기본 2시간) 동안 웹진 화면으로 남는다
 *   (릴리스 전 정리 묶음 C-4 에서 확인).
 */
class HomeCacheGenerationListener implements HookListenerInterface
{
    /** 세대만 올리는 게시판 모듈 훅 */
    public const BOARD_HOOKS = [
        'sirsoft-board.post.after_create',
        'sirsoft-board.post.after_update',
        'sirsoft-board.post.after_delete',
        'sirsoft-board.post.after_blind',
        'sirsoft-board.post.after_restore',
        'sirsoft-board.board.after_update',
    ];

    /** 애드온 활성·비활성 훅(코어가 플러그인 식별자 문자열을 첫 인자로 넘긴다) */
    public const ADDON_TOGGLE_HOOKS = ['core.plugins.activated', 'core.plugins.after_deactivate'];

    public static function getSubscribedHooks(): array
    {
        $hooks = [];
        foreach (self::BOARD_HOOKS as $hook) {
            $hooks[$hook] = ['method' => 'onBoardDataChanged', 'priority' => 30];
        }
        $hooks['core.plugin_settings.after_save'] = ['method' => 'onSettingsSaved', 'priority' => 30];
        foreach (self::ADDON_TOGGLE_HOOKS as $hook) {
            $hooks[$hook] = ['method' => 'onPluginToggled', 'priority' => 30];
        }

        return $hooks;
    }

    public function handle(...$args): void {}

    /**
     * 게시글·게시판 변경 — 위젯 데이터 세대를 올린다.
     */
    public function onBoardDataChanged(...$args): void
    {
        $this->bump();
    }

    /**
     * 플러그인 설정 저장 후 — 이 플러그인일 때만 세대와 봇 홈 캐시를 무효화한다.
     *
     * @param  mixed  $identifier  저장한 플러그인 식별자
     */
    public function onSettingsSaved(mixed $identifier = null, mixed ...$rest): void
    {
        if ($identifier !== BoardFilterSettings::IDENTIFIER) {
            return;
        }
        $this->bump();
        $this->invalidateBotHome();
    }

    /**
     * 플러그인 활성·비활성 후 — 웹진 애드온일 때만 세대와 봇 홈 캐시를 무효화한다.
     *
     * @param  mixed  $identifier  플러그인 식별자(문자열이 아니면 무시)
     */
    public function onPluginToggled(mixed $identifier = null, mixed ...$rest): void
    {
        if ($identifier !== WebzineAddon::IDENTIFIER) {
            return;
        }
        $this->bump();
        $this->invalidateBotHome();
    }

    private function invalidateBotHome(): void
    {
        try {
            app(SeoCacheManagerInterface::class)->invalidateByLayout('home');
        } catch (\Throwable $e) {
            Log::warning('[g7-home-widgets] home seo cache invalidation failed', ['error' => $e->getMessage()]);
        }
    }

    private function bump(): void
    {
        try {
            app(WidgetCache::class)->bump();
        } catch (\Throwable $e) {
            Log::warning('[g7-home-widgets] cache generation bump failed', ['error' => $e->getMessage()]);
        }
    }
}
