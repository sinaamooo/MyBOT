<?php

namespace TelegramShop\Classes;

class Logger {
    public static function info($message) {
        self::log('INFO', $message);
    }

    public static function error($message) {
        self::log('ERROR', $message);
    }

    public static function warning($message) {
        self::log('WARNING', $message);
    }

    public static function debug($message) {
        if (LOG_LEVEL === 'DEBUG') {
            self::log('DEBUG', $message);
        }
    }

    private static function log($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logFile = LOG_PATH . date('Y-m-d') . '.log';

        $logMessage = "[$timestamp] [$level] $message\n";

        error_log($logMessage, 3, $logFile);
    }
}
