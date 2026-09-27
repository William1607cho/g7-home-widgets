<?php

namespace Plugins\G7\Home\Widgets\Support;

/**
 * 플러그인 식별자 상수만 남은 클래스.
 *
 * 0.3.0 부터 0.4.2 까지는 공통 게시판 제외 설정의 읽기·저장을 맡았다. 0.5.0 에서 그 설정을 읽지 않게 되어
 * 나머지는 모두 뺐고, 리스너·홈 설정 읽기가 참조하는 식별자 상수만 둔다(참조하는 쪽을 바꾸지 않기
 * 위해서다). 설정 파일에 남은 옛 값은 지우지 않고 읽지도 않는다.
 */
class BoardFilterSettings
{
    /** 플러그인 식별자 */
    public const IDENTIFIER = 'g7-home-widgets';
}
