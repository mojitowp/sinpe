<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://mojitowp.com/
 * @since      1.0.0
 *
 * @package    Mojito_Sinpe
 * @subpackage Mojito_Sinpe/includes
 */

namespace Mojito_Sinpe;

use Detection\MobileDetect;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Mojito_Sinpe
 * @subpackage Mojito_Sinpe/includes
 * @author     Mojito Team <support@mojitowp.com>
 */
class Mojito_Sinpe {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Mojito_Sinpe_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;


	private $mojito_sinpe_settings;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {

		if ( defined( 'MOJITO_SINPE_VERSION' ) ) {
			$this->version = MOJITO_SINPE_VERSION;
		} else {
			$this->version = '1.3.0';
		}
		$this->plugin_name = 'mojito-sinpe';
		$this->mojito_sinpe_settings = array();

		/**
		 * Define plugin name as constant.
		 */
		if ( ! defined( 'MOJITO_SINPE_SLUG' ) ) {
			define( 'MOJITO_SINPE_SLUG', $this->plugin_name );
		}

		$this->load_dependencies();
		$this->set_locale();
		// @phpstan-ignore-next-line Empty extension point kept for plugin-boilerplate compatibility.
		$this->define_admin_hooks();
		$this->define_public_hooks();

		add_filter(
			'woocommerce_payment_gateways',
			function ( $methods ) {
				$methods[] = 'Mojito_Sinpe\Mojito_Sinpe_Gateway';
				return $methods;
			}
		);

		/**
		 * Load gateway
		 */
		add_action(
			'plugins_loaded',
			function () {
				$this->load_gateway_class();
			}
		);

		/**
		 * Save client bank selection as meta to use it later in the order email
		 */
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_client_bank_selection' ) );

		/**
		 * Add SINPE link to order email
		 */
		add_action( 'woocommerce_email_before_order_table', array( $this, 'add_sinpe_link_to_order_email' ), 10, 4 );

		/**
		 * Add SINPE link to Thank you page
		 */
		add_action( 'woocommerce_thankyou', array( $this, 'add_sinpe_link_to_thankyou_page' ), 10, 1 );

		/**
		 * Add enpoint to rest api
		 */
		add_action(
			'rest_api_init',
				function () {
					register_rest_route(
						'mojito-sinpe/v1',
						'/open-payment-link/',
						array(
							'methods'             => 'GET',
							'callback'            => array( $this, 'payment_link' ),
							'permission_callback' => '__return_true',
						)
					);
				}
		);

		add_action(
			'woocommerce_init',
			function() {
				add_filter( 'woocommerce_available_payment_gateways', function( $available_gateways ) {
					if ( ! empty( $available_gateways[ Mojito_Sinpe_Gateway::ID ] ) ) {
						$this->mojito_sinpe_settings = $available_gateways[ Mojito_Sinpe_Gateway::ID ]->settings;
					}
					return $available_gateways;
				});
			}
		);

		add_action( 'woocommerce_blocks_loaded', function() {
			if ( ! $this->load_gateway_class() ) {
				return;
			}

			if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
				return;
			}

			require_once MOJITO_SINPE_DIR . 'includes/class-mojito-sinpe-gateway-block.php';

			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new Mojito_Sinpe_Gateway_Block() );
				}
			);
		} );

	}

	/**
	 * Open Payment link from confirmation order
	 */
	public function payment_link( $request = null ) {

		/**
		 * Work only in mobile
		 */
		$sinpe_gateway = new Mojito_Sinpe_Gateway();
		if ( ! $sinpe_gateway->is_mobile() ) {
			return __( 'Please open the link only in mobile', 'mojito-sinpe' );
		}

		/**
		 * Get order id
		 */
		if ( $request instanceof \WP_REST_Request ) {
			$order_id = absint( $request->get_param( 'order' ) );
		} else {
			$order_id = isset( $_GET['order'] ) ? absint( wp_unslash( $_GET['order'] ) ) : 0;
		}

		/**
		 * Check order id
		 */
		if ( ! $order_id ) {
			return __( 'Not a valid order', 'mojito-sinpe' );
		}

		/**
		 * Load Order data
		 */
		$order = wc_get_order( $order_id );

		/**
		 * Is a valid order?
		 */
		if ( ! $order instanceof \WC_Order ) {
			return __( 'Not a valid order', 'mojito-sinpe' );
		}

		/**
		 * Check if is the correct payment method
		 */
		if ( Mojito_Sinpe_Gateway::ID !== $order->get_payment_method() ) {
			return __( 'This order hasn\'t SINPE Móvil as payment method', 'mojito-sinpe' );
		}

		/**
		 * Check if order is paid
		 */
		if ( $order->is_paid() ) {
			return __( 'Order is paid', 'mojito-sinpe' );
		}

		/**
		 * Get bank SINPE number
		 */
		$bank_number = $this->get_bank_number( $order->get_id() );

		/**
		 * Check if there is bank number
		 */
		if ( empty( $bank_number ) ) {
			return __( 'Bank was not selected', 'mojito-sinpe' );
		}

		/**
		 * Get Store Owner bank number
		 */
		$store_sinpe_number = $this->get_store_owner_bank_number();

		/**
		 * Build SMS message and link
		 */
		$total   = round( $order->get_total(), 0 );
		$message = sprintf( __( 'Pase %s %s Order %s', 'mojito-sinpe' ), $total, $store_sinpe_number, $order_id );

		/**
		 * The link address to website to prevent double payments. Also gmail blocks "sms" in href attribute.
		 */
		$concat = '?';
		$detect = new MobileDetect();

		try {
			if ( true === $detect->is( 'iPhone' ) ) {
				$concat = '&';
			}
		} catch ( \Exception $exception ) {
			mojito_sinpe_debug( $exception->getMessage() );
		}

		wp_redirect( esc_url_raw( 'sms:+' . rawurlencode( $bank_number ) . $concat . 'body=' . rawurlencode( $message ), array( 'sms' ) ), 302 );

		exit;
	}

	/**
	 * Save client bank selection as meta to use it later in the order email
	 * @return void
	 */
	public function save_client_bank_selection( $order_id ) {

		if ( ! empty( $_POST['mojito_sinpe_bank'] ) ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof \WC_Order ) {
				$order->update_meta_data( 'mojito_sinpe_bank', sanitize_text_field( wp_unslash( $_POST['mojito_sinpe_bank'] ) ) );
				$order->save();
			}
		}
	}

	/**
	 * Add SINPE link to order emai
	 * @return void
	 */
	public function add_sinpe_link_to_order_email( $order, $sent_to_admin, $plain_text, $email ) {

		$settings = $this->get_mojito_sinpe_settings();

		if ( 'yes' !== $settings['show-in-email'] ) {
			return;
		}

		/**
		 * Check if is the correct email
		 */
		if ( 'customer_on_hold_order' !== $email->id ) {
			return;
		}

		/**
		 * Check if is sent to admin
		 */
		if ( $sent_to_admin ) {
			return;
		}

		/**
		 * Check if is the correct payment method
		 */
		if ( Mojito_Sinpe_Gateway::ID !== $order->get_payment_method() ) {
			return;
		}

		/**
		 * Check if order is paid
		 */
		if ( $order->is_paid() ) {
			return;
		}

		$bank_number = $this->get_bank_number( $order->get_id() );

		/**
		 * Check if there is bank number
		 */
		if ( empty( $bank_number ) ) {
			return;
		}

		/**
		 * Get Store Owner bank number
		 */
		$store_sinpe_number = $this->get_store_owner_bank_number();

		/**
		 * Build SMS message and link
		 */
		$total   = round( $order->get_total(), 0);
		$message = sprintf( __( 'Pase %s %s', 'mojito-sinpe' ), $total, $store_sinpe_number );

		printf(
			'<p>%s</p>',
			esc_html( sprintf( __( 'Send a SMS to %s with the content: %s', 'mojito-sinpe' ), $bank_number, $message ) )
		);

		/**
		 * The link address to website to prevent double payments. Also gmail blocks "sms" in href attribute.
		 */
		$link = add_query_arg( 'order', $order->get_id(), rest_url( 'mojito-sinpe/v1/open-payment-link/' ) );

		printf(
			'<p>%1$s <a href="%2$s">%3$s</a></p><br><br>',
			esc_html__( 'Are you on mobile? ', 'mojito-sinpe' ),
			esc_url( $link ),
			esc_html( apply_filters( 'mojito_sinpe_email_label', __( 'Pay here SINPE Móvil', 'mojito-sinpe' ) ) )
		);

	}

	/**
	 * Add SINPE link to Thank you page
	 */
	public function add_sinpe_link_to_thankyou_page( $order_id ) {

		if ( wp_doing_ajax() ) {
			return;
		}

		$settings = $this->get_mojito_sinpe_settings();

		if ( 'yes' !== $settings['show-in-thankyou-page'] ) {
			return;
		}

		/**
		 * Load Order data
		 */
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		/**
		 * Check if order is paid
		 */
		if ( $order->is_paid() ) {
			return;
		}
		$bank_number = $this->get_bank_number( $order->get_id() );

		/**
		 * Check if there is bank number
		 */
		if ( empty( $bank_number ) ) {
			return;
		}

		/**
		 * Get Store Owner bank number
		 */
		$store_sinpe_number = $this->get_store_owner_bank_number();

		/**
		 * Build SMS message and link
		 */
		$total   = round( $order->get_total(), 0 );
		$message = sprintf( __( 'Pase %s %s', 'mojito-sinpe' ), $total, $store_sinpe_number );

		printf(
			'<p>%s</p>',
			esc_html( sprintf( __( 'Send a SMS to %s with the content: %s', 'mojito-sinpe' ), $bank_number, $message ) )
		);

		/**
		 * If mobile, show the link
		 */
		$sinpe_gateway = new Mojito_Sinpe_Gateway();
		if ( $sinpe_gateway->is_mobile() ) {

			echo '<p>' . esc_html__( 'Are you on mobile?', 'mojito-sinpe' );

			/**
			 * The link address to website to prevent double payments. Also gmail blocks "sms" in href attribute.
			 */
			$link = add_query_arg( 'order', $order->get_id(), rest_url( 'mojito-sinpe/v1/open-payment-link/' ) );

			printf(
				' <a href="%1$s">%2$s</a><br><br>',
				esc_url( $link ),
				esc_html( apply_filters( 'mojito_sinpe_email_label', __( 'Pay here SINPE Móvil', 'mojito-sinpe' ) ) )
			);
		}
		
	}

	/**
	 * Get settings stores owner bank number
	 *
	 * @return string
	 */
	private function get_store_owner_bank_number() {
		$settings = $this->get_mojito_sinpe_settings();

		return isset( $settings['number'] ) ? $settings['number'] : '';
	}

	/**
	 * Get bank number
	 *
	 * @return string
	 */
	private function get_bank_number( $order_id ) {

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return '';
		}

		return Mojito_Sinpe_Gateway::get_bank_phone_number( $order->get_meta( 'mojito_sinpe_bank', true ) );

	}

	/**
	 * Get gateway settings with safe defaults.
	 *
	 * @return array<string,mixed>
	 */
	private function get_mojito_sinpe_settings() {
		$defaults = array(
			'number'                => '',
			'show-in-email'         => 'yes',
			'show-in-thankyou-page' => 'yes',
		);

		if ( empty( $this->mojito_sinpe_settings ) ) {
			$this->mojito_sinpe_settings = get_option( 'woocommerce_mojito_sinpe_gateway_settings', array() );
		}

		return wp_parse_args( $this->mojito_sinpe_settings, $defaults );
	}

	/**
	 * Load the WooCommerce payment gateway class when WooCommerce is ready.
	 *
	 * @return bool
	 */
	private function load_gateway_class() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return false;
		}

		if ( ! class_exists( __NAMESPACE__ . '\Mojito_Sinpe_Gateway' ) ) {
			require_once MOJITO_SINPE_DIR . 'includes/class-mojito-sinpe-gateway.php';
		}

		return true;
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Mojito_Sinpe_Loader. Orchestrates the hooks of the plugin.
	 * - Mojito_Sinpe_i18n. Defines internationalization functionality.
	 * - Mojito_Sinpe_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		if ( ! class_exists( 'Mojito_Sinpe_Loader' ) ) {
			require_once MOJITO_SINPE_DIR . 'includes/class-mojito-sinpe-loader.php';
		}		

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		if ( !class_exists( 'Mojito_Sinpe_i18n' ) ) {
			require_once MOJITO_SINPE_DIR . 'includes/class-mojito-sinpe-i18n.php';
		}

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */		
		if ( !class_exists( 'Mojito_Sinpe_Public' ) ) {
			require_once MOJITO_SINPE_DIR . 'public/class-mojito-sinpe-public.php';
		}

		/**
		 * Load Product Vendors Support
		 */
		/*
		if ( !class_exists('Mojito_Sinpe_Compatibility_Product_Vendors_Support' ) ) {
			require_once MOJITO_SINPE_DIR . 'includes/class-mojito-compatibility-product-vendors.php';
			$Product_Vendors_support = new Mojito_Sinpe_Compatibility_Product_Vendors_Support();
			$Product_Vendors_support->run();
		}
		*/


		$this->loader = new Mojito_Sinpe_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Mojito_Sinpe_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale()
	{

		$plugin_i18n = new Mojito_Sinpe_i18n();

		$this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks()
	{
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks()
	{

		$plugin_public = new Mojito_Sinpe_Public($this->get_plugin_name(), $this->get_version());

		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run()
	{

		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Mojito_Sinpe_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader()
	{
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version()
	{
		return $this->version;
	}

}
