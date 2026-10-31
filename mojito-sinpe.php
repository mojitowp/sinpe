<?php
/**
 * Plugin Name: Mojito Sinpe
 * Plugin URI: https://mojitowp.com/
 * Description: Manual SINPE Móvil payments for WooCommerce, including block checkout.
 * Version: 1.3.0
 * Requires at least: 6.2
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * WC requires at least: 8.2.0
 * WC tested up to: 11.2.1
 * Author: Mojito Team
 * Author URI: https://mojitowp.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mojito-sinpe
 * Domain Path: /languages
 *
 * @package Mojito_Sinpe
 */

namespace Mojito_Sinpe;

defined( 'ABSPATH' ) || exit;

define( 'MOJITO_SINPE_VERSION', '1.3.0' );
define( 'MOJITO_SINPE_DIR', plugin_dir_path( __FILE__ ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( version_compare( PHP_VERSION, '8.2', '<' ) || ! class_exists( '\WC_Payment_Gateway' ) || ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, '8.2', '<' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Mojito Sinpe requires PHP 8.2 or newer and WooCommerce 8.2 or newer.', 'mojito-sinpe' ) . '</p></div>';
				}
			);
			return;
		}
		require_once MOJITO_SINPE_DIR . 'includes/class-mojito-sinpe-payment.php';
		require_once MOJITO_SINPE_DIR . 'includes/class-mojito-sinpe-gateway.php';
		require_once MOJITO_SINPE_DIR . 'includes/class-mojito-sinpe.php';
		( new Mojito_Sinpe() )->run();
	},
	20
);
