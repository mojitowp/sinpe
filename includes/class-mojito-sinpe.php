<?php
/**
 * Gateway registration, order instructions and authorized SMS links.
 *
 * @package Mojito_Sinpe
 */

namespace Mojito_Sinpe;

defined( 'ABSPATH' ) || exit;

class Mojito_Sinpe {
	/** @var array<string,bool> */
	private $rendered = array();

	public function run(): void {
		add_action( 'init', array( $this, 'translations' ) );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			$this->blocks();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( $this, 'blocks' ) );
		}
		add_action( 'woocommerce_thankyou_mojito-sinpe', array( $this, 'thankyou' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email' ), 10, 4 );
		add_action( 'rest_api_init', array( $this, 'rest' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'rest_headers' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'woocommerce_order_actions', array( $this, 'order_actions' ), 10, 2 );
		add_action( 'woocommerce_order_action_mojito_sinpe_regenerate', array( $this, 'regenerate' ) );
	}

	public function translations(): void {
		global $wp_textdomain_registry;
		// Register bundled catalogs; WordPress still loads translations on demand.
		$wp_textdomain_registry->set_custom_path( 'mojito-sinpe', MOJITO_SINPE_DIR . 'languages' );
	}

	public function register_gateway( array $gateways ): array {
		$gateways[] = Mojito_Sinpe_Gateway::class;
		return $gateways;
	}

	public static function gateway(): ?Mojito_Sinpe_Gateway {
		$gateways = WC()->payment_gateways()->payment_gateways();
		$gateway  = $gateways[ Mojito_Sinpe_Gateway::ID ] ?? null;
		return $gateway instanceof Mojito_Sinpe_Gateway ? $gateway : null;
	}

	public function blocks(): void {
		if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}
		require_once MOJITO_SINPE_DIR . 'includes/class-mojito-sinpe-gateway-block.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static function ( $registry ) {
				$registry->register( new Mojito_Sinpe_Gateway_Block() );
			}
		);
		if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			woocommerce_store_api_register_endpoint_data(
				array(
					'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
					'namespace'       => 'mojito-sinpe',
					'data_callback'   => static function () {
						$gateway = self::gateway();
						return $gateway ? $gateway->get_blocks_payment_data() : array();
					},
					'schema_callback' => static function () {
						return array(
							'available'  => array(
								'type'     => 'boolean',
								'readonly' => true,
							),
							'recipient'  => array(
								'type'     => 'string',
								'readonly' => true,
							),
							'amount'     => array(
								'type'     => 'string',
								'readonly' => true,
							),
							'message'    => array(
								'type'     => 'string',
								'readonly' => true,
							),
							'cart_total' => array(
								'type'     => 'integer',
								'readonly' => true,
							),
							'error'      => array(
								'type'     => 'string',
								'readonly' => true,
							),
						);
					},
					'schema_type'     => ARRAY_A,
				)
			);
		}
	}

	public function assets(): void {
		if ( ! is_checkout() || is_wc_endpoint_url() || has_block( 'woocommerce/checkout' ) ) {
			return;
		}
		wp_enqueue_script( 'mojito-sinpe-classic', plugins_url( 'public/js/mojito-sinpe-public.js', MOJITO_SINPE_DIR . 'mojito-sinpe.php' ), array( 'jquery', 'wp-i18n' ), MOJITO_SINPE_VERSION, true );
		wp_set_script_translations( 'mojito-sinpe-classic', 'mojito-sinpe', MOJITO_SINPE_DIR . 'languages' );
		wp_localize_script( 'mojito-sinpe-classic', 'mojitoSinpe', array( 'bankNumbers' => Mojito_Sinpe_Gateway::get_bank_phone_numbers() ) );
	}

	public function thankyou( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order || ! $this->can_render( $order ) ) {
			return;
		}
		$key = 'thankyou:' . $order->get_id();
		if ( isset( $this->rendered[ $key ] ) ) {
			return;
		}
		$this->rendered[ $key ] = true;
		$this->instructions( $order, false, 'show-in-thankyou-page' );
	}

	public function email( $order, $sent_to_admin, $plain_text, $email ): void {
		if ( $sent_to_admin || ! $order instanceof \WC_Order || ! $email instanceof \WC_Email || 'customer_on_hold_order' !== $email->id || ! $this->can_render( $order ) ) {
			return;
		}
		$this->instructions( $order, (bool) $plain_text, 'show-in-email' );
	}

	private function can_render( \WC_Order $order ): bool {
		return Mojito_Sinpe_Gateway::ID === $order->get_payment_method() && $order->has_status( array( 'on-hold', 'pending', 'failed' ) );
	}

	private function instructions( \WC_Order $order, bool $plain, string $option ): void {
		$gateway = self::gateway();
		if ( ! $gateway ) {
			return;
		}
		if ( $gateway->instructions ) {
			if ( $plain ) {
				echo esc_html( wp_strip_all_tags( $gateway->instructions ) ) . "\n";
			} else {
				echo wp_kses_post( wpautop( $gateway->instructions ) );
			}
		}
		if ( 'yes' !== $gateway->get_option( $option, 'yes' ) ) {
			return;
		}
		$snapshot = Mojito_Sinpe_Payment::for_order( $order, $gateway->settings );
		if ( is_wp_error( $snapshot ) ) {
			$text = $snapshot->get_error_message();
		} else {
			$bank = Mojito_Sinpe_Gateway::get_bank_phone_number( (string) $order->get_meta( 'mojito_sinpe_bank', true ) );
			$text = Mojito_Sinpe_Payment::instructions( $snapshot, $bank );
		}
		$text .= ' ' . __( 'The store must confirm receipt of funds. Do not pay again if you already sent the transfer.', 'mojito-sinpe' );
		if ( $plain ) {
			echo esc_html( wp_strip_all_tags( $text ) ) . "\n";
		} else {
			echo '<p>' . esc_html( $text ) . '</p>';
		}
		if ( ! is_wp_error( $snapshot ) && ! empty( $bank ) ) {
			$url   = self::payment_url( $order );
			$label = apply_filters( 'mojito_sinpe_email_label', __( 'Open SINPE SMS instructions on your mobile', 'mojito-sinpe' ) );
			if ( $plain ) {
				echo esc_html( wp_strip_all_tags( $label ) ) . ': ' . esc_url_raw( $url ) . "\n";
			} else {
				echo '<p><a rel="noreferrer" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></p>';
			}
		}
	}

	public static function payment_url( \WC_Order $order ): string {
		return add_query_arg(
			array(
				'order' => $order->get_id(),
				'key'   => $order->get_order_key(),
			),
			rest_url( 'mojito-sinpe/v1/open-payment-link/' )
		);
	}

	public function rest(): void {
		register_rest_route(
			'mojito-sinpe/v1',
			'/open-payment-link/',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'payment_link' ),
				'permission_callback' => array( $this, 'can_open_payment_link' ),
				'args'                => array(
					'order' => array(
						'required' => true,
						'type'     => 'integer',
						'minimum'  => 1,
					),
					'key'   => array(
						'required'  => true,
						'type'      => 'string',
						'maxLength' => 100,
					),
				),
			)
		);
	}

	public function rest_headers( $response, $server, $request ) {
		if ( str_starts_with( $request->get_route(), '/mojito-sinpe/v1/' ) ) {
			$response->header( 'Cache-Control', 'no-store, private, max-age=0' );
			$response->header( 'Referrer-Policy', 'no-referrer' );
			$response->header( 'X-Robots-Tag', 'noindex, nofollow' );
		}
		return $response;
	}

	public function can_open_payment_link( \WP_REST_Request $request ) {
		$order = wc_get_order( absint( $request->get_param( 'order' ) ) );
		$key   = $request->get_param( 'key' );
		if ( ! $order instanceof \WC_Order || ! is_string( $key ) || '' === $key || ! hash_equals( $order->get_order_key(), $key ) || ! $this->can_render( $order ) ) {
			return new \WP_Error( 'sinpe_link', __( 'This SINPE payment link is invalid or no longer available.', 'mojito-sinpe' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public function payment_link( \WP_REST_Request $request ) {
		$permission = $this->can_open_payment_link( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$order   = wc_get_order( absint( $request->get_param( 'order' ) ) );
		$gateway = self::gateway();
		if ( ! $order instanceof \WC_Order || ! $gateway ) {
			return new \WP_Error( 'sinpe_link', __( 'SINPE is unavailable.', 'mojito-sinpe' ), array( 'status' => 400 ) );
		}
		$snapshot = Mojito_Sinpe_Payment::for_order( $order, $gateway->settings );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}
		$bank = Mojito_Sinpe_Gateway::get_bank_phone_number( (string) $order->get_meta( 'mojito_sinpe_bank', true ) );
		$text = Mojito_Sinpe_Payment::instructions( $snapshot, $bank );
		// Return a real HTML page: no JSON-encoded HTML or permanent sms: redirects.
		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( "Content-Security-Policy: default-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'" );
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>SINPE Móvil</title><main><h1>SINPE Móvil</h1><p>' . esc_html( $text ) . '</p>';
		if ( $bank ) {
			echo '<p><a href="' . esc_url( Mojito_Sinpe_Payment::sms_url( $bank, Mojito_Sinpe_Payment::message( $snapshot ) ), array( 'sms' ) ) . '">' . esc_html__( 'Open SMS payment instructions', 'mojito-sinpe' ) . '</a></p>';
		}
		echo '<p>' . esc_html__( 'The store must confirm receipt of funds. Do not pay again if you already sent the transfer.', 'mojito-sinpe' ) . '</p></main></html>';
		exit;
	}

	public function order_actions( array $actions, $order ): array {
		if ( $order instanceof \WC_Order && $this->can_render( $order ) ) {
			$actions['mojito_sinpe_regenerate'] = __( 'Regenerate SINPE instructions using the current rate', 'mojito-sinpe' );
		}
		return $actions;
	}

	/** WooCommerce protects order actions with its order-edit nonce and capability. */
	public function regenerate( \WC_Order $order ): void {
		if ( ! current_user_can( 'edit_shop_order', $order->get_id() ) || ! $this->can_render( $order ) ) {
			return;
		}
		$gateway = self::gateway();
		if ( ! $gateway ) {
			return;
		}
		$snapshot = Mojito_Sinpe_Payment::calculate( $order->get_total(), $order->get_currency(), $gateway->settings );
		if ( is_wp_error( $snapshot ) ) {
			$order->add_order_note( $snapshot->get_error_message() );
			return;
		}
		$order->update_meta_data( '_mojito_sinpe_payment', $snapshot );
		$order->save();
		$order->add_order_note( __( 'SINPE instructions regenerated using the current total, recipient and rate. Send the updated instructions to the customer.', 'mojito-sinpe' ) );
	}
}
