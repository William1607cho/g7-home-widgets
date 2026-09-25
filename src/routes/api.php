<?php

use Illuminate\Support\Facades\Route;
use Plugins\G7\Home\Widgets\Http\Controllers\Admin\BoardFilterAdminController;
use Plugins\G7\Home\Widgets\Http\Controllers\Admin\HomeFormAdminController;
use Plugins\G7\Home\Widgets\Http\Controllers\BoardFilterDragScriptController;
use Plugins\G7\Home\Widgets\Http\Controllers\HomeLayoutController;
use Plugins\G7\Home\Widgets\Http\Controllers\NoticePostsController;
use Plugins\G7\Home\Widgets\Http\Controllers\PopularPostsController;
use Plugins\G7\Home\Widgets\Http\Controllers\RecentPostsController;

/*
 * g7-home-widgets 플러그인 API 라우트
 *
 * URL prefix: /api/plugins/g7-home-widgets  (PluginRouteServiceProvider 자동 적용)
 */

// 전체 최근글(게시판 무관 통합) — 공개 접근, 로그인 여부에 따라 권한 필터링 결과가
// 달라지므로 optional.sanctum 으로 현재 사용자를 알 수 있게 한다.
Route::get('recent-posts', [RecentPostsController::class, 'index'])
    ->middleware(['optional.sanctum', 'throttle:600,1'])
    ->name('recent-posts.index');

// 기간별 인기글(게시판 무관 통합, 카테고리 포함) — 코어 boards/popular 와 같은 정렬·필터,
// 로그인 여부에 따라 권한 필터링 결과가 달라지므로 optional.sanctum.
Route::get('popular-posts', [PopularPostsController::class, 'index'])
    ->middleware(['optional.sanctum', 'throttle:600,1'])
    ->name('popular-posts.index');

// 공지 티커(지정 게시판 1곳 최신글, 제목·슬러그·ID·작성일만) — 게시판 없음/권한 없음은 빈 배열,
// 로그인 여부에 따라 권한 필터링 결과가 달라지므로 optional.sanctum.
Route::get('notice-posts', [NoticePostsController::class, 'index'])
    ->middleware(['optional.sanctum', 'throttle:600,1'])
    ->name('notice-posts.index');

// 홈 섹션(0.4.0) — 섹션·칸 구성과 칸별 목록을 한 번에 준다. overlay 데이터소스 g7hw_home 이 부르고,
// 봇 화면은 같은 서비스를 core.seo.filter_context 필터로 받는다. 로그인 여부에 따라 열람 가능
// 게시판이 달라지므로 optional.sanctum.
Route::get('home', [HomeLayoutController::class, 'index'])
    ->middleware(['optional.sanctum', 'throttle:600,1'])
    ->name('home.index');

// 위젯 게시판 설정 화면의 카드 드래그 스크립트 (공개 — 설정 레이아웃의 scripts 가 로드, 0.3.0)
Route::get('board-filter-drag.js', [BoardFilterDragScriptController::class, 'show'])
    ->name('board-filter-drag-script');

// 위젯 게시판 제외 설정(관리자, 0.3.0) — 최근글·인기글에서 뺄 게시판 ID 목록.
// 조회는 코어 플러그인 조회 권한, 저장은 코어 플러그인 수정 권한(g7-webzine-addon 과 동일 표기).
Route::prefix('admin')->name('admin.')->middleware('auth:sanctum')->group(function () {
    Route::get('board-filter', [BoardFilterAdminController::class, 'show'])
        ->middleware('permission:admin,core.plugins.read')
        ->name('board-filter.show');

    Route::put('board-filter', [BoardFilterAdminController::class, 'update'])
        ->middleware('permission:admin,core.plugins.update')
        ->name('board-filter.update');

    // 홈 섹션 설정 화면의 평면 폼(0.4.0). 저장은 코어 PUT /api/admin/plugins/{id}/settings.
    Route::get('home-form', [HomeFormAdminController::class, 'show'])
        ->middleware('permission:admin,core.plugins.read')
        ->name('home-form.show');
});
