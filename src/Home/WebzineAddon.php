<?php

namespace Plugins\G7\Home\Widgets\Home;

use App\Contracts\Repositories\PluginRepositoryInterface;
use Throwable;

/**
 * g7-webzine-addon 연결부(0.4.0 웹진 묶음) — 애드온 유무 판정과 공개 계약 호출만 한다.
 *
 * 애드온 내부 클래스는 부르지 않는다. 공개 계약 `Plugins\G7\Webzine\Addon\PublicApi\WebzineCards`
 * (`VERSION >= 1`, `cards(items)`)만 쓴다(설계서 6-3, 애드온 README "Public contract").
 *
 * 유무 판정 = 코어 플러그인 활성 여부(`findActiveByIdentifier`, 코어가 확장 활성 판정에 쓰는 방식)
 * **그리고** 계약 클래스 존재·판본. 비활성 애드온도 오토로드가 남아 클래스가 보일 수 있어 둘 다 본다.
 * 판정 결과는 이 객체 안에만 기억한다 — 요청(스코프) 단위라 한 요청 안에서는 한 번만 조회하고,
 * 애드온 활성·비활성 직후의 다음 요청에는 바로 반영된다.
 */
final class WebzineAddon
{
    public const IDENTIFIER = 'g7-webzine-addon';

    /** 공개 계약 클래스(문자열로 둔다 — 애드온이 없어도 이 파일은 읽혀야 한다) */
    public const CONTRACT = 'Plugins\\G7\\Webzine\\Addon\\PublicApi\\WebzineCards';

    /** 애드온 저장소(관리자 화면 안내 링크) */
    public const REPOSITORY_URL = 'https://github.com/William1607cho/g7-webzine-addon';

    private ?bool $available = null;

    public function __construct(private readonly PluginRepositoryInterface $plugins) {}

    public function available(): bool
    {
        return $this->available ??= $this->detect();
    }

    /**
     * 공개 계약으로 카드 값(id => summary·thumbnail·fallback_image)을 받는다. 애드온이 없거나 실패하면 빈 배열.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function cards(array $items): array
    {
        if ($items === [] || ! $this->available()) {
            return [];
        }
        try {
            $cards = (self::CONTRACT)::cards($items);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return is_array($cards) ? $cards : [];
    }

    private function detect(): bool
    {
        try {
            if ($this->plugins->findActiveByIdentifier(self::IDENTIFIER) === null) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        return class_exists(self::CONTRACT) && defined(self::CONTRACT.'::VERSION') && constant(self::CONTRACT.'::VERSION') >= 1;
    }
}
