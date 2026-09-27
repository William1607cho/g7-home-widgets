<?php

namespace Plugins\G7\Home\Widgets\Tests\Unit\Home;

use PHPUnit\Framework\TestCase;

/**
 * 방문자 전역 설정 노출 범위 (0.5.0) — `config/settings/defaults.json` 의 `frontend_schema`.
 *
 * 코어는 `frontend_schema` 가 비어 있으면 민감 필드만 빼고 설정 전체를 방문자 전역 설정에 싣는다. 그래서 이
 * 플러그인은 목록을 비우지 않고 모든 항목을 `expose: false` 로 둔다 — 코어 필터 결과가 빈 배열이면 이
 * 플러그인은 전역 설정에서 통째로 빠진다. 코어 트레이트가 있으면 그 필터로 직접 확인한다.
 */
class FrontendSchemaTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function defaultsFile(): array
    {
        $data = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/config/settings/defaults.json'), true);
        $this->assertTrue(is_array($data), 'defaults.json 이 JSON 객체가 아니다');

        return $data;
    }

    public function test_frontend_schema_is_not_empty_and_exposes_nothing(): void
    {
        $schema = $this->defaultsFile()['frontend_schema'] ?? [];

        $this->assertTrue(is_array($schema) && $schema !== [], 'frontend_schema 가 비면 코어가 설정 전체를 노출한다');
        $this->assertArrayHasKey('home_layout', $schema);
        foreach ($schema as $key => $entry) {
            $this->assertFalse((bool) ($entry['expose'] ?? false), $key);
        }
    }

    public function test_the_legacy_key_is_gone_from_defaults(): void
    {
        $data = $this->defaultsFile();

        $this->assertArrayNotHasKey('excluded_board_ids', $data['defaults'] ?? []);
        $this->assertArrayNotHasKey('excluded_board_ids', $data['frontend_schema'] ?? []);
    }

    public function test_core_filter_returns_nothing_for_this_plugin(): void
    {
        if (! trait_exists(\App\Traits\FiltersFrontendSchema::class)) {
            $this->markTestSkipped('코어 FiltersFrontendSchema 트레이트가 없는 환경');
        }

        $filter = new class
        {
            use \App\Traits\FiltersFrontendSchema;

            /**
             * @param  array<string, mixed>  $settings
             * @param  array<string, mixed>  $schema
             * @return array<string, mixed>
             */
            public function run(array $settings, array $schema): array
            {
                return $this->filterByFrontendSchema($settings, $schema);
            }
        };

        $settings = ['home_layout' => ['sections' => []], 'excluded_board_ids' => [33, 34]];
        $out = $filter->run($settings, $this->defaultsFile()['frontend_schema']);

        $this->assertSame([], $out);
    }
}
