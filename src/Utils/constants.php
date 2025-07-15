<?php

if (!function_exists('constants')) {
    function constants(): \Fawaz\config\constants\ConstantsConfig {
        static $instance = null;

        if ($instance === null) {
            $instance = new \Fawaz\config\constants\ConstantsConfig();
        }

        return $instance;
    }
}
