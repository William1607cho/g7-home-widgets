<?php

namespace Plugins\G7\Home\Widgets\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use Illuminate\Support\Facades\Log;
use Plugins\G7\Home\Widgets\Home\HomeLayoutService;

/**
 * 봇 화면 홈 섹션 데이터 주입(0.4.0) — `core.seo.filter_context` 필터.
 *
 * 봇 렌더는 레이아웃 `meta.seo.data_sources`(템플릿 소유)에 적힌 것만 부르므로 overlay 의
 * 데이터소스(`g7hw_home`)는 불리지 않는다. 대신 이 필터가 브라우저 API 와 같은 서비스 결과를
 * 같은 키·같은 모양(`{success, data}`)으로 넣는다 — overlay 바인딩 `g7hw_home.data.*` 가 두
 * 화면에서 똑같이 동작한다. 자기 사이트로의 HTTP 요청은 없다.
 */
class SeoHomeContextListener implements HookListenerInterface
{
    /** overlay 데이터소스 id 와 같아야 한다 */
    public const CONTEXT_KEY = 'g7hw_home';

    public static function getSubscribedHooks(): array
    {
        return [
            'core.seo.filter_context' => [
                'method' => 'fill',
                'type' => 'filter',
                'priority' => 20,
            ],
        ];
    }

    public function handle(...$args): void {}

    /**
     * @param  array<string, mixed>  $context  봇 렌더 컨텍스트
     * @param  array<string, mixed>  $meta  layoutName·moduleIdentifier·pluginIdentifier·routeParams·locale
     * @return array<string, mixed>
     */
    public function fill(array $context, array $meta = []): array
    {
        if (($meta['layoutName'] ?? null) !== 'home') {
            return $context;
        }

        try {
            $context[self::CONTEXT_KEY] = ['success' => true, 'data' => app(HomeLayoutService::class)->build()];
        } catch (\Throwable $e) {
            // 봇 렌더 전체를 깨뜨리지 않는다 — 섹션 없이 그려진다(overlay 루트 조건이 거짓).
            Log::warning('[g7-home-widgets] bot home context failed', ['error' => $e->getMessage()]);
        }

        return $context;
    }
}
