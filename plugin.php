<?php

namespace Plugins\G7\Home\Widgets;

use App\Extension\AbstractPlugin;

/**
 * 홈 화면 위젯 모음 (g7-home-widgets) — "전체 최근글" 위젯, 0.1.0.
 *
 * 홈 화면에 게시판 구분 없이 사이트 전체 게시글을 시간순으로 통합해 보여주는 위젯을
 * 제공한다. sirsoft-board 코어는 **파일 한 줄도 수정하지 않는다** — 이 애드온이
 * `Modules\Sirsoft\Board\Models\Post`/`Board` 코어 Eloquent 모델을 직접 쿼리하는
 * 자체 API 엔드포인트(`/api/plugins/g7-home-widgets/recent-posts`)로만 동작한다.
 *
 * 배경: sirsoft-board 코어에 이미 게시판 무관 통합 최근글 조회
 * (`BoardService::getCachedRecentPosts()` → `GET /boards/posts/recent`)가 있었지만,
 * 이 애드온은 그 경로를 거치지 않는다 — `category` 필드가 코어 쪽엔 없고, 코어를
 * 건드리지 않으면서 이 위젯 전용 필드를 추가하려면 애드온이 자체 쿼리를 갖는 편이
 * 코어 무변경 원칙에 더 맞기 때문이다({@see RecentPostsQuery}).
 *
 * 2026-09-14: "인기글" 위젯용 `/api/plugins/g7-home-widgets/popular-posts` 추가 — 코어
 * `boards/popular` 와 같은 정렬·필터에 `category` 를 더하고 작성자 정보는 빼는 자체 쿼리
 * ({@see Support\PopularPostsQuery}).
 *
 * 훅 리스너·DB 테이블·설정 스키마 없음 — 순수 라우트+쿼리 애드온.
 */
class Plugin extends AbstractPlugin
{
    /**
     * 플러그인 메타데이터.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return [
            'author' => 'William Cho',
            'license' => 'MIT',
            'keywords' => ['home', 'widget', 'recent-posts', 'sirsoft-board'],
        ];
    }
}
