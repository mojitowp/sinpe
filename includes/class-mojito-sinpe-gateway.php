<?php
/**
 * WooCommerce compatibility of the plugin.
 *
 * @link       https://mojitowp.com/
 * @since      1.0.0
 * WooCommerce compatibility of the plugin.
 *
 * @package    Mojito_Sinpe
 * @subpackage Mojito_Sinpe/public
 * @author     Mojito Team <support@mojitowp.com>
 */

namespace Mojito_Sinpe;

use WC_Payment_Gateway;

/**
 * Mojito Sinpe Gateway
 */
class Mojito_Sinpe_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor for gateway class
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {

		$this->id                 = 'mojito-sinpe';
		$this->has_fields         = true;
		$this->method_title       = __( 'SINPE Móvil', 'mojito-sinpe' );
		$this->method_description = __( 'Payment using SINPE Móvil', 'mojito-sinpe' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		$icon = 'sinpe-movil';
		if ( 'no-logo' === $this->settings['sinpe-logo-size'] ) {
			$icon = false;
		} elseif ( '500x275' === $this->settings['sinpe-logo-size'] ) {
			$icon = 'sinpe-movil-500x275';
		} elseif ( '400x220' === $this->settings['sinpe-logo-size'] ) {
			$icon = 'sinpe-movil-400x220';
		} elseif ( '300x165' === $this->settings['sinpe-logo-size'] ) {
			$icon = 'sinpe-movil-300x165';
		} elseif ( '200x110' === $this->settings['sinpe-logo-size'] ) {
			$icon = 'sinpe-movil-200x110';
		} elseif ( '100x55' === $this->settings['sinpe-logo-size'] ) {
			$icon = 'sinpe-movil-100x55';
		} elseif ( '50x28' === $this->settings['sinpe-logo-size'] ) {
			$icon = 'sinpe-movil-50x28';
		} else {
			$icon = 'sinpe-movil';
		}

		if ( false !== $icon ) {
			$this->icon = plugin_dir_url( __DIR__ ) . 'public/img/' . $icon . '.png';
		}

		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' ) ;
		$this->instructions = $this->get_option( 'instructions' );

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
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
					'custom' => __( 'Custom', 'mojito-sinpe' ),
				),
			),
			'exchange-rate-custom' => array(
				'title'   => __( 'Custom exchange rate', 'mojito-sinpe' ),
				'type'    => 'text',
				'label'   => __( 'How many colones is a dollar?', 'mojito-sinpe' ),
			),
		);
	}

	/**
	 * Show options for SINPE in the checkout page
	 *
	 * @return void
	 */
	public function payment_fields() {

		if ( ! is_checkout() ) {
			return;
		}

		$number = $this->settings['number'];

		if ( empty( $number ) ) {
			return;
		}

		$description = $this->get_description();
		if ( $description ) {
			echo wpautop( wptexturize( $description ) );
		}

		/*
		Note: Web Archive URL. The real url was removed from Central Bank's SINPE official documentation.
		URL: https://web.archive.org/web/20210824000600/https://www.bccr.fi.cr/sistema-de-pagos/informaci%C3%B3n-general/tarifas-y-comisiones-del-sinpe/comisiones-cobradas-por-las-entidades-financieras/sinpe-m%C3%B3vil

		2021-12-27
		https://www.bccr.fi.cr/sistema-de-pagos/tarifas-y-comisiones-del-sinpe/comisiones-cobradas-por-las-entidades-financieras/sinpe-m%c3%b3vil

		*/
		$sinpe_banks = array(
			'none'            => __( 'Select your bank', 'mojito-sinpe' ),
			'bn'              => 'Banco Nacional de Costa Rica', // 2627
			'bcr'             => 'Banco de Costa Rica', // 4066
			'bac'             => 'Banco BAC San José', // 7070-1222
			'bct'             => 'Banco BCT', // 6040-0300
			'caja-de-ande'    => 'Caja de Ande', // 6222-9532
			'coopealianza'    => 'Coopealianza', // 6222-9523
			'coopecaja'       => 'Coopecaja', // 6222-9526
			'coopelecheros'   => 'Coopelecheros', // 6040-5957
			'credecoop'       => 'Credecoop', // 7198-4256
			'davivienda'      => 'Banco Davivienda', // 7070-7474
			'lafise'          => 'Banco Lafise', // 9091
			'mucap'           => 'MUCAP', // 8858-4646 o 8861-5353
			'mutual-alajuela' => 'Grupo Mutual Alajuela - La Vivienda', // 7070-7079
			'promerica'       => 'Banco Promerica', // 6223-2450
		);

		$sinpe_banks = apply_filters( 'mojito_sinpe_banks_numbers', $sinpe_banks );

		if ( 'yes' === $this->settings['show-banks-list-in-checkout'] ) {

			?>
			<p>
				<label for="mojito_sinpe_bank"><?php echo __( 'Select your bank', 'mojito-sinpe' ); ?></label>
				<select class="mojito_sinpe_bank_selector" id="mojito_sinpe_bank" name="mojito_sinpe_bank">
					<?php foreach ( $sinpe_banks as $option_key => $option_value ) : ?>
						<option value="<?php echo esc_attr( $option_key ); ?>"><?php echo esc_html( $option_value ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<?php
		}

		if ( 'yes' !== $this->settings['show-in-checkout'] ) {
			echo __( 'You will receive the SINPE Payment link in the order confirmation email. Open it on your mobile.', 'mojito-sinpe' );
			return;
		}

		global $woocommerce;

		$amount  = $woocommerce->cart->total;

		/**
		 * Exchange rate
		 */
		$exchange_rate        = 1;
		$exchange_rate_enable = $this->settings['exchange-rate-enable'];
		$exchange_rate_origin = $this->settings['exchange-rate-origin'];
		$exchange_rate_custom = $this->settings['exchange-rate-custom'];

		if ( 'yes' === $exchange_rate_enable ) {

			switch ( $exchange_rate_origin ) {
				case 'hacienda':
					$rates = \Mojito\ExchangeRate\Factory::create( \Mojito\ExchangeRate\ProviderTypes::CR_Hacienda );
					$rate  = $rates->getRates();
					if ( isset($rate->dolar->venta->valor)) {
						$exchange_rate = $rate->dolar->venta->valor;
					} else {
						$exchange_rate = 1;
					}
					break;
				case 'custom':
					if ( is_numeric( $exchange_rate_custom ) ) {
						$exchange_rate = $exchange_rate_custom;
					}
					break;
			}

			/**
			 * Filter the exchange rate
			 */
			$exchange_rate = apply_filters( 'mojito_sinpe_exchange_rate', $exchange_rate );

			/**
			 * Si el exchange rate está activo, quiere decir que la tienda vende en dólares
			 * Entonces se multiplica el monto por el exchange rate para obtener la cantidad en colones
			 */
			$amount = $amount * $exchange_rate;
			$amount = round( $amount, 0 );
			if ( $amount < 1 ) {
				$amount = $amount * -1;
			}
		}

		$amount = apply_filters( 'mojito_sinpe_amount', $amount );

		$message = 'Pase ' . $amount . ' ' . $number;

		if ( $this->is_mobile() ) {
			echo '<a data-type="mobile" data-msj="' . $message . '" data-amount="' . $amount . '" data-number="' . $number . '" class="mojito-sinpe-link">' . sprintf( __( 'Pay now: %s', 'mojito-sinpe' ), $amount ) . '</a>';
		} else {
			echo '<a data-type="desktop" data-msj="' . $message . '" data-amount="' . $amount . '" data-number="' . $number . '" class="mojito-sinpe-link">' . sprintf( __( 'Pay now: %s', 'mojito-sinpe' ), $amount ) . '</a>';
			echo '<p class="mojito-sinpe-payment-container"></p>';
		}

		if ( 'yes' === $this->settings['ask-voucher-id'] && 'yes' === $this->settings['show-banks-list-in-checkout'] ) {

			$placeholder = apply_filters( 'mojito_sinpe_ask_voucher_placeholder', __( 'Enter your voucher ID here', 'mojito-sinpe' ) );
		?>
			<p>
				<label for="mojito_sinpe_voucher_id"><?php echo __( 'Enter your voucher ID', 'mojito-sinpe' ); ?></label>
				<input required class="mojito_sinpe_voucher_id" id="mojito_sinpe_voucher_id" name="mojito_sinpe_voucher_id" type="text" placeholder="<?php echo $placeholder; ?>">
			</p>
		<?php
		}

		do_action( 'mojito_sinpe_after_fields' );
		
	}

	/**
	 * Detect mobile client
	 *
	 * @return boolean
	 */
	public function is_mobile() {

		if ( ! class_exists( 'Mobile_Detect' ) ) {
			return;
		}

		$detect = new \Mobile_Detect();

		$is_mobile = false;

		if ( method_exists( 'Mobile_Detect', 'isMobile' ) ) {
			$is_mobile = $detect->isMobile();
		}

		if ( false === $is_mobile && method_exists( 'Mobile_Detect', 'isTablet' ) ) {
			$is_mobile = $detect->isTablet();
		}

		return $is_mobile;
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
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

		if ( empty( $_SESSION['mojito-sinpe-email-instructions-already-added'] ) ) {
			if ( ! $sent_to_admin && 'mojito-sinpe' === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
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
	 * @return array
	 */
	public function process_payment( $order_id ) {

		global $woocommerce;

		$bank = sanitize_text_field( $_POST['mojito_sinpe_bank'] );

		if ( 'yes' === $this->settings['show-banks-list-in-checkout'] ) {
			if ( empty( $bank ) || 'none' === $bank ) {
				wc_add_notice( __( 'Payment error: Please select your bank', 'mojito-sinpe' ), 'error' );
				return;
			}
		}

		$order = new \WC_Order( $order_id );

		if ( 'yes' === $this->settings['ask-voucher-id'] && 'yes' === $this->settings['show-banks-list-in-checkout'] ) {

			$voucher = sanitize_text_field( $_POST['mojito_sinpe_voucher_id'] );

			if ( empty( $voucher ) ) {
				wc_add_notice( __( 'Payment error: Please enter your voucher id', 'mojito-sinpe' ), 'error' );
				return;
			}

			$order->add_order_note( sprintf( __( 'SINPE Voucher id: %s' , 'mojito-sinpe' ), $voucher ) );
		}

		// Mark as on-hold ( we're awaiting the SINPE).
		$order->update_status( 'on-hold', __( 'Awaiting SINPE payment', 'mojito-sinpe' ) );

		// Remove cart.
		$woocommerce->cart->empty_cart();

		// Return thankyou redirect.
		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}
