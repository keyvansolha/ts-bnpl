<?php
/**
 * فیلدهای پیشخوان محصول.
 *
 * @package TS_BNPL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TS_BNPL_Admin
 */
class TS_BNPL_Admin {

	/**
	 * نام فیلد در محصول ساده.
	 */
	const FIELD_SIMPLE = '_bnpl_price';

	/**
	 * نام فیلد آرایه‌ای در متغیرها.
	 */
	const FIELD_VARIATION = 'variable_bnpl_price';

	/**
	 * ثبت هوک‌ها.
	 *
	 * @return void
	 */
	public static function init() {
		// محصول ساده.
		add_action( 'woocommerce_product_options_pricing', array( __CLASS__, 'render_simple_field' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_simple_field' ) );

		// متغیرها.
		add_action( 'woocommerce_variation_options_pricing', array( __CLASS__, 'render_variation_field' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation_field' ), 10, 2 );
	}

	/**
	 * برچسب فیلد.
	 *
	 * @return string
	 */
	private static function label() {
		return __( 'قیمت اقساطی (جدول ۶)', 'ts-bnpl' );
	}

	/**
	 * توضیح زیر فیلد.
	 *
	 * @return string
	 */
	private static function description() {
		return __( 'در صورت خالی بودن، این محصول به صورت اقساطی فروخته نمی‌شود', 'ts-bnpl' );
	}

	/**
	 * نمایش فیلد در تب «عمومی» محصول ساده.
	 *
	 * @return void
	 */
	public static function render_simple_field() {
		woocommerce_wp_text_input(
			array(
				'id'            => self::FIELD_SIMPLE,
				'value'         => self::stored_value( get_the_ID() ),
				'label'         => self::label() . ' (' . get_woocommerce_currency_symbol() . ')',
				'description'   => self::description(),
				'desc_tip'      => false,
				'data_type'     => 'price',
				'wrapper_class' => 'show_if_simple show_if_external',
			)
		);
	}

	/**
	 * ذخیره‌ی فیلد محصول ساده.
	 *
	 * نانس این درخواست پیش‌تر توسط WC_Admin_Meta_Boxes بررسی شده است؛
	 * اینجا فقط سطح دسترسی و وجود فیلد کنترل می‌شود.
	 *
	 * @param int $post_id شناسه‌ی محصول.
	 *
	 * @return void
	 */
	public static function save_simple_field( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id || ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::FIELD_SIMPLE ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$raw = wp_unslash( $_POST[ self::FIELD_SIMPLE ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		TS_BNPL_Data::set( $post_id, is_scalar( $raw ) ? $raw : '' );
	}

	/**
	 * نمایش فیلد در هر متغیر.
	 *
	 * @param int     $loop           ایندکس متغیر در فرم.
	 * @param array   $variation_data داده‌ی متغیر.
	 * @param WP_Post $variation      پست متغیر.
	 *
	 * @return void
	 */
	public static function render_variation_field( $loop, $variation_data, $variation ) {
		$variation_id = isset( $variation->ID ) ? absint( $variation->ID ) : 0;

		woocommerce_wp_text_input(
			array(
				'id'            => self::FIELD_VARIATION . '_' . $loop,
				'name'          => self::FIELD_VARIATION . '[' . $loop . ']',
				'value'         => self::stored_value( $variation_id ),
				'label'         => self::label() . ' (' . get_woocommerce_currency_symbol() . ')',
				'description'   => self::description(),
				'desc_tip'      => false,
				'data_type'     => 'price',
				'wrapper_class' => 'form-row form-row-full',
			)
		);
	}

	/**
	 * ذخیره‌ی فیلد هر متغیر.
	 *
	 * @param int $variation_id شناسه‌ی متغیر.
	 * @param int $i            ایندکس متغیر در فرم.
	 *
	 * @return void
	 */
	public static function save_variation_field( $variation_id, $i ) {
		$variation_id = absint( $variation_id );

		if ( ! $variation_id || ! current_user_can( 'edit_product', $variation_id ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ self::FIELD_VARIATION ] ) || ! is_array( $_POST[ self::FIELD_VARIATION ] ) ) {
			return;
		}

		$values = wp_unslash( $_POST[ self::FIELD_VARIATION ] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! array_key_exists( $i, $values ) ) {
			return;
		}

		$raw = $values[ $i ];

		TS_BNPL_Data::set( $variation_id, is_scalar( $raw ) ? $raw : '' );
	}

	/**
	 * مقدار ذخیره‌شده برای نمایش در فرم.
	 *
	 * مقدار خام برگردانده می‌شود؛ محلی‌سازی جداکننده‌ی اعشار را خودِ
	 * woocommerce_wp_text_input با data_type = price انجام می‌دهد.
	 * مقدار صفر به صورت فیلد خالی نمایش داده می‌شود تا «بدون قیمت اقساطی»
	 * از «قیمت اقساطی صفر» قابل تفکیک باشد.
	 *
	 * @param int $product_id شناسه‌ی محصول یا متغیر.
	 *
	 * @return string
	 */
	private static function stored_value( $product_id ) {
		$value = TS_BNPL_Data::get( $product_id );

		return $value > 0 ? wc_format_decimal( $value ) : '';
	}
}
