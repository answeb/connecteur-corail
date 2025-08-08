<?php

declare(strict_types=1);

namespace Answeb\ConnecteurCorail;

use Exception;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Client exporter class for handling WooCommerce customer exports
 */
class ClientExporter extends Exporter
{
    protected ?string $last_exported_file = null;

    /**
     * Export clients from orders
     *
     * @param array $orders Array of WC_Order objects
     * @return int Number of exported clients
     * @throws Exception If export fails
     */
    public function export_from_orders(array $orders): int
    {
        if (empty($orders)) {
            $this->logger->log('info', __('Aucune commande fournie pour l\'export des clients.', 'connecteur-corail'));
            return 0;
        }

        try {
            $clients_data = [];
            $processed_customers = [];

            foreach ($orders as $order) {
                $customer_id = $order->get_customer_id();
                if ($customer_id && !in_array($customer_id, $processed_customers)) {
                    $processed_customers[] = $customer_id;
                    $customer = new \WC_Customer($customer_id);
                    $clients_data[] = $this->format_client_data($customer, $order);
                }
            }

            if (empty($clients_data)) {
                $this->logger->log('info', __('Aucun client à exporter depuis les commandes.', 'connecteur-corail'));
                return 0;
            }

            $filename = $this->generate_filename('clients');
            $headers = [
                'Identifiant client',
                'Prenom',
                'Nom', 
                'Raison Sociale',
                'Adresse1',
                'CP',
                'Adresse2',
                'Ville',
                'Pays',
                'Telephone1',
                'Email'
            ];
            $this->last_exported_file = $this->write_csv_file($filename, $clients_data, $headers);

            $this->mark_clients_as_exported_from_orders($orders);

            $this->logger->log('success', sprintf(
                __('%d clients exportés dans le fichier %s.', 'connecteur-corail'),
                count($clients_data),
                $filename
            ));

            return count($clients_data);
        } catch (Exception $e) {
            $this->logger->log('error', sprintf(
                __('Erreur lors de l\'export des clients : %s', 'connecteur-corail'),
                $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Format client data for CSV export (NEW FORMAT - using WC_Customer)
     *
     * @param \WC_Customer $customer The customer object
     * @return array Formatted client data
     */
    protected function format_client_data(\WC_Customer $customer): array
    {
        return [
            $this->truncate_string((string) $customer->get_id(), 20),
            $this->truncate_string($customer->get_billing_first_name(), 30),
            $this->truncate_string($customer->get_billing_last_name(), 30),
            $this->truncate_string($customer->get_billing_company(), 60),
            $this->truncate_string($customer->get_billing_address_1(), 60),
            $this->truncate_string($customer->get_billing_postcode(), 10),
            $this->truncate_string($customer->get_billing_address_2(), 60),
            $this->truncate_string($customer->get_billing_city(), 60),
            $this->truncate_string($this->get_country_name($customer->get_billing_country()), 60),
            $this->truncate_string($customer->get_billing_phone(), 20),
            $this->truncate_string($customer->get_billing_email(), 100)
        ];
    }

    /**
     * Get country name from country code
     *
     * @param string $country_code The country code
     * @return string Country name
     */
    protected function get_country_name(string $country_code): string
    {
        if (empty($country_code)) {
            return '';
        }

        $countries = WC()->countries->get_countries();
        return $countries[$country_code] ?? $country_code;
    }

    /**
     * Mark clients as exported from orders
     *
     * @param array $orders Array of WC_Order objects
     * @return void
     */
    protected function mark_clients_as_exported_from_orders(array $orders): void
    {
        $processed_customers = [];

        foreach ($orders as $order) {
            $customer_id = $order->get_customer_id();
            if ($customer_id && !in_array($customer_id, $processed_customers)) {
                $processed_customers[] = $customer_id;
                update_user_meta($customer_id, '_connecteur_corail_exported', '1');
                update_user_meta($customer_id, '_connecteur_corail_export_date', current_time('mysql'));
            }
        }
    }

    /**
     * Get last exported file path
     *
     * @return string|null File path or null if no file
     */
    public function get_last_exported_file(): ?string
    {
        return $this->last_exported_file;
    }
}
