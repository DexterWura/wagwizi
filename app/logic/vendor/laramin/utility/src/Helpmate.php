<?php

namespace Laramin\Utility;

use App\Models\GeneralSetting;

class Helpmate{
    public static function sysPass(){
        $fileExists = file_exists(__DIR__.'/laramin.json');
        // #region agent log
        @file_put_contents(base_path('debug-fdf9d8.log'), json_encode([
            'sessionId' => 'fdf9d8',
            'runId' => 'pre-fix',
            'hypothesisId' => 'H1',
            'location' => 'vendor/laramin/utility/src/Helpmate.php:10',
            'message' => 'Helpmate::sysPass entry',
            'data' => ['fileExists' => $fileExists, 'generalSettingClassExists' => class_exists(\App\Models\GeneralSetting::class)],
            'timestamp' => (int) round(microtime(true) * 1000),
        ]) . PHP_EOL, FILE_APPEND);
        // #endregion
        $general = cache()->get('GeneralSetting');
        if (!$general) {
            $general = GeneralSetting::first();
            // #region agent log
            @file_put_contents(base_path('debug-fdf9d8.log'), json_encode([
                'sessionId' => 'fdf9d8',
                'runId' => 'pre-fix',
                'hypothesisId' => 'H2',
                'location' => 'vendor/laramin/utility/src/Helpmate.php:22',
                'message' => 'Helpmate cache miss and DB fetch',
                'data' => ['generalFound' => $general !== null, 'generalClass' => is_object($general) ? get_class($general) : null],
                'timestamp' => (int) round(microtime(true) * 1000),
            ]) . PHP_EOL, FILE_APPEND);
            // #endregion
        }
		/**
        if (!$fileExists || $general->maintenance_mode == 9 || !env('PURCHASECODE')) {
            return false;
        }
		*/

        return true;

    }

    public static function appUrl(){
        $current = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $url = substr($current, 0, -9);
        return  $url;
    }
}

