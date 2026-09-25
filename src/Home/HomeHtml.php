<?php

namespace Plugins\G7\Home\Widgets\Home;

use App\Seo\HtmlSanitizer;

/**
 * HTML 위젯 정제(0.4.0) — 코어 `App\Seo\HtmlSanitizer` 에 위임한다(브라우저 HtmlContent 의 DOMPurify 설정을
 * 옮긴 코어 클래스: script·style·iframe 등 요소째 제거, on* 속성 제거, javascript: 등 허용 밖 스킴 제거).
 *
 * 코어 내부 클래스라 사라질 수 있다(core:update 확인 항목). 없으면 빈 문자열을 돌려주고(표시 안 함),
 * 저장 검증은 거부한다({@see self::available()}).
 */
final class HomeHtml
{
    /** 최대 길이(글자) */
    public const MAX_LENGTH = 20000;

    public static function available(): bool
    {
        return class_exists(HtmlSanitizer::class);
    }

    public static function sanitize(string $html): string
    {
        if ($html === '' || ! self::available()) {
            return '';
        }

        return app(HtmlSanitizer::class)->sanitize(mb_substr($html, 0, self::MAX_LENGTH));
    }
}
