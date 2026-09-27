<?php

namespace Plugins\G7\Home\Widgets\Providers;

use App\Extension\BasePluginServiceProvider;
use Plugins\G7\Home\Widgets\Home\WidgetCache;
use Plugins\G7\Home\Widgets\Home\WidgetRegistry;
use Plugins\G7\Home\Widgets\Home\HomeIcons;
use Plugins\G7\Home\Widgets\Home\HomeLayoutSettings;
use Plugins\G7\Home\Widgets\Home\Widgets\GalleryWidget;
use Plugins\G7\Home\Widgets\Home\Widgets\HtmlWidget;
use Plugins\G7\Home\Widgets\Home\Widgets\PopularWidget;
use Plugins\G7\Home\Widgets\Home\Widgets\RecentWidget;
use Plugins\G7\Home\Widgets\Home\Widgets\TickerWidget;
use Plugins\G7\Home\Widgets\Home\Widgets\WebzineWidget;
use Plugins\G7\Home\Widgets\Home\ImageDelivery;
use Plugins\G7\Home\Widgets\Home\WebzineAddon;

/**
 * 홈 화면 위젯 모음 서비스 프로바이더.
 *
 * 코어 `PluginServiceProvider` 가 `src/Providers/*ServiceProvider.php` 를 자동 발견해 등록하며,
 * 라우트(`src/routes/api.php`)는 PluginManager 가 규약에 따라 자동으로 집어간다.
 *
 * 0.4.0: 홈 섹션 위젯 등록부와 세대 키 캐시를 싱글톤으로 묶는다. 위젯 종류 목록의 원본은
 * {@see self::WIDGETS} 한 곳이고, 순서는 `resources/home/registry.json`(overlay 생성기 입력)과
 * 같아야 한다(생성기 대조기가 확인한다).
 */
class HomeWidgetsServiceProvider extends BasePluginServiceProvider
{
    protected string $pluginIdentifier = 'g7-home-widgets';

    /** 홈 위젯 종류(등록 순서) */
    public const WIDGETS = [
        RecentWidget::class,
        PopularWidget::class,
        TickerWidget::class,
        GalleryWidget::class,
        WebzineWidget::class,
        HtmlWidget::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(WidgetCache::class);
        // 웹진 애드온 유무 판정은 요청 단위로만 기억한다(활성·비활성 직후 다음 요청에 반영).
        $this->app->scoped(WebzineAddon::class);
        // image-delivery 유무 판정도 같은 이유로 요청 단위(0.4.2).
        $this->app->scoped(ImageDelivery::class);
        $this->app->singleton(WidgetRegistry::class, fn ($app) => new WidgetRegistry(
            array_map(fn (string $class) => $app->make($class), self::WIDGETS)
        ));
        $this->app->singleton(HomeLayoutSettings::class, fn ($app) => new HomeLayoutSettings(
            $app->make(WidgetRegistry::class),
            HomeIcons::load(),
        ));
    }
}
