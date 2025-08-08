<?php

declare(strict_types=1);

namespace Answeb\ConnecteurCorail;

use Exception;
use WC_Order;
use WC_Order_Item;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Order exporter class for handling WooCommerce order exports
 */
class OrderExporter extends Exporter
{
    protected array $last_exported_orders = [];
    protected ?string $last_exported_header_file = null;
    protected ?string $last_exported_lines_file = null;

    /**
     * Export orders to CSV (NEW FORMAT - 2 separate files)
     *
     * @return int Number of exported orders
     * @throws Exception If export fails
     */
    public function export_orders(): int
    {
        try {
            $orders = $this->get_orders_to_export();

            if (empty($orders)) {
                $this->logger->log('info', __('Aucune commande à exporter.', 'connecteur-corail'));
                return 0;
            }

            $header_data = [];
            $lines_data = [];

            foreach ($orders as $order) {
                $header_data[] = $this->format_order_header_new($order);

                foreach ($order->get_items() as $item) {
                    $lines_data[] = $this->format_order_line_new($item, $order);
                }
            }

            $header_filename = $this->generate_filename('entete_commandes');
            $lines_filename = $this->generate_filename('lignes_commandes');

            $header_headers = [
                'Date Commande',
                'Num Commande',
                'Identifiant Client Facturé',
                'Montant Frais Port',
                'Prenom Livraison',
                'Nom Livraison',
                'Adresse1 Livraison',
                'Adresse2 Livraison',
                'CP Livraison',
                'Ville Livraison',
                'Prenom Facturation',
                'Nom Facturation',
                'Adresse1 Facturation',
                'Adresse2 Facturation',
                'CP Facturation',
                'Ville Facturation',
                'Designation Remise',
                'Montant Remise'
            ];

            $lines_headers = [
                'Num Commande',
                'Identifiant Produit',
                'Qte',
                'PU',
                'Pourcentage Remise'
            ];

            $this->last_exported_header_file = $this->write_csv_file($header_filename, $header_data, $header_headers);
            $this->last_exported_lines_file = $this->write_csv_file($lines_filename, $lines_data, $lines_headers);

            $this->mark_orders_as_exported($orders);
            $this->last_exported_orders = $orders;

            $this->logger->log('success', sprintf(
                __('%d commandes exportées dans les fichiers %s et %s.', 'connecteur-corail'),
                count($orders),
                $header_filename,
                $lines_filename
            ));

            return count($orders);
        } catch (Exception $e) {
            $this->logger->log('error', sprintf(
                __('Erreur lors de l\'export des commandes : %s', 'connecteur-corail'),
                $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Get orders that need to be exported
     *
     * @return array Array of WC_Order objects
     */
    protected function get_orders_to_export(): array
    {
        $order_statuses = $this->settings['order_statuses'];

        if (empty($order_statuses)) {
            return [];
        }

        $args = [
            'status' => $order_statuses,
            'limit' => -1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_connecteur_corail_exported',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => '_connecteur_corail_exported',
                    'value' => '1',
                    'compare' => '!='
                ]
            ]
        ];

        return wc_get_orders($args);
    }

    /**
     * Format order data for CSV export
     *
     * @param WC_Order $order The order object
     * @return array Formatted order data
     */
    protected function format_order_data(WC_Order $order): array
    {
        $data = [];

        $header_data = $this->format_order_header($order);
        $data[] = $header_data;

        foreach ($order->get_items() as $item) {
            $line_data = $this->format_order_line($item, $order);
            $data[] = $line_data;
        }

        return $data;
    }

    /**
     * Format order header data (NEW FORMAT)
     *
     * @param WC_Order $order The order object
     * @return array Header data
     */
    protected function format_order_header_new(WC_Order $order): array
    {
        $order_date = $order->get_date_created() ? $order->get_date_created()->format('Y-m-d') : '';

        return [
            $order_date,
            $order->get_order_number(),
            $order->get_customer_id(),
            $order->get_shipping_total(),
            $this->truncate_string($order->get_shipping_first_name(), 30),
            $this->truncate_string($order->get_shipping_last_name(), 30),
            $this->truncate_string($order->get_shipping_address_1(), 60),
            $this->truncate_string($order->get_shipping_address_2(), 60),
            $this->truncate_string($order->get_shipping_postcode(), 10),
            $this->truncate_string($order->get_shipping_city(), 60),
            $this->truncate_string($order->get_billing_first_name(), 30),
            $this->truncate_string($order->get_billing_last_name(), 30),
            $this->truncate_string($order->get_billing_address_1(), 60),
            $this->truncate_string($order->get_billing_address_2(), 60),
            $this->truncate_string($order->get_billing_postcode(), 10),
            $this->truncate_string($order->get_billing_city(), 60),
            $this->get_discount_description($order),
            $this->get_discount_amount($order)
        ];
    }

    /**
     * Format order line data (NEW FORMAT)
     *
     * @param WC_Order_Item $item The order item
     * @param WC_Order $order The order object
     * @return array Line data
     */
    protected function format_order_line_new(WC_Order_Item $item, WC_Order $order): array
    {
        $product = $item->get_product();
        $product_sku = $product ? ($product->get_sku() ?: (string) $product->get_id()) : '';

        return [
            $order->get_order_number(),
            $this->truncate_string($product_sku, 20),
            $item->get_quantity(),
            $this->calculate_unit_price($item, $order),
            0
        ];
    }

    /**
     * Get discount description from order
     *
     * @param WC_Order $order The order object
     * @return string Discount description
     */
    protected function get_discount_description(WC_Order $order): string
    {
        $coupons = $order->get_coupon_codes();
        return !empty($coupons) ? implode(', ', $coupons) : '';
    }

    /**
     * Get total discount amount from order
     *
     * @param WC_Order $order The order object
     * @return float Discount amount
     */
    protected function get_discount_amount(WC_Order $order): float
    {
        return (float) $order->get_total_discount();
    }

    /**
     * Calculate unit price for an item
     *
     * @param WC_Order_Item $item The order item
     * @param WC_Order $order The order object
     * @return float Unit price
     */
    protected function calculate_unit_price(WC_Order_Item $item, WC_Order $order): float
    {
        $total = (float) $item->get_total();
        $quantity = (int) $item->get_quantity();

        if ($quantity > 0) {
            $unit_price = $total / $quantity;
        } else {
            $unit_price = 0;
        }

        if ($this->is_tax_inclusive($order)) {
            $tax = (float) $item->get_total_tax();
            $unit_price += ($tax / $quantity);
        }

        return round($unit_price, 2);
    }

    /**
     * Check if tax should be included in price
     *
     * @param WC_Order $order The order object
     * @return bool True if tax inclusive
     */
    protected function is_tax_inclusive(WC_Order $order): bool
    {
        $customer_id = $order->get_customer_id();

        if (!$customer_id) {
            return true;
        }

        if (get_user_meta($customer_id, '_is_vat_exempt', true)) {
            return false;
        }

        return empty($order->get_billing_company());
    }

    /**
     * Mark orders as exported
     *
     * @param array $orders Array of WC_Order objects
     * @return void
     */
    protected function mark_orders_as_exported(array $orders): void
    {
        foreach ($orders as $order) {
            $order->update_meta_data('_connecteur_corail_exported', '1');
            $order->update_meta_data('_connecteur_corail_export_date', current_time('mysql'));

            $order->add_order_note(
                __('Commande exportée vers Corail.', 'connecteur-corail'),
                false,
                true
            );

            $order->save();
        }
    }

    /**
     * Get last exported orders
     *
     * @return array Array of WC_Order objects
     */
    public function get_last_exported_orders(): array
    {
        return $this->last_exported_orders;
    }

    /**
     * Get both exported files paths
     *
     * @return array Array of file paths
     */
    public function get_last_exported_files(): array
    {
        return [
            'header' => $this->last_exported_header_file,
            'lines' => $this->last_exported_lines_file
        ];
    }
}
