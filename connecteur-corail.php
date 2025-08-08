<?php
/*
* Plugin WordPress pour exporter les données clients et commandes WooCommerce vers un ERP Corail au format CSV.
* Copyright (C) 2025 answeb - https://www.answeb.net/
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

declare(strict_types=1);

/**
 * Plugin Name: Connecteur Corail
 * Plugin URI: https://www.answeb.net
 * Description: Plugin WordPress pour exporter les données clients et commandes WooCommerce vers un ERP Corail au format CSV.
 * Version: 1.2.0
 * Author: Answeb
 * Author URI: https://www.answeb.net
 * Requires at least: 6.8.1
 * Tested up to: 6.8.1
 * Requires PHP: 8.0
 * Text Domain: connecteur-corail
 * Domain Path: /languages
 * WC requires at least: 9.8.5
 * WC tested up to: 9.8.5
 * Woo: declare=high_performance_order_storage:compatible
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Answeb\ConnecteurCorail\Admin;
use Answeb\ConnecteurCorail\Cron;
use Answeb\ConnecteurCorail\AdminColumns;
use Answeb\ConnecteurCorail\OrderStatuses;

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

const CONNECTEUR_CORAIL_VERSION = '1.0.0';
define('CONNECTEUR_CORAIL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CONNECTEUR_CORAIL_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Composer autoloader
if (file_exists(CONNECTEUR_CORAIL_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once CONNECTEUR_CORAIL_PLUGIN_DIR . 'vendor/autoload.php';
}

class ConnecteurCorail {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('before_woocommerce_init', [ $this, 'declare_hpos_compatibility' ] );
        add_action('plugins_loaded', [ $this, 'init' ] );
        register_activation_hook(__FILE__, [ $this, 'activate' ] );
        register_deactivation_hook(__FILE__, [ $this, 'deactivate' ] );
    }

    public function init() {
        if (!$this->check_requirements()) {
            return;
        }

        $this->load_textdomain();
        $this->init_hooks();
    }

    private function check_requirements() {
        if (!is_plugin_active('woocommerce/woocommerce.php')) {
            add_action('admin_notices', [ $this, 'woocommerce_missing_notice' ] );
            return false;
        }

        if (version_compare(PHP_VERSION, '8.0', '<')) {
            add_action('admin_notices', [ $this, 'php_version_notice' ] );
            return false;
        }

        return true;
    }

    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo __('Le plugin Connecteur Corail nécessite WooCommerce pour fonctionner.', 'connecteur-corail');
        echo '</p></div>';
    }

    public function php_version_notice() {
        echo '<div class="notice notice-error"><p>';
        echo __('Le plugin Connecteur Corail nécessite PHP 8.0 ou supérieur.', 'connecteur-corail');
        echo '</p></div>';
    }

    private function load_textdomain() {
        load_plugin_textdomain('connecteur-corail', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    private function init_hooks() {
        Admin::get_instance();
        Cron::get_instance();
        OrderStatuses::get_instance();

        // Initialiser les colonnes d'administration plus tôt
        add_action('admin_init', [ $this, 'init_admin_columns' ] );
    }

    public function init_admin_columns() {
        AdminColumns::get_instance();
    }

    public function activate() {
        $this->create_default_options();
	    $settings = get_option('connecteur_corail_settings');
        Cron::schedule_events($settings['export_frequency'] ?? '', $settings['export_time'] ?? '');
    }

    public function deactivate() {
        Cron::clear_scheduled_events();
    }

    public function declare_hpos_compatibility() {
        FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }

    private function create_default_options() {
	    if (get_option('connecteur_corail_settings')) {
		    return;
	    }
        $default_options = [
            'export_directory' => '',
            'clients_filename_template' => '%Y%m%d%H%i_CLIENTS.csv',
            'orders_header_filename_template' => '%Y%m%d%H%i_COMMANDES_ENTETES.csv',
            'orders_lines_filename_template' => '%Y%m%d%H%i_COMMANDES_LIGNES.csv',
            'column_separator' => ';',
            'export_frequency' => 'daily',
            'export_time' => '02:00',
            'order_statuses' => ['wc-completed', 'wc-processing', 'wc-shipped'],
        ];
        add_option('connecteur_corail_settings', $default_options);
    }

}

ConnecteurCorail::get_instance();