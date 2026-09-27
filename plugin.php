<?php

namespace Plugins\G7\Home\Widgets;

use App\Enums\ExtensionOwnerType;
use App\Extension\AbstractPlugin;
use App\Extension\Helpers\ExtensionMenuSyncHelper;
use Plugins\G7\Home\Widgets\Home\HomeLayoutSettings;
use Plugins\G7\Home\Widgets\Listeners\HomeCacheGenerationListener;
use Plugins\G7\Home\Widgets\Listeners\HomeSettingsFormListener;
use Plugins\G7\Home\Widgets\Listeners\SeoHomeContextListener;

/**
 * 홈 화면 위젯 모음 (g7-home-widgets).
 *
 * 홈 섹션(5개, 섹션마다 켜기/끄기·1단/2단)을 overlay(`resources/extensions/home.json`,
 * `main_content` 앞쪽 주입)로 그리고, 칸마다 최근글·인기글·뉴스 티커·갤러리·웹진·HTML 위젯을 고른다.
 * 칸 데이터는 `GET /api/plugins/g7-home-widgets/home` 한 번, 봇 화면은 `core.seo.filter_context`
 * 필터로 같은 서비스 결과를 받는다. 설정 키 `home_layout` 이 없으면 기본값으로 동작한다.
 * sirsoft-board 코어는 수정하지 않으며, 글 목록·열람 권한·비밀글 판정은 코어 게시판 서비스에 맡긴다.
 *
 * 0.5.0: 0.4.x 의 옛 위젯 API 3종, 게시판 제외 API, 공통 제외 설정을 뺐다(목록은 CHANGELOG). 설정 파일에
 * 남은 옛 값은 지우지 않고 읽지도 않는다. 게시판 선택은 칸마다 저장하며, 저장되지 않은 칸은 모든 게시판을 포함한다.
 *
 * DB 테이블 없음.
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
            'keywords' => ['home', 'widget', 'sirsoft-board'],
        ];
    }

    /**
     * 플러그인 설정 스키마.
     *
     * 0.5.0: 옛 공통 제외 키를 뺐다. 코어 저장 경로는 스키마 밖 키를 요청에서 버리고 기존 파일과
     * 병합하므로, 파일에 남은 값은 그대로 남는다(읽지 않는다).
     *
     * @return array<string, array<string, mixed>> 설정 스키마
     */
    public function getSettingsSchema(): array
    {
        return [
            // 0.4.0 — 홈 섹션 구성. 객체지만 코어 스키마 타입에 객체가 없어 array 로 선언한다.
            // 저장 파일에 없으면 HomeLayoutSettings 기본값을 쓴다(기본값에 공지 게시판 id 조회가
            // 필요해 getConfigValues() 에는 넣지 않는다).
            HomeLayoutSettings::KEY => [
                'type' => 'array',
                'label' => [
                    'ko' => '홈 섹션 구성',
                    'en' => 'Home Sections',
                ],
                'hint' => [
                    'ko' => '홈 화면 섹션 5개의 켜기/끄기, 단 수, 칸별 위젯 설정입니다.',
                    'en' => 'On/off, column count and per-column widget settings for the five home sections.',
                ],
                'required' => false,
            ],
        ];
    }

    /**
     * 훅 리스너 (0.4.0) — 봇 홈 컨텍스트 주입, 위젯 데이터 캐시 세대 올리기.
     *
     * @return array<int, class-string>
     */
    public function getHookListeners(): array
    {
        return [
            SeoHomeContextListener::class,
            HomeCacheGenerationListener::class,
            HomeSettingsFormListener::class,
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
                'name' => ['ko' => '홈 화면 설정', 'en' => 'Home Page Settings'],
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
