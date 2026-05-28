<?php
/**
 * WooCommerce compatibility of the plugin.
 *
 * @link       https://mojitowp.com/
 * @since      1.0.0
 *
 * @package    Mojito_Sinpe
 * @subpackage Mojito_Sinpe/public
 * @author     Mojito Team <support@mojitowp.com>
 */

namespace Mojito_Sinpe;

use Detection\MobileDetect;
use WC_Payment_Gateway;

/**
 * Mojito Sinpe Gateway
 */
class Mojito_Sinpe_Gateway extends WC_Payment_Gateway {

	const ID = 'mojito-sinpe';

	public $instructions;

	/**
	 * Constructor for gateway class
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {

		$this->id                 = self::ID;
		$this->has_fields         = true;
		$this->supports           = array( 'products' );
		$this->method_title       = __( 'SINPE Móvil', 'mojito-sinpe' );
		$this->method_description = __( 'Payment using SINPE Móvil', 'mojito-sinpe' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		$this->sync_debug_setting();
		$this->set_gateway_icon();

		$this->enabled      = $this->get_option( 'enabled' );
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions' );

		// Actions.
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			function() {
				$this->process_admin_options();
			}
		);
		add_action( 'woocommerce_thankyou_mojito-sinpe', array( $this, 'thankyou_page' ) );

		// Customer Emails.
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

		if ( isset( $_SESSION['mojito-sinpe-thank-you-page-already-showed'] ) ) {
			unset( $_SESSION['mojito-sinpe-thank-you-page-already-showed'] );
		}
		if ( isset( $_SESSION['mojito-sinpe-email-instructions-already-added'] ) ) {
			unset( $_SESSION['mojito-sinpe-email-instructions-already-added'] );
		}
	}

	/**
	 * Init your settings
	 *
	 * @access public
	 * @return void
	 */
	public function init() {}

	/**
	 * Add configuration fields to woocommerce payment settings
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'          => array(
				'title'   => __( 'Enable/Disable', 'mojito-sinpe' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable SINPE Payment', 'mojito-sinpe' ),
				'default' => 'yes',
			),
			'title'            => array(
				'title'       => __( 'Title', 'mojito-sinpe' ),
				'type'        => 'text',
				'description' => __( 'Pay using SINPE Móvil', 'mojito-sinpe' ),
				'default'     => __( 'SINPE Móvil Payment', 'mojito-sinpe' ),
				'desc_tip'    => true,
			),
			'number'           => array(
				'title'   => __( 'Phone number', 'mojito-sinpe' ),
				'type'    => 'text',
				'default' => '',
			),
			'ask-voucher-id'   => array(
				'title'   => __( 'Ask voucher id in check-out page', 'mojito-sinpe' ),
				'type'    => 'checkbox',
				'label'   => __( 'Ask voucher id in check-out page', 'mojito-sinpe' ),
				'default' => 'no',
			),
			'show-in-checkout' => array(
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
			'show-in-thankyou-page' => array(
				'title'   => __( 'Show link in thank you page', 'mojito-sinpe' ),
				'type'    => 'checkbox',
				'label'   => __( 'Show link in thank you page', 'mojito-sinpe' ),
				'default' => 'yes',
			),
			'show-in-email' => array(
				'title'   => __( 'Show link in email', 'mojito-sinpe' ),
				'type'    => 'checkbox',
				'label'   => __( 'Show link in email', 'mojito-sinpe' ),
				'default' => 'yes',
			),
			'sinpe-logo-size'  => array(
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
			'description'      => array(
				'title'       => __( 'Description', 'mojito-sinpe' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'mojito-sinpe' ),
				'default'     => __( 'Make your payment with your mobile. Your order will not be shipped until the funds have cleared in our account.', 'mojito-sinpe' ),
				'desc_tip'    => true,
			),
			'instructions'     => array(
				'title'       => __( 'Instructions', 'mojito-sinpe' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page and emails.', 'mojito-sinpe' ),
				'default'     => 'Please send us the Sinpe Móvil voucher. Use your order ID as a payment reference.',
				'desc_tip'    => true,
			),
			/** Exchange rates */
			'exchange-rate-enable' => array(
				'title'   => __( 'Enable/Disable exchange rate', 'mojito-sinpe' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable exchange rates', 'mojito-sinpe' ),
				'default' => 'yes',
			),
			'exchange-rate-origin'  => array(
				'title'   => __( 'Exchange rate origin', 'mojito-sinpe' ),
				'type'    => 'select',
				'label'   => __( 'Pick the origin of the dolar price', 'mojito-sinpe' ),
				'default' => 'hacienda',
				'options' => array(
					'hacienda' => __( 'Ministerio de Hacienda', 'mojito-sinpe' ),
					'custom'   => __( 'Custom', 'mojito-sinpe' ),
				),
			),
			'exchange-rate-custom' => array(
				'title'   => __( 'Custom exchange rate', 'mojito-sinpe' ),
				'type'    => 'text',
				'label'   => __( 'How many colones is a dollar?', 'mojito-sinpe' ),
			),
			'enable-mojito-sinpe-debug' => array(
				'title'   => __( 'Enable/Disable debug', 'mojito-sinpe' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable debug', 'mojito-sinpe' ),
				'default' => 'no',
			),
		);
	}

