<?php

namespace Plugins\G7\Home\Widgets\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Http\JsonResponse;
use Plugins\G7\Home\Widgets\Home\HomeSettingsAdmin;

/**
 * 홈 섹션 설정 화면의 평면 폼 조회(0.4.0) — `GET /api/plugins/g7-home-widgets/admin/home-form`.
 *
 * 저장은 코어 설정 저장 경로(`PUT /api/admin/plugins/g7-home-widgets/settings`)를 쓴다
 * ({@see \Plugins\G7\Home\Widgets\Listeners\HomeSettingsFormListener}).
 */
class HomeFormAdminController extends AdminBaseController
{
    public function __construct(private readonly HomeSettingsAdmin $admin)
    {
        parent::__construct();
    }

    public function show(): JsonResponse
    {
        return $this->success('common.success', $this->admin->form());
    }
}
