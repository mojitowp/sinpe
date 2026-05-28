<?php

namespace {
	define( 'MOJITO_SINPE_DIR', '' );
	define( 'WC_PRODUCT_VENDORS_TAXONOMY', 'wcpv_product_vendors' );

	class WC_Cart {
		/** @var float|int|string */
		public $total = 0;

		public function empty_cart(): void {}
	}

	class WC_Global {
		/** @var WC_Cart|null */
		public $cart;
	}

	class WC_Order {
		public function get_id(): int {}
		public function get_payment_method(): string {}
		public function has_status( string $status ): bool {}
		public function is_paid(): bool {}
		/** @return float|int|string */
		public function get_total() {}
		public function update_meta_data( string $key, $value ): void {}
		public function get_meta( string $key, bool $single = true ) {}
		public function add_order_note( string $note ): void {}
		public function update_status( string $new_status, string $note = '' ): void {}
		public function save(): void {}
	}

	class WC_Payment_Gateway {
		/** @var string */
		public $id = '';
		/** @var bool */
		public $has_fields = false;
		/** @var array<int,string> */
		public $supports = array();
		/** @var string */
		public $method_title = '';
		/** @var string */
		public $method_description = '';
		/** @var array<string,mixed> */
		public $settings = array();
		/** @var array<string,mixed> */
		public $form_fields = array();
		/** @var string */
		public $enabled = 'yes';
		/** @var string */
		public $icon = '';
		/** @var string */
		public $title = '';
		/** @var string */
		public $description = '';

		public function init_settings(): void {}
		public function get_option( string $key, $empty_value = null ) {}
		public function get_description(): string {}
		public function process_admin_options(): bool {}
		public function get_return_url( WC_Order $order ): string {}
		public function is_available(): bool {}
	}

	function WC(): WC_Global {}

	/** @return WC_Order|false */
	function wc_get_order( $order_id ) {}

	function wc_add_notice( string $message, string $notice_type = 'success' ): void {}

	function is_checkout(): bool {}
}

namespace Automattic\WooCommerce\Blocks\Payments\Integrations {
	abstract class AbstractPaymentMethodType {
		/** @var array<string,mixed> */
		protected $settings = array();
		/** @var string */
		protected $name = '';

		public function get_setting( string $name, $default = null ) {}
		/** @return array<int,string> */
		public function get_supported_features(): array {}
	}
}

namespace Automattic\WooCommerce\Blocks\Payments {
	class PaymentMethodRegistry {
		public function register( $payment_method_type ): void {}
	}
}
