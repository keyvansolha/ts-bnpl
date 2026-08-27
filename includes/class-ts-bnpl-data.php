<?php
/**
 * لایه‌ی داده.
 *
 * تنها نقطه‌ی خواندن و نوشتن متای _bnpl_price در کل افزونه.
 * اسکریپت سینک بیرونی هم فقط باید TS_BNPL_Data::set() را صدا بزند.
 *
 * @package TS_BNPL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TS_BNPL_Data
 */
class TS_BNPL_Data {

	/**
	 * کلید متای قیمت اقساطی.
	 */
	const META = '_bnpl_price';

	/**
	 * ثبت هوک‌ها.
	 *
	 * این کلاس عمداً هیچ هوکی ثبت نمی‌کند؛ فقط یک لایه‌ی خالص داده است.
	 * متد برای یکدست ماندن قرارداد کلاس‌ها نگه داشته شده.
	 *
	 * @return void
	 */
	public static function init() {
	}

	/**
	 * خواندن قیمت اقساطی یک محصول.
	 *
	 * تنها نقطه‌ی خواندن این متا در کل افزونه.
	 *
	 * @param WC_Product|int $product آبجکت محصول یا شناسه‌ی عددی.
	 *
	 * @return float|int مقدار float در صورت معتبر بودن، در غیر این صورت 0.
	 */
	public static function get( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( absint( $product ) );
		}

		if ( ! $product instanceof WC_Product ) {
			return 0;
		}

		$raw = $product->get_meta( self::META, true );

		if ( is_array( $raw ) || is_object( $raw ) ) {
			return 0;
		}

		$raw = is_string( $raw ) ? trim( $raw ) : $raw;

		if ( '' === $raw || null === $raw || ! is_numeric( $raw ) ) {
			return 0;
		}

		$value = (float) $raw;

		return $value > 0 ? $value : 0;
	}

	/**
	 * نوشتن قیمت اقساطی یک محصول.
	 *
	 * تنها نقطه‌ی نوشتن این متا در کل افزونه.
	 * عمداً از update_post_meta استفاده می‌شود و نه از $product->save()، چون
	 * سینک انبوه نباید چرخه‌ی کامل ذخیره‌ی محصول ووکامرس را برای هزاران آیتم
	 * اجرا کند. متای اختصاصی ما هیچ ترنزینتی از ووکامرس را بی‌اعتبار نمی‌کند.
	 *
	 * @param int   $product_id شناسه‌ی محصول یا متغیر.
	 * @param mixed $value      مقدار خام.
	 *
	 * @return bool موفقیت عملیات.
	 */
	public static function set( $product_id, $value ) {
		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return false;
		}

		$clean = wc_format_decimal( $value );

		if ( '' !== $clean && null !== $clean && (float) $clean > 0 ) {
			return (bool) update_post_meta( $product_id, self::META, $clean );
		}

		return (bool) delete_post_meta( $product_id, self::META );
	}

	/**
	 * محاسبه‌ی مبلغ هر قسط.
	 *
	 * تمام منطق گرد کردن باید در همین متد ایزوله بماند. در این مرحله عمداً
	 * هیچ گرد کردنی انجام نمی‌شود و فقط تقسیم ساده صورت می‌گیرد.
	 *
	 * @param float $total  مبلغ کل اقساطی.
	 * @param int   $months تعداد اقساط.
	 *
	 * @return float مبلغ هر قسط.
	 */
	public static function installment( $total, $months = TS_BNPL_MONTHS ) {
		$total  = (float) $total;
		$months = (int) $months;

		if ( $months <= 0 || $total <= 0 ) {
			return 0;
		}

		$amount = $total / $months;

		// نقطه‌ی ایزوله‌ی گرد کردن: هر منطق گرد کردنی در آینده فقط اینجا اضافه شود.
		return $amount;
	}
}
