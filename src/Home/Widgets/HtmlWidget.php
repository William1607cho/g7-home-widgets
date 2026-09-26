<?php

namespace Plugins\G7\Home\Widgets\Home\Widgets;

use Plugins\G7\Home\Widgets\Home\HomeHtml;
use Plugins\G7\Home\Widgets\Home\HomeWidget;

/**
 * HTML 위젯 — 관리자가 넣은 HTML 을 그대로(정제 후) 보여 준다(0.4.0). 스크립트는 허용하지 않는다.
 *
 * 정제는 세 겹이다: 저장 때 코어 `App\Seo\HtmlSanitizer`({@see HomeHtml}), 읽을 때 같은 정제기 한 번 더
 * (저장 경로 밖에서 파일이 바뀐 경우), 표시 때 브라우저 `HtmlContent`(DOMPurify)·봇 `html_content`
 * 렌더(같은 HtmlSanitizer). 게시판을 쓰지 않는다.
 */
final class HtmlWidget implements HomeWidget
{
    public function id(): string
    {
        return 'html';
    }

    public function defaults(): array
    {
        return ['limit' => 1, 'boards' => ['mode' => 'all', 'ids' => []], 'html' => ''];
    }

    public function titleKey(): string
    {
        return 'messages.home.titles.html';
    }

    public function defaultIcon(): string
    {
        return 'code';
    }

    public function limitRange(): array
    {
        return [1, 1];
    }

    public function normalize(array $col, array $raw): array
    {
        $html = $raw['html'] ?? ($col['html'] ?? '');
        $col['html'] = is_string($html) ? mb_substr($html, 0, HomeHtml::MAX_LENGTH) : '';

        return $col;
    }

    public function rules(string $prefix): array
    {
        return ["{$prefix}.html" => ['nullable', 'string', 'max:'.HomeHtml::MAX_LENGTH]];
    }

    public function boardSelection(): string
    {
        return 'none';
    }

    public function available(): bool
    {
        return true;
    }

    public function fallback(): ?string
    {
        return null;
    }

    public function data(array $col, array $boards): array
    {
        return ['html' => HomeHtml::sanitize((string) ($col['html'] ?? ''))];
    }
}
