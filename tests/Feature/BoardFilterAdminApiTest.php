<?php

namespace Plugins\G7\Home\Widgets\Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Modules\Sirsoft\Board\Models\Board;
use Plugins\G7\Home\Widgets\Http\Controllers\Admin\BoardFilterAdminController;
use Plugins\G7\Home\Widgets\Http\Requests\UpdateBoardFilterRequest;
use Plugins\G7\Home\Widgets\Support\BoardFilterSettings;
use Plugins\G7\Home\Widgets\Tests\PluginTestCase;

/**
 * 게시판 제외 설정 관리자 API (0.3.0)
 *
 * - 권한: 비회원 401, 권한 없는 회원 403, 조회 권한만 있으면 저장 403 (라우트가 등록된 설치 환경에서만)
 * - 검증: 배열·정수·양수·최대 500개, 빈 배열 허용
 * - 저장: 중복 제거, 없는 게시판 ID 제거, 응답은 조회와 같은 형식
 */
class BoardFilterAdminApiTest extends PluginTestCase
{
    private const URL = '/api/plugins/g7-home-widgets/admin/board-filter';

    private function routeRegistered(): bool
    {
        return app('router')->getRoutes()->match(\Illuminate\Http\Request::create(self::URL, 'GET')) !== null;
    }

    private function skipUnlessRouted(): void
    {
        try {
            $this->routeRegistered();
        } catch (\Throwable) {
            $this->markTestSkipped('관리자 라우트는 플러그인이 활성화된 환경에서만 등록된다.');
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function passes(array $body): bool
    {
        return Validator::make($body, (new UpdateBoardFilterRequest)->rules())->passes();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function put(array $body): array
    {
        $request = UpdateBoardFilterRequest::create(self::URL, 'PUT', $body);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();

        return $this->dataOf(app(BoardFilterAdminController::class)->update($request));
    }

    // ── 권한 ────────────────────────────────────────────────

    public function test_guest_gets_401(): void
    {
        $this->skipUnlessRouted();

        $this->getJson(self::URL)->assertStatus(401);
        $this->putJson(self::URL, ['excluded_board_ids' => []])->assertStatus(401);
    }

    public function test_member_without_plugin_permission_gets_403(): void
    {
        $this->skipUnlessRouted();
        $user = $this->createUserWithRole('user');

        $this->actingAs($user, 'sanctum')->getJson(self::URL)->assertStatus(403);
        $this->actingAs($user, 'sanctum')->putJson(self::URL, ['excluded_board_ids' => []])->assertStatus(403);
    }

    public function test_read_only_admin_cannot_save(): void
    {
        $this->skipUnlessRouted();
        $role = $this->role('hw_test_plugins_read');
        $role->permissions()->syncWithoutDetaching(
            \App\Models\Permission::where('identifier', 'core.plugins.read')->pluck('id')->all()
        );
        $user = $this->createUserWithRole('hw_test_plugins_read');

        $this->actingAs($user, 'sanctum')->getJson(self::URL)->assertStatus(200);
        $this->actingAs($user, 'sanctum')->putJson(self::URL, ['excluded_board_ids' => []])->assertStatus(403);
    }

    // ── 검증 ────────────────────────────────────────────────

    public function test_validation_rules(): void
    {
        $this->assertTrue($this->passes(['excluded_board_ids' => []]));
        $this->assertTrue($this->passes(['excluded_board_ids' => [1, 2, 2]]));
        $this->assertFalse($this->passes([]));
        $this->assertFalse($this->passes(['excluded_board_ids' => 'abc']));
        $this->assertFalse($this->passes(['excluded_board_ids' => ['a']]));
        $this->assertFalse($this->passes(['excluded_board_ids' => [-1]]));
        $this->assertFalse($this->passes(['excluded_board_ids' => [0]]));
        $this->assertFalse($this->passes(['excluded_board_ids' => range(1, BoardFilterSettings::MAX_IDS + 1)]));
        $this->assertTrue($this->passes(['excluded_board_ids' => range(1, BoardFilterSettings::MAX_IDS)]));
    }

    // ── 저장 ────────────────────────────────────────────────

    public function test_save_removes_duplicates_and_unknown_ids(): void
    {
        $a = $this->createBoard();
        $b = $this->createBoard(active: false);
        $unknown = (int) Board::max('id') + 1000;

        $data = $this->put(['excluded_board_ids' => [$b->id, $a->id, $a->id, $unknown]]);

        $expected = [min($a->id, $b->id), max($a->id, $b->id)];
        $this->assertSame($expected, $data['excluded_board_ids']);
        $this->assertSame($expected, BoardFilterSettings::excludedIds());
    }

    public function test_unknown_id_only_saves_empty_list(): void
    {
        $unknown = (int) Board::max('id') + 1000;

        $data = $this->put(['excluded_board_ids' => [$unknown]]);

        $this->assertSame([], $data['excluded_board_ids']);
        $this->assertSame([], BoardFilterSettings::excludedIds());
    }

    public function test_show_lists_all_boards_with_fields_and_intersects_excluded(): void
    {
        $active = $this->createBoard();
        $inactive = $this->createBoard(active: false);
        $this->setExcluded([$inactive->id, 999999999]);

        $data = $this->dataOf(app(BoardFilterAdminController::class)->show());

        $byId = collect($data['boards'])->keyBy('id');
        $this->assertSame(Board::count(), count($data['boards']));
        $this->assertTrue($byId[$active->id]['is_active']);
        $this->assertFalse($byId[$inactive->id]['is_active']);
        $this->assertSame($inactive->slug, $byId[$inactive->id]['slug']);
        $this->assertArrayHasKey('name', $byId[$active->id]);
        $this->assertSame([$inactive->id], $data['excluded_board_ids']);
    }

    public function test_settings_normalize_ignores_garbage(): void
    {
        $this->assertSame([2, 5], BoardFilterSettings::normalize([5, '2', 'x', -3, 0, 5, null, 1.5]));
        $this->assertSame([], BoardFilterSettings::normalize('5'));
        $this->assertSame('none', BoardFilterSettings::fingerprint([]));
        $this->assertNotSame(BoardFilterSettings::fingerprint([1]), BoardFilterSettings::fingerprint([2]));
    }
}
