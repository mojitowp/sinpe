<?php
/**
 * WooCommerce Blocks payment method integration.
 *
 * @package Mojito_Sinpe
 */

namespace Mojito_Sinpe;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\StoreApi\Payments\PaymentContext;
use Automattic\WooCommerce\StoreApi\Payments\PaymentResult;

defined( 'ABSPATH' ) || exit;

class Mojito_Sinpe_Gateway_Block extends AbstractPaymentMethodType {
	protected $name                        = Mojito_Sinpe_Gateway::ID;
	private ?Mojito_Sinpe_Gateway $gateway = null;

	public function initialize() {
		$this->gateway  = Mojito_Sinpe::gateway();
		$this->settings = $this->gateway ? $this->gateway->settings : array();
		add_action( 'woocommerce_rest_checkout_process_payment_with_context', array( $this, 'process' ), 10, 2 );
	}

	public function is_active() {
		// Include the client integration even when the cart changes availability later.
		return $this->gateway && 'yes' === $this->gateway->enabled;
	}

	public function get_payment_method_script_handles() {
		wp_register_script( 'mojito-sinpe-blocks', plugins_url( 'includes/checkout.js', MOJITO_SINPE_DIR . 'mojito-sinpe.php' ), array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n', 'wc-blocks-checkout' ), MOJITO_SINPE_VERSION, true );
		wp_set_script_translations( 'mojito-sinpe-blocks', 'mojito-sinpe', MOJITO_SINPE_DIR . 'languages' );
		return array( 'mojito-sinpe-blocks' );
	}

	public function get_payment_method_script_handles_for_admin() {
		return $this->get_payment_method_script_handles();
	}

	public function get_payment_method_data() {
		$gateway = $this->gateway;
		if ( ! $gateway ) {
			return array();
		}
		$banks = array();
		foreach ( Mojito_Sinpe_Gateway::get_banks() as $id => $label ) {
			$banks[] = array(
				'id'     => $id,
				'label'  => $label,
				'number' => Mojito_Sinpe_Gateway::get_bank_phone_number( $id ),
			);
		}
		return array(
			'title'              => $gateway->title,
			'description'        => wp_strip_all_tags( $gateway->description ),
			'supports'           => array( 'products' ),
			'banks'              => $banks,
			'showBanks'          => $gateway->show_banks(),
			'askVoucher'         => $gateway->ask_voucher(),
			'showLink'           => 'yes' === $gateway->get_option( 'show-in-checkout', 'yes' ) && 'yes' === apply_filters( 'mojito_sinpe_show_text_after_banks_list', 'yes' ),
			'voucherPlaceholder' => apply_filters( 'mojito_sinpe_ask_voucher_placeholder', __( 'Enter your voucher ID here', 'mojito-sinpe' ) ),
		);
	}

	public function process( PaymentContext $context, PaymentResult $result ): void {
		if ( Mojito_Sinpe_Gateway::ID !== $context->__get( 'payment_method' ) || ! $this->gateway ) {
			return;
		}
		$order = $context->__get( 'order' );
		if ( ! $order instanceof \WC_Order ) {
			throw new \Exception( esc_html__( 'Not a valid order', 'mojito-sinpe' ) );
		}
		$processed = $this->gateway->process_sinpe_order( $order, is_array( $context->__get( 'payment_data' ) ) ? $context->__get( 'payment_data' ) : array() );
		if ( is_wp_error( $processed ) ) {
			throw new \Exception( esc_html( $processed->get_error_message() ) );
		}
		$result->set_status( 'success' );
		$result->set_redirect_url( $this->gateway->get_return_url( $order ) );
	}
}
