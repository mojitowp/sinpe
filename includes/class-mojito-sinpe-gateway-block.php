<?php
/**
 * WooCommerce
 *
 * @link       https://mojitowp.com/
 * @since      1.1.1
  *
 * @package    Mojito_Sinpe
 * @subpackage Mojito_Sinpe/public
 * @author     Mojito Team <support@mojitowp.com>
 */

namespace Mojito_Sinpe;
use \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Mojito Sinpe Gateway Block
 */
class Mojito_Sinpe_Gateway_Block extends AbstractPaymentMethodType 
{
    private $gateway;
    protected $name = 'mojito-sinpe';

    public function initialize() {
        $this->settings = get_option( 'woocommerce_mojito_sinpe_gateway_settings', [] );
        $this->gateway = new Mojito_Sinpe_Gateway();
    }
    public function is_active() {
        return $this->gateway->is_available();
    }
    public function get_payment_method_script_handles() {
        wp_register_script(
            'moji-sinpe-checkout-js',
            plugin_dir_url(__FILE__) . 'checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
		return [ 'moji-sinpe-checkout-js' ];


    }
    public function get_payment_method_data() {
        return [
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => $this->get_supported_features(),
		];
    }

}