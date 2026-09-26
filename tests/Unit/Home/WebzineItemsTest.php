<?php

namespace Plugins\G7\Home\Widgets\Tests\Unit\Home;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Home\Widgets\Home\WebzineItems;

/**
 * 웹진 위젯 항목에 애드온 카드 값 입히기(0.4.0 웹진 묶음) — 비밀글이 새지 않는지를 먼저 본다.
 */
class WebzineItemsTest extends TestCase
{
    public function test_secret_item_never_gets_summary_or_thumbnail_even_if_addon_returns_them(): void
    {
        $items = [['id' => 1, 'is_secret' => true, 'thumbnail' => '/a.jpg', 'title' => 't']];
        $cards = [1 => ['summary' => '새면 안 되는 요약', 'thumbnail' => '/a.jpg', 'fallback_image' => null]];

        $out = WebzineItems::apply($items, $cards);

        $this->assertNull($out[0]['summary']);
        $this->assertNull($out[0]['thumbnail']);
        $this->assertFalse($out[0]['has_thumbnail']);
        $this->assertSame('t', $out[0]['title']);
    }

    public function test_contract_input_drops_secret_thumbnail(): void
    {
        $in = WebzineItems::contractInput([['id' => '3', 'is_secret' => true, 'thumbnail' => '/a.jpg']]);

        $this->assertSame([['id' => 3, 'is_secret' => true, 'status' => 'published', 'deleted_at' => null, 'thumbnail' => null]], $in);
    }

    public function test_normal_item_takes_addon_summary_and_thumbnail(): void
    {
        $out = WebzineItems::apply([['id' => 2, 'is_secret' => false, 'thumbnail' => '/b.jpg']], [2 => ['summary' => '요약', 'thumbnail' => '/b.jpg']]);

        $this->assertSame('요약', $out[0]['summary']);
        $this->assertSame('/b.jpg', $out[0]['thumbnail']);
        $this->assertTrue($out[0]['has_thumbnail']);
    }

    public function test_item_without_thumbnail_uses_empty_frame_not_fallback_image(): void
    {
        $out = WebzineItems::apply([['id' => 4, 'is_secret' => false, 'thumbnail' => null]], [4 => ['summary' => '', 'thumbnail' => null, 'fallback_image' => '/fb.webp']]);

        $this->assertNull($out[0]['thumbnail']);
        $this->assertFalse($out[0]['has_thumbnail']);
        $this->assertNull($out[0]['summary']);
    }

    public function test_missing_card_keeps_own_thumbnail_and_no_summary(): void
    {
        $out = WebzineItems::apply([['id' => 5, 'is_secret' => false, 'thumbnail' => '/c.jpg']], []);

        $this->assertSame('/c.jpg', $out[0]['thumbnail']);
        $this->assertNull($out[0]['summary']);
    }
}
