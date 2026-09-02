<?php
/**
 * اتصال نمایش ارائه‌دهندگان به وضعیت واقعی درگاه‌های ووکامرس.
 *
 * @package TS_BNPL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provider registry for the Visual Landing.
 */
class TS_BNPL_Providers {

	/**
	 * درگاه‌های اقساطی ثبت‌شده که مدیر می‌تواند برایشان ارائه بسازد.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function choices() {
		$choices = array();

		foreach ( self::gateways() as $gateway_id => $gateway ) {
			if ( ! self::is_installment( $gateway ) ) {
				continue;
			}

			$name = '';
			if ( method_exists( $gateway, 'get_title' ) ) {
				$name = (string) $gateway->get_title();
			} elseif ( isset( $gateway->title ) ) {
				$name = (string) $gateway->title;
			} elseif ( isset( $gateway->method_title ) ) {
				$name = (string) $gateway->method_title;
			}

			$choices[ $gateway_id ] = array(
				'id'      => $gateway_id,
				'name'    => '' !== trim( $name ) ? trim( $name ) : $gateway_id,
				'gateway' => $gateway,
			);
		}

		return $choices;
	}

	/**
	 * ارائه‌دهندگان قابل نمایش عمومی.
	 *
	 * @param mixed $entries تنظیمات ارائه.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function public_entries( $entries ) {
		if ( ! is_array( $entries ) || self::is_test_mode() ) {
			return array();
		}

		$choices = self::choices();
		$public  = array();
		$seen    = array();

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$provider_id = isset( $entry['provider_id'] ) ? sanitize_key( $entry['provider_id'] ) : '';

			if (
				'' === $provider_id ||
				empty( $entry['display_enabled'] ) ||
				isset( $seen[ $provider_id ] ) ||
				! isset( $choices[ $provider_id ] )
			) {
				continue;
			}

			$gateway = $choices[ $provider_id ]['gateway'];

			if ( ! self::is_operational( $gateway ) ) {
				continue;
			}

			$seen[ $provider_id ] = true;
			$entry['provider_id'] = $provider_id;
			$entry['gateway']     = $gateway;
			$entry['fallback_logo_url'] = TS_BNPL_GATEWAY_ID === $provider_id
				? TS_BNPL_URL . 'assets/images/digipay.svg'
				: '';
			$public[] = $entry;
		}

		return $public;
	}

	/**
	 * رجیستری واقعی ووکامرس.
	 *
	 * @return array<string,WC_Payment_Gateway>
	 */
	private static function gateways() {
		if ( ! function_exists( 'WC' ) || ! WC() || ! method_exists( WC(), 'payment_gateways' ) ) {
			return array();
		}

		$manager = WC()->payment_gateways();

		if ( ! $manager || ! method_exists( $manager, 'payment_gateways' ) ) {
			return array();
		}

		$gateways = $manager->payment_gateways();

		return is_array( $gateways ) ? $gateways : array();
	}

	/**
	 * آیا درگاه واقعاً اقساطی معرفی شده است؟
	 *
	 * @param mixed $gateway درگاه.
	 *
	 * @return bool
	 */
	private static function is_installment( $gateway ) {
		if ( ! is_object( $gateway ) || empty( $gateway->id ) ) {
			return false;
		}

		$is_installment = TS_BNPL_GATEWAY_ID === (string) $gateway->id;

		if ( function_exists( 'wbs_checkout_gateway_is_installment' ) ) {
			$is_installment = (bool) wbs_checkout_gateway_is_installment( $gateway );
		}

		return (bool) apply_filters( 'ts_bnpl_provider_is_installment', $is_installment, $gateway );
	}

	/**
	 * وضعیت عمومی درگاه.
	 *
	 * @param mixed $gateway درگاه.
	 *
	 * @return bool
	 */
	private static function is_operational( $gateway ) {
		if ( ! is_object( $gateway ) || ! isset( $gateway->enabled ) || 'yes' !== $gateway->enabled ) {
			return false;
		}

		if ( ! method_exists( $gateway, 'is_available' ) ) {
			return true;
		}

		try {
			return (bool) $gateway->is_available();
		} catch ( Throwable $error ) {
			return false;
		}
	}

	/**
	 * حالت آزمایشی نباید به‌عنوان سرویس عمومی فعال معرفی شود.
	 *
	 * @return bool
	 */
	private static function is_test_mode() {
		return class_exists( 'TS_BNPL_Checkout' ) && method_exists( 'TS_BNPL_Checkout', 'is_test_mode' )
			? (bool) TS_BNPL_Checkout::is_test_mode()
			: false;
	}
}

