<?php

namespace Plugins\G7\Home\Widgets\Http\Controllers;

use App\Http\Controllers\Api\Base\PublicBaseController;
use Illuminate\Http\JsonResponse;
use Plugins\G7\Home\Widgets\Http\Requests\PopularPostsRequest;
use Plugins\G7\Home\Widgets\Support\PopularPostsQuery;

/**
 * "인기글" 위젯 API 컨트롤러.
 *
 * sirsoft-board 코어를 거치지 않고 {@see PopularPostsQuery} 가 직접 조회한다.
 */
class PopularPostsController extends PublicBaseController
{
    public function __construct(private readonly PopularPostsQuery $query)
    {
        parent::__construct();
    }

    /**
     * 게시판 무관 기간별 인기글(카테고리 포함, 작성자 미포함)을 반환한다.
     */
    public function index(PopularPostsRequest $request): JsonResponse
    {
        return $this->success('common.success', $this->query->forCurrentUser($request->period(), $request->limit()));
    }
}
