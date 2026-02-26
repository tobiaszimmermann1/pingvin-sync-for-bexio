<?php
namespace Pingvin;

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\JsonFormatter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PingvinLogger {

    /** Number of daily log files to retain before the oldest is deleted. */
    const MAX_FILES = 14;

    private static ?Logger $logger = null;

    private function __construct() {}
    private function __clone() {}

    public static function getLogger(): Logger {
        if ( self::$logger === null ) {
            self::$logger = new Logger( 'pvbexio' );

            // One file per day, kept for MAX_FILES days.
            // Files are named: uploads/pvbexio/pvbexio-2026-02-18.log
            $upload_dir = wp_upload_dir();
            $log_dir    = trailingslashit( $upload_dir['basedir'] ) . 'pvbexio';

            // Create the directory if it doesn't exist, and drop an index.php
            // guard so the folder contents are not directly web-browsable.
            if ( ! is_dir( $log_dir ) ) {
                wp_mkdir_p( $log_dir );
                file_put_contents( $log_dir . '/index.php', '<?php // Silence is golden.' );
            }

            $log_path = $log_dir . '/pvbexio.log';
            $handler  = new RotatingFileHandler( $log_path, self::MAX_FILES, Logger::DEBUG );

            // Each line is a JSON object — easy to parse in the admin UI.
            $handler->setFormatter( new JsonFormatter() );

            self::$logger->pushHandler( $handler );
        }

        return self::$logger;
    }

    /**
     * @param string $level   Monolog level name: 'debug', 'info', 'warning', 'error', 'critical'
     * @param string $message Log message.
     * @param array  $context Optional key→value context data.
     */
    public static function log( string $level, string $message, array $context = [] ): void {
        self::getLogger()->$level( $message, $context );
    }
}
