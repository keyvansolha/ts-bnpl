<?php
/**
 * تنظیمات کنترل‌شده‌ی لندینگ تصویری.
 *
 * @package TS_BNPL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings storage and normalization.
 */
class TS_BNPL_Visual_Settings {

	/** نام آپشن مستقل و غیر autoload. */
	const OPTION = 'ts_bnpl_visual_landing_settings';

	/** نسخه‌ی ساختار. */
	const SCHEMA_VERSION = 1;

	/** حداکثر تعداد ردیف تکرارشونده. */
	const MAX_ROWS = 20;

	/**
	 * پیش‌فرض‌های factual و بدون وعده‌ی مالی.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		$media = TS_BNPL_Responsive_Media::defaults();

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'banners'       => array(),
			'hero'          => array(
				'eyebrow'         => 'خرید اعتباری',
				'title'           => 'خرید اعتباری از تهران اسپیکر',
				'description'     => 'کالای موردنظرتان را انتخاب کنید و در مرحله‌ی پرداخت، یکی از روش‌های اعتباری فعال را برگزینید. کالا مثل هر خرید دیگری برایتان ارسال می‌شود.',
				'benefits'        => array(
					'بدون مسیر خرید جداگانه',
					'تحویل کالا مثل خرید عادی',
					'مشاهده شرایط پرداخت پیش از تأیید نهایی',
				),
				'primary_label'   => 'مشاهده محصولات قابل خرید اعتباری',
				'primary_url'     => '#ts-bnpl-visual-products',
				'secondary_label' => 'مراحل خرید',
				'secondary_url'   => '#ts-bnpl-visual-how',
				'media'           => $media,
			),
			'providers'     => array(
				array(
					'provider_id'     => TS_BNPL_GATEWAY_ID,
					'display_enabled' => true,
					'display_name'    => 'دیجی‌پی',
					'logo_id'         => 0,
					'description'     => 'سرویس پرداخت اعتباری فعال تهران اسپیکر',
					'url'             => '',
				),
			),
			'eligibility'   => array(
				'title'       => 'از کجا بفهمم یک کالا قابل خرید اعتباری است؟',
				'description' => 'اگر کالا امکان خرید اعتباری داشته باشد، نشان آن نزدیک قیمت و دکمه‌ی افزودن به سبد خرید دیده می‌شود. این امکان ممکن است میان مدل‌ها یا رنگ‌های یک کالا متفاوت باشد.',
				'media'       => $media,
			),
			'conditions'    => array(
				'title'       => 'درباره‌ی مبلغ نهایی',
				'lead'        => 'قیمتی که در صفحه‌ی کالا می‌بینید، قیمت خرید نقدی است. اگر پرداخت را با یک روش اعتباری انجام دهید، مبلغ نهایی می‌تواند با این عدد متفاوت باشد.',
				'description' => 'مبلغ دقیق و شرایط پرداخت، در مرحله‌ی تسویه‌حساب و بر اساس روش اعتباری‌ای که انتخاب می‌کنید مشخص و به شما نمایش داده می‌شود. پیش از تأیید نهایی، فرصت دارید مبلغ و شرایط را ببینید و در صورت تمایل به پرداخت نقدی برگردید.',
				'media'       => $media,
			),
			'final_cta'     => array(
				'title'       => 'محصول موردنظرت رو پیدا کردی؟',
				'description' => 'کالاهای واجد شرایط را ببینید و خریدتان را مثل همیشه شروع کنید.',
				'label'       => 'مشاهده محصولات',
				'url'         => '#ts-bnpl-visual-products',
				'media'       => $media,
			),
		);
	}

	/**
	 * خواندن تنظیمات با پیش‌فرض‌های read-time.
	 *
	 * @return array<string,mixed>
	 */
	public static function get() {
		$stored = get_option( self::OPTION, null );

		if ( ! is_array( $stored ) ) {
			return self::defaults();
		}

		$normalized = self::sanitize( $stored, self::provider_ids( $stored ) );

		return is_wp_error( $normalized ) ? self::defaults() : $normalized;
	}

