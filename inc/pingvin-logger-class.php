<?php 

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class PingvinLogger {
    private static $logger = null;

    // Prevent direct instantiation
    private function __construct() {}
    private function __clone() {}

    // Get the logger instance (singleton pattern)
    public static function getLogger() {
        if (self::$logger === null) {
            self::$logger = new Logger('pv-loonity-logger'); // 'myplugin' is the channel name
            $logPath = WP_CONTENT_DIR . '/pv-loonity-logger.log'; // Log file location
            self::$logger->pushHandler(new StreamHandler($logPath, Logger::DEBUG)); // DEBUG for all levels
        }
        return self::$logger;
    }

    public static function log($level, $message, array $context = []) {
        $logger = self::getLogger();
        $logger->$level($message);
    }

}

?>