<?php

// 순수 실행 환경(코어 없음)에서만 코어 설정 헬퍼 plugin_settings() 자리를 채운다. 코어가 있으면 정의하지 않는다.
// 값은 테스트가 $GLOBALS['g7hw_test_plugin_settings'] 에 넣는다.
if (! function_exists('plugin_settings')) {
    define('G7HW_TEST_PLUGIN_SETTINGS_STUB', true);

    function plugin_settings(string $identifier): array
    {
        return $GLOBALS['g7hw_test_plugin_settings'] ?? [];
    }
}