	/**
	 * ذخیره‌ی اتمیک داده‌ی پاکسازی‌شده.
	 *
	 * @param mixed $raw ورودی.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function save( $raw ) {
		$stored     = get_option( self::OPTION, array() );
		$normalized = self::sanitize( $raw, self::provider_ids( $stored ) );

		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $normalized, '', 'no' );
		} else {
			update_option( self::OPTION, $normalized, false );
		}

		return $normalized;
	}

	/**
	 * پاکسازی کامل payload.
	 *
	 * @param mixed    $raw                  ورودی.
	 * @param string[] $trusted_provider_ids شناسه‌هایی که قبلاً ذخیره شده‌اند.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function sanitize( $raw, $trusted_provider_ids = array() ) {
		if ( ! is_array( $raw ) ) {
			return new WP_Error( 'ts_bnpl_visual_invalid_payload', __( 'ساختار تنظیمات لندینگ تصویری معتبر نیست.', 'ts-bnpl' ) );
		}
		if ( isset( $raw['schema_version'] ) && self::SCHEMA_VERSION !== (int) $raw['schema_version'] ) {
			return new WP_Error( 'ts_bnpl_visual_unsupported_schema', __( 'نسخه‌ی ساختار تنظیمات لندینگ تصویری پشتیبانی نمی‌شود.', 'ts-bnpl' ) );
		}

		$defaults = self::defaults();

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'banners'       => self::sanitize_banners( isset( $raw['banners'] ) && is_array( $raw['banners'] ) ? $raw['banners'] : $defaults['banners'] ),
			'hero'          => self::sanitize_hero( isset( $raw['hero'] ) && is_array( $raw['hero'] ) ? $raw['hero'] : array(), $defaults['hero'] ),
			'providers'     => self::sanitize_providers( isset( $raw['providers'] ) && is_array( $raw['providers'] ) ? $raw['providers'] : $defaults['providers'], $trusted_provider_ids ),
			'eligibility'   => self::sanitize_content_section( isset( $raw['eligibility'] ) ? $raw['eligibility'] : array(), $defaults['eligibility'] ),
			'conditions'    => self::sanitize_conditions( isset( $raw['conditions'] ) ? $raw['conditions'] : array(), $defaults['conditions'] ),
			'final_cta'     => self::sanitize_cta( isset( $raw['final_cta'] ) ? $raw['final_cta'] : array(), $defaults['final_cta'] ),
		);
	}

	/** @return array<int,array<string,mixed>> */
	private static function sanitize_banners( $rows ) {
		$clean = array();
		foreach ( array_slice( array_values( $rows ), 0, self::MAX_ROWS ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean[] = array(
				'media' => TS_BNPL_Responsive_Media::normalize( isset( $row['media'] ) ? $row['media'] : array() ),
				'url'   => self::url( isset( $row['url'] ) ? $row['url'] : '' ),
			);
		}
		return array_values( $clean );
	}

	/** @return array<string,mixed> */
	private static function sanitize_hero( $raw, $defaults ) {
		$raw      = is_array( $raw ) ? $raw : array();
		$benefits = isset( $raw['benefits'] ) && is_array( $raw['benefits'] ) ? $raw['benefits'] : $defaults['benefits'];
		$benefits = array_slice( array_map( array( __CLASS__, 'short_text' ), array_values( $benefits ) ), 0, 3 );
		while ( count( $benefits ) < 3 ) {
			$benefits[] = '';
		}

		return array(
			'eyebrow'         => self::short_text( isset( $raw['eyebrow'] ) ? $raw['eyebrow'] : $defaults['eyebrow'] ),
			'title'           => self::short_text( isset( $raw['title'] ) ? $raw['title'] : $defaults['title'] ),
			'description'     => self::long_text( isset( $raw['description'] ) ? $raw['description'] : $defaults['description'] ),
			'benefits'        => $benefits,
			'primary_label'   => self::short_text( isset( $raw['primary_label'] ) ? $raw['primary_label'] : $defaults['primary_label'] ),
			'primary_url'     => self::url( isset( $raw['primary_url'] ) ? $raw['primary_url'] : $defaults['primary_url'] ),
			'secondary_label' => self::short_text( isset( $raw['secondary_label'] ) ? $raw['secondary_label'] : $defaults['secondary_label'] ),
			'secondary_url'   => self::url( isset( $raw['secondary_url'] ) ? $raw['secondary_url'] : $defaults['secondary_url'] ),
			'media'           => TS_BNPL_Responsive_Media::normalize( isset( $raw['media'] ) ? $raw['media'] : array() ),
		);
	}

	/** @return array<int,array<string,mixed>> */
	private static function sanitize_providers( $rows, $trusted_provider_ids = array() ) {
		$clean   = array();
		$seen    = array();
		$choices = class_exists( 'TS_BNPL_Providers' ) ? TS_BNPL_Providers::choices() : array();
		$known   = array_keys( $choices );
		$known[] = TS_BNPL_GATEWAY_ID;
		$known   = array_merge( $known, array_map( 'sanitize_key', is_array( $trusted_provider_ids ) ? $trusted_provider_ids : array() ) );
		$known   = array_values( array_unique( array_filter( $known ) ) );

		foreach ( array_slice( array_values( $rows ), 0, self::MAX_ROWS ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$provider_id = sanitize_key( isset( $row['provider_id'] ) ? $row['provider_id'] : '' );
			if ( '' === $provider_id || isset( $seen[ $provider_id ] ) || ! in_array( $provider_id, $known, true ) ) {
				continue;
			}
			$seen[ $provider_id ] = true;
			$clean[] = array(
				'provider_id'     => $provider_id,
				'display_enabled' => ! empty( $row['display_enabled'] ),
				'display_name'    => self::short_text( isset( $row['display_name'] ) ? $row['display_name'] : '' ),
				'logo_id'         => self::logo_id( isset( $row['logo_id'] ) ? $row['logo_id'] : 0 ),
				'description'     => self::long_text( isset( $row['description'] ) ? $row['description'] : '' ),
				'url'             => self::url( isset( $row['url'] ) ? $row['url'] : '' ),
			);
		}

		return array_values( $clean );
	}

	/**
	 * شناسه‌های ذخیره‌شده قبلی برای حفظ ردیف هنگام قطع موقت یک درگاه.
	 *
	 * این فهرست فقط از option قبلی می‌آید؛ یک شناسه‌ی آزاد در درخواست جدید
	 * همچنان رد می‌شود.
	 *
	 * @param mixed $settings تنظیمات ذخیره‌شده.
	 *
	 * @return string[]
	 */
	private static function provider_ids( $settings ) {
		$rows = is_array( $settings ) && isset( $settings['providers'] ) && is_array( $settings['providers'] )
			? $settings['providers']
			: array();
		$ids = array();

		foreach ( $rows as $row ) {
			$id = is_array( $row ) && isset( $row['provider_id'] ) ? sanitize_key( $row['provider_id'] ) : '';
			if ( '' !== $id ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/** @return array<string,mixed> */
	private static function sanitize_content_section( $raw, $defaults ) {
		$raw = is_array( $raw ) ? $raw : array();
		return array(
			'title'       => self::short_text( isset( $raw['title'] ) ? $raw['title'] : $defaults['title'] ),
			'description' => self::long_text( isset( $raw['description'] ) ? $raw['description'] : $defaults['description'] ),
			'media'       => TS_BNPL_Responsive_Media::normalize( isset( $raw['media'] ) ? $raw['media'] : array() ),
		);
	}

	/** @return array<string,mixed> */
	private static function sanitize_conditions( $raw, $defaults ) {
		$raw = is_array( $raw ) ? $raw : array();
		return array(
			'title'       => self::short_text( isset( $raw['title'] ) ? $raw['title'] : $defaults['title'] ),
			'lead'        => self::long_text( isset( $raw['lead'] ) ? $raw['lead'] : $defaults['lead'] ),
			'description' => self::long_text( isset( $raw['description'] ) ? $raw['description'] : $defaults['description'] ),
			'media'       => TS_BNPL_Responsive_Media::normalize( isset( $raw['media'] ) ? $raw['media'] : array() ),
		);
	}

	/** @return array<string,mixed> */
	private static function sanitize_cta( $raw, $defaults ) {
		$raw = is_array( $raw ) ? $raw : array();
		return array(
			'title'       => self::short_text( isset( $raw['title'] ) ? $raw['title'] : $defaults['title'] ),
			'description' => self::long_text( isset( $raw['description'] ) ? $raw['description'] : $defaults['description'] ),
			'label'       => self::short_text( isset( $raw['label'] ) ? $raw['label'] : $defaults['label'] ),
			'url'         => self::url( isset( $raw['url'] ) ? $raw['url'] : $defaults['url'] ),
			'media'       => TS_BNPL_Responsive_Media::normalize( isset( $raw['media'] ) ? $raw['media'] : array() ),
		);
	}

	/** @return int */
	private static function logo_id( $value ) {
		$attachment_id = absint( $value );
		if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) || 'trash' === get_post_status( $attachment_id ) ) {
			return 0;
		}
		$allowed = array( 'image/png', 'image/webp', 'image/jpeg' );
		return in_array( get_post_mime_type( $attachment_id ), $allowed, true ) && wp_get_attachment_url( $attachment_id ) ? $attachment_id : 0;
	}

	/** @return string */
	private static function short_text( $value ) {
		return self::limit( sanitize_text_field( $value ), 220 );
	}

	/** @return string */
	private static function long_text( $value ) {
		return self::limit( sanitize_textarea_field( $value ), 1200 );
	}

	/** @return string */
	private static function url( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( 0 === strpos( $value, '#' ) && preg_match( '/^#[A-Za-z][A-Za-z0-9_\-:.]*$/', $value ) ) {
			return $value;
		}
		if ( 0 === strpos( $value, '/' ) && 0 !== strpos( $value, '//' ) ) {
			return esc_url_raw( $value );
		}
		return preg_match( '#^https?://#i', $value ) ? esc_url_raw( $value ) : '';
	}

	/** @return string */
	private static function limit( $value, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}
}
