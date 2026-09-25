<?php

namespace Plugins\G7\Home\Widgets\Home;

/**
 * 홈 섹션 칸 하나에 들어가는 위젯 종류(0.4.0).
 *
 * 위젯 하나가 설정 기본값·정리·검증 규칙·데이터 조립을 모두 안다. 새 종류는 이 인터페이스를
 * 구현한 파일 하나 + {@see WidgetRegistry} 한 줄 + overlay 조각 하나로 추가한다.
 *
 * `data()` 가 돌려주는 칸 데이터는 브라우저 API 와 봇 컨텍스트가 같은 값을 쓴다
 * ({@see HomeLayoutService}). 권한·비밀글·삭제글 판정은 하지 않는다 — 코어 게시판 서비스가
 * 판정한 결과만 받아 합치고 자른다.
 */
interface HomeWidget
{
    /**
     * 종류 id (설정의 `type`).
     */
    public function id(): string;

    /**
     * 칸 설정 기본값(종류 전용 칸 포함). `type`·`title`·`icon` 은 공통 칸이라 넣지 않는다.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array;

    /**
     * 기본 제목의 번역 키(플러그인 도메인 `messages` 파일 기준).
     */
    public function titleKey(): string;

    /**
     * 기본 아이콘 이름(Font Awesome solid, 템플릿 서브셋 안).
     */
    public function defaultIcon(): string;

    /**
     * 개수(`limit`) 허용 범위.
     *
     * @return array{0: int, 1: int} [최솟값, 최댓값]
     */
    public function limitRange(): array;

    /**
     * 종류 전용 칸 정리. 공통 칸은 {@see HomeLayoutSettings} 가 이미 정리해 넘긴다.
     *
     * @param  array<string, mixed>  $col  공통 칸이 정리된 칸 설정
     * @param  array<string, mixed>  $raw  원래 입력(종류 전용 칸을 읽는 곳)
     * @return array<string, mixed>
     */
    public function normalize(array $col, array $raw): array;

    /**
     * 설정 저장 검증 규칙(코어 설정 저장 필터에 합칠 것, 2묶음에서 연결).
     *
     * @param  string  $prefix  `home_layout.sections.N.cols.M`
     * @return array<string, mixed>
     */
    public function rules(string $prefix): array;

    /**
     * 공통 제외 목록(`excluded_board_ids`)을 적용하는가. 티커는 고른 게시판 1개를 그대로 쓰므로 거짓.
     */
    public function appliesCommonExclusion(): bool;

    /**
     * 지금 고를 수 있는 종류인가(애드온 의존 종류용).
     */
    public function available(): bool;

    /**
     * 쓸 수 없을 때 대신 그릴 종류 id. 없으면 null.
     */
    public function fallback(): ?string;

    /**
     * 칸 데이터(종류 전용 키만). 공통 키(`key`·`type`·`title`·`icon`)는 조립기가 붙인다.
     *
     * @param  array<string, mixed>  $col  정리된 칸 설정
     * @param  array<int, array{id: int, slug: string, name: string}>  $boards  이 칸이 쓸 게시판(권한·제외 적용 끝)
     * @return array<string, mixed>
     */
    public function data(array $col, array $boards): array;
}
