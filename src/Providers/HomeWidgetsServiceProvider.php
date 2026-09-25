<?php

namespace Plugins\G7\Home\Widgets\Providers;

use App\Extension\BasePluginServiceProvider;
use Plugins\G7\Home\Widgets\Home\WidgetCache;
use Plugins\G7\Home\Widgets\Home\WidgetRegistry;
use Plugins\G7\Home\Widgets\Home\Widgets\PopularWidget;
use Plugins\G7\Home\Widgets\Home\Widgets\RecentWidget;
use Plugins\G7\Home\Widgets\Home\Widgets\TickerWidget;

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
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(WidgetCache::class);
        $this->app->singleton(WidgetRegistry::class, fn ($app) => new WidgetRegistry(
            array_map(fn (string $class) => $app->make($class), self::WIDGETS)
        ));
    }
}
