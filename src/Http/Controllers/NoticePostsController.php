<?php

namespace Plugins\G7\Home\Widgets\Http\Controllers;

use App\Http\Controllers\Api\Base\PublicBaseController;
use Illuminate\Http\JsonResponse;
use Plugins\G7\Home\Widgets\Http\Requests\NoticePostsRequest;
use Plugins\G7\Home\Widgets\Support\NoticePostsQuery;

/**
 * "공지 티커" 위젯 API 컨트롤러.
 *
 * sirsoft-board 코어를 거치지 않고 {@see NoticePostsQuery} 가 직접 조회한다.
 */
class NoticePostsController extends PublicBaseController
{
    public function __construct(private readonly NoticePostsQuery $query)
    {
        parent::__construct();
    }

    /**
     * 지정 게시판의 최신 공지(제목·슬러그·ID·작성일)를 반환한다.
     */
    public function index(NoticePostsRequest $request): JsonResponse
    {
        return $this->success('common.success', $this->query->forCurrentUser($request->boardSlug(), $request->limit()));
    }
}
