<?php

namespace Plugins\G7\Home\Widgets\Home;

use App\Contracts\Repositories\PluginRepositoryInterface;
use Throwable;

/**
 * g7-image-delivery 연결부(0.4.2) — 플러그인 유무 판정과 공개 계약 호출만 한다.
 *
 * 내부 모델·표는 읽지 않는다. 공개 계약 `Plugins\G7\Image\Delivery\PublicApi\ThumbnailVariants`
 * (`VERSION >= 1`, `lookup(urls)`, image-delivery 0.3.0 이상)만 쓴다. {@see WebzineAddon} 과 같은 방식이다.
 *
 * 유무 판정 = 코어 플러그인 활성 여부 **그리고** 계약 클래스 존재·판본. 없으면(미설치·비활성·계약 없는 옛 판)
 * 빈 결과를 돌려주고, 호출자는 0.4.1 과 같이 원본 주소를 쓴다. 판정은 요청(스코프) 단위로 한 번만 한다.
 */
final class ImageDelivery
{
    public const IDENTIFIER = 'g7-image-delivery';

    /** 공개 계약 클래스(문자열로 둔다 — 플러그인이 없어도 이 파일은 읽혀야 한다) */
    public const CONTRACT = 'Plugins\\G7\\Image\\Delivery\\PublicApi\\ThumbnailVariants';

    private ?bool $available = null;

    public function __construct(private readonly PluginRepositoryInterface $plugins) {}

    public function available(): bool
    {
        return $this->available ??= $this->detect();
    }

    /**
     * 주소 => 변환본 조회 결과(계약 출력 그대로). 플러그인이 없거나 실패하면 빈 배열.
     *
     * @param  array<int, string>  $urls
     * @return array<string, mixed>
     */
    public function lookup(array $urls): array
    {
        if ($urls === [] || ! $this->available()) {
            return [];
        }
        try {
            $found = (self::CONTRACT)::lookup($urls);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        return is_array($found) ? $found : [];
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
