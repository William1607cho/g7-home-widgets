<?php

use Illuminate\Support\Facades\Route;
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
