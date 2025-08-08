<?php

declare(strict_types=1);

/**
 * Uninstall script for Connecteur Corail plugin
 *
 * This file is executed when the plugin is deleted (not just deactivated).
 * It removes all plugin data from the database.
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options
delete_option('connecteur_corail_settings');
delete_option('connecteur_corail_logs');

// Remove scheduled cron events
wp_clear_scheduled_hook('connecteur_corail_export_cron');
