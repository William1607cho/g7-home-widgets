/*
 * g7-home-widgets 홈 섹션 스크립트(0.4.0) — 뉴스 티커 움직임.
 *
 * - 핸들러 `g7-home-widgets.ticker`({key}) / `g7-home-widgets.tickerStop`({key}) 를 등록한다.
 *   overlay 티커 노드의 lifecycle onMount·onUnmount 가 부른다.
 * - 핸들러가 불리지 못한 경우(스크립트가 늦게 뜬 경우 등)를 위해 DOM 을 지켜보다가 아직 돌지
 *   않는 티커를 시작한다. 같은 칸을 두 번 시작하지 않는다(칸 key 별 타이머 1개).
 * - 움직임: 4초마다 한 줄 위로(transform). 마우스를 올리거나 포커스가 들어오면 멈춘다.
 *   `prefers-reduced-motion: reduce` 이거나 1건이면 움직이지 않는다. 마지막 줄 다음에는
 *   애니메이션 없이 첫 줄로 돌아간다.
 * - 네트워크 요청·저장소 접근 없음. React 가 관리하지 않는 `style`·`data-index` 만 바꾼다.
 * scripts/build-home.sh 가 이 파일을 dist/js/plugin.iife.js 로 복사한다(원본은 이 파일).
 */
