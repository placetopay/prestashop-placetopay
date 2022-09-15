<?php

if (!function_exists('getPathCMS')) {
    function getPathCMS(string $filename): string
    {
        $option = 'Default';
        $pathUsed = getcwd();
        $pathCMS = dirname(dirname($pathUsed));

        if (isset($_SERVER['PWD']) && is_link($_SERVER['PWD'])) {
            $option = 'PWD';
            $pathUsed = $_SERVER['PWD'];
            $pathCMS = dirname(dirname($pathUsed));
        } elseif (isset($_SERVER['SCRIPT_FILENAME'])) {
            $option = 'File';
            $pathUsed = fixPath($_SERVER['SCRIPT_FILENAME']);
            $pathCMS = str_replace(
                fixPath(sprintf('/modules/%s/%s', getModuleName(), $filename)),
                '',
                $pathUsed
            );
        }

        if (!file_exists(fixPath($pathCMS . '/config/config.inc.php'))) {
            $message = "Miss-configuration in Server [mode: " . php_sapi_name() . "] [{$filename}]" . breakLine();
            $message .= "Option [{$option}]" . breakLine();
            $message .= "Used [{$pathUsed}]" . breakLine();
            $message .= "Path [{$pathCMS}]" . breakLine();

            die($message);
        }

        return $pathCMS;
    }
}

if (!function_exists('versionComparePlaceToPay')) {
    function versionComparePlaceToPay(string $version, string $operator): bool
    {
        return version_compare(_PS_VERSION_, $version, $operator);
    }
}

if (!function_exists('isDebugEnable')) {
    function isDebugEnable(): bool
    {
        return defined('_PS_MODE_DEV_') && _PS_MODE_DEV_ === true;
    }
}

if (!function_exists('isConsole')) {
    function isConsole(): bool
    {
        static $isConsole;

        if (is_null($isConsole)) {
            $isConsole = \Tools::isPHPCLI();
        }

        return $isConsole;
    }
}

if (!function_exists('breakLine')) {
    function breakLine(int $multiplier = 1): string
    {
        static $breakLine;

        if (is_null($breakLine)) {
            $breakLine = isConsole() ? PHP_EOL : '<br />';
        }

        return str_repeat($breakLine, $multiplier);
    }
}

if (!function_exists('getModuleName')) {
    function getModuleName(): string
    {
        return 'placetopaypayment';
    }
}

if (!function_exists('fixPath')) {
    function fixPath(string $path): string
    {
        // Case:
        // IIS:     \ (backslash)
        // Apache:  / (slash)
        return str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $path);
    }
}

if (!function_exists('unmaskString')) {
    function unmaskString(string $string): string
    {
        return str_rot13($string);
    }
}
