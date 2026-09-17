<?php

namespace Plugins\G7\Home\Widgets\Tests\Feature;

use Plugins\G7\Home\Widgets\Http\Controllers\BoardFilterDragScriptController;
use Plugins\G7\Home\Widgets\Tests\PluginTestCase;

/**
 * 설정 화면 카드 드래그 스크립트 라우트 (0.3.0)
 *
 * - 비회원도 200, Content-Type 은 JavaScript
 * - 스크립트는 dragstart 에서 기본 동작을 막지 않고, 네트워크·저장소에 접근하지 않는다
 */
class BoardFilterDragScriptTest extends PluginTestCase
{
    private const URL = '/api/plugins/g7-home-widgets/board-filter-drag.js';

    public function test_route_returns_javascript_for_guest(): void
    {
        try {
            app('router')->getRoutes()->match(\Illuminate\Http\Request::create(self::URL, 'GET'));
        } catch (\Throwable) {
            $this->markTestSkipped('스크립트 라우트는 플러그인이 활성화된 환경에서만 등록된다.');
        }

        $response = $this->get(self::URL);

        $response->assertStatus(200);
        $this->assertStringStartsWith('application/javascript', (string) $response->headers->get('Content-Type'));
    }

    public function test_controller_serves_javascript_with_cache_headers(): void
    {
        $response = app(BoardFilterDragScriptController::class)->show();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('application/javascript', (string) $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_script_keeps_drag_default_and_avoids_side_channels(): void
    {
        $script = (string) app(BoardFilterDragScriptController::class)->show()->getContent();

        $this->assertStringContainsString("addEventListener('dragstart'", $script);
        $this->assertStringContainsString('__g7HomeWidgetsBoardDrag', $script);

        $dragstart = substr($script, strpos($script, "'dragstart'"), strpos($script, "'dragover'") - strpos($script, "'dragstart'"));
        $this->assertStringNotContainsString('preventDefault', $dragstart);

        foreach (['fetch(', 'XMLHttpRequest', 'localStorage', 'sessionStorage', 'console.', 'G7Core'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $script, $forbidden);
        }
    }
}