(function () {
    'use strict';

    var ID = 'g7-home-widgets';
    var ROW_REM = 1.5;
    var INTERVAL_MS = 4000;
    var timers = {};

    function reducedMotion() {
        return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function findTrack(key) {
        var col = document.querySelector('[data-testid="g7hw-' + key + '"]');
        return col ? col.querySelector('.g7hw-ticker-track') : null;
    }

    function keyOf(track) {
        var col = track.closest('.g7hw-col');
        var tid = col ? col.getAttribute('data-testid') || '' : '';
        return tid.indexOf('g7hw-') === 0 ? tid.slice(5) : '';
    }

    function setIndex(track, idx, animate) {
        track.style.transition = animate ? '' : 'none';
        track.style.transform = 'translateY(-' + (idx * ROW_REM) + 'rem)';
        track.setAttribute('data-index', String(idx));
    }

    function stop(key) {
        var t = timers[key];
        if (!t) {
            return;
        }
        window.clearInterval(t.interval);
        if (t.box) {
            t.box.removeEventListener('mouseenter', t.pause);
            t.box.removeEventListener('mouseleave', t.resume);
            t.box.removeEventListener('focusin', t.pause);
            t.box.removeEventListener('focusout', t.resume);
        }
        delete timers[key];
    }

    function start(key) {
        if (!key) {
            return;
        }
        var track = findTrack(key);
        if (!track) {
            return;
        }
        if (timers[key] && timers[key].track === track) {
            return;
        }
        stop(key);
        setIndex(track, 0, false);
        var rows = track.children.length;
        if (rows <= 1 || reducedMotion()) {
            timers[key] = { track: track, interval: 0, box: null };
            return;
        }
        var state = { idx: 0, paused: false };
        var t = {
            track: track,
            box: track.closest('.g7hw-ticker'),
            pause: function () { state.paused = true; },
            resume: function () { state.paused = false; }
        };
        t.interval = window.setInterval(function () {
            if (!document.contains(t.track)) {
                stop(key);
                return;
            }
            if (state.paused) {
                return;
            }
            var n = t.track.children.length;
            state.idx = n > 0 ? (state.idx + 1) % n : 0;
            setIndex(t.track, state.idx, state.idx !== 0);
        }, INTERVAL_MS);
        if (t.box) {
            t.box.addEventListener('mouseenter', t.pause);
            t.box.addEventListener('mouseleave', t.resume);
            t.box.addEventListener('focusin', t.pause);
            t.box.addEventListener('focusout', t.resume);
        }
        timers[key] = t;
    }

    function scan() {
        var tracks = document.querySelectorAll('.g7hw-w-ticker .g7hw-ticker-track');
        for (var i = 0; i < tracks.length; i++) {
            start(keyOf(tracks[i]));
        }
        for (var k in timers) {
            if (Object.prototype.hasOwnProperty.call(timers, k) && !document.contains(timers[k].track)) {
                stop(k);
            }
        }
    }

    function paramKey(action) {
        var key = action && action.params ? action.params.key : '';
        return typeof key === 'string' && /^s[1-5]c[12]$/.test(key) ? key : '';
    }

    function register(dispatcher) {
        dispatcher.registerHandler(ID + '.ticker', function (action) {
            var key = paramKey(action);
            window.setTimeout(function () { key ? start(key) : scan(); }, 0);
        }, { category: 'plugin', source: ID });
        dispatcher.registerHandler(ID + '.tickerStop', function (action) {
            var key = paramKey(action);
            if (key) {
                stop(key);
            }
        }, { category: 'plugin', source: ID });
    }

    function registerHandlers() {
        var get = function () {
            return window.G7Core && window.G7Core.getActionDispatcher && window.G7Core.getActionDispatcher();
        };
        var d = get();
        if (d) {
            register(d);
            return;
        }
        var tries = 0;
        var timer = window.setInterval(function () {
            var found = get();
            if (found) {
                register(found);
                window.clearInterval(timer);
            } else if (++tries >= 50) {
                window.clearInterval(timer);
            }
        }, 100);
    }

    function watch() {
        var pending = 0;
        var observer = new MutationObserver(function () {
            if (pending) {
                return;
            }
            pending = window.setTimeout(function () {
                pending = 0;
                scan();
            }, 200);
        });
        observer.observe(document.body, { childList: true, subtree: true });
        scan();
    }

    /*
     * 관리자 설정 화면 칩 끌기(관리자 화면 보완 묶음). 칸마다 영역이 따로라 영역 값에 칸 키를 붙인다
     * (`data-hw-zone="s1c1:included|excluded"`, 카드 `data-hw-card`, 옮기기 버튼 `data-hw-move`).
     * 레이아웃 JSON 액션은 dragstart 를 막으므로(0.3.0 과 같은 사정) 끌기를 여기서 시작하고, 다른 영역에
     * 놓으면 그 카드의 X·+ 버튼을 대신 누른다 — 클릭과 같은 상태 변경 경로. 요청·저장소 접근 없음.
     */
    function initAdminDrag() {
        if (window.__g7HomeWidgetsAdminDrag) {
            return;
        }
        window.__g7HomeWidgetsAdminDrag = true;
        var dragging = null;
        var zoneOf = function (el) {
            return el && el.closest ? el.closest('[data-hw-zone]') : null;
        };
        document.addEventListener('dragstart', function (event) {
            var card = event.target && event.target.closest ? event.target.closest('[data-hw-card]') : null;
            var zone = zoneOf(card);
            var id = card ? card.getAttribute('data-hw-card') : '';
            var from = zone ? zone.getAttribute('data-hw-zone') : '';
            if (!/^[0-9]+$/.test(id) || !/^s[1-5]c[12]:(included|excluded)$/.test(from)) {
                return;
            }
            dragging = { id: id, from: from };
            if (event.dataTransfer) {
                event.dataTransfer.setData('text/plain', id);
                event.dataTransfer.effectAllowed = 'move';
            }
        });
        document.addEventListener('dragover', function (event) {
            var zone = zoneOf(event.target);
            if (dragging && zone && zone.getAttribute('data-hw-zone').split(':')[0] === dragging.from.split(':')[0]) {
                event.preventDefault();
            }
        });
        document.addEventListener('drop', function (event) {
            var zone = zoneOf(event.target);
            var info = dragging;
            dragging = null;
            if (!info || !zone) {
                return;
            }
            var to = zone.getAttribute('data-hw-zone');
            if (to === info.from || to.split(':')[0] !== info.from.split(':')[0]) {
                return;
            }
            event.preventDefault();
            var source = document.querySelector('[data-hw-zone="' + info.from + '"]');
            var card = source ? source.querySelector('[data-hw-card="' + info.id + '"]') : null;
            var button = card ? card.querySelector('[data-hw-move]') : null;
            if (button) {
                button.click();
            }
        });
        document.addEventListener('dragend', function () {
            dragging = null;
        });
    }

    function init() {
        registerHandlers();
        watch();
        initAdminDrag();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
