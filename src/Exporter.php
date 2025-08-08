<?php

declare(strict_types=1);

namespace Answeb\ConnecteurCorail;

use DateTime;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base exporter class for handling data exports
 */
class Exporter
{
    protected array $settings;
    protected Logger $logger;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->settings = get_option('connecteur_corail_settings');
        $this->logger = new Logger();
    }

    /**
     * Main export method
     *
     * @return array Export results with counts and file information
     * @throws Exception If export fails
     */
    public function export(): array
    {
        $this->validate_settings();

        $order_exporter = new OrderExporter();
        $client_exporter = new ClientExporter();

        $orders_count = $order_exporter->export_orders();
        $exported_orders = $order_exporter->get_last_exported_orders();
        $clients_count = $client_exporter->export_from_orders($exported_orders);

        $this->logger->log('info', sprintf(
            __('Export terminé : %d clients et %d commandes exportés.', 'connecteur-corail'),
            $clients_count,
            $orders_count
        ));

        $files = [];

        // Get generated file paths
        if ($orders_count > 0) {
            $orders_files = $order_exporter->get_last_exported_files();
            if ($orders_files['header']) {
                $files[] = [
                    'path' => $orders_files['header'],
                    'name' => basename($orders_files['header']),
                    'url' => $this->get_download_url($orders_files['header'])
                ];
            }
            if ($orders_files['lines']) {
                $files[] = [
                    'path' => $orders_files['lines'],
                    'name' => basename($orders_files['lines']),
                    'url' => $this->get_download_url($orders_files['lines'])
                ];
            }
        }

        if ($clients_count > 0) {
            $clients_file = $client_exporter->get_last_exported_file();
            if ($clients_file) {
                $files[] = [
                    'path' => $clients_file,
                    'name' => basename($clients_file),
                    'url' => $this->get_download_url($clients_file)
                ];
            }
        }

        return [
            'clients' => $clients_count,
            'orders' => $orders_count,
            'files' => $files
        ];
    }

    /**
     * Validate export settings
     *
     * @return void
     * @throws Exception If settings are invalid
     */
    protected function validate_settings(): void
    {
        if (empty($this->settings['export_directory'])) {
            throw new Exception(__('Le répertoire d\'export n\'est pas configuré.', 'connecteur-corail'));
        }

        if (!is_dir($this->settings['export_directory'])) {
            throw new Exception(__('Le répertoire d\'export n\'existe pas.', 'connecteur-corail'));
        }

        if (!is_writable($this->settings['export_directory'])) {
            throw new Exception(__('Le répertoire d\'export n\'est pas accessible en écriture.', 'connecteur-corail'));
        }
    }

    /**
     * Generate filename for export
     *
     * @param string $type The export type (clients, entete_commandes, lignes_commandes)
     * @return string Generated filename
     */
    protected function generate_filename(string $type): string
    {
        $template_key = '';
        switch ($type) {
            case 'clients':
                $template_key = 'clients_filename_template';
                break;
            case 'entete_commandes':
                $template_key = 'orders_header_filename_template';
                break;
            case 'lignes_commandes':
                $template_key = 'orders_lines_filename_template';
                break;
            default:
                throw new \InvalidArgumentException('Unknown export type: ' . $type);
        }

        $template = $this->settings[$template_key] ?? '';
        if (empty($template)) {
            // Fallback to default naming
            $template = sprintf('%%Y%%m%%d%%H%%i_%s.csv', strtoupper($type));
        }

        // Replace date placeholders with actual date values
        return $this->process_filename_template($template);
    }

    /**
     * Process filename template by replacing % placeholders with date values
     *
     * @param string $template Template string with % date placeholders
     * @return string Processed filename
     */
    private function process_filename_template(string $template): string
    {
        // Use preg_replace_callback to replace only % placeholders with date values
        return preg_replace_callback('/%([YymdHhisMFljnGgAa])/', function($matches) {
            return date($matches[1]);
        }, $template);
    }

    /**
     * Get full file path
     *
     * @param string $filename The filename
     * @return string Full file path
     */
    protected function get_file_path(string $filename): string
    {
        return rtrim($this->settings['export_directory'], '/') . '/' . $filename;
    }

    /**
     * Write data to CSV file
     *
     * @param string $filename The filename
     * @param array $data The data to write
     * @param array|null $headers Optional headers
     * @return string The file path
     * @throws Exception If file cannot be created
     */
    protected function write_csv_file(string $filename, array $data, ?array $headers = null): string
    {
        $file_path = $this->get_file_path($filename);

        $handle = fopen($file_path, 'w');
        if (!$handle) {
            throw new Exception(sprintf(__('Impossible de créer le fichier %s.', 'connecteur-corail'), $filename));
        }

        $separator = $this->settings['column_separator'];

        if ($headers) {
            fputcsv($handle, $headers, $separator);
        }

        foreach ($data as $row) {
            fputcsv($handle, $row, $separator);
        }

        fclose($handle);

        return $file_path;
    }

    /**
     * Truncate string to maximum length
     *
     * @param string $string The string to truncate
     * @param int $max_length Maximum length
     * @return string Truncated string
     */
    protected function truncate_string(string $string, int $max_length): string
    {
        if (strlen($string) > $max_length) {
            return substr($string, 0, $max_length);
        }
        return $string;
    }

    /**
     * Get download URL for file
     *
     * @param string $file_path The file path
     * @return string Download URL
     */
    protected function get_download_url(string $file_path): string
    {
        $upload_dir = wp_upload_dir();
        $export_dir = $this->settings['export_directory'];

        // If export directory is within wp-content/uploads, create a direct URL
        if (strpos($export_dir, $upload_dir['basedir']) === 0) {
            $relative_path = str_replace($upload_dir['basedir'], '', $file_path);
            return $upload_dir['baseurl'] . $relative_path;
        }

        // Otherwise, create a download handler URL
        return add_query_arg([
            'action' => 'download_corail_file',
            'file' => basename($file_path),
            'nonce' => wp_create_nonce('download_corail_file')
        ], admin_url('admin-ajax.php'));
    }

    /**
     * Format date string
     *
     * @param mixed $date The date to format
     * @param string $format Date format
     * @return string Formatted date
     */
    protected function format_date(mixed $date, string $format = 'd/m/Y'): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            if ($date instanceof DateTime) {
                return $date->format($format);
            }

            $datetime = new DateTime($date);
            return $datetime->format($format);
        } catch (Exception $e) {
            return '';
        }
    }
}
