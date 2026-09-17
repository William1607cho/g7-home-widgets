<?php

namespace Plugins\G7\Home\Widgets\Tests;

use App\Extension\PluginManager;
use App\Http\Middleware\PermissionMiddleware;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Sirsoft\Board\Models\Board;
use Plugins\G7\Home\Widgets\Support\BoardFilterSettings;
use Tests\TestCase;

/**
 * g7-home-widgets 테스트 베이스 클래스 (0.3.0)
 *
 * - 데이터 정리는 DatabaseTransactions 롤백으로만 한다. 기존 행을 DELETE/TRUNCATE 하지 않는다.
 * - 제외 설정은 플러그인 설정 파일에 저장되므로 롤백되지 않는다 — 각 테스트 앞뒤로 원래 값을
 *   되돌린다.
 * - 이 플러그인은 번들(`plugins/_bundled`)이 아니므로 코어 phpunit 스위트에 자동 포함되지 않는다.
 *   실행할 때는 테스트 전용 DB·스토리지에서 경로를 직접 지정한다.
 */
abstract class PluginTestCase extends TestCase
{
    use DatabaseTransactions;

    protected static bool $migrated = false;

    /** 테스트 시작 시점의 제외 목록 (복원용) */
    private array $originalExcluded = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerPluginAutoload();
        $this->runMigrationsIfNeeded();

        if (! app(PluginManager::class)->getPlugin(BoardFilterSettings::IDENTIFIER)) {
            $this->markTestSkipped('g7-home-widgets 가 설치된 테스트 환경에서만 실행한다.');
        }

        $this->originalExcluded = BoardFilterSettings::excludedIds();
        $this->setExcluded([]);

        PermissionMiddleware::clearGuestRoleCache();
    }

    protected function tearDown(): void
    {
        if (app()->bound(PluginManager::class) && app(PluginManager::class)->getPlugin(BoardFilterSettings::IDENTIFIER)) {
            $this->setExcluded($this->originalExcluded);
        }

        parent::tearDown();
    }

    protected function getPluginBasePath(): string
    {
        return dirname(__DIR__);
    }

    /**
     * composer.json 의 PSR-4 매핑(`src/`, `./`)과 같은 규칙으로 오토로드를 등록한다.
     */
    protected function registerPluginAutoload(): void
    {
        $base = $this->getPluginBasePath();

        spl_autoload_register(function ($class) use ($base) {
            $prefix = 'Plugins\\G7\\Home\\Widgets\\';
            if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
                return;
            }

            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            foreach ([$base.'/src/'.$relative.'.php', $base.'/'.lcfirst($relative).'.php', $base.'/'.$relative.'.php'] as $file) {
                if (file_exists($file) && ! class_exists($class, false)) {
                    require_once $file;

                    return;
                }
            }
        });
    }

    protected function runMigrationsIfNeeded(): void
    {
        if (static::$migrated) {
            return;
        }

        if (! Schema::hasTable('users') || ! Schema::hasTable('board_posts')) {
            $paths = ['database/migrations'];
            foreach (glob(base_path('modules/*/database/migrations'), GLOB_ONLYDIR) as $p) {
                $paths[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $p);
            }
            $this->artisan('migrate', ['--path' => $paths]);
        }

        static::$migrated = true;
    }

    /**
     * 제외 목록을 저장하고 위젯 풀 캐시를 비운다.
     *
     * @param  array<int, int>  $ids
     */
    protected function setExcluded(array $ids): void
    {
        $before = BoardFilterSettings::fingerprint(BoardFilterSettings::excludedIds());
        BoardFilterSettings::save($ids);
        BoardFilterSettings::forgetPoolsFor($before);
        BoardFilterSettings::forgetPoolsFor(BoardFilterSettings::fingerprint(BoardFilterSettings::normalize($ids)));
    }

    /**
     * 테스트 게시판 — slug 는 기존 데이터와 겹치지 않게 무작위 접미사를 붙이고, 비회원 읽기 권한을 준다.
     */
    protected function createBoard(bool $active = true, bool $guestReadable = true): Board
    {
        $slug = 'hw-test-'.substr(md5(uniqid('', true)), 0, 8);

        $board = Board::create([
            'slug' => $slug,
            'name' => ['ko' => '홈 위젯 테스트', 'en' => 'Home widgets test'],
            'type' => 'basic',
            'is_active' => $active,
            'secret_mode' => 'enabled',
            'blocked_keywords' => [],
        ]);

        $permission = Permission::firstOrCreate(
            ['identifier' => "sirsoft-board.{$slug}.posts.read"],
            ['name' => ['ko' => 'posts.read', 'en' => 'posts.read'], 'type' => 'user']
        );

        if ($guestReadable) {
            $this->role('guest')->permissions()->syncWithoutDetaching([$permission->id]);
            $this->role('user')->permissions()->syncWithoutDetaching([$permission->id]);
        }

        PermissionMiddleware::clearGuestRoleCache();

        return $board;
    }

    /**
     * 게시글 — 기존 글보다 확실히 최근·인기 순위가 높도록 작성 시각과 조회수를 준다.
     */
    protected function createPost(Board $board, array $attributes = []): int
    {
        static $seq = 0;
        $seq++;

        return DB::table('board_posts')->insertGetId(array_merge([
            'board_id' => $board->id,
            'title' => 'test',
            'content' => 'test',
            'user_id' => null,
            'author_name' => 'test',
            'password' => null,
            'ip_address' => '127.0.0.1',
            'is_notice' => false,
            'is_secret' => false,
            'status' => 'published',
            'trigger_type' => 'admin',
            'view_count' => 1000000 + $seq,
            'created_at' => now()->addMinutes(10)->addSeconds($seq),
            'updated_at' => now(),
        ], $attributes));
    }

    protected function role(string $identifier): Role
    {
        return Role::firstOrCreate(
            ['identifier' => $identifier],
            ['name' => ['ko' => $identifier, 'en' => $identifier]]
        );
    }

    protected function createUserWithRole(?string $roleIdentifier = null): User
    {
        $user = User::factory()->create();
        if ($roleIdentifier !== null) {
            $user->roles()->syncWithoutDetaching([$this->role($roleIdentifier)->id]);
        }

        return $user->fresh();
    }

    /**
     * 현재 요청 사용자 설정 (null = 비회원).
     */
    protected function actAs(?User $user): void
    {
        if ($user !== null) {
            Auth::setUser($user);
        } else {
            Auth::forgetUser();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function dataOf(JsonResponse $response): array
    {
        return $response->getData(true)['data'] ?? [];
    }
}
