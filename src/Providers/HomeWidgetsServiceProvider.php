<?php

namespace Plugins\G7\Home\Widgets\Providers;

use App\Extension\BasePluginServiceProvider;

/**
 * 홈 화면 위젯 모음 서비스 프로바이더.
 *
 * 컨테이너 바인딩이 필요한 서비스가 없어 식별자만 지정한다. 코어 `PluginServiceProvider`
 * 가 `src/Providers/*ServiceProvider.php` 를 자동 발견해 등록하며, 라우트
 * (`src/routes/api.php`)는 PluginManager 가 규약에 따라 자동으로 집어간다.
 */
class HomeWidgetsServiceProvider extends BasePluginServiceProvider
{
    protected string $pluginIdentifier = 'g7-home-widgets';
}
