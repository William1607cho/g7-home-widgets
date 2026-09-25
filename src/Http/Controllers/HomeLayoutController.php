<?php

namespace Plugins\G7\Home\Widgets\Http\Controllers;

use App\Http\Controllers\Api\Base\PublicBaseController;
use Illuminate\Http\JsonResponse;
use Plugins\G7\Home\Widgets\Home\HomeLayoutService;

/**
 * 홈 섹션 데이터 API 컨트롤러(0.4.0) — `GET /api/plugins/g7-home-widgets/home`.
 *
 * 섹션·칸 구성과 칸별 목록을 한 번에 준다. 봇 화면은 같은 서비스를 컨텍스트 필터로 받는다
 * ({@see \Plugins\G7\Home\Widgets\Listeners\SeoHomeContextListener}).
 */
class HomeLayoutController extends PublicBaseController
{
    public function __construct(private readonly HomeLayoutService $service)
    {
        parent::__construct();
    }

    /**
     * 현재 호출자(비회원이면 guest 권한) 기준 홈 섹션 데이터.
     */
    public function index(): JsonResponse
    {
        return $this->success('common.success', $this->service->build());
    }
}
