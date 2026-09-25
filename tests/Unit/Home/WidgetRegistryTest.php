<?php

namespace Plugins\G7\Home\Widgets\Tests\Unit\Home;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Plugins\G7\Home\Widgets\Home\HomeWidget;
use Plugins\G7\Home\Widgets\Home\WidgetRegistry;

/**
 * 위젯 등록부 (0.4.0) — 순서 유지, 중복 거부, 사용 불가 시 대체 종류.
 */
class WidgetRegistryTest extends TestCase
{
    public function test_ids_keep_registration_order(): void
    {
        $r = new WidgetRegistry([new FakeHomeWidget('b'), new FakeHomeWidget('a')]);

        $this->assertSame(['b', 'a'], $r->ids());
        $this->assertTrue($r->has('a'));
        $this->assertFalse($r->has('c'));
    }

    public function test_duplicate_id_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WidgetRegistry([new FakeHomeWidget('a'), new FakeHomeWidget('a')]);
    }

    public function test_unavailable_widget_resolves_to_its_fallback(): void
    {
        $r = new WidgetRegistry([
            new FakeHomeWidget('recent'),
            new FakeHomeWidget('webzine', available: false, fallback: 'recent'),
            new FakeHomeWidget('orphan', available: false),
        ]);

        $this->assertSame('recent', $r->resolveAvailable('webzine')?->id());
        $this->assertSame('recent', $r->resolveAvailable('recent')?->id());
        $this->assertNull($r->resolveAvailable('orphan'));
        $this->assertNull($r->resolveAvailable('missing'));
    }
}

/**
 * 시험용 위젯(데이터 없음).
 */
final class FakeHomeWidget implements HomeWidget
{
    public function __construct(
        private readonly string $id,
        private readonly bool $available = true,
        private readonly ?string $fallback = null,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function defaults(): array
    {
        return ['limit' => 1, 'boards' => ['mode' => 'all', 'ids' => []]];
    }

    public function titleKey(): string
    {
        return 'messages.home.titles.'.$this->id;
    }

    public function defaultIcon(): string
    {
        return 'clock';
    }

    public function limitRange(): array
    {
        return [1, 1];
    }

    public function normalize(array $col, array $raw): array
    {
        return $col;
    }

    public function rules(string $prefix): array
    {
        return [];
    }

    public function available(): bool
    {
        return $this->available;
    }

    public function fallback(): ?string
    {
        return $this->fallback;
    }

    public function data(array $col, array $boards): array
    {
        return [];
    }
}
