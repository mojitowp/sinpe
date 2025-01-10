<?php
/**
 * Mojito Sinpe
 *
 * @package           Mojito_Sinpe
 * @author            Mojito Team
 * @link              https://mojitowp.com/
 *
 * @wordpress-plugin
 * Plugin Name: Mojito Sinpe
 * Plugin URI: https://mojitowp.com/
 * Description: Sinpe Móvil as Woocommerce gateway
 * Version: 1.2.0
 * Requires at least: 5.2
 * Requires PHP: 8.1
 * Author: Mojito Team
 * Author URI: https://mojitowp.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mojito-sinpe
 * Domain Path: /languages
 * WC requires at least: 8.2.0
 * WC tested up to: 9.5.2
 */

namespace Mojito_Sinpe;

/**
 * If this file is called directly, abort.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Debuggin function
 */
if ( ! function_exists( 'mojito_sinpe_debug' ) ) {
	/**
	 * Show a message
	 *
	 * @param mixed $message Text to log.
	 * @return void
	 */
	function mojito_sinpe_debug( $message ) {
		
		error_log( print_r( $message, 1 ) );

		if ( class_exists( 'WC_Logger' ) ) {
			$logger = new \WC_Logger();
			$logger->log( 'debug', print_r( $message, true ), [] );
		}
	}
}

/**
 * Version.
 */
define( 'MOJITO_SINPE_VERSION', '1.2.0' );

/**
 * Define plugin constants.
 */
define( 'MOJITO_SINPE_DIR', plugin_dir_path( __FILE__ ) );


/**
 * Plugin activation
 */

register_activation_hook(
	__FILE__,
	function () {
		require_once MOJITO_SINPE_DIR . 'includes/class-mojito-sinpe-activator.php';
		Mojito_Sinpe_Activator::activate();
	}
);

/**
 * Plugin deactivation.
 */
register_deactivation_hook(
	__FILE__,
	function () {
		require_once MOJITO_SINPE_DIR . 'includes/class-mojito-sinpe-deactivator.php';
		Mojito_Sinpe_Deactivator::deactivate();
	}
);

/**
 * The core plugin class that is used to define internationalization and public-facing site hooks.
 */

require_once MOJITO_SINPE_DIR . 'vendor/autoload.php';
require MOJITO_SINPE_DIR . 'includes/class-mojito-sinpe.php';

/**
 * Begins execution.
 *
 * @since    1.0.0
 */
function mojito_sinpe_run() {
	global $mojito_sinpe;
	if ( ! isset( $mojito_sinpe ) ) {
		$mojito_sinpe = new Mojito_Sinpe();
		$mojito_sinpe->run();
	}
}

/**
 * Is multisite?
 */
$load = true;
if ( function_exists( 'is_multisite' ) && is_multisite() ) {

	if ( function_exists( 'is_plugin_active' ) && ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
		$load = false;
		require_once MOJITO_SINPE_DIR . 'admin/partials/mojito-sinpe-require-plugins-woocommerce.php';
	}
} else {
	if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
		$load = false;
		require_once MOJITO_SINPE_DIR . 'admin/partials/mojito-sinpe-require-plugins-woocommerce.php';
	}
}
if ( $load ) {

	/**
	 * Compatibility with WooCommerce declarations
	 */
	add_action('before_woocommerce_init', function(){
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			// Declare compatibility for WooCommerce HPOS (High-Performance Order Storage)
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			
			// Declare compatibility for 'cart_checkout_blocks'
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
		}
	});

	mojito_sinpe_run();
}
