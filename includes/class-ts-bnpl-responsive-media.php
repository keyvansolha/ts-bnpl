<?php
/**
 * رسانه‌ی پاسخ‌گو برای لندینگ تصویری.
 *
 * @package TS_BNPL
 */

defined( 'ABSPATH' ) || exit;

/**
 * اعتبارسنجی شناسه‌های رسانه و ساخت picture بدون تشخیص فرمت با جاوااسکریپت.
 */
class TS_BNPL_Responsive_Media {

	/**
	 * ساختار خالی یک گروه رسانه.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'desktop_avif_id' => 0,
			'desktop_webp_id' => 0,
			'mobile_avif_id'  => 0,
			'mobile_webp_id'  => 0,
			'alt'             => '',
		);
	}

	/**
	 * پاکسازی یک گروه رسانه.
	 *
	 * @param mixed $raw داده‌ی ورودی.
	 *
	 * @return array<string,mixed>
	 */
	public static function normalize( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();

		return array(
			'desktop_avif_id' => self::attachment_id( isset( $raw['desktop_avif_id'] ) ? $raw['desktop_avif_id'] : 0, 'image/avif' ),
			'desktop_webp_id' => self::attachment_id( isset( $raw['desktop_webp_id'] ) ? $raw['desktop_webp_id'] : 0, 'image/webp' ),
			'mobile_avif_id'  => self::attachment_id( isset( $raw['mobile_avif_id'] ) ? $raw['mobile_avif_id'] : 0, 'image/avif' ),
			'mobile_webp_id'  => self::attachment_id( isset( $raw['mobile_webp_id'] ) ? $raw['mobile_webp_id'] : 0, 'image/webp' ),
			'alt'             => self::limit( sanitize_text_field( isset( $raw['alt'] ) ? $raw['alt'] : '' ), 220 ),
		);
	}

	/**
	 * آیا گروه یک fallback قابل نمایش دارد؟
	 *
	 * @param array<string,mixed> $media گروه پاکسازی‌شده.
	 *
	 * @return bool
	 */
	public static function is_renderable( $media ) {
		$media = self::normalize( $media );

		return $media['desktop_webp_id'] > 0 || $media['mobile_webp_id'] > 0;
	}

	/**
	 * ساخت مارک‌آپ picture.
	 *
	 * @param array<string,mixed> $media رسانه.
	 * @param array<string,mixed> $args  ویژگی‌های رندر.
	 *
	 * @return string
	 */
	public static function render( $media, $args = array() ) {
		$media = self::normalize( $media );
		$args  = wp_parse_args(
			$args,
			array(
				'class'         => '',
				'loading'       => 'lazy',
				'fetchpriority' => '',
				'sizes'         => '100vw',
				'mobile_media'  => '(max-width: 650px)',
				'decorative'    => false,
			)
		);

		$fallback_id = $media['desktop_webp_id'] > 0 ? $media['desktop_webp_id'] : $media['mobile_webp_id'];

		if ( $fallback_id <= 0 ) {
			return '';
		}

		$fallback = self::image_data( $fallback_id );

		if ( ! $fallback ) {
			return '';
		}

		$sources = array(
			array( $media['mobile_avif_id'], 'image/avif', $args['mobile_media'] ),
			array( $media['mobile_webp_id'], 'image/webp', $args['mobile_media'] ),
			array( $media['desktop_avif_id'], 'image/avif', '' ),
			array( $media['desktop_webp_id'], 'image/webp', '' ),
		);

		ob_start();
		?>
		<picture>
			<?php foreach ( $sources as $source ) : ?>
				<?php $data = $source[0] > 0 ? self::image_data( $source[0] ) : false; ?>
				<?php if ( $data ) : ?>
					<source
						type="<?php echo esc_attr( $source[1] ); ?>"
						<?php if ( '' !== $source[2] ) : ?>media="<?php echo esc_attr( $source[2] ); ?>"<?php endif; ?>
						srcset="<?php echo esc_url( $data['url'] ); ?>"
						sizes="<?php echo esc_attr( $args['sizes'] ); ?>"
						width="<?php echo esc_attr( (string) $data['width'] ); ?>"
						height="<?php echo esc_attr( (string) $data['height'] ); ?>"
					/>
				<?php endif; ?>
			<?php endforeach; ?>
			<img
				src="<?php echo esc_url( $fallback['url'] ); ?>"
				class="<?php echo esc_attr( $args['class'] ); ?>"
				alt="<?php echo esc_attr( $args['decorative'] ? '' : $media['alt'] ); ?>"
				width="<?php echo esc_attr( (string) $fallback['width'] ); ?>"
				height="<?php echo esc_attr( (string) $fallback['height'] ); ?>"
				loading="<?php echo esc_attr( 'eager' === $args['loading'] ? 'eager' : 'lazy' ); ?>"
				decoding="async"
				<?php if ( in_array( $args['fetchpriority'], array( 'high', 'low', 'auto' ), true ) ) : ?>fetchpriority="<?php echo esc_attr( $args['fetchpriority'] ); ?>"<?php endif; ?>
			/>
		</picture>
		<?php

		return trim( (string) ob_get_clean() );
	}

	/**
	 * پذیرش شناسه‌ی پیوست با MIME دقیق.
	 *
	 * @param mixed  $value شناسه.
	 * @param string $mime  MIME مورد انتظار.
	 *
	 * @return int
	 */
	private static function attachment_id( $value, $mime ) {
		$attachment_id = absint( $value );

		if (
			$attachment_id <= 0 ||
			'attachment' !== get_post_type( $attachment_id ) ||
			'trash' === get_post_status( $attachment_id ) ||
			$mime !== get_post_mime_type( $attachment_id ) ||
			! wp_get_attachment_url( $attachment_id )
		) {
			return 0;
		}

		return $attachment_id;
	}

	/**
	 * داده‌ی واقعی فایل پیوست.
	 *
	 * @param int $attachment_id شناسه.
	 *
	 * @return array<string,mixed>|false
	 */
	private static function image_data( $attachment_id ) {
		$image = wp_get_attachment_image_src( $attachment_id, 'full' );

		if ( ! is_array( $image ) || empty( $image[0] ) || empty( $image[1] ) || empty( $image[2] ) ) {
			return false;
		}

		return array(
			'url'    => (string) $image[0],
			'width'  => absint( $image[1] ),
			'height' => absint( $image[2] ),
		);
	}

	/**
	 * محدودکردن متن بدون وابستگی اجباری به mbstring.
	 *
	 * @param string $value متن.
	 * @param int    $length طول.
	 *
	 * @return string
	 */
	private static function limit( $value, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}
}

