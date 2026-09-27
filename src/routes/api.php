<?php

use Illuminate\Support\Facades\Route;
use Plugins\G7\Home\Widgets\Http\Controllers\Admin\HomeFormAdminController;
use Plugins\G7\Home\Widgets\Http\Controllers\HomeLayoutController;

/*
 * g7-home-widgets 플러그인 API 라우트
 *
 * URL prefix: /api/plugins/g7-home-widgets  (PluginRouteServiceProvider 자동 적용)
 *
 * 0.5.0: 0.4.x 의 옛 위젯 API 3종과 게시판 제외 API 를 뺐다(목록은 CHANGELOG).
 */

// 홈 섹션(0.4.0) — 섹션·칸 구성과 칸별 목록을 한 번에 준다. overlay 데이터소스 g7hw_home 이 부르고,
// 봇 화면은 같은 서비스를 core.seo.filter_context 필터로 받는다. 로그인 여부에 따라 열람 가능
// 게시판이 달라지므로 optional.sanctum.
Route::get('home', [HomeLayoutController::class, 'index'])
    ->middleware(['optional.sanctum', 'throttle:600,1'])
    ->name('home.index');

// 홈 화면 설정 화면(관리자). 조회는 코어 플러그인 조회 권한.
Route::prefix('admin')->name('admin.')->middleware('auth:sanctum')->group(function () {
    // 홈 섹션 설정 화면의 평면 폼(0.4.0). 저장은 코어 PUT /api/admin/plugins/{id}/settings.
    Route::get('home-form', [HomeFormAdminController::class, 'show'])
        ->middleware('permission:admin,core.plugins.read')
        ->name('home-form.show');

    // 홈 화면 설정 화면의 메타(활성 게시판·아이콘 허용 목록·종류별 기본 아이콘). 관리자 화면 보완.
    Route::get('home-meta', [HomeFormAdminController::class, 'meta'])
        ->middleware('permission:admin,core.plugins.read')
        ->name('home-meta.show');
});