	/**
	 * Get the configured SINPE bank labels.
	 *
	 * @return array<string,string>
	 */
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

		return isset( $bank_phone_numbers[ $bank ] ) ? $bank_phone_numbers[ $bank ] : '';
	}

	/**
	 * Get settings/data used by the checkout block script.
	 *
	 * @return array<string,mixed>
	 */
	public function get_blocks_payment_data() {
		$amount = $this->get_checkout_amount();
		$banks  = array();

		foreach ( self::get_banks() as $bank_key => $bank_label ) {
			$banks[] = array(
				'id'     => $bank_key,
				'label'  => $bank_label,
				'number' => self::get_bank_phone_number( $bank_key ),
			);
		}

		return array(
			'title'                      => $this->title,
			'description'                => $this->description,
			'supports'                   => $this->supports,
			'enabled'                    => $this->is_available(),
			'amount'                     => $amount,
			'message'                    => $this->get_payment_message( $amount ),
			'store_sinpe_number'         => $this->get_store_owner_number(),
			'is_mobile'                  => $this->is_mobile(),
			'show_in_checkout'           => $this->should_show_link_in_checkout(),
			'show_banks_list'            => $this->should_show_banks_list(),
			'ask_voucher_id'             => $this->should_ask_voucher_id(),
			'show_text_after_banks_list' => apply_filters( 'mojito_sinpe_show_text_after_banks_list', 'yes' ),
			'banks'                      => $banks,
			'i18n'                       => array(
				'select_bank'           => __( 'Select your bank', 'mojito-sinpe' ),
				'bank_required'         => __( 'Payment error: Please select your bank', 'mojito-sinpe' ),
				'voucher_label'         => __( 'Enter your voucher ID', 'mojito-sinpe' ),
				'voucher_placeholder'   => apply_filters( 'mojito_sinpe_ask_voucher_placeholder', __( 'Enter your voucher ID here', 'mojito-sinpe' ) ),
				'voucher_required'      => __( 'Payment error: Please enter your voucher id', 'mojito-sinpe' ),
				'receive_link_in_email' => __( 'You will receive the SINPE Payment link in the order confirmation email. Open it on your mobile.', 'mojito-sinpe' ),
				'pay_now'               => __( 'Pay now: %s', 'mojito-sinpe' ),
				'send_sms'              => __( 'Send a SMS to %s with the content: %s', 'mojito-sinpe' ),
			),
		);
	}

	/**
	 * Show options for SINPE in the checkout page.
	 *
	 * @return void
	 */
	public function payment_fields() {

		if ( ! is_checkout() ) {
			return;
		}

		$number = $this->get_store_owner_number();

		if ( empty( $number ) ) {
			mojito_sinpe_debug( 'Phone number is empty' );
			return;
		}

		$description = $this->get_description();
		if ( $description ) {
			echo wp_kses_post( wpautop( wptexturize( $description ) ) );
		}

		if ( $this->should_show_banks_list() ) {
			?>
			<p>
				<label for="mojito_sinpe_bank"><?php echo esc_html__( 'Select your bank', 'mojito-sinpe' ); ?></label>
				<select class="mojito_sinpe_bank_selector" id="mojito_sinpe_bank" name="mojito_sinpe_bank">
					<?php foreach ( self::get_banks() as $option_key => $option_value ) : ?>
						<option value="<?php echo esc_attr( $option_key ); ?>"><?php echo esc_html( $option_value ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<?php
		}

		if ( ! $this->should_show_link_in_checkout() ) {
			echo esc_html__( 'You will receive the SINPE Payment link in the order confirmation email. Open it on your mobile.', 'mojito-sinpe' );
			return;
		}

		$amount  = $this->get_checkout_amount();
		$message = $this->get_payment_message( $amount );
		$type    = $this->is_mobile() ? 'mobile' : 'desktop';

		printf(
			'<a href="#" data-type="%1$s" data-msj="%2$s" data-amount="%3$s" data-number="%4$s" class="mojito-sinpe-link">%5$s</a>',
			esc_attr( $type ),
			esc_attr( $message ),
			esc_attr( (string) $amount ),
			esc_attr( $number ),
			esc_html( sprintf( __( 'Pay now: %s', 'mojito-sinpe' ), $amount ) )
		);

		if ( 'desktop' === $type ) {
			echo '<p class="mojito-sinpe-payment-container"></p>';
		}

		if ( $this->should_ask_voucher_id() ) {
			$placeholder = apply_filters( 'mojito_sinpe_ask_voucher_placeholder', __( 'Enter your voucher ID here', 'mojito-sinpe' ) );
			?>
			<p>
				<label for="mojito_sinpe_voucher_id"><?php echo esc_html__( 'Enter your voucher ID', 'mojito-sinpe' ); ?></label>
				<input required class="mojito_sinpe_voucher_id" id="mojito_sinpe_voucher_id" name="mojito_sinpe_voucher_id" type="text" placeholder="<?php echo esc_attr( $placeholder ); ?>">
			</p>
			<?php
		}

		do_action( 'mojito_sinpe_after_fields' );
	}

	/**
	 * Detect mobile client.
	 *
	 * @return boolean
	 */
	public function is_mobile() {

		if ( ! class_exists( 'Detection\MobileDetect' ) ) {
			return false;
		}

		$detect = new MobileDetect();

		try {
			if ( $detect->isMobile() ) {
				return true;
			}

			return $detect->isTablet();
		} catch ( \Exception $exception ) {
			mojito_sinpe_debug( $exception->getMessage() );
			return false;
		}
	}


	/**
	 * Output for the order received page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function thankyou_page( $order_id ) {
		if ( empty( $_SESSION['mojito-sinpe-thank-you-page-already-showed'] ) ) {
			if ( ! empty( $this->instructions ) ) {
				echo wp_kses_post( wpautop( wptexturize( wp_kses_post( $this->instructions ) ) ) );
				$_SESSION['mojito-sinpe-thank-you-page-already-showed'] = 1;
			}
		}
	}


	/**
	 * Add content to the WC emails.
	 *
	 * @param \WC_Order $order Order object.
	 * @param bool      $sent_to_admin Sent to admin.
	 * @param bool      $plain_text Email format: plain text or HTML.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

		if ( empty( $_SESSION['mojito-sinpe-email-instructions-already-added'] ) ) {
			if ( ! $sent_to_admin && self::ID === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
				if ( $this->instructions ) {
					echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
					$_SESSION['mojito-sinpe-email-instructions-already-added'] = 1;
				}
			}
		}
	}


	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array<string,string>
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			wc_add_notice( __( 'Not a valid order', 'mojito-sinpe' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$payment_data = array(
			'mojito_sinpe_bank'       => isset( $_POST['mojito_sinpe_bank'] ) ? sanitize_text_field( wp_unslash( $_POST['mojito_sinpe_bank'] ) ) : '',
			'mojito_sinpe_voucher_id' => isset( $_POST['mojito_sinpe_voucher_id'] ) ? sanitize_text_field( wp_unslash( $_POST['mojito_sinpe_voucher_id'] ) ) : '',
		);

		$result = $this->process_sinpe_order( $order, $payment_data );
		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
			return array( 'result' => 'failure' );
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Shared order processing for classic checkout and Store API checkout.
	 *
	 * @param mixed               $order Order object.
	 * @param array<string,mixed> $payment_data Payment data.
	 * @return true|\WP_Error
	 */
	public function process_sinpe_order( $order, array $payment_data ) {
		if ( ! $order instanceof \WC_Order ) {
			return new \WP_Error( 'mojito_sinpe_invalid_order', __( 'Not a valid order', 'mojito-sinpe' ) );
		}

		$bank    = isset( $payment_data['mojito_sinpe_bank'] ) ? sanitize_text_field( $payment_data['mojito_sinpe_bank'] ) : '';
		$voucher = isset( $payment_data['mojito_sinpe_voucher_id'] ) ? sanitize_text_field( $payment_data['mojito_sinpe_voucher_id'] ) : '';

		$validation = $this->validate_payment_data( $bank, $voucher );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		if ( $this->should_show_banks_list() ) {
			$order->update_meta_data( 'mojito_sinpe_bank', $bank );
		}

		if ( $this->should_ask_voucher_id() ) {
			$order->add_order_note( sprintf( __( 'SINPE Voucher id: %s', 'mojito-sinpe' ), $voucher ) );
		}

		$order->update_status( 'on-hold', __( 'Awaiting SINPE payment', 'mojito-sinpe' ) );
		$order->save();

		return true;
	}

	public function is_available(): bool {
		return parent::is_available() && '' !== $this->get_store_owner_number();
	}

	/**
	 * Validate checkout payment data.
	 *
	 * @param string $bank Selected bank.
	 * @param string $voucher Voucher id.
	 * @return true|\WP_Error
	 */
	private function validate_payment_data( $bank, $voucher ) {
		if ( $this->should_show_banks_list() && ( empty( $bank ) || 'none' === $bank || '' === self::get_bank_phone_number( $bank ) ) ) {
			return new \WP_Error( 'mojito_sinpe_bank_required', __( 'Payment error: Please select your bank', 'mojito-sinpe' ) );
		}

		if ( $this->should_ask_voucher_id() && empty( $voucher ) ) {
			return new \WP_Error( 'mojito_sinpe_voucher_required', __( 'Payment error: Please enter your voucher id', 'mojito-sinpe' ) );
		}

		return true;
	}

	/**
	 * Get a setting safely.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private function get_setting_value( $key, $default = '' ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Synchronize debug option with gateway settings.
	 *
	 * @return void
	 */
	private function sync_debug_setting() {
		$setting_debug = $this->get_setting_value( 'enable-mojito-sinpe-debug', 'no' );
		$current_debug = get_option( 'mojito_sinpe_debug' );

		if ( $current_debug !== $setting_debug ) {
			update_option( 'mojito_sinpe_debug', $setting_debug );
		}
	}

	/**
	 * Set the gateway icon URL.
	 *
	 * @return void
	 */
	private function set_gateway_icon() {
		$icon_map = array(
			'500x275' => 'sinpe-movil',
			'400x220' => 'sinpe-movil-400x220',
			'300x165' => 'sinpe-movil-300x165',
			'200x110' => 'sinpe-movil-200x110',
			'100x55'  => 'sinpe-movil-100x55',
			'50x28'   => 'sinpe-movil-50x28',
		);

		$icon_size = $this->get_setting_value( 'sinpe-logo-size', 'no-logo' );
		if ( 'no-logo' === $icon_size ) {
			return;
		}

		$icon = isset( $icon_map[ $icon_size ] ) ? $icon_map[ $icon_size ] : 'sinpe-movil';

		$this->icon = plugin_dir_url( __DIR__ ) . 'public/img/' . $icon . '.png';
	}

	/**
	 * Check if the checkout bank list should be shown.
	 *
	 * @return bool
	 */
	private function should_show_banks_list() {
		return 'yes' === $this->get_setting_value( 'show-banks-list-in-checkout', 'yes' );
	}

	/**
	 * Check if the checkout payment link should be shown.
	 *
	 * @return bool
	 */
	private function should_show_link_in_checkout() {
		return 'yes' === $this->get_setting_value( 'show-in-checkout', 'yes' );
	}

	/**
	 * Check if voucher id should be requested.
	 *
	 * @return bool
	 */
	private function should_ask_voucher_id() {
		return 'yes' === $this->get_setting_value( 'ask-voucher-id', 'no' ) && $this->should_show_banks_list();
	}

	/**
	 * Get store owner SINPE number.
	 *
	 * @return string
	 */
	private function get_store_owner_number() {
		return trim( (string) $this->get_setting_value( 'number', '' ) );
	}

	/**
	 * Get checkout amount transformed by exchange-rate options and filters.
	 *
	 * @return float|int
	 */
	private function get_checkout_amount() {
		$amount = 0;

		if ( function_exists( 'WC' ) && WC()->cart ) {
			$amount = WC()->cart->total;
		}

		return $this->calculate_sinpe_amount( $amount );
	}

	/**
	 * Calculate the amount to include in SINPE instructions.
	 *
	 * @param float|int|string $amount Amount in store currency.
	 * @return float|int
	 */
	private function calculate_sinpe_amount( $amount ) {
		$amount = is_numeric( $amount ) ? (float) $amount : 0;

		if ( 'yes' === $this->get_setting_value( 'exchange-rate-enable', 'yes' ) ) {
			$exchange_rate        = 1;
			$exchange_rate_origin = $this->get_setting_value( 'exchange-rate-origin', 'hacienda' );
			$exchange_rate_custom = $this->get_setting_value( 'exchange-rate-custom', '' );

			switch ( $exchange_rate_origin ) {
				case 'hacienda':
					$rates = \Mojito\ExchangeRate\Factory::create( \Mojito\ExchangeRate\ProviderTypes::CR_Hacienda );
					$rate  = $rates->getRates();
					if ( isset( $rate->dolar->venta->valor ) ) {
						$exchange_rate = $rate->dolar->venta->valor;
					}
					break;
				case 'custom':
					if ( is_numeric( $exchange_rate_custom ) ) {
						$exchange_rate = (float) $exchange_rate_custom;
					}
					break;
			}

			$exchange_rate = apply_filters( 'mojito_sinpe_exchange_rate', $exchange_rate );
			$amount        = round( $amount * (float) $exchange_rate, 0 );
		}

		if ( $amount < 0 ) {
			$amount *= -1;
		}

		return apply_filters( 'mojito_sinpe_amount', $amount );
	}

	/**
	 * Get SINPE SMS message.
	 *
	 * @param float|int|null $amount Amount.
	 * @return string
	 */
	private function get_payment_message( $amount = null ) {
		if ( null === $amount ) {
			$amount = $this->get_checkout_amount();
		}

		return sprintf( 'Pase %s %s', $amount, $this->get_store_owner_number() );
	}
}
