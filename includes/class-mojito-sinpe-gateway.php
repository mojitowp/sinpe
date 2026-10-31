<?php
/**
 * Manual SINPE gateway shared by classic checkout and Store API.
 *
 * @package Mojito_Sinpe
 */

namespace Mojito_Sinpe;

defined( 'ABSPATH' ) || exit;

class Mojito_Sinpe_Gateway extends \WC_Payment_Gateway {
	const ID = 'mojito-sinpe';
	public $instructions;

	public function __construct() {
		$this->id                 = self::ID;
		$this->has_fields         = true;
		$this->supports           = array( 'products' );
		$this->method_title       = __( 'SINPE Móvil', 'mojito-sinpe' );
		$this->method_description = __( 'Manual payment using SINPE Móvil. The store must confirm receipt of funds.', 'mojito-sinpe' );
		$this->init_form_fields();
		$this->init_settings();
		$this->enabled      = $this->get_option( 'enabled', 'yes' );
		$this->title        = $this->get_option( 'title', __( 'SINPE Móvil', 'mojito-sinpe' ) );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions' );
		$size               = $this->get_option( 'sinpe-logo-size', 'no-logo' );
		if ( 'no-logo' !== $size ) {
			$suffix     = in_array( $size, array( '400x220', '300x165', '200x110', '100x55', '50x28' ), true ) ? '-' . $size : '';
			$this->icon = plugins_url( 'public/img/sinpe-movil' . $suffix . '.png', MOJITO_SINPE_DIR . 'mojito-sinpe.php' );
		}
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			function () {
				$this->process_admin_options();
			}
		);
	}

	public function show_banks(): bool {
		return 'yes' === $this->get_option( 'show-banks-list-in-checkout', 'yes' );
	}

	public function ask_voucher(): bool {
		return $this->show_banks() && 'yes' === $this->get_option( 'ask-voucher-id', 'no' );
	}

	public function quote() {
		if ( ! WC()->cart ) {
			return new \WP_Error( 'sinpe_cart', __( 'SINPE instructions will appear after the cart is ready.', 'mojito-sinpe' ) );
		}
		return Mojito_Sinpe_Payment::calculate( WC()->cart->get_total( 'edit' ), get_woocommerce_currency(), $this->settings );
	}

	public function is_available() {
		return parent::is_available() && ! is_wp_error( $this->quote() );
	}

	public function is_mobile(): bool {
		return wp_is_mobile();
	}

	public function payment_fields() {
		echo wp_kses_post( wpautop( $this->get_description() ) );
		if ( $this->show_banks() ) {
			echo '<p><label for="mojito_sinpe_bank">' . esc_html__( 'Select your bank', 'mojito-sinpe' ) . '</label><select id="mojito_sinpe_bank" name="mojito_sinpe_bank" class="mojito_sinpe_bank_selector">';
			foreach ( self::get_banks() as $id => $label ) {
				echo '<option value="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</option>';
			}
			echo '</select></p>';
		}
		$quote = $this->quote();
		if ( ! is_wp_error( $quote ) && 'yes' === $this->get_option( 'show-in-checkout', 'yes' ) && 'yes' === apply_filters( 'mojito_sinpe_show_text_after_banks_list', 'yes' ) && $this->show_banks() ) {
			echo '<div class="mojito-sinpe-classic-instructions" data-message="' . esc_attr( Mojito_Sinpe_Payment::message( $quote ) ) . '"><p class="mojito-sinpe-payment-container" aria-live="polite"></p><a class="mojito-sinpe-link" hidden>' . esc_html__( 'Open SMS payment instructions', 'mojito-sinpe' ) . '</a></div>';
		}
		if ( ! is_wp_error( $quote ) ) {
			// translators: %s: amount in Costa Rican colones.
			echo '<p>' . esc_html( sprintf( __( 'Amount to send: %s CRC. The store will confirm receipt of funds.', 'mojito-sinpe' ), $quote['amount'] ) ) . '</p>';
		}
		if ( $this->ask_voucher() ) {
			if ( ! is_wp_error( $quote ) && 'yes' !== $this->get_option( 'show-in-checkout', 'yes' ) ) {
				echo '<p>' . esc_html( Mojito_Sinpe_Payment::instructions( $quote ) ) . '</p>';
			}
			echo '<p><label for="mojito_sinpe_voucher_id">' . esc_html__( 'Enter your voucher ID', 'mojito-sinpe' ) . '</label><input id="mojito_sinpe_voucher_id" name="mojito_sinpe_voucher_id" type="text" maxlength="100" required placeholder="' . esc_attr( apply_filters( 'mojito_sinpe_ask_voucher_placeholder', __( 'Enter your voucher ID here', 'mojito-sinpe' ) ) ) . '"></p>';
		}
		do_action( 'mojito_sinpe_after_fields' );
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			wc_add_notice( __( 'Not a valid order', 'mojito-sinpe' ), 'error' );
			return array( 'result' => 'failure' );
		}
		// WooCommerce validates the checkout nonce before invoking the gateway.
		$data   = array(
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates the classic/pay-order nonce before invoking process_payment.
			'mojito_sinpe_bank'       => isset( $_POST['mojito_sinpe_bank'] ) && is_string( $_POST['mojito_sinpe_bank'] ) ? sanitize_text_field( wp_unslash( $_POST['mojito_sinpe_bank'] ) ) : '',
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Same WooCommerce-validated gateway callback.
			'mojito_sinpe_voucher_id' => isset( $_POST['mojito_sinpe_voucher_id'] ) && is_string( $_POST['mojito_sinpe_voucher_id'] ) ? sanitize_text_field( wp_unslash( $_POST['mojito_sinpe_voucher_id'] ) ) : '',
		);
		$result = $this->process_sinpe_order( $order, $data );
		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
			return array( 'result' => 'failure' );
		}
		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/** Idempotent, serialized processing; metadata is persisted before status emails. */
	public function process_sinpe_order( \WC_Order $order, array $data ) {
		if ( 'yes' !== $this->enabled ) {
			return new \WP_Error( 'sinpe_disabled', __( 'SINPE is unavailable.', 'mojito-sinpe' ) );
		}
		$lock    = 'mojito_sinpe_order_' . $order->get_id();
		$created = get_option( $lock );
		if ( is_numeric( $created ) && (int) $created < time() - 300 ) {
			global $wpdb;
			// Compare and delete only the expired lease, never a replacement lock.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Atomic compare-and-delete of an expired option lease; order data always uses CRUD.
			$wpdb->delete(
				$wpdb->options,
				array(
					'option_name'  => $lock,
					'option_value' => (string) $created,
				)
			);
			wp_cache_delete( $lock, 'options' );
		}
		if ( ! add_option( $lock, time(), '', false ) ) {
			return new \WP_Error( 'sinpe_busy', __( 'This order is being processed. Please try again.', 'mojito-sinpe' ) );
		}
		try {
			// Reload after acquiring the lock, so a second request sees the first result.
			$fresh = wc_get_order( $order->get_id() );
			if ( ! $fresh instanceof \WC_Order || self::ID !== $fresh->get_payment_method() || ! $fresh->has_status( array( 'pending', 'checkout-draft', 'failed', 'on-hold' ) ) ) {
				return new \WP_Error( 'sinpe_order', __( 'This order cannot be paid using SINPE.', 'mojito-sinpe' ) );
			}
			if ( $fresh->get_meta( '_mojito_sinpe_processed', true ) ) {
				$snapshot = Mojito_Sinpe_Payment::for_order( $fresh, $this->settings );
				if ( is_wp_error( $snapshot ) ) {
					return $snapshot;
				}
				if ( ! $fresh->has_status( 'on-hold' ) ) {
					$fresh->update_status( 'on-hold' );
				}
				$order->get_data_store()->read( $order );
				return true;
			}
			$bank    = $data['mojito_sinpe_bank'] ?? '';
			$voucher = $data['mojito_sinpe_voucher_id'] ?? '';
			if ( ! is_string( $bank ) || ! is_string( $voucher ) || strlen( $bank ) > 64 || strlen( $voucher ) > 100 ) {
				return new \WP_Error( 'sinpe_data', __( 'Invalid SINPE payment details.', 'mojito-sinpe' ) );
			}
			$bank    = sanitize_text_field( $bank );
			$voucher = trim( sanitize_text_field( $voucher ) );
			if ( $this->show_banks() && '' === self::get_bank_phone_number( $bank ) ) {
				return new \WP_Error( 'sinpe_bank', __( 'Payment error: Please select your bank', 'mojito-sinpe' ) );
			}
			if ( $this->ask_voucher() && '' === $voucher ) {
				return new \WP_Error( 'sinpe_voucher', __( 'Payment error: Please enter your voucher id', 'mojito-sinpe' ) );
			}
			$snapshot = Mojito_Sinpe_Payment::calculate( $fresh->get_total(), $fresh->get_currency(), $this->settings );
			if ( is_wp_error( $snapshot ) ) {
				return $snapshot;
			}
			if ( $this->show_banks() ) {
				$fresh->update_meta_data( 'mojito_sinpe_bank', $bank );
			}
			if ( $this->ask_voucher() ) {
				$fresh->update_meta_data( '_mojito_sinpe_voucher_id', $voucher );
			}
			$fresh->update_meta_data( '_mojito_sinpe_payment', $snapshot );
			$fresh->update_meta_data( '_mojito_sinpe_processed', 'yes' );
			$fresh->save();
			if ( $this->ask_voucher() ) {
				// translators: %s: customer supplied voucher reference, not proof of funds.
				$fresh->add_order_note( sprintf( __( 'SINPE Voucher id: %s', 'mojito-sinpe' ), $voucher ) );
			}
			$fresh->update_status( 'on-hold', __( 'Awaiting SINPE payment. Receipt of funds must be confirmed manually.', 'mojito-sinpe' ) );
			$order->get_data_store()->read( $order );
			return true;
		} finally {
			delete_option( $lock );
		}
	}

	/** Server-side cart extension data, refreshed by every Store API cart response. */
	public function get_blocks_payment_data(): array {
		$quote = $this->quote();
		$valid = ! is_wp_error( $quote );
		return array(
			'available'  => $valid && 'yes' === $this->enabled,
			'amount'     => $valid ? $quote['amount'] : '',
			'recipient'  => $valid ? $quote['recipient'] : '',
			'message'    => $valid ? Mojito_Sinpe_Payment::message( $quote ) : '',
			'cart_total' => WC()->cart ? (int) round( (float) WC()->cart->get_total( 'edit' ) * 10 ** wc_get_price_decimals() ) : 0,
			'error'      => $valid ? '' : $quote->get_error_message(),
		);
	}
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'                     => array(
				'title'   => __( 'Enable/Disable', 'mojito-sinpe' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable SINPE Payment', 'mojito-sinpe' ),
				'default' => 'yes',
			),
			'title'                       => array(
				'title'       => __( 'Title', 'mojito-sinpe' ),
				'type'        => 'text',
				'description' => __( 'Pay using SINPE Móvil', 'mojito-sinpe' ),
				'default'     => __( 'SINPE Móvil Payment', 'mojito-sinpe' ),
				'desc_tip'    => true,
			),
			'number'                      => array(
				'title'   => __( 'Phone number', 'mojito-sinpe' ),
				'type'    => 'text',
				'default' => '',
			),
			'ask-voucher-id'              => array(
				'title'   => __( 'Ask voucher id in check-out page', 'mojito-sinpe' ),
				'type'    => 'checkbox',
				'label'   => __( 'Ask voucher id in check-out page', 'mojito-sinpe' ),
				'default' => 'no',
			),
			'show-in-checkout'            => array(
				'title'   => __( 'Show link in check-out page', 'mojito-sinpe' ),
				'type'    => 'checkbox',
				'label'   => __( 'Show link in check-out page', 'mojito-sinpe' ),
				'default' => 'yes',
			),
			'show-banks-list-in-checkout' => array(
				'title'   => __( 'Show banks list in check-out page', 'mojito-sinpe' ),
				'type'    => 'checkbox',
				'label'   => __( 'Show banks list in check-out page', 'mojito-sinpe' ),
				'default' => 'yes',
			),
			'show-in-thankyou-page'       => array(
				'title'   => __( 'Show link in thank you page', 'mojito-sinpe' ),
				'type'    => 'checkbox',
				'label'   => __( 'Show link in thank you page', 'mojito-sinpe' ),
				'default' => 'yes',
			),
			'show-in-email'               => array(
				'title'   => __( 'Show link in email', 'mojito-sinpe' ),
				'type'    => 'checkbox',
				'label'   => __( 'Show link in email', 'mojito-sinpe' ),
				'default' => 'yes',
			),
			'sinpe-logo-size'             => array(
				'title'   => __( 'Sinpe logo size in check-out page', 'mojito-sinpe' ),
				'type'    => 'select',
				'label'   => __( 'Sinpe logo size in check-out page', 'mojito-sinpe' ),
				'default' => 'no-logo',
				'options' => array(
					'no-logo' => __( 'No logo', 'mojito-sinpe' ),
					'500x275' => '500x275',
					'400x220' => '400x220',
					'300x165' => '300x165',
					'200x110' => '200x110',
					'100x55'  => '100x55',
					'50x28'   => '50x28',
				),
			),
			'description'                 => array(
				'title'       => __( 'Description', 'mojito-sinpe' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'mojito-sinpe' ),
				'default'     => __( 'Make your payment with your mobile. Your order will not be shipped until the funds have cleared in our account.', 'mojito-sinpe' ),
				'desc_tip'    => true,
			),
			'instructions'                => array(
				'title'       => __( 'Instructions', 'mojito-sinpe' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page and emails.', 'mojito-sinpe' ),
				'default'     => __( 'Please send us the Sinpe Móvil voucher. Use your order ID as a payment reference.', 'mojito-sinpe' ),
				'desc_tip'    => true,
			),
			/** Exchange rates */
			'exchange-rate-enable'        => array(
				'title'   => __( 'Enable/Disable exchange rate', 'mojito-sinpe' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable exchange rates', 'mojito-sinpe' ),
				'default' => 'yes',
			),
			'exchange-rate-origin'        => array(
				'title'   => __( 'Exchange rate origin', 'mojito-sinpe' ),
				'type'    => 'select',
				'label'   => __( 'Pick the origin of the dolar price', 'mojito-sinpe' ),
				'default' => 'hacienda',
				'options' => array(
					'hacienda' => __( 'Ministerio de Hacienda', 'mojito-sinpe' ),
					'custom'   => __( 'Custom', 'mojito-sinpe' ),
				),
			),
			'exchange-rate-custom'        => array(
				'title' => __( 'Custom exchange rate', 'mojito-sinpe' ),
				'type'  => 'text',
				'label' => __( 'How many colones is a dollar?', 'mojito-sinpe' ),
			),
			'enable-mojito-sinpe-debug'   => array(
				'title'   => __( 'Enable/Disable debug', 'mojito-sinpe' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable debug', 'mojito-sinpe' ),
				'default' => 'no',
			),
		);
	}

	public static function get_banks() {
		$sinpe_banks = array(
			'none'            => __( 'Select your bank', 'mojito-sinpe' ),
			'bn'              => 'Banco Nacional de Costa Rica',
			'bcr'             => 'Banco de Costa Rica',
			'bac'             => 'Banco BAC San José',
			'bct'             => 'Banco BCT',
			'caja-de-ande'    => 'Caja de Ande',
			'coopealianza'    => 'Coopealianza',
			'coopecaja'       => 'Coopecaja',
			'coopelecheros'   => 'Coopelecheros',
			'coocique'        => 'Coocique',
			'credecoop'       => 'Credecoop',
			'davivienda'      => 'Banco Davivienda',
			'lafise'          => 'Banco Lafise',
			'mucap'           => 'MUCAP',
			'mutual-alajuela' => 'Grupo Mutual Alajuela - La Vivienda',
			'promerica'       => 'Banco Promerica',
		);

		return apply_filters( 'mojito_sinpe_banks_numbers', $sinpe_banks );
	}

	/**
	 * Get the SINPE phone number per bank.
	 *
	 * @return array<string,string>
	 */
	public static function get_bank_phone_numbers() {
		$bank_phone_numbers = array(
			'bn'              => '2627',
			'bcr'             => '4066',
			'bac'             => '70701222',
			'bct'             => '60400300',
			'caja-de-ande'    => '62229532',
			'coopealianza'    => '62229523',
			'coopecaja'       => '62229526',
			'coopelecheros'   => '60405957',
			'coocique'        => '46002905',
			'credecoop'       => '71984256',
			'davivienda'      => '70707474',
			'lafise'          => '9091',
			'mucap'           => '62229525',
			'mutual-alajuela' => '60575079',
			'promerica'       => '62232450',
		);

		return apply_filters( 'mojito_sinpe_bank_phone_numbers', $bank_phone_numbers );
	}

	/**
	 * Get a bank SINPE phone number.
	 *
	 * @param string $bank Bank key.
	 * @return string
	 */
	public static function get_bank_phone_number( $bank ) {
		$bank_phone_numbers = self::get_bank_phone_numbers();

		return isset( $bank_phone_numbers[ $bank ] ) && is_scalar( $bank_phone_numbers[ $bank ] ) && preg_match( '/^[0-9]{4,8}$/D', (string) $bank_phone_numbers[ $bank ] ) ? (string) $bank_phone_numbers[ $bank ] : '';
	}
}
