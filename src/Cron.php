<?php

declare(strict_types=1);

namespace Answeb\ConnecteurCorail;

use DateTime;
use DateInterval;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cron handling class for scheduled exports
 */
class Cron
{
    private static ?Cron $instance = null;

    /**
     * Get singleton instance
     *
     * @return Cron
     */
    public static function get_instance(): Cron
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        add_action('connecteur_corail_export_cron', [$this, 'run_scheduled_export']);
    }

    /**
     * Schedule events
     *
     * @param string $frequency Frequency of export
     * @param string $time Time of export
     * @return bool Success status
     */
    public static function schedule_events(string $frequency, string $time): bool
    {
        if (empty($frequency) || empty($time)) {
            return false;
        }
        self::clear_scheduled_events();
        if ($frequency !== 'disabled') {
            $timestamp = self::get_next_scheduled_time($frequency, $time);
            wp_schedule_event($timestamp, $frequency, 'connecteur_corail_export_cron');
        }
        return true;
    }

    /**
     * Clear scheduled events
     *
     * @return void
     */
    public static function clear_scheduled_events(): void
    {
        $timestamp = wp_next_scheduled('connecteur_corail_export_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'connecteur_corail_export_cron');
        }
        wp_clear_scheduled_hook('connecteur_corail_export_cron');
    }

    /**
     * Get next scheduled time
     *
     * @param string $frequency Frequency of export
     * @param string $time Time of export
     * @return int Timestamp
     */
    private static function get_next_scheduled_time(string $frequency, string $time): int
    {
        $now = new DateTime('now', wp_timezone());
        [$hour, $minute] = explode(':', $time . ':0');
        $hour = (int) $hour;
        $minute = (int) $minute;

        $scheduled = clone $now;
        $scheduled->setTime($hour, $minute, 0);

        switch ($frequency) {
            case 'hourly':
                if ($scheduled <= $now) {
                    $scheduled = $now;
                    $scheduled->setTime((int) $now->format('H') + 1, $minute, 0);
                }
                break;

            case 'daily':
                if ($scheduled <= $now) {
                    $scheduled->add(new DateInterval('P1D'));
                }
                break;

            case 'weekly':
                $scheduled->modify('next monday');
                if ($scheduled->format('w') == 1 && $scheduled <= $now) {
                    $scheduled->add(new DateInterval('P7D'));
                }
                $scheduled->setTime($hour, $minute, 0);
                break;

            default:
                $scheduled->add(new DateInterval('PT1H'));
                break;
        }

        return $scheduled->getTimestamp();
    }

    /**
     * Run scheduled export
     *
     * @return void
     */
    public function run_scheduled_export(): void
    {
        $logger = new Logger();

        try {
            $exporter = new Exporter();
            $result = $exporter->export();

            $logger->log('info', sprintf(
                __('Export automatique terminé : %d clients et %d commandes exportés.', 'connecteur-corail'),
                $result['clients'],
                $result['orders']
            ));
        } catch (Exception $e) {
            $logger->log('error', sprintf(
                __('Erreur lors de l\'export automatique : %s', 'connecteur-corail'),
                $e->getMessage()
            ));
        }
    }
}
