<?php

namespace Plugins\G7\Home\Widgets\Home;

use Illuminate\Support\Facades\Auth;
use Modules\Sirsoft\Board\Http\Resources\PostResource;
use Modules\Sirsoft\Board\Services\BoardService;
use Modules\Sirsoft\Board\Services\PostService;

/**
 * 갤러리 위젯용 게시판 글 목록(0.4.0) — 코어 게시판 목록 경로를 그대로 따른다.
 *
 * 코어 사용자 목록 화면(`User\PostController::index`)과 같은 순서로 부른다:
 * `PostService::buildListParams()` → `PostService::getPosts(…, 'user', $board)` → board 관계 주입 →
 * `PostResource`. 썸네일은 `PostResource` 의 `thumbnail`(첨부 이미지 우선, 없으면 본문 첫 내부 이미지,
 * 볼 권한 없는 비밀글은 null)을 그대로 쓴다. 이미지 변환본(image-delivery)은 쓰지 않는다.
 *
 * 추가로 하는 것:
 * - 답글(parent_id 있음)과 발행 상태가 아닌 글은 뺀다(홈 최근글과 같은 기준).
 * - **비밀글 썸네일은 호출자와 무관하게 null** 로 둔다. 목록을 캐시로 공유하려면 사용자에 따라 달라지는
 *   값이 없어야 하기 때문이다(코어 판정보다 좁다 — 더 가린다).
 * - 캐시는 비로그인 요청에만 쓴다(목록 권한 범위가 사용자마다 다를 수 있다). 로그인 요청은 매번 조회한다.
 */
final class BoardGallerySource
{
    /** 게시판별 풀 크기(갤러리 개수 최대 20) */
    public const POOL = 20;

    public function __construct(
        private readonly BoardService $boards,
        private readonly PostService $posts,
        private readonly WidgetCache $cache,
    ) {}

    /**
     * @param  array{id: int, slug: string, name: string}  $board  열람 가능 판정이 끝난 게시판
     * @return array<int, array<string, mixed>>
     */
    public function ofBoard(array $board): array
    {
        $load = fn () => $this->load($board['slug']);

        return Auth::check() ? $load() : $this->cache->remember('gallery:'.$board['id'].':'.self::POOL, $load);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function load(string $slug): array
    {
        $board = $this->boards->getBoardBySlug($slug, checkScope: false);
        if (! $board || ! $board->is_active) {
            return [];
        }
        $params = $this->posts->buildListParams([], ['context' => 'user', 'board' => $board]);
        $page = $this->posts->getPosts($slug, $params['filters'], self::POOL, false, 'user', $board);

        $rows = [];
        foreach ($page as $post) {
            $post->setRelation('board', $board);
            $r = (new PostResource($post))->resolve(request());
            if (! empty($r['parent_id']) || ($r['status'] ?? null) !== 'published') {
                continue;
            }
            $secret = (bool) ($r['is_secret'] ?? false);
            $rows[] = [
                'id' => (int) $r['id'],
                'title' => (string) ($r['title'] ?? ''),
                'is_secret' => $secret,
                'thumbnail' => $secret ? null : ($r['thumbnail'] ?? null),
                'created_at' => $r['created_at'] ?? '',
                'created_at_formatted' => $r['created_at_formatted'] ?? '',
            ];
        }

        return $rows;
    }
}
