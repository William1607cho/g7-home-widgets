<?php

namespace Plugins\G7\Home\Widgets\Support;

use App\Services\PluginSettingsService;
use Illuminate\Support\Facades\Cache;

/**
 * 위젯 게시판 제외 설정 (0.3.0).
 *
 * 최근글·인기글 위젯에서 뺄 게시판 ID 목록 하나를 플러그인 설정(`excluded_board_ids`)으로
 * 관리한다. 공지 티커는 게시판을 직접 지정하는 방식이라 대상이 아니다.
 *
 * - **ID 만 저장한다(slug 는 쓰지 않는다).** 새로 만든 게시판은 목록에 없으므로 자동으로
 *   포함되고, 삭제된 게시판의 ID 는 조회 때 무해하게 무시되다가 다음 저장 때 빠진다.
 * - **표시 여부만 정한다.** 열람 권한 필터는 각 쿼리가 그대로 적용한다 — 이 설정은 보안
 *   장치가 아니다.
 * - 설정 값은 읽을 때마다 정리한다(정수 변환·양수만·중복 제거·정렬). 코어의 범용 설정
 *   API 로도 이 키를 쓸 수 있고 그쪽 검증은 "배열인가" 뿐이기 때문이다.
 * - 캐시: 두 풀 캐시 키에 제외 목록의 지문을 넣는다. 목록이 바뀌면 키가 달라져 TTL 을
 *   기다리지 않고 바로 반영되며, 저장 API 는 이전 지문의 키를 모두 지운다
 *   ({@see self::forgetPoolsFor()}).
 */
class BoardFilterSettings
{
    /** 플러그인 식별자 */
    public const IDENTIFIER = 'g7-home-widgets';

    /** 설정 키 */
    public const KEY = 'excluded_board_ids';

    /** 저장할 수 있는 최대 개수 */
    public const MAX_IDS = 500;

    /** 제외 목록이 비었을 때의 지문 */
    private const EMPTY_FINGERPRINT = 'none';

    /**
     * 현재 저장된 제외 게시판 ID 목록(정리됨).
     *
     * @return array<int, int>
     */
    public static function excludedIds(): array
    {
        $raw = function_exists('plugin_settings') ? plugin_settings(self::IDENTIFIER) : [];
        $raw = is_array($raw) ? ($raw[self::KEY] ?? []) : [];

        return self::normalize($raw);
    }

    /**
     * 임의 입력을 정수·양수·중복 없음·오름차순 목록으로 정리한다.
     *
     * @param  mixed  $value  설정 값 또는 요청 값
     * @return array<int, int>
     */
    public static function normalize(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            if (is_int($item) || (is_string($item) && ctype_digit($item))) {
                $id = (int) $item;
                if ($id >= 1) {
                    $ids[$id] = $id;
                }
            }
        }

        sort($ids);

        return array_values($ids);
    }

    /**
     * 제외 목록의 짧은 지문 — 캐시 키에 들어간다.
     *
     * @param  array<int, int>  $ids  정리된 ID 목록
     */
    public static function fingerprint(array $ids): string
    {
        if ($ids === []) {
            return self::EMPTY_FINGERPRINT;
        }

        return substr(sha1(implode(',', $ids)), 0, 12);
    }

    /**
     * 제외 목록을 저장한다. 호출자가 존재하는 게시판 ID 로 걸러서 넘긴다.
     *
     * @param  array<int, int>  $ids  저장할 ID 목록
     * @param  string|null  $failureReason  실패 사유 (출력)
     */
    public static function save(array $ids, ?string &$failureReason = null): bool
    {
        return app(PluginSettingsService::class)->save(
            self::IDENTIFIER,
            [self::KEY => self::normalize($ids)],
            $failureReason,
        );
    }

    /**
     * 지정한 지문의 두 위젯 풀 캐시를 모두 지운다
     * (최근글 풀 크기 전체, 인기글 기간 4종 × 풀 크기 전체).
     *
     * @param  string  $fingerprint  지울 지문
     */
    public static function forgetPoolsFor(string $fingerprint): void
    {
        foreach (array_merge(
            RecentPostsQuery::cacheKeysFor($fingerprint),
            PopularPostsQuery::cacheKeysFor($fingerprint),
        ) as $key) {
            Cache::forget($key);
        }
    }
}
