<?php

namespace Plugins\G7\Home\Widgets;

use App\Enums\ExtensionOwnerType;
use App\Extension\AbstractPlugin;
use App\Extension\Helpers\ExtensionMenuSyncHelper;
use Plugins\G7\Home\Widgets\Support\BoardFilterSettings;

/**
 * 홈 화면 위젯 모음 (g7-home-widgets) — "전체 최근글"·"인기글"·"공지 티커" 위젯, 0.3.0.
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
 * 0.2.0: 홈 하단 "공지 티커"용 `/api/plugins/g7-home-widgets/notice-posts?board={slug}&limit=` 추가 —
 * 지정 게시판 1곳의 최신글을 최근글과 같은 필터(+비밀글 제외)로 조회하고 제목·슬러그·ID·작성일만
 * 돌려준다. 게시판 없음/권한 없음은 빈 배열({@see Support\NoticePostsQuery}).
 *
 * 0.3.0: 최근글·인기글에서 뺄 게시판을 관리자가 고르는 설정 화면 추가
 * (`/admin/plugins/g7-home-widgets/settings`). 게시판 ID 목록 하나(`excluded_board_ids`)를
 * 플러그인 설정에 저장하고, 자체 API(`/api/plugins/g7-home-widgets/admin/board-filter`)로
 * 읽고 쓴다({@see Support\BoardFilterSettings}). 표시 여부만 정하며 열람 권한과는 무관하다.
 *
 * 훅 리스너·DB 테이블 없음 — 라우트+쿼리 애드온에 설정 1개와 관리자 화면 1개.
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

    /**
     * 플러그인 설정 스키마 (0.3.0 신설).
     *
     * `excluded_board_ids` 는 설정 화면이 드래그·버튼으로 채우는 게시판 ID 목록이다.
     * 저장은 자체 API 가 정리·검증한 뒤 수행하고, 읽을 때도 한 번 더 정리한다.
     *
     * @return array<string, array<string, mixed>> 설정 스키마
     */
    public function getSettingsSchema(): array
    {
        return [
            BoardFilterSettings::KEY => [
                'type' => 'array',
                'default' => [],
                'label' => [
                    'ko' => '위젯에서 제외할 게시판',
                    'en' => 'Boards Excluded From Widgets',
                ],
                'hint' => [
                    'ko' => '최근글·인기글 위젯에 표시하지 않을 게시판의 ID 목록입니다. 표시 여부만 정하며, 열람 권한과는 무관합니다.',
                    'en' => 'IDs of boards hidden from the recent and popular posts widgets. This only controls visibility and does not change read permissions.',
                ],
                'required' => false,
            ],
        ];
    }

    /**
     * 플러그인 설정 기본값 (0.3.0 신설) — 제외 게시판 없음.
     *
     * @return array<string, array<int, int>> 기본 설정값
     */
    public function getConfigValues(): array
    {
        return [
            BoardFilterSettings::KEY => [],
        ];
    }

    /**
     * 관리자 메뉴 정의 (0.3.0 신설).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdminMenus(): array
    {
        return [
            [
                'name' => ['ko' => '홈 위젯 게시판 설정', 'en' => 'Home Widget Boards'],
                'slug' => 'g7-home-widgets-settings',
                'url' => '/admin/plugins/g7-home-widgets/settings',
                'icon' => 'fas fa-house',
                'order' => 64,
            ],
        ];
    }

    /**
     * 플러그인 활성화 — 관리자 메뉴 등록 (0.3.0 신설).
     *
     * 설치 시에는 PluginManager 가 선언형 동기화로 등록하지만, 비활성화 후 재활성화할
     * 때는 그 경로를 타지 않으므로 여기서도 동기화한다(멱등).
     *
     * @return bool 활성화 성공 여부
     */
    public function activate(): bool
    {
        $helper = app(ExtensionMenuSyncHelper::class);

        foreach ($this->getAdminMenus() as $menuData) {
            $helper->syncMenuRecursive(
                $menuData,
                ExtensionOwnerType::Plugin,
                $this->getIdentifier(),
            );
        }

        return true;
    }

    /**
     * 플러그인 비활성화 — 관리자 메뉴 제거 (0.3.0 신설).
     *
     * @return bool 비활성화 성공 여부
     */
    public function deactivate(): bool
    {
        app(ExtensionMenuSyncHelper::class)->cleanupStaleMenus(
            ExtensionOwnerType::Plugin,
            $this->getIdentifier(),
            currentSlugs: [],
        );

        return true;
    }
}
