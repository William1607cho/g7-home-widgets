<?php

namespace Plugins\G7\Home\Widgets\Home;

use InvalidArgumentException;

/**
 * 홈 위젯 종류 등록부(0.4.0).
 *
 * 종류 목록의 원본은 {@see self::__construct()} 에 넘기는 배열 한 곳이다(서비스 프로바이더가
 * 싱글톤으로 묶는다). 등록 순서가 곧 overlay 생성기가 칸 노드에 조각을 끼우는 순서와 같아야
 * 한다 — 생성기 대조기가 두 목록을 비교한다.
 */
final class WidgetRegistry
{
    /** @var array<string, HomeWidget> */
    private array $widgets = [];

    /**
     * @param  iterable<HomeWidget>  $widgets  등록할 위젯(순서 유지)
     */
    public function __construct(iterable $widgets)
    {
        foreach ($widgets as $widget) {
            $id = $widget->id();
            if (isset($this->widgets[$id])) {
                throw new InvalidArgumentException("duplicate home widget id: {$id}");
            }
            $this->widgets[$id] = $widget;
        }
    }

    /**
     * 등록된 종류 id 목록(등록 순서).
     *
     * @return array<int, string>
     */
    public function ids(): array
    {
        return array_keys($this->widgets);
    }

    public function has(string $id): bool
    {
        return isset($this->widgets[$id]);
    }

    public function get(string $id): HomeWidget
    {
        if (! isset($this->widgets[$id])) {
            throw new InvalidArgumentException("unknown home widget id: {$id}");
        }

        return $this->widgets[$id];
    }

    /**
     * 실제로 그릴 종류를 정한다. 쓸 수 없으면 대체 종류를 따라가고(한 번만), 대체도 없거나
     * 쓸 수 없으면 null.
     */
    public function resolveAvailable(string $id): ?HomeWidget
    {
        if (! $this->has($id)) {
            return null;
        }
        $widget = $this->get($id);
        if ($widget->available()) {
            return $widget;
        }
        $fallback = $widget->fallback();
        if ($fallback === null || ! $this->has($fallback)) {
            return null;
        }
        $alt = $this->get($fallback);

        return $alt->available() ? $alt : null;
    }
}
