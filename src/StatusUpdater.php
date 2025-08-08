<?php

declare(strict_types=1);

namespace Answeb\ConnecteurCorail;

use Automattic\WooCommerce\Enums\OrderInternalStatus;
use Exception;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Status updater class for updating order statuses from CSV files
 */
class StatusUpdater
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
     * Update orders from file
     *
     * @param string $file_path Path to CSV file
     * @return int Number of updated orders
     * @throws Exception If file operations fail
     */
    public function update_orders_from_file(string $file_path): int
    {
        if (!file_exists($file_path)) {
            throw new Exception(__('Le fichier spécifié n\'existe pas.', 'connecteur-corail'));
        }

        if (!is_readable($file_path)) {
            throw new Exception(__('Le fichier spécifié n\'est pas lisible.', 'connecteur-corail'));
        }

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            throw new Exception(__('Impossible d\'ouvrir le fichier.', 'connecteur-corail'));
        }

        $separator = $this->settings['column_separator'] ?? ';';
        $updates_count = 0;
        $line_number = 0;

        while (($data = fgetcsv($handle, 1000, $separator)) !== false) {
            $line_number++;

            if (count($data) < 2) {
                $this->logger->log('warning', sprintf(
                    __('Ligne %d ignorée : format invalide.', 'connecteur-corail'),
                    $line_number
                ));
                continue;
            }

            $order_number = trim($data[0]);
            $new_status = trim($data[1]);
            $notes = isset($data[2]) ? trim($data[2]) : '';

            if (empty($order_number) || empty($new_status)) {
                $this->logger->log('warning', sprintf(
                    __('Ligne %d ignorée : numéro de commande ou statut manquant.', 'connecteur-corail'),
                    $line_number
                ));
                continue;
            }

            $order = $this->find_order_by_number($order_number);
            if (!$order) {
                $this->logger->log('warning', sprintf(
                    __('Commande %s introuvable.', 'connecteur-corail'),
                    $order_number
                ));
                continue;
            }

            $wc_status = $this->map_corail_status_to_wc($new_status);
            if (!$wc_status) {
                $this->logger->log('warning', sprintf(
                    __('Statut %s non reconnu pour la commande %s.', 'connecteur-corail'),
                    $new_status,
                    $order_number
                ));
                continue;
            }

			if ($notes) {
				$order->add_order_note($notes);
			}

            if ($order->get_status() !== $wc_status) {
                $order->update_status($wc_status);

                $updates_count++;

                $this->logger->log('info', sprintf(
                    __('Commande %s : statut mis à jour vers %s.', 'connecteur-corail'),
                    $order_number,
                    $new_status
                ));
            }
        }

        fclose($handle);

        $this->logger->log('success', sprintf(
            __('Import terminé : %d commandes mises à jour.', 'connecteur-corail'),
            $updates_count
        ));

        return $updates_count;
    }

    /**
     * Find order by number
     *
     * @param string $order_number Order number to search for
     * @return WC_Order|null Found order or null
     */
    protected function find_order_by_number(string $order_number): ?WC_Order
    {
        if (is_numeric($order_number)) {
            $order = wc_get_order((int) $order_number);
            if ($order && $order->get_order_number() == $order_number) {
                return $order;
            }
        }

        $orders = wc_get_orders([
            'orderby' => 'ID',
            'order' => 'DESC',
            'search' => $order_number,
            'limit' => 1
        ]);

        return $orders[0] ?? null;
    }

    /**
     * Map Corail status to WooCommerce status
     *
     * @param string $corail_status Corail status
     * @return string|null WooCommerce status or null if not found
     */
    protected function map_corail_status_to_wc(string $corail_status): ?string
    {
        $status_mapping = $this->settings['status_mapping'] ?? [];

        $corail_status_upper = strtoupper($corail_status);

        if (isset($status_mapping[$corail_status_upper])) {
            return $status_mapping[$corail_status_upper];
        }

        foreach ($status_mapping as $key => $value) {
            if (strtoupper($key) === $corail_status_upper) {
                return $value;
            }
        }

        return null;
    }

}
