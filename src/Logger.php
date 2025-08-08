<?php

declare(strict_types=1);

namespace Answeb\ConnecteurCorail;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger class for handling plugin logs
 */
class Logger
{
    public const LOG_OPTION = 'connecteur_corail_logs';
    public const MAX_LOGS = 100;

    /**
     * Log a message with specified type
     *
     * @param string $type The log type (error, success, info, etc.)
     * @param string $message The log message
     * @return void
     */
    public function log(string $type, string $message): void
    {
        $logs = get_option(self::LOG_OPTION, []);

        $log_entry = [
            'date' => current_time('Y-m-d H:i:s'),
            'type' => $type,
            'message' => $message
        ];

        array_unshift($logs, $log_entry);

        $logs = array_slice($logs, 0, self::MAX_LOGS);

        update_option(self::LOG_OPTION, $logs);
    }

    /**
     * Get recent logs
     *
     * @param int $limit Maximum number of logs to return
     * @return array Array of log entries
     */
    public static function get_recent_logs(int $limit = 20): array
    {
        $logs = get_option(self::LOG_OPTION, []);
        return array_slice($logs, 0, $limit);
    }

    /**
     * Clear all logs
     *
     * @return void
     */
    public static function clear_logs(): void
    {
        delete_option(self::LOG_OPTION);
    }
}
