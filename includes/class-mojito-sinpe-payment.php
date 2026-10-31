<?php
/**
 * Authoritative amounts and SMS instructions shared by all checkout surfaces.
 *
 * @package Mojito_Sinpe
 */

namespace Mojito_Sinpe;

defined( 'ABSPATH' ) || exit;

class Mojito_Sinpe_Payment {
	/** Normalize a Costa Rican SINPE mobile number. */
	public static function phone( $number ): string {
		if ( ! is_scalar( $number ) ) {
			return '';
		}
		$number = preg_replace( '/[\s()-]/', '', (string) $number );
		return preg_match( '/^(?:\+?506)?([5-8][0-9]{7})$/D', $number, $matches ) ? $matches[1] : '';
	}

	/** Calculate an amount in CRC; a failed conversion never falls back to 1. */
	public static function calculate( $total, string $currency, array $settings ) {
		if ( ! is_numeric( $total ) || ! is_finite( (float) $total ) || (float) $total < 0 || ! in_array( $currency, array( 'CRC', 'USD' ), true ) ) {
			return new \WP_Error( 'sinpe_currency', __( 'SINPE supports CRC and USD with a valid conversion rate.', 'mojito-sinpe' ) );
		}
		$rate = 1.0;
		if ( 'USD' === $currency ) {
			if ( 'yes' !== ( $settings['exchange-rate-enable'] ?? 'yes' ) ) {
				return new \WP_Error( 'sinpe_rate', __( 'Enable a valid USD to CRC conversion rate to use SINPE.', 'mojito-sinpe' ) );
			}
			$rate = 'custom' === ( $settings['exchange-rate-origin'] ?? 'hacienda' ) ? ( $settings['exchange-rate-custom'] ?? '' ) : self::hacienda_rate();
			if ( is_wp_error( $rate ) ) {
				return $rate;
			}
			$rate = apply_filters( 'mojito_sinpe_exchange_rate', $rate );
		}
		if ( ! is_numeric( $rate ) || ! is_finite( (float) $rate ) || (float) $rate <= 0 ) {
			return new \WP_Error( 'sinpe_rate', __( 'The SINPE exchange rate is unavailable. Please try again later.', 'mojito-sinpe' ) );
		}
		$amount = apply_filters( 'mojito_sinpe_amount', round( (float) $total * (float) $rate, 0 ) );
		if ( ! is_numeric( $amount ) || ! is_finite( (float) $amount ) || (float) $amount < 0 ) {
			return new \WP_Error( 'sinpe_amount', __( 'The SINPE amount is invalid.', 'mojito-sinpe' ) );
		}
		$recipient = self::phone( $settings['number'] ?? '' );
		if ( '' === $recipient ) {
			return new \WP_Error( 'sinpe_phone', __( 'The store SINPE phone number is invalid.', 'mojito-sinpe' ) );
		}
		return array(
			'currency'  => $currency,
			'total'     => wc_format_decimal( $total, 8 ),
			'rate'      => (float) $rate,
			'amount'    => number_format( round( (float) $amount, 0 ), 0, '.', '' ),
			'recipient' => $recipient,
		);
	}

	/** Retrieve only a validated rate, caching successful responses for six hours. */
	private static function hacienda_rate() {
		$cached = get_transient( 'mojito_sinpe_hacienda_rate_v1' );
		if ( is_numeric( $cached ) && is_finite( (float) $cached ) && (float) $cached > 0 ) {
			return (float) $cached;
		}
		// Brief failure cache prevents repeated five-second waits within checkout refreshes.
		if ( get_transient( 'mojito_sinpe_hacienda_failure_v1' ) ) {
			return new \WP_Error( 'sinpe_rate', __( 'The SINPE exchange rate is unavailable. Please try again later.', 'mojito-sinpe' ) );
		}
		$response = wp_remote_get(
			'https://api.hacienda.go.cr/indicadores/tc',
			array(
				'timeout'             => 5,
				'redirection'         => 0,
				'limit_response_size' => 32768,
			)
		);
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			$rate = is_array( $data ) ? ( $data['dolar']['venta']['valor'] ?? null ) : null;
			if ( is_numeric( $rate ) && is_finite( (float) $rate ) && (float) $rate > 0 ) {
				set_transient( 'mojito_sinpe_hacienda_rate_v1', (float) $rate, 6 * HOUR_IN_SECONDS );
				return (float) $rate;
			}
		}
		set_transient( 'mojito_sinpe_hacienda_failure_v1', 1, MINUTE_IN_SECONDS );
		return new \WP_Error( 'sinpe_rate', __( 'The SINPE exchange rate is unavailable. Please try again later.', 'mojito-sinpe' ) );
	}

	/** Read instructions only when the saved quote still matches the order. */
	public static function for_order( \WC_Order $order, array $settings ) {
		$snapshot = $order->get_meta( '_mojito_sinpe_payment', true );
		if ( is_array( $snapshot ) && isset( $snapshot['currency'], $snapshot['total'], $snapshot['rate'], $snapshot['amount'], $snapshot['recipient'] ) ) {
			// Compare stored totals independently of the store's display decimal setting.
			if ( $snapshot['currency'] === $order->get_currency()
				&& wc_format_decimal( $snapshot['total'], 8 ) === wc_format_decimal( $order->get_total(), 8 )
				&& self::phone( $snapshot['recipient'] )
				&& is_numeric( $snapshot['amount'] ) && is_finite( (float) $snapshot['amount'] ) && (float) $snapshot['amount'] >= 0
				&& is_numeric( $snapshot['rate'] ) && is_finite( (float) $snapshot['rate'] ) && (float) $snapshot['rate'] > 0
			) {
				return $snapshot;
			}
			return new \WP_Error( 'sinpe_changed', __( 'The order changed. Ask the store to regenerate SINPE instructions.', 'mojito-sinpe' ), array( 'status' => 409 ) );
		}
		if ( 'CRC' === $order->get_currency() ) {
			return self::calculate( $order->get_total(), 'CRC', $settings );
		}
		return new \WP_Error( 'sinpe_legacy', __( 'Ask the store to regenerate SINPE instructions for this older USD order.', 'mojito-sinpe' ), array( 'status' => 409 ) );
	}

	/** SMS commands are protocol tokens, not translated prose. */
	public static function message( array $snapshot ): string {
		return 'Pase ' . $snapshot['amount'] . ' ' . $snapshot['recipient'];
	}

	/** Human-readable instructions, including the banking-app fallback. */
	public static function instructions( array $snapshot, string $bank_number = '' ): string {
		if ( '' !== $bank_number ) {
			// translators: 1: bank SMS service number, 2: SMS command.
			return sprintf( __( 'Send an SMS to %1$s with the content: %2$s', 'mojito-sinpe' ), $bank_number, self::message( $snapshot ) );
		}
		// translators: 1: amount in CRC, 2: store SINPE phone number.
		return sprintf( __( 'Send %1$s CRC to SINPE phone %2$s using your banking app.', 'mojito-sinpe' ), $snapshot['amount'], $snapshot['recipient'] );
	}

	/** Keep short service codes intact; + is not an international prefix for them. */
	public static function sms_url( string $bank_number, string $message, ?string $user_agent = null ): string {
		if ( ! preg_match( '/^[0-9]{4,8}$/D', $bank_number ) ) {
			return '';
		}
		$user_agent = $user_agent ?? ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' );
		$separator  = preg_match( '/iPhone|iPad|iPod/i', $user_agent ) ? '&' : '?';
		return 'sms:' . $bank_number . $separator . 'body=' . rawurlencode( $message );
	}
}
