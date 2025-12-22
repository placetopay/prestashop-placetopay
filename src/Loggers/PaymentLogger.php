<?php

namespace PlacetoPay\Loggers;

use Exception;
use \FileLogger;
use \PrestaShopLogger;

class PaymentLogger
{
    const DEBUG = 0;
    const INFO = 1;
    const WARNING = 2;
    const ERROR = 3;
    const NOTIFY = 99;

    public static function log(string $message, int $severity, int $errorCode, string $file, string $line, ?string $moduleName = null): bool
    {
        if ($moduleName === null) {
            $moduleName = getModuleName();
        }
        
        $format = sprintf("[%s:%d] => [%d]\n %s", $file, $line, $errorCode, $message);

        $logger = self::getLogInstance($moduleName);
        if ($logger) {
            $logger->log($format, $severity, $errorCode);
        }

        if ($severity >= self::WARNING) {
            return self::logInDatabase(
                preg_replace(['/(\s+)/', '/(<([^>=]+)>)/'], ' ', $message),
                $severity,
                $errorCode
            );
        }

        return true;
    }

    public static function logInDatabase(string $message, int $severity = self::INFO, int $errorCode = null): bool
    {
        try {
            $errorCode = $errorCode > 0 ? $errorCode : 999;

            PrestaShopLogger::addLog($message, $severity, $errorCode, getModuleName(), $errorCode);
        } catch (Exception $exception) {
            return false;
        }

        return true;
    }

    public static function getLogFilename(?string $moduleName = null): string
    {
        if ($moduleName === null) {
            $moduleName = getModuleName();
        }

        static $logfiles = [];
        
        if (!isset($logfiles[$moduleName])) {
            $filename = sprintf('%s_%s_%s.log', (isDebugEnable() ? 'dev' : 'prod'), date('Ymd'), $moduleName);

            // PS < 1.7.0.0
            $pathLogs = '/log/';

            if (version_compare(_PS_VERSION_, '1.7.4.0', '>=')) {
                $pathLogs = '/var/logs/';
            } elseif (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
                $pathLogs = '/app/logs/';
            }

            $logfiles[$moduleName] = fixPath(_PS_ROOT_DIR_ . $pathLogs . $filename);
        }

        return $logfiles[$moduleName];
    }

    /**
     * @return FileLogger|null
     */
    private static function getLogInstance(?string $moduleName = null)
    {
        if ($moduleName === null) {
            $moduleName = getModuleName();
        }
        
        static $loggers = [];
        
        if (!isset($loggers[$moduleName])) {
            $loggers[$moduleName] = false;
            $logfile = self::getLogFilename($moduleName);

            if (!is_file($logfile) && is_writable(dirname($logfile))) {
                file_put_contents($logfile, '');
            }

            if (is_writable($logfile)) {
                $loggers[$moduleName] = new FileLogger(0);
                $loggers[$moduleName]->setFilename($logfile);
            }
        }

        return $loggers[$moduleName];
    }
}
