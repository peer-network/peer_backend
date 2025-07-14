<?php

use Fawaz\config\constants\ConstantsConfig;

if (!function_exists('constants')) {
    function constants(): ConstantsConfig {
        static $instance = null;

        if ($instance === null) {
            $instance = new ConstantsConfig();
        }

        return $instance;
    }
}
