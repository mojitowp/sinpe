<?php
/**
 * WooCommerce Blocks integration.
 *
 * @link       https://mojitowp.com/
 * @since      1.1.1
 *
 * @package    Mojito_Sinpe
 * @subpackage Mojito_Sinpe/public
 * @author     Mojito Team <support@mojitowp.com>
 */

namespace Mojito_Sinpe;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Mojito Sinpe Gateway Block.
 */
class Mojito_Sinpe_Gateway_Block extends AbstractPaymentMethodType {

	/**
	 * Gateway instance.
	 *
	 * @var Mojito_Sinpe_Gateway|null
	 */
	private $gateway = null;

	/**
	 * Payment method name.
	 *
	 * @var string
	 */
	protected $name = Mojito_Sinpe_Gateway::ID;

	/**
	 * Initialize the payment method.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_mojito_sinpe_gateway_settings', array() );
		$this->gateway  = new Mojito_Sinpe_Gateway();

		add_action( 'woocommerce_rest_checkout_process_payment_with_context', array( $this, 'process_payment_with_context' ), 10, 2 );
	}

	/**
	 * Check if the payment method is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->gateway instanceof Mojito_Sinpe_Gateway && $this->gateway->is_available();
	}

	/**
	 * Register block checkout scripts.
	 *
	 * @return array<int,string>
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'mojito-sinpe-checkout-blocks',
			plugin_dir_url( __FILE__ ) . 'checkout.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			),
			defined( 'MOJITO_SINPE_VERSION' ) ? MOJITO_SINPE_VERSION : null,
			true
		);

		return array( 'mojito-sinpe-checkout-blocks' );
	}

	/**
	 * Load the same script in the block editor.
	 *
	 * @return array<int,string>
	 */
	public function get_payment_method_script_handles_for_admin() {
		return $this->get_payment_method_script_handles();
	}

	/**
	 * Data made available to the checkout block script.
	 *
	 * @return array<string,mixed>
	 */
	public function get_payment_method_data() {
		if ( ! $this->gateway instanceof Mojito_Sinpe_Gateway ) {
			$this->gateway = new Mojito_Sinpe_Gateway();
		}

		return $this->gateway->get_blocks_payment_data();
	}

	/**
	 * Process block checkout payment data through the Store API hook.
	 *
	 * @param object $context Payment context.
	 * @param object $result Payment result.
	 * @throws \Exception When SINPE validation fails.
	 * @return void
	 */
	public function process_payment_with_context( $context, $result ) {
		if ( ! isset( $context->payment_method ) || Mojito_Sinpe_Gateway::ID !== $context->payment_method ) {
			return;
		}

		if ( ! $this->gateway instanceof Mojito_Sinpe_Gateway ) {
			$this->gateway = new Mojito_Sinpe_Gateway();
		}

		$payment_data = isset( $context->payment_data ) && is_array( $context->payment_data ) ? $context->payment_data : array();
		// Store API owns cart emptying and redirects; this shared path persists SINPE meta, note, and status.
		$processed = $this->gateway->process_sinpe_order( $context->order, $payment_data );

		if ( is_wp_error( $processed ) ) {
			throw new \Exception( $processed->get_error_message() );
		}

		if ( method_exists( $result, 'set_status' ) ) {
			$result->set_status( 'success' );
		}

		if ( method_exists( $result, 'set_redirect_url' ) ) {
			$result->set_redirect_url( $this->gateway->get_return_url( $context->order ) );
		}
	}
}
