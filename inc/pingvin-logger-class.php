<?php 
namespace Pingvin;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class PingvinLogger {
    private static $logger = null;

    // Prevent direct instantiation
    private function __construct() {}
    private function __clone() {}

    // Get the logger instance
    public static function getLogger() {
        if (self::$logger === null) {
            self::$logger = new Logger('pv-bexio'); 
            $logPath = WP_CONTENT_DIR . '/pv-bexio.log';
            self::$logger->pushHandler(new StreamHandler($logPath, Logger::DEBUG)); 
        }
        return self::$logger;
    }

    public static function log($level, $message, array $context = []) {
        $logger = self::getLogger();
        $logger->$level($message);
    }
}

?>