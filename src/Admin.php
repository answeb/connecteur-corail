<?php

declare(strict_types=1);

namespace Answeb\ConnecteurCorail;

use Exception;
use WP_Error;
use Automattic\WooCommerce\Enums\OrderInternalStatus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin interface class for the Connecteur Corail plugin
 */
class Admin
{
    private static ?Admin $instance = null;

    /**
     * Get singleton instance
     *
     * @return Admin
     */
    public static function get_instance(): Admin
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
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_export_corail_data', [$this, 'handle_manual_export']);
        add_action('wp_ajax_import_corail_status', [$this, 'handle_status_import']);
        add_action('wp_ajax_download_corail_file', [$this, 'handle_file_download']);
        add_action('wp_ajax_clear_corail_logs', [$this, 'handle_clear_logs']);
    }

    /**
     * Add admin menu
     *
     * @return void
     */
    public function add_admin_menu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Connecteur Corail', 'connecteur-corail'),
            __('Connecteur Corail', 'connecteur-corail'),
            'manage_woocommerce',
            'connecteur-corail',
            [$this, 'admin_page']
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_assets(string $hook): void
    {
        if ($hook !== 'woocommerce_page_connecteur-corail') {
            return;
        }

        wp_enqueue_style(
            'connecteur-corail-admin',
            CONNECTEUR_CORAIL_PLUGIN_URL . 'assets/css/admin.css',
            [],
            CONNECTEUR_CORAIL_VERSION
        );

        wp_enqueue_script(
            'connecteur-corail-admin',
            CONNECTEUR_CORAIL_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            CONNECTEUR_CORAIL_VERSION,
            true
        );

        wp_localize_script(
            'connecteur-corail-admin',
            'connecteurCorailAdmin',
            [
                'exportNonce' => wp_create_nonce('export_corail_nonce'),
                'importNonce' => wp_create_nonce('import_corail_nonce'),
                'exportInProgress' => __('Export en cours...', 'connecteur-corail'),
                'launchExport' => __('Lancer l\'export', 'connecteur-corail'),
                'exportError' => __('Erreur lors de l\'export.', 'connecteur-corail'),
                'importInProgress' => __('Import en cours...', 'connecteur-corail'),
                'importStatuses' => __('Importer les statuts', 'connecteur-corail'),
                'importError' => __('Erreur lors de l\'import.', 'connecteur-corail'),
                'selectFile' => __('Veuillez sélectionner un fichier.', 'connecteur-corail'),
                'downloadFiles' => __('Fichiers téléchargeables :', 'connecteur-corail'),
                'download' => __('Télécharger', 'connecteur-corail'),
                'clearLogsNonce' => wp_create_nonce('clear_corail_logs'),
                'clearLogsConfirm' => __('Êtes-vous sûr de vouloir effacer tous les logs ? Cette action est irréversible.', 'connecteur-corail'),
                'clearLogsInProgress' => __('Effacement en cours...', 'connecteur-corail'),
                'clearLogsButton' => __('Effacer les logs', 'connecteur-corail'),
                'clearLogsError' => __('Erreur lors de l\'effacement des logs.', 'connecteur-corail')
            ]
        );
    }

    /**
     * Register settings
     *
     * @return void
     */
    public function register_settings(): void
    {
        register_setting('connecteur_corail_settings', 'connecteur_corail_settings', ['sanitize_callback' => [$this, 'sanitize_settings']]);

        add_settings_section(
            'connecteur_corail_general',
            __('Configuration générale', 'connecteur-corail'),
            [$this, 'general_section_callback'],
            'connecteur_corail_settings'
        );

        add_settings_field(
            'export_directory',
            __('Répertoire d\'export', 'connecteur-corail'),
            [$this, 'export_directory_callback'],
            'connecteur_corail_settings',
            'connecteur_corail_general'
        );

        add_settings_field(
            'clients_filename_template',
            __('Template nom fichier clients', 'connecteur-corail'),
            [$this, 'clients_filename_template_callback'],
            'connecteur_corail_settings',
            'connecteur_corail_general'
        );

        add_settings_field(
            'orders_header_filename_template',
            __('Template nom fichier entêtes commandes', 'connecteur-corail'),
            [$this, 'orders_header_filename_template_callback'],
            'connecteur_corail_settings',
            'connecteur_corail_general'
        );

        add_settings_field(
            'orders_lines_filename_template',
            __('Template nom fichier lignes commandes', 'connecteur-corail'),
            [$this, 'orders_lines_filename_template_callback'],
            'connecteur_corail_settings',
            'connecteur_corail_general'
        );

        add_settings_field(
            'column_separator',
            __('Séparateur de colonnes', 'connecteur-corail'),
            [$this, 'column_separator_callback'],
            'connecteur_corail_settings',
            'connecteur_corail_general'
        );

        add_settings_field(
            'export_frequency',
            __('Fréquence d\'export', 'connecteur-corail'),
            [$this, 'export_frequency_callback'],
            'connecteur_corail_settings',
            'connecteur_corail_general'
        );

        add_settings_field(
            'export_time',
            __('Heure d\'export', 'connecteur-corail'),
            [$this, 'export_time_callback'],
            'connecteur_corail_settings',
            'connecteur_corail_general'
        );

        add_settings_field(
            'order_statuses',
            __('États des commandes', 'connecteur-corail'),
            [$this, 'order_statuses_callback'],
            'connecteur_corail_settings',
            'connecteur_corail_general'
        );

        add_settings_section(
            'connecteur_corail_status_mapping',
            __('Mappage des statuts', 'connecteur-corail'),
            [$this, 'status_mapping_section_callback'],
            'connecteur_corail_settings'
        );

        add_settings_field(
            'status_mapping',
            __('Mappage Corail vers WooCommerce', 'connecteur-corail'),
            [$this, 'status_mapping_callback'],
            'connecteur_corail_settings',
            'connecteur_corail_status_mapping'
        );
    }

    /**
     * Sanitize settings
     *
     * @param array $input Input data
     * @return array Sanitized data
     */
    public function sanitize_settings(array $input): array
    {
        $fields = [
            'export_directory', 'clients_filename_template', 'orders_header_filename_template',
            'orders_lines_filename_template', 'column_separator', 'export_frequency', 'export_time'
        ];

        $sanitized = [];

        foreach ($fields as $field) {
            $sanitized[$field] = sanitize_text_field($input[$field] ?? '');
        }

        $sanitized['order_statuses'] = isset($input['order_statuses']) ?
            array_map('sanitize_text_field', $input['order_statuses']) : [];

        $sanitized['status_mapping'] = [];
        if (isset($input['status_mapping_keys']) && isset($input['status_mapping_values'])
            && is_array($input['status_mapping_keys']) && is_array($input['status_mapping_values'])) {

            $keys = $input['status_mapping_keys'];
            $values = $input['status_mapping_values'];

            for ($i = 0; $i < min(count($keys), count($values)); $i++) {
                $corail_clean = sanitize_text_field($keys[$i]);
                $wc_clean = sanitize_text_field($values[$i]);
                if (!empty($corail_clean) && !empty($wc_clean)) {
                    $sanitized['status_mapping'][$corail_clean] = $wc_clean;
                }
            }
        }

        if (empty($sanitized['status_mapping'])) {
            $sanitized['status_mapping'] = $this->get_default_status_mapping();
        }

        $new_frequency = $sanitized['export_frequency'] ?? '';
        $new_time = $sanitized['export_time'] ?? '';
        if ($new_frequency && $new_time) {
            Cron::schedule_events($new_frequency, $new_time);
        }
        return $sanitized;
    }

    /**
     * Display admin page
     *
     * @return void
     */
    public function admin_page(): void
    {
        $logs = Logger::get_recent_logs();
        ?>
        <div class="wrap connecteur-corail-admin">
            <h1><?php echo esc_html__('Connecteur Corail', 'connecteur-corail'); ?></h1>

            <div class="connecteur-corail-container">
                <!-- Panel de gauche : Configuration -->
                <div class="connecteur-corail-left-panel">
                    <div class="connecteur-corail-action-card grow">
                        <h3 class="connecteur-corail-action-header">
                            <?php echo esc_html__('Configuration', 'connecteur-corail'); ?>
                        </h3>
                        <div class="connecteur-corail-action-content">

                        <form method="post" action="options.php">
                            <?php settings_fields('connecteur_corail_settings'); ?>

                            <div class="connecteur-corail-form-section">
                                <h3><?php echo esc_html__('Paramètres d\'export', 'connecteur-corail'); ?></h3>

                                <div class="connecteur-corail-form-row">
                                    <label for="export_directory"><?php echo esc_html__('Répertoire d\'export', 'connecteur-corail'); ?></label>
                                    <?php $this->export_directory_callback(); ?>
                                </div>

                                <div class="connecteur-corail-form-row">
                                    <label for="clients_filename_template"><?php echo esc_html__('Template nom fichier clients', 'connecteur-corail'); ?></label>
                                    <?php $this->clients_filename_template_callback(); ?>
                                </div>

                                <div class="connecteur-corail-form-row">
                                    <label for="orders_header_filename_template"><?php echo esc_html__('Template nom fichier entêtes commandes', 'connecteur-corail'); ?></label>
                                    <?php $this->orders_header_filename_template_callback(); ?>
                                </div>

                                <div class="connecteur-corail-form-row">
                                    <label for="orders_lines_filename_template"><?php echo esc_html__('Template nom fichier lignes commandes', 'connecteur-corail'); ?></label>
                                    <?php $this->orders_lines_filename_template_callback(); ?>
                                </div>

                                <div class="connecteur-corail-form-row">
                                    <label for="column_separator"><?php echo esc_html__('Séparateur de champs', 'connecteur-corail'); ?></label>
                                    <?php $this->column_separator_callback(); ?>
                                </div>
                            </div>

                            <div class="connecteur-corail-form-section">
                                <h3><?php echo esc_html__('Planification', 'connecteur-corail'); ?></h3>

                                <div class="connecteur-corail-form-row">
                                    <label for="export_frequency"><?php echo esc_html__('Fréquence d\'export', 'connecteur-corail'); ?></label>
                                    <?php $this->export_frequency_callback(); ?>
                                </div>

                                <div class="connecteur-corail-form-row">
                                    <label for="export_time"><?php echo esc_html__('Heure d\'export', 'connecteur-corail'); ?></label>
                                    <?php $this->export_time_callback(); ?>
                                </div>
                            </div>

                            <div class="connecteur-corail-form-section">
                                <h3><?php echo esc_html__('États des commandes', 'connecteur-corail'); ?></h3>

                                <div class="connecteur-corail-form-row">
                                    <label for="order_statuses"><?php echo esc_html__('Sélectionnez les états à exporter', 'connecteur-corail'); ?></label>
                                    <?php $this->order_statuses_callback(); ?>
                                </div>
                            </div>

                            <div class="connecteur-corail-form-section">
                                <h3><?php echo esc_html__('Mappage des statuts', 'connecteur-corail'); ?></h3>
                                <?php $this->status_mapping_section_callback(); ?>

                                <div class="connecteur-corail-form-row">
                                    <?php $this->status_mapping_callback(); ?>
                                </div>
                            </div>

                            <div class="connecteur-corail-save-section">
                                <button type="submit" class="connecteur-corail-btn connecteur-corail-btn-primary">
                                    <?php echo esc_html__('Enregistrer les paramètres', 'connecteur-corail'); ?>
                                </button>
                            </div>
                        </form>
                        </div>
                    </div>
                </div>

                <!-- Panel de droite : Actions et logs -->
                <div class="connecteur-corail-right-panel">

                        <!-- Export manuel -->
                        <div class="connecteur-corail-action-card">
                            <h3 class="connecteur-corail-action-header">
                                <?php echo esc_html__('Export manuel', 'connecteur-corail'); ?>
                            </h3>
                            <div class="connecteur-corail-action-content">
                                <p><?php echo esc_html__('Cliquez sur le bouton ci-dessous pour lancer un export manuel des données.', 'connecteur-corail'); ?></p>
                                <button type="button" id="manual-export" class="connecteur-corail-btn connecteur-corail-btn-primary">
                                    <?php echo esc_html__('Lancer l\'export', 'connecteur-corail'); ?>
                                </button>
                                <?php $this->display_next_sync_info(); ?>
                                <div id="export-result" class="connecteur-corail-result"></div>
                            </div>
                        </div>

                        <!-- Import des statuts -->
                        <div class="connecteur-corail-action-card">
                            <h3 class="connecteur-corail-action-header">
                                <?php echo esc_html__('Import des statuts', 'connecteur-corail'); ?>
                            </h3>
                            <div class="connecteur-corail-action-content">
                                <p><?php echo esc_html__('Sélectionnez un fichier CSV pour mettre à jour les statuts des commandes.', 'connecteur-corail'); ?></p>
                                <p><small><?php echo esc_html__('Format attendu : Numéro_commande;Nouveau_statut;Notes (optionnel)', 'connecteur-corail'); ?></small></p>
                                <div class="connecteur-corail-file-input">
                                    <input type="file" id="status-file" accept=".csv" />
                                </div>
                                <button type="button" id="import-status" class="connecteur-corail-btn connecteur-corail-btn-secondary" disabled>
                                    <?php echo esc_html__('Importer les statuts', 'connecteur-corail'); ?>
                                </button>
                                <div id="import-result" class="connecteur-corail-result"></div>
                            </div>
                        </div>

                        <!-- Journal des exports -->
                        <div class="connecteur-corail-action-card grow">
                            <h3 class="connecteur-corail-action-header">
                                <?php echo esc_html__('Journal des exports', 'connecteur-corail'); ?>
                                <?php if (!empty($logs)) : ?>
                                <button type="button" id="clear-logs" class="button button-small button-link-delete" style="float: right; margin-top: -2px;">
                                    <?php echo esc_html__('Effacer les logs', 'connecteur-corail'); ?>
                                </button>
                                <?php endif; ?>
                            </h3>
                            <div class="connecteur-corail-action-content">
                                <div id="clear-logs-result" class="connecteur-corail-result"></div>
                                <?php if (!empty($logs)) : ?>
                                <div class="logs-container">
                                    <table class="wp-list-table widefat fixed striped">
                                        <thead>
                                            <tr>
                                                <th scope="col" class="manage-column"><?php echo esc_html__('Date', 'connecteur-corail'); ?></th>
                                                <th scope="col" class="manage-column"><?php echo esc_html__('Type', 'connecteur-corail'); ?></th>
                                                <th scope="col" class="manage-column"><?php echo esc_html__('Message', 'connecteur-corail'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($logs as $log) : ?>
                                                <tr class="connecteur-corail-logtype-<?php echo esc_attr($log['type']); ?>">
                                                    <td><?php echo esc_html($log['date']); ?></td>
                                                    <td><span class="log-type log-type-<?php echo esc_attr($log['type']); ?>"><?php echo esc_html($log['type']); ?></span></td>
                                                    <td><?php echo esc_html($log['message']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else : ?>
                                    <div class="connecteur-corail-no-content">
                                        <p><?php echo esc_html__('Aucun log disponible.', 'connecteur-corail'); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                </div>
            </div>
            <div class="connecteur-corail-action-card">
                <div class="connecteur-corail-action-content">
                    <div id="markdown-content"><?php echo file_get_contents(CONNECTEUR_CORAIL_PLUGIN_DIR . '/README.md'); ?></div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            document.getElementById('markdown-content').innerHTML = marked.parse(document.getElementById('markdown-content').innerHTML);
                        });
                    </script>
                </div>
            </div>
        </div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/marked/15.0.7/marked.min.js" integrity="sha512-rPuOZPx/WHMHNx2RoALKwiCDiDrCo4ekUctyTYKzBo8NGA79NcTW2gfrbcCL2RYL7RdjX2v9zR0fKyI4U4kPew==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <?php
    }

    /**
     * General section callback
     *
     * @return void
     */
    public function general_section_callback(): void
    {
        echo '<p>' . esc_html__('Configurez les paramètres d\'export pour le connecteur Corail.', 'connecteur-corail') . '</p>';
    }

    /**
     * Export directory callback
     *
     * @return void
     */
    public function export_directory_callback(): void
    {
        $settings = get_option('connecteur_corail_settings');
        $value = $settings['export_directory'] ?? '';
        echo '<input type="text" id="export_directory" name="connecteur_corail_settings[export_directory]" value="' . esc_attr($value) . '" class="regular-text" aria-describedby="export_directory_desc" />';
        echo '<p id="export_directory_desc" class="description">' . esc_html__('Chemin complet du répertoire où les fichiers CSV seront enregistrés.', 'connecteur-corail') . '</p>';
    }

    /**
     * Clients filename template callback
     *
     * @return void
     */
    public function clients_filename_template_callback(): void
    {
        $settings = get_option('connecteur_corail_settings');
        $value = $settings['clients_filename_template'] ?: '%Y%m%d%H%i_CLIENTS.csv';
        echo '<input type="text" id="clients_filename_template" name="connecteur_corail_settings[clients_filename_template]" value="' . esc_attr($value) . '" placeholder="C_%d%m%Y%H%i_c.csv" title="Ex: C_%d%m%Y%H%i_c.csv" class="regular-text" aria-describedby="clients_filename_template_desc" />';
        echo '<p id="clients_filename_template_desc" class="description">' . esc_html__('Template pour le nom du fichier clients.', 'connecteur-corail') . '</p>';
    }

    /**
     * Orders header filename template callback
     *
     * @return void
     */
    public function orders_header_filename_template_callback(): void
    {
        $settings = get_option('connecteur_corail_settings');
        $value = $settings['orders_header_filename_template'] ?: '%Y%m%d%H%i_COMMANDES_ENTETES.csv';
        echo '<input type="text" id="orders_header_filename_template" name="connecteur_corail_settings[orders_header_filename_template]" value="' . esc_attr($value) . '" placeholder="C_%d%m%Y%H%i_e.csv" title="Ex: C_%d%m%Y%H%i_e.csv" class="regular-text" aria-describedby="orders_header_filename_template_desc" />';
        echo '<p id="orders_header_filename_template_desc" class="description">' . esc_html__('Template pour le nom du fichier entêtes commandes.', 'connecteur-corail') . '</p>';
    }

    /**
     * Orders lines filename template callback
     *
     * @return void
     */
    public function orders_lines_filename_template_callback(): void
    {
        $settings = get_option('connecteur_corail_settings');
        $value = $settings['orders_lines_filename_template'] ?: '%Y%m%d%H%i_COMMANDES_LIGNES.csv';
        echo '<input type="text" id="orders_lines_filename_template" name="connecteur_corail_settings[orders_lines_filename_template]" value="' . esc_attr($value) . '" placeholder="C_%d%m%Y%H%i_l.csv" title="Ex: C_%d%m%Y%H%i_l.csv" class="regular-text" aria-describedby="orders_lines_filename_template_desc" />';
        echo '<p id="orders_lines_filename_template_desc" class="description">' . esc_html__('Template pour le nom du fichier lignes commandes.', 'connecteur-corail') . '</p>';
    }

    /**
     * Column separator callback
     *
     * @return void
     */
    public function column_separator_callback(): void
    {
        $settings = get_option('connecteur_corail_settings');
        $value = $settings['column_separator'] ?? ';';
        $options = [
            ';' => __('Point-virgule (;)', 'connecteur-corail'),
            ',' => __('Virgule (,)', 'connecteur-corail'),
            "\t" => __('Tabulation', 'connecteur-corail')
        ];

        echo '<select id="column_separator" name="connecteur_corail_settings[column_separator]" aria-describedby="column_separator_desc">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p id="column_separator_desc" class="description">' . esc_html__('Caractère utilisé pour séparer les colonnes dans les fichiers CSV.', 'connecteur-corail') . '</p>';
    }

    /**
     * Display next sync info
     *
     * @return void
     */
    private function display_next_sync_info(): void
    {
        $next_scheduled = wp_next_scheduled('connecteur_corail_export_cron');

        if ($next_scheduled) {
            $next_time = wp_date('l d/m/Y H:i', $next_scheduled);
        } else {
            $next_time = esc_html__('Synchronisation désactivée', 'connecteur-corail');
        }
        echo '<div class="connecteur-corail-next-sync">';
        echo '<p><small><strong>' . esc_html__('Prochaine synchronisation :', 'connecteur-corail') . '</strong> ' . $next_time . '</small></p>';
        echo '</div>';
    }


    /**
     * Export frequency callback
     *
     * @return void
     */
    public function export_frequency_callback(): void
    {
        $settings = get_option('connecteur_corail_settings');
        $value = $settings['export_frequency'] ?? 'daily';
        $options = [
            'disabled' => __('Désactivé', 'connecteur-corail'),
            'hourly' => __('Une fois par heure', 'connecteur-corail'),
            'daily' => __('Une fois par jour', 'connecteur-corail'),
            'weekly' => __('Une fois par semaine', 'connecteur-corail'),
        ];

        echo '<select id="export_frequency" name="connecteur_corail_settings[export_frequency]" aria-describedby="export_frequency_desc">';
        foreach ($options as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($value, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p id="export_frequency_desc" class="description">' . esc_html__('Intervalle de temps entre les exports automatiques.', 'connecteur-corail') . '</p>';
    }

    /**
     * Export time callback
     *
     * @return void
     */
    public function export_time_callback(): void
    {
        $settings = get_option('connecteur_corail_settings');
        $value = $settings['export_time'] ?? '02:00';
        echo '<input type="time" id="export_time" name="connecteur_corail_settings[export_time]" value="' . esc_attr($value) . '" aria-describedby="export_time_desc" />';
        echo '<p id="export_time_desc" class="description">' . esc_html__('Heure à laquelle l\'export automatique sera lancé.', 'connecteur-corail') . '</p>';
    }

    /**
     * Order statuses callback
     *
     * @return void
     */
    public function order_statuses_callback(): void
    {
        $settings = get_option('connecteur_corail_settings');
        $selected_statuses = $settings['order_statuses'] ?? ['wc-completed', 'wc-processing'];

        $wc_statuses = wc_get_order_statuses();

        echo '<fieldset id="order_statuses" aria-describedby="order_statuses_desc">';
        echo '<legend class="screen-reader-text">' . esc_html__('Sélectionnez les états de commandes à exporter', 'connecteur-corail') . '</legend>';
        echo '<div class="connecteur-corail-checkbox-group">';
        foreach ($wc_statuses as $status_key => $status_label) {
            $checked = in_array($status_key, $selected_statuses) ? 'checked="checked"' : '';
            echo '<label>';
            echo '<input type="checkbox" name="connecteur_corail_settings[order_statuses][]" value="' . esc_attr($status_key) . '" ' . $checked . ' />';
            echo ' ' . esc_html($status_label);
            echo '</label>';
        }
        echo '</div>';
        echo '</fieldset>';
        echo '<p id="order_statuses_desc" class="description">' . esc_html__('Sélectionnez les états de commandes à exporter.', 'connecteur-corail') . '</p>';
    }

    /**
     * Status mapping section callback
     *
     * @return void
     */
    public function status_mapping_section_callback(): void
    {
        echo '<p>' . esc_html__('Configurez le mappage entre les statuts Corail et les statuts WooCommerce pour l\'import.', 'connecteur-corail') . '</p>';
    }

    /**
     * Status mapping callback
     *
     * @return void
     */
    public function status_mapping_callback(): void
    {
        $settings = get_option('connecteur_corail_settings');
        $mapping = $settings['status_mapping'] ?? $this->get_default_status_mapping();

        $wc_statuses = wc_get_order_statuses();

        echo '<div class="connecteur-corail-status-mapping">';
        echo '<div class="connecteur-corail-mapping-header">';
        echo '<span>' . esc_html__('Statut Corail', 'connecteur-corail') . '</span>';
        echo '<span>' . esc_html__('Statut WooCommerce', 'connecteur-corail') . '</span>';
        echo '<span>' . esc_html__('Action', 'connecteur-corail') . '</span>';
        echo '</div>';

        echo '<div id="status-mapping-rows">';

        foreach ($mapping as $corail_status => $wc_status) {
            $this->render_mapping_row($corail_status, $wc_status, $wc_statuses);
        }

        echo '</div>';
	    echo '</div>';

        echo '<button type="button" id="add-mapping-row" class="button button-secondary">';
        echo esc_html__('Ajouter un mappage', 'connecteur-corail');
        echo '</button>';


        echo '<p class="description">' . esc_html__('Définissez le mappage entre les statuts Corail (à gauche) et les statuts WooCommerce correspondants (à droite).', 'connecteur-corail') . '</p>';
    }

    /**
     * Get default status mapping
     *
     * @return array
     */
    private function get_default_status_mapping(): array
    {
        return [
	        'EN_COURS' => OrderInternalStatus::PROCESSING,
	        'EXPEDIEE' => OrderStatuses::get_shipped_status(),
	        'LIVREE' => OrderStatuses::get_delivered_status(),
	        'ANNULEE' => OrderInternalStatus::CANCELLED,
	        'REMBOURSEE' => OrderInternalStatus::REFUNDED,
	        'EN_ATTENTE' => OrderInternalStatus::ON_HOLD,
        ];
    }

    /**
     * Render a single mapping row
     *
     * @param string $corail_status
     * @param string $wc_status
     * @param array $wc_statuses
     * @return void
     */
    private function render_mapping_row(string $corail_status, string $wc_status, array $wc_statuses): void
    {
        $row_id = uniqid('mapping_');

        echo '<div class="connecteur-corail-mapping-row" data-row-id="' . esc_attr($row_id) . '">';

        echo '<input type="text" ';
        echo 'name="connecteur_corail_settings[status_mapping_keys][]" ';
        echo 'value="' . esc_attr($corail_status) . '" ';
        echo 'class="corail-status-input" ';
        echo 'placeholder="' . esc_attr__('Statut Corail', 'connecteur-corail') . '" />';

        echo '<select name="connecteur_corail_settings[status_mapping_values][]" class="wc-status-select">';
        echo '<option value="">' . esc_html__('-- Sélectionner --', 'connecteur-corail') . '</option>';
        foreach ($wc_statuses as $status_key => $status_label) {
            $selected = ($wc_status === $status_key) ? 'selected="selected"' : '';
            echo '<option value="' . esc_attr($status_key) . '" ' . $selected . '>' . esc_html($status_label) . '</option>';
        }
        echo '</select>';

        echo '<button type="button" class="button button-link-delete remove-mapping-row">';
        echo esc_html__('Supprimer', 'connecteur-corail');
        echo '</button>';

        echo '</div>';
    }

    /**
     * Verify security
     *
     * @param string $nonce_action Nonce action
     * @return bool
     */
    private function verify_security(string $nonce_action): bool
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], $nonce_action)) {
            $error = new WP_Error('security_failed', __('Vérification de sécurité échouée.', 'connecteur-corail'));
            wp_send_json_error([
                'message' => $error->get_error_message(),
                'code' => $error->get_error_code()
            ]);
            return false;
        }

        if (!current_user_can('manage_woocommerce')) {
            $error = new WP_Error('insufficient_permissions', __('Permissions insuffisantes.', 'connecteur-corail'));
            wp_send_json_error([
                'message' => $error->get_error_message(),
                'code' => $error->get_error_code()
            ]);
            return false;
        }

        return true;
    }

    /**
     * Handle manual export
     *
     * @return void
     */
    public function handle_manual_export(): void
    {
        if (!$this->verify_security('export_corail_nonce')) {
            return;
        }

        try {
            $exporter = new Exporter();
            $result = $exporter->export();

            wp_send_json_success([
                'message' => sprintf(
                    __('Export terminé : %d clients et %d commandes exportés.', 'connecteur-corail'),
                    $result['clients'],
                    $result['orders']
                ),
                'files' => $result['files'] ?? []
            ]);
        } catch (Exception $e) {
            $error = new WP_Error('export_error', __('Erreur lors de l\'export : ', 'connecteur-corail') . $e->getMessage());
            wp_send_json_error([
                'message' => $error->get_error_message(),
                'code' => $error->get_error_code()
            ]);
        }
    }

    /**
     * Handle status import
     *
     * @return void
     */
    public function handle_status_import(): void
    {
        if (!$this->verify_security('import_corail_nonce')) {
            return;
        }

        if (!isset($_FILES['status_file']) || $_FILES['status_file']['error'] !== UPLOAD_ERR_OK) {
            $error = new WP_Error('upload_error', __('Erreur lors de l\'upload du fichier.', 'connecteur-corail'));
            wp_send_json_error([
                'message' => $error->get_error_message(),
                'code' => $error->get_error_code()
            ]);
        }

        try {
            $updater = new StatusUpdater();
            $updates_count = $updater->update_orders_from_file($_FILES['status_file']['tmp_name']);

            wp_send_json_success([
                'message' => sprintf(
                    __('Import terminé : %d commandes mises à jour.', 'connecteur-corail'),
                    $updates_count
                )
            ]);
        } catch (Exception $e) {
            $error = new WP_Error('import_error', __('Erreur lors de l\'import : ', 'connecteur-corail') . $e->getMessage());
            wp_send_json_error([
                'message' => $error->get_error_message(),
                'code' => $error->get_error_code()
            ]);
        }
    }

    /**
     * Handle file download
     *
     * @return void
     */
    public function handle_file_download(): void
    {
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'download_corail_file')) {
            wp_die(__('Vérification de sécurité échouée.', 'connecteur-corail'));
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permissions insuffisantes.', 'connecteur-corail'));
        }

        $filename = sanitize_file_name($_GET['file'] ?? '');
        if (empty($filename)) {
            wp_die(__('Fichier non spécifié.', 'connecteur-corail'));
        }

        $settings = get_option('connecteur_corail_settings');
        $export_dir = rtrim($settings['export_directory'], '/');
        $file_path = $export_dir . '/' . $filename;

        if (!file_exists($file_path) || !is_readable($file_path)) {
            wp_die(__('Fichier introuvable.', 'connecteur-corail'));
        }

        // Security check: ensure file is within export directory
        $real_file_path = realpath($file_path);
        $real_export_dir = realpath($export_dir);

        if (strpos($real_file_path, $real_export_dir) !== 0) {
            wp_die(__('Accès interdit.', 'connecteur-corail'));
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file_path));

        readfile($file_path);
        exit;
    }

    /**
     * Handle clear logs
     *
     * @return void
     */
    public function handle_clear_logs(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'clear_corail_logs')) {
            $error = new WP_Error('security_failed', __('Vérification de sécurité échouée.', 'connecteur-corail'));
            wp_send_json_error([
                'message' => $error->get_error_message(),
                'code' => $error->get_error_code()
            ]);
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            $error = new WP_Error('insufficient_permissions', __('Permissions insuffisantes.', 'connecteur-corail'));
            wp_send_json_error([
                'message' => $error->get_error_message(),
                'code' => $error->get_error_code()
            ]);
            return;
        }

        try {
            Logger::clear_logs();

            wp_send_json_success([
                'message' => __('Les logs ont été effacés avec succès.', 'connecteur-corail')
            ]);
        } catch (Exception $e) {
            $error = new WP_Error(
                'clear_logs_error',
                __('Erreur lors de l\'effacement des logs : ', 'connecteur-corail') . $e->getMessage()
            );
            wp_send_json_error([
                'message' => $error->get_error_message(),
                'code' => $error->get_error_code()
            ]);
        }
    }
}