<?php
/**
 * مدیریت اختصاصی لندینگ تصویری در پیشخوان.
 *
 * @package TS_BNPL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dedicated, controlled Visual Landing manager.
 */
class TS_BNPL_Visual_Admin {

	/** اسلاگ زیرمنو. */
	const PAGE_SLUG = 'ts-bnpl-visual-landing';

	/** سطح دسترسی. */
	const CAPABILITY = 'manage_woocommerce';

	/** اکشن ذخیره. */
	const SAVE_ACTION = 'ts_bnpl_save_visual_landing';

	/**
	 * ثبت هوک‌ها.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * افزودن زیرمنو کنار گزارش اقساط.
	 *
	 * @return void
	 */
	public static function register_page() {
		add_submenu_page(
			'edit.php?post_type=product',
			__( 'لندینگ تصویری خرید اعتباری', 'ts-bnpl' ),
			__( 'لندینگ تصویری (آزمایشی)', 'ts-bnpl' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * دارایی‌ها فقط در همین صفحه.
	 *
	 * @param string $hook_suffix شناسه‌ی صفحه.
	 *
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( 'product_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'ts-bnpl-visual-admin',
			TS_BNPL_URL . 'assets/css/ts-bnpl-visual-admin.css',
			array(),
			TS_BNPL_VERSION
		);
		wp_enqueue_script(
			'ts-bnpl-visual-admin',
			TS_BNPL_URL . 'assets/js/ts-bnpl-visual-admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			TS_BNPL_VERSION,
			true
		);
		wp_localize_script(
			'ts-bnpl-visual-admin',
			'tsBnplVisualAdmin',
			array(
				'mediaTitle'  => __( 'انتخاب فایل رسانه', 'ts-bnpl' ),
				'mediaButton' => __( 'استفاده از این فایل', 'ts-bnpl' ),
				'removeRow'   => __( 'حذف این ردیف؟', 'ts-bnpl' ),
				'invalidMedia' => __( 'نوع فایل با این جایگاه سازگار نیست. فایل AVIF یا WebP درست را انتخاب کنید.', 'ts-bnpl' ),
			)
		);
	}

	/**
	 * صفحه‌ی مدیریت.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$settings      = TS_BNPL_Visual_Settings::get();
		$choices       = TS_BNPL_Providers::choices();
		$invalid_media = self::invalid_media_count( get_option( TS_BNPL_Visual_Settings::OPTION, array() ) );
		?>
		<div class="wrap ts-bnpl-visual-admin" dir="rtl">
			<h1><?php esc_html_e( 'لندینگ تصویری خرید اعتباری', 'ts-bnpl' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'این صفحه فقط محتوای حالت آزمایشی را مدیریت می‌کند. حالت صفحه‌ی فرود فعلی و تنظیمات پرداخت دست‌نخورده می‌مانند.', 'ts-bnpl' ); ?>
			</p>

			<?php self::render_notice(); ?>
			<?php if ( $invalid_media > 0 ) : ?>
				<div class="notice notice-warning inline"><p>
					<?php echo esc_html( sprintf( __( '%d فایل رسانه‌ی نامعتبر یا حذف‌شده پیدا شد. جایگاه‌های خالی را دوباره انتخاب کنید.', 'ts-bnpl' ), $invalid_media ) ); ?>
				</p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'بخش‌های لندینگ تصویری', 'ts-bnpl' ); ?>">
				<?php
				$tabs = array(
					'banners'     => 'Top Banners / بنرهای بالا',
					'hero'        => 'Hero / هیرو',
					'providers'   => 'Providers / سرویس‌ها',
					'eligibility' => 'Eligibility / تشخیص کالا',
					'conditions'  => 'Conditions / Information',
					'final-cta'   => 'Final CTA / فراخوان پایانی',
				);
				foreach ( $tabs as $id => $label ) :
					?>
					<button type="button" class="nav-tab<?php echo 'banners' === $id ? ' nav-tab-active' : ''; ?>" data-ts-bnpl-tab="<?php echo esc_attr( $id ); ?>">
						<?php echo esc_html( $label ); ?>
					</button>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>" />
				<input type="hidden" name="ts_bnpl_visual[schema_version]" value="<?php echo esc_attr( TS_BNPL_Visual_Settings::SCHEMA_VERSION ); ?>" />
				<?php wp_nonce_field( self::SAVE_ACTION ); ?>

				<?php self::render_banners( $settings['banners'] ); ?>
				<?php self::render_hero( $settings['hero'] ); ?>
				<?php self::render_providers( $settings['providers'], $choices ); ?>
				<?php self::render_content_section( 'eligibility', $settings['eligibility'], 'Eligibility / تشخیص کالای واجد شرایط' ); ?>
				<?php self::render_conditions( $settings['conditions'] ); ?>
				<?php self::render_final_cta( $settings['final_cta'] ); ?>

				<?php submit_button( __( 'ذخیره‌ی لندینگ تصویری', 'ts-bnpl' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * اعتبارسنجی و ذخیره‌ی درخواست، بدون redirect برای آزمون‌پذیری.
	 *
	 * @param mixed $request درخواست خام.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function save_request( $request ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return new WP_Error( 'ts_bnpl_visual_forbidden', __( 'اجازه‌ی مدیریت این تنظیمات را ندارید.', 'ts-bnpl' ) );
		}

		$request = is_array( $request ) ? $request : array();
		$nonce   = isset( $request['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $request['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::SAVE_ACTION ) ) {
			return new WP_Error( 'ts_bnpl_visual_bad_nonce', __( 'درخواست ذخیره معتبر نیست.', 'ts-bnpl' ) );
		}

		$payload = isset( $request['ts_bnpl_visual'] ) ? wp_unslash( $request['ts_bnpl_visual'] ) : array();
		$media   = self::validate_media_request( $payload );

		if ( is_wp_error( $media ) ) {
			return $media;
		}

		return TS_BNPL_Visual_Settings::save( $payload );
	}

	/**
	 * مجوز و MIME رسانه‌های ارسالی را قبل از normalize بررسی می‌کند.
	 *
	 * @param mixed $payload داده‌ی فرم.
	 *
	 * @return true|WP_Error
	 */
	private static function validate_media_request( $payload ) {
		$current = get_option( TS_BNPL_Visual_Settings::OPTION, array() );
		$old     = self::media_references( $current );
		$new     = self::media_references( $payload );
		$old_sig = self::media_signature( $old );
		$new_sig = self::media_signature( $new );

		if ( $old_sig !== $new_sig && ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'ts_bnpl_visual_forbidden', __( 'برای تغییر فایل‌های رسانه اجازه‌ی بارگذاری لازم است.', 'ts-bnpl' ) );
		}

		$old_assignments = self::media_signature( $old );
		foreach ( $new as $reference ) {
			$attachment_id = $reference['id'];
			if (
				'attachment' !== get_post_type( $attachment_id ) ||
				'trash' === get_post_status( $attachment_id ) ||
				! in_array( get_post_mime_type( $attachment_id ), $reference['mimes'], true ) ||
				! wp_get_attachment_url( $attachment_id )
			) {
				return new WP_Error( 'ts_bnpl_visual_invalid_media', __( 'یکی از فایل‌ها با نوع فیلد انتخابی سازگار نیست یا دیگر در دسترس نیست.', 'ts-bnpl' ) );
			}

			$assignment = $reference['slot'] . ':' . $attachment_id;
			if ( ! in_array( $assignment, $old_assignments, true ) && ! current_user_can( 'edit_post', $attachment_id ) ) {
				return new WP_Error( 'ts_bnpl_visual_media_forbidden', __( 'اجازه‌ی استفاده از یکی از فایل‌های انتخابی را ندارید.', 'ts-bnpl' ) );
			}
		}

		return true;
	}

	/**
	 * فهرست رسانه‌های شناخته‌شده‌ی payload؛ کلیدهای دلخواه نادیده گرفته می‌شوند.
	 *
	 * @param mixed  $value داده‌ی تو در تو.
	 * @param string $path  مسیر معنایی فعلی؛ اندیس بنر برای reorder نادیده گرفته می‌شود.
	 *
	 * @return array<int,array{id:int,field:string,slot:string,mimes:string[]}>
	 */
	private static function media_references( $value, $path = '' ) {
		$allowed = array(
			'desktop_avif_id' => array( 'image/avif' ),
			'mobile_avif_id'  => array( 'image/avif' ),
			'desktop_webp_id' => array( 'image/webp' ),
			'mobile_webp_id'  => array( 'image/webp' ),
			'logo_id'         => array( 'image/png', 'image/webp', 'image/jpeg' ),
		);
		$references = array();

		if ( ! is_array( $value ) ) {
			return $references;
		}

		foreach ( $value as $key => $item ) {
			if ( isset( $allowed[ $key ] ) ) {
				$attachment_id = absint( $item );
				if ( $attachment_id > 0 ) {
					$references[] = array(
						'id'     => $attachment_id,
						'field'  => $key,
						'slot'   => ltrim( $path . '.' . $key, '.' ),
						'mimes'  => $allowed[ $key ],
					);
				}
			} elseif ( is_array( $item ) ) {
				if ( is_int( $key ) || ctype_digit( (string) $key ) ) {
					$next_path = $path;
					if ( 'providers' === $path ) {
						$provider_id = isset( $item['provider_id'] ) ? sanitize_key( $item['provider_id'] ) : 'row';
						$next_path   = 'providers.' . ( '' !== $provider_id ? $provider_id : 'row' );
					}
				} else {
					$next_path = ltrim( $path . '.' . $key, '.' );
				}
				$references = array_merge( $references, self::media_references( $item, $next_path ) );
			}
		}

		return $references;
	}

	/** @return string[] */
	private static function media_signature( $references ) {
		$signature = array();
		foreach ( $references as $reference ) {
			$signature[] = $reference['slot'] . ':' . $reference['id'];
		}
		sort( $signature, SORT_STRING );

		return $signature;
	}

	/** @return int */
	private static function invalid_media_count( $settings ) {
		$count = 0;
		foreach ( self::media_references( $settings ) as $reference ) {
			$attachment_id = $reference['id'];
			if (
				'attachment' !== get_post_type( $attachment_id ) ||
				'trash' === get_post_status( $attachment_id ) ||
				! in_array( get_post_mime_type( $attachment_id ), $reference['mimes'], true ) ||
				! wp_get_attachment_url( $attachment_id )
			) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * ذخیره و بازگشت به صفحه.
	 *
	 * @return void
	 */
	public static function handle_save() {
		$result = self::save_request( $_POST );
		$args   = array(
			'post_type' => 'product',
			'page'      => self::PAGE_SLUG,
		);

		if ( is_wp_error( $result ) ) {
			$args['ts-bnpl-visual-error'] = $result->get_error_code();
		} else {
			$args['ts-bnpl-visual-saved'] = '1';
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php' ) ) );
		exit;
	}

	/** @return void */
	private static function render_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط اعلان نتیجه‌ی redirect.
		if ( isset( $_GET['ts-bnpl-visual-saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'تنظیمات لندینگ تصویری ذخیره شد.', 'ts-bnpl' ) . '</p></div>';
		}
		if ( isset( $_GET['ts-bnpl-visual-error'] ) ) {
			$error_code = sanitize_key( wp_unslash( $_GET['ts-bnpl-visual-error'] ) );
			$messages   = array(
				'ts_bnpl_visual_invalid_media'   => __( 'تنظیمات ذخیره نشد: نوع یا وضعیت یکی از فایل‌های انتخابی معتبر نیست.', 'ts-bnpl' ),
				'ts_bnpl_visual_media_forbidden' => __( 'تنظیمات ذخیره نشد: اجازه‌ی استفاده از یکی از فایل‌های انتخابی را ندارید.', 'ts-bnpl' ),
				'ts_bnpl_visual_forbidden'       => __( 'تنظیمات ذخیره نشد: دسترسی لازم برای این تغییر را ندارید.', 'ts-bnpl' ),
			);
			$message    = isset( $messages[ $error_code ] ) ? $messages[ $error_code ] : __( 'تنظیمات ذخیره نشد. دسترسی، فایل‌ها و مقادیر فرم را بررسی کنید.', 'ts-bnpl' );
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/** @return void */
	private static function render_banners( $rows ) {
		?>
		<section class="card ts-bnpl-visual-admin__panel" data-ts-bnpl-panel="banners">
			<h2><?php esc_html_e( 'Top Banners / بنرهای تبلیغاتی بالا', 'ts-bnpl' ); ?></h2>
			<p><?php esc_html_e( 'یک بنر بدون کنترل اسلایدر نمایش داده می‌شود. دو بنر یا بیشتر از اسلایدر اصلی سایت استفاده می‌کنند.', 'ts-bnpl' ); ?></p>
			<div data-ts-bnpl-banner-list>
				<?php foreach ( $rows as $index => $row ) : ?>
					<?php self::render_banner_row( $row, (string) $index ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button button-secondary" data-ts-bnpl-add-banner><?php esc_html_e( '+ افزودن بنر', 'ts-bnpl' ); ?></button>
			<template data-ts-bnpl-banner-template><?php self::render_banner_row( array(), '__INDEX__' ); ?></template>
		</section>
		<?php
	}

	/** @return void */
	private static function render_banner_row( $row, $index ) {
		$row   = is_array( $row ) ? $row : array();
		$media = isset( $row['media'] ) ? $row['media'] : array();
		$url   = isset( $row['url'] ) ? $row['url'] : '';
		?>
		<div class="ts-bnpl-visual-admin__repeat-row" data-ts-bnpl-row>
			<div class="ts-bnpl-visual-admin__row-head">
				<h3><span data-ts-bnpl-row-number><?php echo esc_html( is_numeric( $index ) ? (string) ( (int) $index + 1 ) : '#' ); ?></span>. <?php esc_html_e( 'بنر', 'ts-bnpl' ); ?></h3>
				<?php self::render_row_controls(); ?>
			</div>
			<?php self::render_media_group( 'ts_bnpl_visual[banners][' . $index . '][media]', $media ); ?>
			<?php self::text_field( 'ts_bnpl_visual[banners][' . $index . '][url]', __( 'نشانی مقصد (اختیاری)', 'ts-bnpl' ), $url, 'url' ); ?>
		</div>
		<?php
	}

	/** @return void */
	private static function render_hero( $hero ) {
		?>
		<section class="card ts-bnpl-visual-admin__panel" data-ts-bnpl-panel="hero" hidden>
			<h2><?php esc_html_e( 'Hero / هیرو خرید اعتباری', 'ts-bnpl' ); ?></h2>
			<?php self::text_field( 'ts_bnpl_visual[hero][eyebrow]', __( 'پیش‌عنوان', 'ts-bnpl' ), $hero['eyebrow'] ); ?>
			<?php self::text_field( 'ts_bnpl_visual[hero][title]', __( 'عنوان', 'ts-bnpl' ), $hero['title'] ); ?>
			<?php self::textarea_field( 'ts_bnpl_visual[hero][description]', __( 'توضیح', 'ts-bnpl' ), $hero['description'] ); ?>
			<div class="ts-bnpl-visual-admin__grid">
				<?php foreach ( $hero['benefits'] as $index => $benefit ) : ?>
					<?php self::text_field( 'ts_bnpl_visual[hero][benefits][' . $index . ']', sprintf( __( 'مزیت %d', 'ts-bnpl' ), $index + 1 ), $benefit ); ?>
				<?php endforeach; ?>
			</div>
			<div class="ts-bnpl-visual-admin__grid">
				<?php self::text_field( 'ts_bnpl_visual[hero][primary_label]', __( 'متن دکمه‌ی اصلی', 'ts-bnpl' ), $hero['primary_label'] ); ?>
				<?php self::text_field( 'ts_bnpl_visual[hero][primary_url]', __( 'نشانی دکمه‌ی اصلی', 'ts-bnpl' ), $hero['primary_url'], 'url' ); ?>
				<?php self::text_field( 'ts_bnpl_visual[hero][secondary_label]', __( 'متن دکمه‌ی دوم', 'ts-bnpl' ), $hero['secondary_label'] ); ?>
				<?php self::text_field( 'ts_bnpl_visual[hero][secondary_url]', __( 'نشانی دکمه‌ی دوم', 'ts-bnpl' ), $hero['secondary_url'], 'url' ); ?>
			</div>
			<?php self::render_media_group( 'ts_bnpl_visual[hero][media]', $hero['media'] ); ?>
		</section>
		<?php
	}

	/** @return void */
	private static function render_providers( $rows, $choices ) {
		?>
		<section class="card ts-bnpl-visual-admin__panel" data-ts-bnpl-panel="providers" hidden>
			<h2><?php esc_html_e( 'Providers / سرویس‌های اعتباری', 'ts-bnpl' ); ?></h2>
			<p><?php esc_html_e( 'فقط درگاه‌های اقساطی ثبت‌شده قابل انتخاب‌اند. غیرفعال‌کردن درگاه در ووکامرس همیشه بر نمایش عمومی اولویت دارد.', 'ts-bnpl' ); ?></p>
			<div data-ts-bnpl-provider-list>
				<?php foreach ( $rows as $index => $row ) : ?>
					<?php self::render_provider_row( $row, (string) $index, $choices ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button button-secondary" data-ts-bnpl-add-provider><?php esc_html_e( '+ افزودن سرویس', 'ts-bnpl' ); ?></button>
			<template data-ts-bnpl-provider-template><?php self::render_provider_row( array(), '__INDEX__', $choices ); ?></template>
		</section>
		<?php
	}

	/** @return void */
	private static function render_provider_row( $row, $index, $choices ) {
		$row = wp_parse_args(
			is_array( $row ) ? $row : array(),
			array( 'provider_id' => '', 'display_enabled' => true, 'display_name' => '', 'logo_id' => 0, 'description' => '', 'url' => '' )
		);
		?>
		<div class="ts-bnpl-visual-admin__repeat-row" data-ts-bnpl-row>
			<div class="ts-bnpl-visual-admin__row-head">
				<h3><span data-ts-bnpl-row-number><?php echo esc_html( is_numeric( $index ) ? (string) ( (int) $index + 1 ) : '#' ); ?></span>. <?php esc_html_e( 'سرویس', 'ts-bnpl' ); ?></h3>
				<?php self::render_row_controls(); ?>
			</div>
			<label class="ts-bnpl-visual-admin__field">
				<span><?php esc_html_e( 'درگاه واقعی', 'ts-bnpl' ); ?></span>
				<select name="ts_bnpl_visual[providers][<?php echo esc_attr( $index ); ?>][provider_id]">
					<option value=""><?php esc_html_e( '— انتخاب کنید —', 'ts-bnpl' ); ?></option>
					<?php foreach ( $choices as $provider_id => $choice ) : ?>
						<option value="<?php echo esc_attr( $provider_id ); ?>"<?php selected( $row['provider_id'], $provider_id ); ?>><?php echo esc_html( $choice['name'] ); ?></option>
					<?php endforeach; ?>
					<?php if ( $row['provider_id'] && ! isset( $choices[ $row['provider_id'] ] ) ) : ?>
						<option value="<?php echo esc_attr( $row['provider_id'] ); ?>" selected><?php echo esc_html( $row['provider_id'] . ' — ' . __( 'در حال حاضر ثبت/فعال نیست', 'ts-bnpl' ) ); ?></option>
					<?php endif; ?>
				</select>
			</label>
			<label class="ts-bnpl-visual-admin__check">
				<input type="checkbox" name="ts_bnpl_visual[providers][<?php echo esc_attr( $index ); ?>][display_enabled]" value="1"<?php checked( $row['display_enabled'], true ); ?> />
				<?php esc_html_e( 'نمایش در لندینگ (تنها در صورت فعال‌بودن واقعی درگاه)', 'ts-bnpl' ); ?>
			</label>
			<div class="ts-bnpl-visual-admin__grid">
				<?php self::text_field( 'ts_bnpl_visual[providers][' . $index . '][display_name]', __( 'نام نمایشی', 'ts-bnpl' ), $row['display_name'] ); ?>
				<?php self::text_field( 'ts_bnpl_visual[providers][' . $index . '][url]', __( 'نشانی اختیاری', 'ts-bnpl' ), $row['url'], 'url' ); ?>
			</div>
			<?php self::textarea_field( 'ts_bnpl_visual[providers][' . $index . '][description]', __( 'توضیح کوتاه', 'ts-bnpl' ), $row['description'] ); ?>
			<?php self::render_media_field( 'ts_bnpl_visual[providers][' . $index . '][logo_id]', __( 'لوگو (PNG/WebP/JPEG)', 'ts-bnpl' ), $row['logo_id'], 'logo' ); ?>
			<?php if ( $row['provider_id'] && ! isset( $choices[ $row['provider_id'] ] ) ) : ?>
				<p class="notice notice-warning inline"><span><?php esc_html_e( 'این درگاه فعلاً ثبت نشده است؛ ردیف برای ویرایش آینده حفظ می‌شود ولی عمومی نیست.', 'ts-bnpl' ); ?></span></p>
			<?php elseif ( $row['provider_id'] && ! empty( $choices[ $row['provider_id'] ]['status'] ) ) : ?>
				<p class="notice notice-warning inline"><span><?php echo esc_html( $choices[ $row['provider_id'] ]['status'] ); ?></span></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/** @return void */
	private static function render_content_section( $key, $section, $heading ) {
		?>
		<section class="card ts-bnpl-visual-admin__panel" data-ts-bnpl-panel="<?php echo esc_attr( $key ); ?>" hidden>
			<h2><?php echo esc_html( $heading ); ?></h2>
			<?php self::text_field( 'ts_bnpl_visual[' . $key . '][title]', __( 'عنوان', 'ts-bnpl' ), $section['title'] ); ?>
			<?php self::textarea_field( 'ts_bnpl_visual[' . $key . '][description]', __( 'توضیح', 'ts-bnpl' ), $section['description'] ); ?>
			<?php self::render_media_group( 'ts_bnpl_visual[' . $key . '][media]', $section['media'] ); ?>
		</section>
		<?php
	}

	/** @return void */
	private static function render_conditions( $section ) {
		?>
		<section class="card ts-bnpl-visual-admin__panel" data-ts-bnpl-panel="conditions" hidden>
			<h2><?php esc_html_e( 'Conditions / Information', 'ts-bnpl' ); ?></h2>
			<?php self::text_field( 'ts_bnpl_visual[conditions][title]', __( 'عنوان', 'ts-bnpl' ), $section['title'] ); ?>
			<?php self::textarea_field( 'ts_bnpl_visual[conditions][lead]', __( 'متن اول', 'ts-bnpl' ), $section['lead'] ); ?>
			<?php self::textarea_field( 'ts_bnpl_visual[conditions][description]', __( 'متن دوم', 'ts-bnpl' ), $section['description'] ); ?>
			<?php self::render_media_group( 'ts_bnpl_visual[conditions][media]', $section['media'] ); ?>
		</section>
		<?php
	}

	/** @return void */
	private static function render_final_cta( $section ) {
		?>
		<section class="card ts-bnpl-visual-admin__panel" data-ts-bnpl-panel="final-cta" hidden>
			<h2><?php esc_html_e( 'Final CTA / فراخوان پایانی', 'ts-bnpl' ); ?></h2>
			<p class="description"><?php esc_html_e( 'با بارگذاری تصویر، این بخش دقیقاً مثل بنر بالای صفحه یک بنر تمام‌عرض لینک‌دار می‌شود و عنوان، توضیح و دکمه باید داخل خود تصویر طراحی شوند. اگر تصویری بارگذاری نشود، همان کارت متنی سرمه‌ای با دکمه نمایش داده می‌شود.', 'ts-bnpl' ); ?></p>
			<?php self::text_field( 'ts_bnpl_visual[final_cta][title]', __( 'عنوان', 'ts-bnpl' ), $section['title'] ); ?>
			<?php self::textarea_field( 'ts_bnpl_visual[final_cta][description]', __( 'توضیح', 'ts-bnpl' ), $section['description'] ); ?>
			<div class="ts-bnpl-visual-admin__grid">
				<?php self::text_field( 'ts_bnpl_visual[final_cta][label]', __( 'متن دکمه', 'ts-bnpl' ), $section['label'] ); ?>
				<?php self::text_field( 'ts_bnpl_visual[final_cta][url]', __( 'نشانی مقصد', 'ts-bnpl' ), $section['url'], 'url' ); ?>
			</div>
			<p class="description"><?php esc_html_e( 'در حالت بنری، نشانی بالا مقصد کلیک روی کل بنر است و عنوان/توضیح/متن دکمه فقط برای حالت بدون تصویر و نام دسترس‌پذیر لینک به کار می‌روند. اندازه‌ی تصویر: دسکتاپ 1326×400 و موبایل 1300×975 — دقیقاً مثل بنر بالای صفحه.', 'ts-bnpl' ); ?></p>
			<?php self::render_media_group( 'ts_bnpl_visual[final_cta][media]', $section['media'] ); ?>
		</section>
		<?php
	}

	/** @return void */
	private static function render_media_group( $prefix, $media ) {
		$media = wp_parse_args( is_array( $media ) ? $media : array(), TS_BNPL_Responsive_Media::defaults() );
		$fields = array(
			'desktop_avif_id' => __( 'Desktop AVIF', 'ts-bnpl' ),
			'desktop_webp_id' => __( 'Desktop WebP', 'ts-bnpl' ),
			'mobile_avif_id'  => __( 'Mobile AVIF', 'ts-bnpl' ),
			'mobile_webp_id'  => __( 'Mobile WebP', 'ts-bnpl' ),
		);
		?>
		<fieldset class="ts-bnpl-visual-admin__media-group">
			<legend><?php esc_html_e( 'تصویر پاسخ‌گو', 'ts-bnpl' ); ?></legend>
			<p class="description"><?php esc_html_e( 'AVIF بهینه و WebP fallback را جداگانه برای دسکتاپ و موبایل انتخاب کنید. اگر WebP معتبر نباشد تصویر عمومی نمایش داده نمی‌شود.', 'ts-bnpl' ); ?></p>
			<div class="ts-bnpl-visual-admin__media-grid">
				<?php foreach ( $fields as $key => $label ) : ?>
					<?php self::render_media_field( $prefix . '[' . $key . ']', $label, $media[ $key ], $key ); ?>
				<?php endforeach; ?>
			</div>
			<?php self::text_field( $prefix . '[alt]', __( 'متن جایگزین تصویر', 'ts-bnpl' ), $media['alt'] ); ?>
		</fieldset>
		<?php
	}

	/** @return void */
	private static function render_media_field( $name, $label, $attachment_id, $kind = false ) {
		$attachment_id = absint( $attachment_id );
		$preview       = $attachment_id > 0 ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
		?>
		<div class="ts-bnpl-visual-admin__media" data-ts-bnpl-media-field data-media-kind="<?php echo esc_attr( $kind ? $kind : 'image' ); ?>">
			<strong><?php echo esc_html( $label ); ?></strong>
			<div class="ts-bnpl-visual-admin__preview" data-ts-bnpl-media-preview>
				<?php if ( $preview ) : ?><img src="<?php echo esc_url( $preview ); ?>" alt="" /><?php endif; ?>
			</div>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $attachment_id ); ?>" data-ts-bnpl-media-id />
			<button type="button" class="button" data-ts-bnpl-select-media><?php esc_html_e( 'انتخاب/جایگزینی', 'ts-bnpl' ); ?></button>
			<button type="button" class="button-link-delete" data-ts-bnpl-clear-media><?php esc_html_e( 'پاک‌کردن', 'ts-bnpl' ); ?></button>
		</div>
		<?php
	}

	/** @return void */
	private static function render_row_controls() {
		?>
		<div class="ts-bnpl-visual-admin__row-controls">
			<button type="button" class="button" data-ts-bnpl-move-up aria-label="<?php esc_attr_e( 'انتقال یک ردیف به بالا', 'ts-bnpl' ); ?>">↑</button>
			<button type="button" class="button" data-ts-bnpl-move-down aria-label="<?php esc_attr_e( 'انتقال یک ردیف به پایین', 'ts-bnpl' ); ?>">↓</button>
			<button type="button" class="button button-link-delete" data-ts-bnpl-remove-row><?php esc_html_e( 'حذف', 'ts-bnpl' ); ?></button>
		</div>
		<?php
	}

	/** @return void */
	private static function text_field( $name, $label, $value, $type = 'text' ) {
		$is_url = 'url' === $type;
		?>
		<label class="ts-bnpl-visual-admin__field">
			<span><?php echo esc_html( $label ); ?></span>
			<input type="<?php echo esc_attr( $is_url ? 'text' : $type ); ?>"<?php if ( $is_url ) : ?> inputmode="url"<?php endif; ?> name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
		</label>
		<?php
	}

	/** @return void */
	private static function textarea_field( $name, $label, $value ) {
		?>
		<label class="ts-bnpl-visual-admin__field">
			<span><?php echo esc_html( $label ); ?></span>
			<textarea name="<?php echo esc_attr( $name ); ?>" rows="4" class="large-text"><?php echo esc_html( $value ); ?></textarea>
		</label>
		<?php
	}
}
