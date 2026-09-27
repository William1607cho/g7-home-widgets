<?php

namespace Plugins\G7\Home\Widgets\Home;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * 홈 섹션 데이터 캐시(0.4.0) — 세대 키 방식.
 *
 * 캐시 드라이버가 file 이라 태그로 한꺼번에 지울 수 없다. 대신 모든 키 앞에 세대 번호
 * (`g7hw:gen`)를 넣고, 무효화할 때는 세대만 올린다. 옛 세대의 키는 TTL 이 지나면 사라진다.
 *
 * 왜 필요한가: 게시판 모듈은 글 작성·수정·삭제 때 봇 홈 캐시(`invalidateByLayout('home')`)를
 * 지운다. 그 직후 봇이 오면 봇 홈을 다시 그리는데, 이때 위젯 데이터 캐시(TTL 90초)에 옛 목록이
 * 남아 있으면 옛 목록으로 그린 봇 화면이 봇 캐시 TTL(기본 2시간) 동안 남는다. 같은 글 이벤트에서
 * 세대를 올려 두면 봇 재렌더가 새 목록을 읽는다({@see \Plugins\G7\Home\Widgets\Listeners\HomeCacheGenerationListener}).
 *
 * 담는 것은 사용자와 무관한 "게시판별 안전집합"뿐이다. 권한 교집합은 요청마다 계산한다.
 */
final class WidgetCache
{
    /** 데이터 TTL(초) */
    public const TTL_SECONDS = 90;

    /** 세대 번호 키 */
    public const GENERATION_KEY = 'g7hw:gen';

    private ?int $generation = null;

    /**
     * 현재 세대의 키로 값을 기억한다.
     *
     * @param  string  $suffix  키 뒷부분(`recent:12` 등)
     * @param  Closure(): mixed  $resolver  값 계산
     */
    public function remember(string $suffix, Closure $resolver): mixed
    {
        return Cache::remember($this->key($suffix), self::TTL_SECONDS, $resolver);
    }

    /**
     * 현재 세대가 붙은 전체 키.
     */
    public function key(string $suffix): string
    {
        return 'g7hw:'.$this->generation().':'.$suffix;
    }

    /**
     * 세대를 올려 이전 데이터를 모두 무효로 만든다.
     */
    public function bump(): void
    {
        $next = $this->readGeneration() + 1;
        Cache::forever(self::GENERATION_KEY, $next);
        $this->generation = $next;
    }

    private function generation(): int
    {
        return $this->generation ??= $this->readGeneration();
    }

    private function readGeneration(): int
    {
        $value = Cache::get(self::GENERATION_KEY, 0);

        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : 0;
    }
}
