<?php

namespace Plugins\G7\Home\Widgets\Tests\Unit\Home;

use PHPUnit\Framework\TestCase;
use Plugins\G7\Home\Widgets\Home\ThumbnailVariants;

/**
 * 갤러리·웹진 썸네일 → image-delivery 변환본(0.4.2). 비밀글·대체 이미지를 건드리지 않는지와 조회 1회 입력을 본다.
 */
class ThumbnailVariantsTest extends TestCase
{
    private const A = '/api/plugins/sirsoft-ckeditor5/images/aaaaaaaaaaaa';

    private const B = '/api/plugins/sirsoft-ckeditor5/images/bbbbbbbbbbbb';

    /** @return array<string, mixed> */
    private function full(): array
    {
        return [
            'available' => true,
            'variants' => [
                960 => ['url' => '/v/a-960.webp', 'width' => 960, 'height' => 540],
                240 => ['url' => '/v/a-240.webp', 'width' => 240, 'height' => 135],
                1600 => ['url' => '/v/a-1600.webp', 'width' => 1600, 'height' => 900],
            ],
            'source' => ['width' => 2000, 'height' => 1125, 'mime' => 'image/jpeg'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function sections(): array
    {
        return [
            ['key' => 's1', 'cols' => [
                ['type' => 'recent', 'items' => [['id' => 9, 'thumbnail' => self::A]]],
                ['type' => 'gallery', 'items' => [
                    ['id' => 1, 'is_secret' => false, 'thumbnail' => self::A],
                    ['id' => 2, 'is_secret' => true, 'thumbnail' => self::B],
                    ['id' => 3, 'is_secret' => false, 'thumbnail' => null],
                ]],
            ]],
            ['key' => 's2', 'cols' => [
                ['type' => 'webzine', 'items' => [
                    ['id' => 4, 'is_secret' => false, 'thumbnail' => self::A, 'fallback_image' => null],
                    ['id' => 5, 'is_secret' => false, 'thumbnail' => null, 'fallback_image' => '/fallback/x'],
                    ['id' => 6, 'is_secret' => false, 'thumbnail' => self::B, 'fallback_image' => null],
                ]],
            ]],
        ];
    }

    public function test_urls_collects_gallery_and_webzine_once_and_skips_secret_and_other_types(): void
    {
        $this->assertSame([self::A, self::B], ThumbnailVariants::urls($this->sections()));
    }

    public function test_urls_does_not_ask_for_secret_thumbnail(): void
    {
        $sections = [['cols' => [['type' => 'gallery', 'items' => [['id' => 2, 'is_secret' => true, 'thumbnail' => self::B]]]]]];

        $this->assertSame([], ThumbnailVariants::urls($sections));
    }

    public function test_apply_uses_240_as_src_and_240_960_as_srcset(): void
    {
        $out = ThumbnailVariants::apply($this->sections(), [self::A => $this->full()]);
        $item = $out[0]['cols'][1]['items'][0];

        $this->assertSame('/v/a-240.webp', $item['thumbnail']);
        $this->assertSame('/v/a-240.webp 240w, /v/a-960.webp 960w', $item['thumbnail_srcset']);
        $this->assertSame('/v/a-240.webp', $out[1]['cols'][0]['items'][0]['thumbnail']);
    }

    public function test_missing_variant_keeps_original_and_no_srcset(): void
    {
        $out = ThumbnailVariants::apply($this->sections(), [self::B => ['available' => false, 'variants' => [], 'source' => null]]);

        $this->assertSame(self::B, $out[1]['cols'][0]['items'][2]['thumbnail']);
        $this->assertNull($out[1]['cols'][0]['items'][2]['thumbnail_srcset']);
    }

    public function test_no_lookup_result_at_all_keeps_original(): void
    {
        $out = ThumbnailVariants::apply($this->sections(), []);

        $this->assertSame(self::A, $out[0]['cols'][1]['items'][0]['thumbnail']);
        $this->assertNull($out[0]['cols'][1]['items'][0]['thumbnail_srcset']);
    }

    public function test_secret_null_thumbnail_and_fallback_image_are_untouched(): void
    {
        $out = ThumbnailVariants::apply($this->sections(), [self::A => $this->full(), self::B => $this->full()]);

        $this->assertSame(self::B, $out[0]['cols'][1]['items'][1]['thumbnail'], '비밀글 값은 바꾸지 않는다(화면은 is_secret 으로 자물쇠)');
        $this->assertNull($out[0]['cols'][1]['items'][1]['thumbnail_srcset']);
        $this->assertNull($out[0]['cols'][1]['items'][2]['thumbnail']);
        $this->assertNull($out[1]['cols'][0]['items'][1]['thumbnail']);
        $this->assertSame('/fallback/x', $out[1]['cols'][0]['items'][1]['fallback_image']);
    }

    public function test_other_widget_types_are_untouched(): void
    {
        $out = ThumbnailVariants::apply($this->sections(), [self::A => $this->full()]);

        $this->assertSame(['id' => 9, 'thumbnail' => self::A], $out[0]['cols'][0]['items'][0]);
    }

    public function test_small_jpeg_original_without_960_is_added_as_candidate(): void
    {
        $hit = [
            'available' => true,
            'variants' => [240 => ['url' => '/v/a-240.webp', 'width' => 240, 'height' => 160]],
            'source' => ['width' => 480, 'height' => 320, 'mime' => 'image/jpeg'],
        ];
        $item = ThumbnailVariants::applyItem(['id' => 1, 'thumbnail' => self::A], [self::A => $hit]);

        $this->assertSame('/v/a-240.webp', $item['thumbnail']);
        $this->assertSame('/v/a-240.webp 240w, '.self::A.' 480w', $item['thumbnail_srcset']);
    }

    public function test_small_png_original_is_not_added_and_single_candidate_means_no_srcset(): void
    {
        $hit = [
            'available' => true,
            'variants' => [240 => ['url' => '/v/a-240.webp', 'width' => 240, 'height' => 160]],
            'source' => ['width' => 480, 'height' => 320, 'mime' => 'image/png'],
        ];
        $item = ThumbnailVariants::applyItem(['id' => 1, 'thumbnail' => self::A], [self::A => $hit]);

        $this->assertSame('/v/a-240.webp', $item['thumbnail']);
        $this->assertNull($item['thumbnail_srcset']);
    }

    public function test_malformed_lookup_values_are_ignored(): void
    {
        $item = ThumbnailVariants::applyItem(['id' => 1, 'thumbnail' => self::A], [self::A => ['available' => true, 'variants' => [240 => ['url' => '']]]]);

        $this->assertSame(self::A, $item['thumbnail']);
        $this->assertNull($item['thumbnail_srcset']);
    }
}
