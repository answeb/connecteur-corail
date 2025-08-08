<?php

declare( strict_types=1 );

namespace Answeb\ConnecteurCorail;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom order statuses handler for Connecteur Corail
 */
class OrderStatuses {
	private static ?OrderStatuses $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return OrderStatuses
	 */
	public static function get_instance(): OrderStatuses {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'init', [ $this, 'register_custom_order_statuses' ] );
		add_filter( 'wc_order_statuses', [ $this, 'add_custom_order_statuses_to_list' ] );
		add_filter( 'woocommerce_reports_order_statuses', [ $this, 'include_custom_order_statuses_in_reports' ] );
	}

	/**
	 * Register custom order statuses
	 *
	 * @return void
	 */
	public function register_custom_order_statuses(): void {
		register_post_status( 'wc-shipped', [
			'label'                     => _x( 'Expédiée', 'Order status', 'connecteur-corail' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop(
				'Expédiée <span class="count">(%s)</span>',
				'Expédiées <span class="count">(%s)</span>',
				'connecteur-corail'
			),
		] );
		register_post_status( 'wc-delivered', [
			'label'                     => _x( 'Livrée', 'Order status', 'connecteur-corail' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop(
				'Livrée <span class="count">(%s)</span>',
				'Livrées <span class="count">(%s)</span>',
				'connecteur-corail'
			),
		] );
	}

	/**
	 * Add custom order statuses to WooCommerce status list
	 *
	 * @param   array  $order_statuses  Current order statuses
	 *
	 * @return array Modified order statuses
	 */
	public function add_custom_order_statuses_to_list( array $order_statuses ): array {
		$order_statuses[ 'wc-shipped' ]   = _x( 'Expédiée', 'Order status', 'connecteur-corail' );
		$order_statuses[ 'wc-delivered' ] = _x( 'Livrée', 'Order status', 'connecteur-corail' );

		return $order_statuses;
	}

	/**
	 * Include custom order statuses in reports
	 *
	 * @param   array  $statuses  Current statuses for reports
	 *
	 * @return array Modified statuses
	 */
	public function include_custom_order_statuses_in_reports( $statuses ) {
		if ( is_array( $statuses ) ) {
			$statuses[] = 'shipped';
			$statuses[] = 'delivered';
		}

		return $statuses;
	}

	/**
	 * Get the shipped status key
	 *
	 * @return string
	 */
	public static function get_shipped_status(): string {
		return 'wc-shipped';
	}

	/**
	 * Get the delivered status key
	 *
	 * @return string
	 */
	public static function get_delivered_status(): string {
		return 'wc-delivered';
	}
}