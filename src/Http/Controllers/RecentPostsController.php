<?php

namespace Plugins\G7\Home\Widgets\Http\Controllers;

use App\Http\Controllers\Api\Base\PublicBaseController;
use Illuminate\Http\JsonResponse;
use Plugins\G7\Home\Widgets\Http\Requests\RecentPostsRequest;
use Plugins\G7\Home\Widgets\Support\RecentPostsQuery;

/**
 * "전체 최근글" 위젯 API 컨트롤러.
 *
 * sirsoft-board 코어를 거치지 않고 {@see RecentPostsQuery} 가 직접 조회한다.
 */
class RecentPostsController extends PublicBaseController
{
    public function __construct(private readonly RecentPostsQuery $query)
    {
        parent::__construct();
    }

    /**
     * 게시판 무관 전체 최근글을 반환한다.
     */
    public function index(RecentPostsRequest $request): JsonResponse
    {
        return $this->success('common.success', $this->query->forCurrentUser($request->limit()));
    }
}
