<?php
/**
 * نمایش فرانت‌اند.
 *
 * @package TS_BNPL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TS_BNPL_Display
 */
class TS_BNPL_Display {

	/**
	 * شناسه‌ی کانتینر ردیف اقساط.
	 */
	const CONTAINER_ID = 'ts-bnpl';

	/**
	 * شناسه‌ی مودال راهنما.
	 */
	const MODAL_ID = 'ts-bnpl-modal';

	/**
	 * ثبت هوک‌ها.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp', array( __CLASS__, 'maybe_replace_core_price' ) );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_price_block' ), 11 );
		add_filter( 'woocommerce_available_variation', array( __CLASS__, 'add_variation_data' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_modal' ) );
	}

	/**
	 * حذف قالب قیمت پیش‌فرض ووکامرس در صفحه‌ی محصول.
	 *
	 * کارت ما خودش قیمت نقدی را نمایش می‌دهد، پس اگر قالب قیمت هسته روی
	 * woocommerce_single_product_summary فعال باشد، قیمت دوبار رندر می‌شد.
	 * قالب‌هایی که قیمت را خودشان می‌سازند و این هوک را اجرا نمی‌کنند از این
	 * کار تأثیری نمی‌گیرند. با فیلتر زیر می‌توان رفتار را برگرداند.
	 *
	 * @return void
	 */
	public static function maybe_replace_core_price() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		if ( ! apply_filters( 'ts_bnpl_replace_core_price', true ) ) {
			return;
		}

		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
	}

	/*
	|--------------------------------------------------------------------------
	| ۴. استایل و بارگذاری
	|--------------------------------------------------------------------------
	*/

	/**
	 * بارگذاری CSS و JS فقط در صفحه‌ی محصول.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( ! function_exists( 'is_product' ) ) {
			return;
		}

		$is_product = is_product();

		// در تسویه‌حساب فقط اسکریپت لازم است: بازمحاسبه‌ی مبالغ هنگام تعویض درگاه.
		$is_checkout = function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' );

		if ( ! $is_product && ! $is_checkout ) {
			return;
		}

		if ( $is_product ) {
			wp_enqueue_style(
				'ts-bnpl',
				TS_BNPL_URL . 'assets/css/ts-bnpl.css',
				array(),
				TS_BNPL_VERSION
			);
		}

		wp_enqueue_script(
			'ts-bnpl',
			TS_BNPL_URL . 'assets/js/ts-bnpl.js',
			array( 'jquery' ),
			TS_BNPL_VERSION,
			true
		);
	}

	/*
	|--------------------------------------------------------------------------
	| ۱. بلوک قیمت
	|--------------------------------------------------------------------------
	*/

	/**
	 * رندر کارت قیمت روی صفحه‌ی محصول.
	 *
	 * @return void
	 */
	public static function render_price_block() {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		echo self::price_block_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- مارک‌آپ داخل متد اسکیپ می‌شود.
	}

	/**
	 * ساخت مارک‌آپ کامل کارت قیمت.
	 *
	 * این متد عمداً public است تا قالب‌هایی که هوک‌های استاندارد ووکامرس را
	 * اجرا نمی‌کنند بتوانند مستقیم آن را صدا بزنند.
	 *
	 * @param WC_Product $product محصول.
	 *
	 * @return string
	 */
	public static function price_block_html( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		$is_variable = $product->is_type( 'variable' );
		$currency    = get_woocommerce_currency_symbol();

		$regular = $product->get_regular_price();
		$price   = $product->get_price();

		$has_regular_row = ! $is_variable
			&& is_numeric( $regular )
			&& is_numeric( $price )
			&& (float) $regular > (float) $price;

		ob_start();
		?>
		<div class="ts-bnpl" dir="rtl">
			<?php if ( $has_regular_row ) : ?>
				<div class="ts-bnpl__row ts-bnpl__row--regular">
					<span class="ts-bnpl__label"><?php esc_html_e( 'قیمت نقدی', 'ts-bnpl' ); ?></span>
					<del class="ts-bnpl__regular">
						<bdi><?php echo esc_html( self::format_amount( $regular ) ); ?></bdi>
						<span class="ts-bnpl__unit"><?php echo esc_html( $currency ); ?></span>
					</del>
				</div>
			<?php endif; ?>

			<div class="ts-bnpl__row ts-bnpl__row--price">
				<?php if ( $is_variable || ! is_numeric( $price ) ) : ?>
					<span class="ts-bnpl__price ts-bnpl__price--html"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
				<?php else : ?>
					<span class="ts-bnpl__price">
						<bdi><?php echo esc_html( self::format_amount( $price ) ); ?></bdi>
						<span class="ts-bnpl__unit"><?php echo esc_html( $currency ); ?></span>
					</span>
				<?php endif; ?>
			</div>

			<?php
			$installment_total = $is_variable ? 0 : TS_BNPL_Data::get( $product );
			$has_installment   = $installment_total > 0;
			?>
			<div id="<?php echo esc_attr( self::CONTAINER_ID ); ?>" class="ts-bnpl__installment"<?php echo ( $is_variable || ! $has_installment ) ? ' hidden' : ''; ?>>
				<?php
				if ( $has_installment ) {
					echo self::installment_row_html( $installment_total ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- مارک‌آپ داخل متد اسکیپ می‌شود.
				}
				?>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * رندر کارت مستقل اقساط، برای قالب‌هایی که قیمت را خودشان می‌سازند.
	 *
	 * قالب amazing هوک‌های استاندارد ووکامرس را اجرا نمی‌کند و قیمت را در
	 * lib/Product/.../price.php رندر می‌کند. همان فایل هنگام تعویض variation
	 * از سمت سرور دوباره رندر می‌شود، پس این متد برای هر دو حالت ساده و متغیر
	 * کافی است و به رویدادهای جاوااسکریپت ووکامرس نیازی ندارد.
	 *
	 * اگر محصول قیمت اقساطی نداشته باشد، هیچ چیزی چاپ نمی‌شود.
	 *
	 * @param WC_Product|null $product محصول یا متغیر جاری.
	 *
	 * @return void
	 */
	public static function render_standalone( $product = null ) {
		if ( ! $product instanceof WC_Product || ! $product->get_id() ) {
			$product = isset( $GLOBALS['product'] ) ? $GLOBALS['product'] : null;
		}

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$total = TS_BNPL_Data::get( $product );

		if ( $total <= 0 ) {
			return;
		}

		printf(
			'<div class="ts-bnpl ts-bnpl--standalone" dir="rtl"><div id="%1$s" class="ts-bnpl__installment">%2$s</div></div>',
			esc_attr( self::CONTAINER_ID ),
			self::installment_row_html( $total ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- مارک‌آپ داخل متد اسکیپ می‌شود.
		);
	}

	/**
	 * ساخت مارک‌آپ ردیف سوم (طرح اقساط).
	 *
	 * همین مارک‌آپ در داده‌ی هر variation با کلید bnpl_html قرار می‌گیرد.
	 *
	 * @param float $total مبلغ کل اقساطی.
	 *
	 * @return string
	 */
	public static function installment_row_html( $total ) {
		$total = (float) $total;

		if ( $total <= 0 ) {
			return '';
		}

		$currency    = get_woocommerce_currency_symbol();
		$per_month   = TS_BNPL_Data::installment( $total );
		$months_text = self::to_persian_digits( (string) (int) TS_BNPL_MONTHS );

		ob_start();
		?>
		<div class="ts-bnpl__plan">
			<span class="ts-bnpl__icon">
				<img
					src="<?php echo esc_url( TS_BNPL_URL . 'assets/images/digipay.svg' ); ?>"
					alt="<?php esc_attr_e( 'دیجی‌پی', 'ts-bnpl' ); ?>"
					width="62"
					height="16"
					loading="lazy"
					decoding="async"
				/>
			</span>

			<span class="ts-bnpl__plan-text">
				<span class="ts-bnpl__plan-amount">
					<bdi><?php echo esc_html( self::format_amount( $per_month ) ); ?></bdi>
					<span class="ts-bnpl__unit">
						<?php
						printf(
							/* translators: 1: واحد پول، 2: تعداد اقساط. */
							esc_html__( '%1$s × %2$s قسط', 'ts-bnpl' ),
							esc_html( $currency ),
							esc_html( $months_text )
						);
						?>
					</span>
				</span>
				<span class="ts-bnpl__plan-note">
					<?php
					printf(
						/* translators: 1: مبلغ کل، 2: واحد پول. */
						esc_html__( 'با دیجی‌پی · مجموع تقریبی %1$s %2$s', 'ts-bnpl' ),
						esc_html( self::format_amount( $total ) ),
						esc_html( $currency )
					);
					?>
				</span>
			</span>

			<?php
			/*
			 * علامت سؤال به صورت SVG کشیده می‌شود نه نویسه‌ی «؟».
			 * آن نویسه در محیط راست‌به‌چپ بِرینگ نامتقارن دارد و هرچه فلکس را
			 * تنظیم کنیم کمی از مرکز دایره خارج می‌ماند.
			 */
			?>
			<button type="button"
				class="ts-bnpl__help"
				aria-label="<?php esc_attr_e( 'راهنمای خرید اقساطی با دیجی‌پی', 'ts-bnpl' ); ?>"
				aria-haspopup="dialog"
				aria-controls="<?php echo esc_attr( self::MODAL_ID ); ?>">
				<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
					<path d="M9.2 9.3a2.9 2.9 0 1 1 4.05 2.66c-.77.36-1.25 1.13-1.25 1.98v.31"></path>
					<path d="M12 17.6h.01"></path>
				</svg>
			</button>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/*
	|--------------------------------------------------------------------------
	| ۲. پشتیبانی از variation
	|--------------------------------------------------------------------------
	*/

	/**
	 * افزودن داده‌ی اقساط به هر variation.
	 *
	 * @param array                $data      داده‌ی variation.
	 * @param WC_Product_Variable  $parent    محصول والد.
	 * @param WC_Product_Variation $variation متغیر.
	 *
	 * @return array
	 */
	public static function add_variation_data( $data, $parent, $variation ) {
		unset( $parent );

		$total = TS_BNPL_Data::get( $variation );

		$data['bnpl_total'] = $total;
		$data['bnpl_html']  = $total > 0 ? self::installment_row_html( $total ) : '';

		return $data;
	}

	/*
	|--------------------------------------------------------------------------
	| ۳. مودال راهنما
	|--------------------------------------------------------------------------
	*/

	/**
	 * آیا مودال باید در این صفحه رندر شود؟
	 *
	 * @return bool
	 */
	private static function should_render_modal() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return false;
		}

		/*
		 * عمداً از گلوبال $product استفاده نمی‌شود؛ تا رسیدن به wp_footer ممکن
		 * است حلقه‌ی محصولات مرتبط آن را عوض کرده باشد.
		 */
		$product = wc_get_product( get_queried_object_id() );

		if ( ! $product instanceof WC_Product ) {
			return false;
		}

		// برای محصول متغیر، مقدار اقساط با انتخاب متغیر مشخص می‌شود.
		return $product->is_type( 'variable' ) || TS_BNPL_Data::get( $product ) > 0;
	}

	/**
	 * رندر مودال راهنما در فوتر.
	 *
	 * @return void
	 */
	public static function render_modal() {
		if ( ! self::should_render_modal() ) {
			return;
		}

		$steps = array(
			__( 'اپلیکیشن دیجی‌پی را نصب و در آن ثبت‌نام کنید. از بخش «وام و اعتبار» گزینه «اعتبار ۴ قسطه» را انتخاب کرده و مراحل را تا دریافت اعتبار ادامه دهید.', 'ts-bnpl' ),
			__( 'پس از دریافت اعتبار، مانند یک خرید عادی محصول موردنظر را به سبد خرید اضافه کرده و وارد صفحه تسویه‌حساب شوید.', 'ts-bnpl' ),
			__( 'اطلاعات هویتی و شماره تماسی که در دیجی‌پی ثبت کرده‌اید را وارد کنید و درگاه «پرداخت اقساطی دیجی‌پی» را انتخاب کنید.', 'ts-bnpl' ),
			__( 'در درگاه، یک قسط به‌عنوان پیش‌پرداخت پرداخت می‌شود و مابقی مبلغ طی ۳ ماه آینده تسویه می‌گردد.', 'ts-bnpl' ),
			__( 'بلافاصله پس از تکمیل پرداخت به سایت بازمی‌گردید، رسید خرید را می‌بینید و روند آماده‌سازی سفارش آغاز می‌شود. هیچ تفاوتی با خرید نقدی ندارد.', 'ts-bnpl' ),
		);
		?>
		<div class="ts-bnpl-modal" id="<?php echo esc_attr( self::MODAL_ID ); ?>" hidden>
			<div class="ts-bnpl-modal__backdrop" data-ts-bnpl-close></div>

			<div class="ts-bnpl-modal__dialog"
				role="dialog"
				aria-modal="true"
				aria-labelledby="<?php echo esc_attr( self::MODAL_ID ); ?>-title"
				tabindex="-1"
				dir="rtl">

				<button type="button"
					class="ts-bnpl-modal__close"
					data-ts-bnpl-close
					aria-label="<?php esc_attr_e( 'بستن راهنما', 'ts-bnpl' ); ?>">
					<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" focusable="false" aria-hidden="true">
						<path d="m6 6 12 12M18 6 6 18"></path>
					</svg>
				</button>

				<h2 class="ts-bnpl-modal__title" id="<?php echo esc_attr( self::MODAL_ID ); ?>-title">
					<?php esc_html_e( 'خرید اقساطی با دیجی‌پی چگونه انجام می‌شود؟', 'ts-bnpl' ); ?>
				</h2>

				<div class="ts-bnpl-modal__body">
					<p><?php esc_html_e( 'اگر امکان پرداخت نقدی ندارید، می‌توانید با استفاده از سرویس اعتباری دیجی‌پی محصول را همین امروز دریافت کنید و هزینه آن را طی ۴ ماه پرداخت کنید.', 'ts-bnpl' ); ?></p>

					<ol class="ts-bnpl-modal__steps">
						<?php foreach ( $steps as $step ) : ?>
							<li><?php echo esc_html( $step ); ?></li>
						<?php endforeach; ?>
					</ol>

					<p class="ts-bnpl-modal__note">
						<?php esc_html_e( 'نکته: به دلیل هزینه تأمین مالی و کارمزد، قیمت محصول در حالت اقساطی کمی بالاتر از قیمت نقدی است. اگر امکان پرداخت نقدی دارید، پیشنهاد ما همیشه خرید نقدی است، چون کارمزد آن صفر است.', 'ts-bnpl' ); ?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/*
	|--------------------------------------------------------------------------
	| کمکی‌ها
	|--------------------------------------------------------------------------
	*/

	/**
	 * قالب‌بندی مبلغ با number_format و ارقام فارسی.
	 *
	 * @param float $amount مبلغ.
	 *
	 * @return string
	 */
	public static function format_amount( $amount ) {
		return self::to_persian_digits( number_format( round( (float) $amount ) ) );
	}

	/**
	 * تبدیل ارقام لاتین به فارسی.
	 *
	 * @param string $value رشته‌ی ورودی.
	 *
	 * @return string
	 */
	public static function to_persian_digits( $value ) {
		return str_replace(
			array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
			array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ),
			(string) $value
		);
	}
}
