<?php

namespace Plugins\G7\Home\Widgets\Http\Controllers;

use App\Http\Controllers\Api\Base\PublicBaseController;
use Illuminate\Http\Response;

/**
 * 위젯 게시판 설정 화면의 카드 드래그 스크립트 서빙 컨트롤러 (공개, 0.3.0).
 *
 * GET /api/plugins/g7-home-widgets/board-filter-drag.js
 *
 * **왜 스크립트가 필요한가**: 레이아웃 JSON 액션 핸들러는 `change` 를 뺀 모든 이벤트에서
 * `preventDefault()` 를 호출한다(코어 ActionDispatcher). HTML 규격상 `dragstart` 에서
 * 기본 동작을 막으면 끌기 자체가 취소되므로, JSON 액션만으로는 카드를 끌 수 없다.
 *
 * **왜 이렇게 작은가**: 이동 로직(상태 갱신)은 레이아웃 JSON 의 X·+ 버튼에 이미 있다.
 * 이 스크립트는 끌기를 시작시키고, 카드가 다른 영역에 놓이면 **그 카드의 X·+ 버튼을
 * 대신 눌러 줄 뿐**이다 — 앱 상태·네트워크·저장소에 직접 손대지 않는다. 문서 전체에
 * 이벤트를 위임하므로 화면이 다시 그려져도 계속 동작한다.
 *
 * 스크립트 본문은 고정 문자열이다(주입되는 값 없음). URL 의 `?v=` 는 레이아웃이 붙인다.
 */
class BoardFilterDragScriptController extends PublicBaseController
{
    /**
     * 드래그 스크립트를 내려줍니다.
     */
    public function show(): Response
    {
        return response($this->buildScript(), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * 스크립트 본문을 조립합니다.
     *
     * 선택자에 넣는 값(게시판 ID·영역 이름)은 숫자와 두 영역 이름만 받아 CSS 선택자 문법을
     * 건드릴 여지를 없앤다.
     */
    private function buildScript(): string
    {
        return <<<'JS'
        /* g7-home-widgets — 위젯 게시판 설정 화면 카드 드래그 */
        (function () {
            'use strict';

            if (window.__g7HomeWidgetsBoardDrag) {
                return;
            }
            window.__g7HomeWidgetsBoardDrag = true;

            var CARD = '[data-board-card]';
            var ZONE = '[data-zone-drop]';
            var MOVE = '[data-board-action]';
            var dragging = null;

            function closest(el, selector) {
                return el && el.closest ? el.closest(selector) : null;
            }

            document.addEventListener('dragstart', function (event) {
                var card = closest(event.target, CARD);
                var zone = closest(card, ZONE);
                var id = card ? card.getAttribute('data-board-card') : '';
                var from = zone ? zone.getAttribute('data-zone-drop') : '';
                if (!/^[0-9]+$/.test(id) || (from !== 'included' && from !== 'excluded')) {
                    return;
                }
                dragging = { id: id, from: from };
                if (event.dataTransfer) {
                    event.dataTransfer.setData('text/plain', id);
                    event.dataTransfer.effectAllowed = 'move';
                }
            });

            document.addEventListener('dragover', function (event) {
                if (dragging && closest(event.target, ZONE)) {
                    event.preventDefault();
                }
            });

            document.addEventListener('drop', function (event) {
                var zone = closest(event.target, ZONE);
                if (!dragging || !zone) {
                    return;
                }
                event.preventDefault();
                var info = dragging;
                dragging = null;
                if (zone.getAttribute('data-zone-drop') === info.from) {
                    return;
                }
                var source = document.querySelector('[data-zone-drop="' + info.from + '"]');
                var card = source ? source.querySelector('[data-board-card="' + info.id + '"]') : null;
                var button = card ? card.querySelector(MOVE) : null;
                if (button) {
                    button.click();
                }
            });

            document.addEventListener('dragend', function () {
                dragging = null;
            });
        })();
        JS;
    }
}
