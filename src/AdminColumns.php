<?php

declare(strict_types=1);

namespace Answeb\ConnecteurCorail;

use WC_Order;
use WP_Post;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin columns class for managing export status columns
 */
class AdminColumns
{
    private static ?AdminColumns $instance = null;

    /**
     * Get singleton instance
     *
     * @return AdminColumns
     */
    public static function get_instance(): AdminColumns
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        // Hooks pour la page utilisateurs WordPress (Utilisateurs > Tous les utilisateurs)
        add_filter('manage_users_columns', [$this, 'add_user_export_column']);
        add_filter('manage_users_custom_column', [$this, 'show_user_export_column'], 10, 3);

        add_action('show_user_profile', [$this, 'add_user_export_field']);
        add_action('edit_user_profile', [$this, 'add_user_export_field']);
        add_action('personal_options_update', [$this, 'save_user_export_field']);
        add_action('edit_user_profile_update', [$this, 'save_user_export_field']);

        // Hooks pour les colonnes des commandes (anciens posts)
        add_filter('manage_edit-shop_order_columns', [$this, 'add_order_export_column']);
        add_action('manage_shop_order_posts_custom_column', [$this, 'show_order_export_column'], 10, 2);

        // Hooks pour HPOS (nouvelle table des commandes)
        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_order_export_column']);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'show_order_export_column'], 10, 2);
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'add_order_export_field']);
        add_action('woocommerce_process_shop_order_meta', [$this, 'save_order_export_field'], 10, 2);
    }

    /**
     * Add user export column
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_user_export_column(array $columns): array
    {
        $columns['corail_exported'] = __('Export Corail', 'connecteur-corail');
        return $columns;
    }

    /**
     * Show user export column content
     *
     * @param string $value Current value
     * @param string $column_name Column name
     * @param int $user_id User ID
     * @return string Column content
     */
    public function show_user_export_column(string $value, string $column_name, int $user_id): string
    {
        if ($column_name !== 'corail_exported') {
            return $value;
        }

        $exported = get_user_meta($user_id, '_connecteur_corail_exported', true);
        $export_date = get_user_meta($user_id, '_connecteur_corail_export_date', true);

        if ($exported === '1') {
            $date_text = $export_date ? ' (' . date('d/m/Y H:i', strtotime($export_date)) . ')' : '';
            return '<span style="color: darkgreen;">✓</span>' . $date_text;
        }

        return '<span style="color: darkred;">✗</span>';
    }

    /**
     * Add user export field to profile
     *
     * @param WP_User $user User object
     * @return void
     */
    public function add_user_export_field(WP_User $user): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $exported = get_user_meta($user->ID, '_connecteur_corail_exported', true);
        $export_date = get_user_meta($user->ID, '_connecteur_corail_export_date', true);
        ?>
        <h3><?php echo esc_html__('Connecteur Corail', 'connecteur-corail'); ?></h3>
        <table class="form-table">
            <tr>
                <th>
                    <label for="corail_exported">
                        <?php echo esc_html__('Statut d\'export', 'connecteur-corail'); ?>
                    </label>
                </th>
                <td>
                    <?php if ($exported === '1') : ?>
                        <p>
                            <span style="color: green;"><?php echo esc_html__('Client exporté', 'connecteur-corail'); ?></span>
                            <?php if ($export_date) : ?>
                                <small><?php echo ' (' . date('d/m/Y à H:i', strtotime($export_date)) . ')'; ?></small>
                            <?php endif; ?>
                        </p>
                        <label>
                            <input type="checkbox" name="reset_corail_export" value="1"/>
                            <?php echo esc_html__('Réinitialiser le statut d\'export', 'connecteur-corail'); ?>
                        </label>
                    <?php else : ?>
                        <p style="color: red;"><?php echo esc_html__('Client non exporté', 'connecteur-corail'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save user export field
     *
     * @param int $user_id User ID
     * @return void
     */
    public function save_user_export_field(int $user_id): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        if (isset($_POST['reset_corail_export']) && $_POST['reset_corail_export'] === '1') {
            delete_user_meta($user_id, '_connecteur_corail_exported');
            delete_user_meta($user_id, '_connecteur_corail_export_date');
        }
    }

    /**
     * Add order export column
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_order_export_column(array $columns): array
    {
        $new_columns = [];

        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ($key === 'order_status') {
                $new_columns['corail_exported'] = __('Export Corail', 'connecteur-corail');
            }
        }

        return $new_columns;
    }

    /**
     * Add order export field to order details
     *
     * @param WC_Order $order Order object
     * @return void
     */
    public function add_order_export_field(WC_Order $order): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $exported = $order->get_meta('_connecteur_corail_exported');
        $export_date = $order->get_meta('_connecteur_corail_export_date');
        ?>
        <div class="form-field form-field-wide">
            <h3><?php echo esc_html__('Connecteur Corail', 'connecteur-corail'); ?></h3>
            <?php if ($exported === '1') : ?>
                <p>
                    <span style="color: green;"><?php echo esc_html__('Commande exportée', 'connecteur-corail'); ?></span>
                    <?php
                    if ($export_date) {
                        echo ' (le ' . date('d/m/Y à H:i', strtotime($export_date)) . ')';
                    }
                    ?>
                </p>
                <label>
                    <input type="checkbox" name="reset_corail_export" value="1" style="width:auto"/>
                    <?php echo esc_html__('Réinitialiser le statut d\'export', 'connecteur-corail'); ?>
                </label>
            <?php else : ?>
                <p style="color: red;"><?php echo esc_html__('Commande non exportée', 'connecteur-corail'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Show order export column content
     *
     * @param string $column Column name
     * @param int|WC_Order $post_id_or_order Post ID or Order object
     * @return void
     */
    public function show_order_export_column(string $column, $post_id_or_order): void
    {
        if ($column !== 'corail_exported') {
            return;
        }

        $order = is_a($post_id_or_order, 'WC_Order') ? $post_id_or_order : wc_get_order($post_id_or_order);
        if (!$order) {
            return;
        }

        $exported = $order->get_meta('_connecteur_corail_exported');
        $export_date = $order->get_meta('_connecteur_corail_export_date');

        if ($exported === '1') {
            $date_text = $export_date ? ' (' . date('d/m/Y H:i', strtotime($export_date)) . ')' : '';
            echo '<span style="color: green;">✓</span>' . $date_text;
        } else {
            echo '<span style="color: red;">✗</span>';
        }
    }

    /**
     * Save order export field
     *
     * @param int $post_id Post ID
     * @param WP_Post|WC_Order $post Post or Order object
     * @return void
     */
    public function save_order_export_field(int $post_id, $post): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        if (!is_a($post, 'WC_Order')) {
            $post = wc_get_order($post);
        }

        if (!$post) {
            return;
        }

        if (isset($_POST['reset_corail_export']) && $_POST['reset_corail_export'] === '1') {
            $post->delete_meta_data('_connecteur_corail_exported');
            $post->delete_meta_data('_connecteur_corail_export_date');
            $post->save();
        }
    }
}