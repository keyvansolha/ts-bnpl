<?php
/**
 * منطق سبد خرید و درگاه.
 *
 * @package TS_BNPL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TS_BNPL_Checkout
 */
class TS_BNPL_Checkout {

	/**
	 * کلید متای سفارش اقساطی.
	 */
	const ORDER_META = '_is_bnpl';

	/**
	 * کلید تنظیمِ حالت نمایشی در تنظیمات درگاه.
	 */
	const TEST_MODE_FIELD = 'ts_bnpl_test_mode';

	/**
	 * ثبت هوک‌ها.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_installment_prices' ), 20 );
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'filter_gateways' ) );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'flag_order' ), 10, 2 );

		// کیف پول جزئی نباید با اقساطی ترکیب شود (بخش ۷). اولویت‌ها عمداً پیش از
		// افزونه‌ی کیف پول است که روی هر دو هوک روی ۱۰ نشسته.
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'block_partial_wallet' ), 9 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'block_partial_wallet_on_order' ), 5, 2 );

		// یکپارچه‌سازی با منطق اقساطی قالب (بخش ۵).
		add_filter( 'wbs_checkout_product_supports_installments', array( __CLASS__, 'product_supports_installments' ), 10, 2 );
		add_filter( 'wbs_checkout_gateway_is_installment', array( __CLASS__, 'gateway_is_installment' ), 10, 2 );

		// حالت نمایشی درگاه، با تیک در تنظیمات همان درگاه (بخش ۶).
		add_filter( 'woocommerce_settings_api_form_fields_' . TS_BNPL_GATEWAY_ID, array( __CLASS__, 'add_test_mode_field' ) );
		add_filter( 'wbs_digipay_is_configured', array( __CLASS__, 'declare_test_configured' ), 10, 2 );
		add_filter( 'wbs_digipay_create_payment', array( __CLASS__, 'block_test_payment' ), 10, 3 );

		if ( is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'maybe_hide_theme_product_field' ) );
			add_action( 'admin_notices', array( __CLASS__, 'render_test_mode_notice' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
			add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'render_order_detail' ) );

			// لیست سفارش‌ها، حالت کلاسیک (جدول پست‌ها).
			add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_order_column' ), 20, 2 );

			// لیست سفارش‌ها، حالت HPOS (جدول سفارش‌های اختصاصی).
			$hpos_screen = function_exists( 'wc_get_page_screen_id' )
				? wc_get_page_screen_id( 'shop-order' )
				: 'woocommerce_page_wc-orders';

			add_action( 'manage_' . $hpos_screen . '_custom_column', array( __CLASS__, 'render_order_column' ), 20, 2 );
		}
	}

	/**
	 * بارگذاری استایل در صفحه‌های سفارش پیشخوان.
	 *
	 * @param string $hook_suffix شناسه‌ی صفحه‌ی جاری پیشخوان.
	 *
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		unset( $hook_suffix );

		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id = $screen ? $screen->id : '';

		// لیست و ویرایش سفارش، هم در حالت کلاسیک و هم HPOS.
		$order_screens = array(
			'edit-shop_order',
			'shop_order',
			'woocommerce_page_wc-orders',
		);

		if ( ! in_array( $screen_id, $order_screens, true ) ) {
			return;
		}

		wp_enqueue_style(
			'ts-bnpl',
			TS_BNPL_URL . 'assets/css/ts-bnpl.css',
			array(),
			TS_BNPL_VERSION
		);
	}

	/*
	|--------------------------------------------------------------------------
	| ۱. جایگزینی قیمت
	|--------------------------------------------------------------------------
	*/

	/**
	 * جایگزینی قیمت هر قلم سبد با قیمت اقساطی.
	 *
	 * عمداً از add_fee استفاده نمی‌شود؛ قیمت خط سفارش باید خودِ قیمت اقساطی
	 * باشد تا فاکتور برگشتی به حسابداری درست ثبت شود.
	 *
	 * @param WC_Cart $cart سبد خرید.
	 *
	 * @return void
	 */
	public static function apply_installment_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		if ( TS_BNPL_GATEWAY_ID !== WC()->session->get( 'chosen_payment_method' ) ) {
			return;
		}

		if ( ! $cart instanceof WC_Cart ) {
			return;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
				continue;
			}

			$installment_price = TS_BNPL_Data::get( $item['data'] );

			if ( $installment_price > 0 ) {
				$item['data']->set_price( $installment_price );
			}
		}
	}

	/*
	|--------------------------------------------------------------------------
	| ۲. واجد شرایط بودن سبد
	|--------------------------------------------------------------------------
	*/

	/**
	 * آیا سبد جاری واجد شرایط خرید اقساطی است؟
	 *
	 * تنها شرط: سبد خالی نباشد و هر قلم قیمت اقساطی داشته باشد. هیچ کف و سقف
	 * مبلغی اعمال نمی‌شود؛ عدد از حسابداری می‌آید. اگر بعداً دیجی‌پی محدودیت
	 * مبلغ اعلام کرد، همین‌جا اضافه شود.
	 *
	 * @return bool
	 */
	public static function is_cart_eligible() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		$items = WC()->cart->get_cart();

		if ( empty( $items ) ) {
			return false;
		}

		foreach ( $items as $item ) {
			if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
				return false;
			}

			if ( TS_BNPL_Data::get( $item['data'] ) <= 0 ) {
				return false;
			}
		}

		return true;
	}

	/*
	|--------------------------------------------------------------------------
	| ۳. گارد درگاه
	|--------------------------------------------------------------------------
	*/

	/**
	 * حذف درگاه اقساطی وقتی سبد واجد شرایط نیست.
	 *
	 * توجه: شرط is_admin() با wp_doing_ajax() ترکیب شده، چون صفحه‌ی تسویه‌حساب
	 * بخش پرداخت را از طریق admin-ajax.php تازه می‌کند و در آن درخواست
	 * is_admin() برابر true است. بدون این ترکیب، سبد ناواجدشرایط می‌توانست
	 * درگاه را در بازخوانی‌های ایجکسی همچنان ببیند.
	 *
	 * @param array<string,WC_Payment_Gateway> $gateways درگاه‌های در دسترس.
	 *
	 * @return array<string,WC_Payment_Gateway>
	 */
	public static function filter_gateways( $gateways ) {
		if ( ! is_array( $gateways ) ) {
			return $gateways;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return $gateways;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $gateways;
		}

		if ( ! isset( $gateways[ TS_BNPL_GATEWAY_ID ] ) ) {
			return $gateways;
		}

		if ( ! self::is_payment_context_eligible() ) {
			unset( $gateways[ TS_BNPL_GATEWAY_ID ] );
		}

		return $gateways;
	}

	/**
	 * واجد شرایط بودن زمینه‌ی پرداخت جاری.
	 *
	 * جدا از is_cart_eligible() نگه داشته شده تا صفحه‌ی «پرداخت سفارش»
	 * (order-pay) هم پوشش داده شود. در آن صفحه مجموع سفارش از قبل با قیمت
	 * نقدی قفل شده و جایگزینی قیمت اقساطی دیگر ممکن نیست، پس درگاه اقساطی
	 * عمداً نمایش داده نمی‌شود؛ در غیر این صورت سفارش اقساطی با مبلغ نقدی
	 * تسویه می‌شد و اختلاف تأمین مالی از دست می‌رفت.
	 *
	 * با فیلتر ts_bnpl_allow_order_pay می‌توان این رفتار را تغییر داد.
	 *
	 * @return bool
	 */
	private static function is_payment_context_eligible() {
		if ( WC()->cart && ! WC()->cart->is_empty() ) {
			return self::is_cart_eligible();
		}

		$order_id = absint( get_query_var( 'order-pay' ) );

		if ( $order_id > 0 ) {
			return (bool) apply_filters( 'ts_bnpl_allow_order_pay', false, $order_id );
		}

		return false;
	}

	/*
	|--------------------------------------------------------------------------
	| ۴. فلگ سفارش
	|--------------------------------------------------------------------------
	*/

	/**
	 * ثبت فلگ اقساطی روی سفارش هنگام ساخت.
	 *
	 * @param WC_Order $order سفارش در حال ساخت.
	 * @param array    $data  داده‌های فرم تسویه‌حساب.
	 *
	 * @return void
	 */
	public static function flag_order( $order, $data ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$method = $order->get_payment_method();

		if ( '' === $method && isset( $data['payment_method'] ) ) {
			$method = sanitize_text_field( (string) $data['payment_method'] );
		}

		if ( TS_BNPL_GATEWAY_ID === $method ) {
			$order->update_meta_data( self::ORDER_META, 'yes' );
		}
	}

	/**
	 * آیا سفارش اقساطی است؟
	 *
	 * @param WC_Order|int $order سفارش یا شناسه‌ی آن.
	 *
	 * @return bool
	 */
	public static function is_bnpl_order( $order ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( absint( $order ) );
		}

		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		return 'yes' === $order->get_meta( self::ORDER_META, true );
	}

	/**
	 * نمایش فلگ در باکس جزئیات سفارش.
	 *
	 * @param WC_Order $order سفارش.
	 *
	 * @return void
	 */
	public static function render_order_detail( $order ) {
		if ( ! self::is_bnpl_order( $order ) ) {
			return;
		}

		printf(
			'<p class="form-field form-field-wide ts-bnpl-order-flag"><strong>%1$s</strong> %2$s</p>',
			esc_html__( 'نوع خرید:', 'ts-bnpl' ),
			esc_html(
				sprintf(
					/* translators: %d: تعداد اقساط. */
					__( 'اقساطی (%d قسط)', 'ts-bnpl' ),
					(int) TS_BNPL_MONTHS
				)
			)
		);
	}

	/*
	|--------------------------------------------------------------------------
	| ۷. جدا نگه داشتن کیف پول جزئی از خرید اقساطی
	|--------------------------------------------------------------------------
	|
	| افزونه‌ی TS Partial Wallet درگاه‌آگنوستیک است و کل رفتارش را از کلید
	| نشستِ ts_wallet_checkbox_enabled می‌گیرد. اگر با اقساطی ترکیب شود، مبلغی
	| که واقعاً به دیجی‌پی می‌رود کمتر از مجموع اقساطی می‌شود و طرح ۴ قسطه‌ای که
	| به مشتری نشان داده‌ایم با فاکتور نمی‌خواند.
	|
	| به‌جای دست زدن به آن افزونه، همان کلید نشست را درست پیش از خودش خاموش
	| می‌کنیم. مسیرهای موجودِ خودش بقیه‌ی کار را می‌کنند: کارمزد اضافه نمی‌شود،
	| کیف پول کسر نمی‌شود و ردیف‌های کیف پول در جدول سفارش رندر نمی‌شوند.
	|
	*/

	/**
	 * خاموش کردن کیف پول جزئی وقتی درگاه اقساطی انتخاب شده است.
	 *
	 * @param WC_Cart $cart سبد خرید.
	 *
	 * @return void
	 */
	public static function block_partial_wallet( $cart ) {
		unset( $cart );

		self::force_partial_wallet_off( self::chosen_payment_method() );
	}

	/**
	 * همان خاموشی، این بار هنگام ساخت سفارش.
	 *
	 * به عنوان لایه‌ی دوم نگه داشته شده، چون هر مسیر ثبت سفارشی لزوماً
	 * woocommerce_cart_calculate_fees را اجرا نکرده است.
	 *
	 * @param WC_Order $order سفارش در حال ساخت.
	 * @param array    $data  داده‌های فرم تسویه‌حساب.
	 *
	 * @return void
	 */
	public static function block_partial_wallet_on_order( $order, $data ) {
		$method = $order instanceof WC_Order ? $order->get_payment_method() : '';

		if ( '' === $method && isset( $data['payment_method'] ) ) {
			$method = sanitize_text_field( (string) $data['payment_method'] );
		}

		self::force_partial_wallet_off( $method );
	}

	/**
	 * پاک کردن کلیدهای نشست کیف پول جزئی.
	 *
	 * @param string $payment_method روش پرداخت جاری.
	 *
	 * @return void
	 */
	private static function force_partial_wallet_off( $payment_method ) {
		if ( TS_BNPL_GATEWAY_ID !== $payment_method ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		WC()->session->set( 'ts_wallet_checkbox_enabled', 'no' );
		WC()->session->__unset( 'ts_wallet_used_amount' );
		WC()->session->__unset( 'ts_original_cart_total' );
	}

	/**
	 * روش پرداخت انتخاب‌شده در نشست.
	 *
	 * @return string
	 */
	private static function chosen_payment_method() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return '';
		}

		return (string) WC()->session->get( 'chosen_payment_method' );
	}

	/*
	|--------------------------------------------------------------------------
	| ۵. یکپارچه‌سازی با منطق اقساطی قالب
	|--------------------------------------------------------------------------
	|
	| قالب از قبل یک زنجیره‌ی کامل دارد: فیلتر درگاه روی اولویت ۴۰، اعتبارسنجی
	| سمت سرور روی woocommerce_after_checkout_validation و پشتیبانی از صفحه‌ی
	| پرداخت سفارش. آن زنجیره تا امروز از چک‌باکس _wbs_installment_eligible
	| تغذیه می‌شد. با اتصال به فیلترهای خودِ قالب، قیمت اقساطی تنها منبع
	| واجد شرایط بودن می‌شود و کل آن ماشین بدون تغییر کد قالب با داده‌ی ما کار
	| می‌کند.
	|
	*/

	/**
	 * واجد شرایط بودن محصول برای اقساط، از دید قالب.
	 *
	 * @param bool       $supports مقدار پیش‌فرض قالب.
	 * @param WC_Product $product  محصول یا متغیر.
	 *
	 * @return bool
	 */
	public static function product_supports_installments( $supports, $product ) {
		if ( ! $product instanceof WC_Product ) {
			return $supports;
		}

		/*
		 * عمداً به والد بازگشت داده نمی‌شود: متغیری که قیمت اقساطی ندارد واقعاً
		 * واجد شرایط نیست، حتی اگر متغیرهای دیگرِ همان محصول داشته باشند.
		 */
		return TS_BNPL_Data::get( $product ) > 0;
	}

	/**
	 * اعلام اینکه درگاه دیجی‌پی ذاتاً اقساطی است.
	 *
	 * بدون این، اعتبارسنجی سمت سرور قالب فقط زمانی فعال می‌شد که ادمین یادش
	 * بماند تیک «محدودیت خرید اقساطی» را در تنظیمات درگاه بزند. سایر درگاه‌ها
	 * دست‌نخورده می‌مانند.
	 *
	 * @param bool               $is_installment مقدار پیش‌فرض قالب.
	 * @param WC_Payment_Gateway $gateway        درگاه.
	 *
	 * @return bool
	 */
	public static function gateway_is_installment( $is_installment, $gateway ) {
		if ( $gateway instanceof WC_Payment_Gateway && TS_BNPL_GATEWAY_ID === $gateway->id ) {
			return true;
		}

		return $is_installment;
	}

	/**
	 * پنهان کردن چک‌باکس اقساطی قالب در صفحه‌ی محصول.
	 *
	 * حالا که قیمت اقساطی منبع یگانه است، آن چک‌باکس دیگر اثری ندارد و فقط
	 * ادمین را گمراه می‌کند. متای موجود پاک نمی‌شود؛ با غیرفعال کردن این
	 * افزونه فیلد و رفتار قبلی کاملاً برمی‌گردد.
	 *
	 * @return void
	 */
	public static function maybe_hide_theme_product_field() {
		if ( ! apply_filters( 'ts_bnpl_hide_theme_installment_field', true ) ) {
			return;
		}

		remove_action(
			'woocommerce_product_options_general_product_data',
			'wbs_checkout_render_installment_product_field'
		);

		/*
		 * ذخیره‌کننده هم باید برداشته شود. آن تابع نبودِ فیلد در POST را «نه»
		 * تفسیر می‌کند، پس با فیلد پنهان، هر بار ذخیره‌ی محصول مقدار قبلی قالب
		 * را بی‌صدا پاک می‌کرد.
		 */
		remove_action(
			'woocommerce_admin_process_product_object',
			'wbs_checkout_save_installment_product_field',
			20
		);
	}

	/*
	|--------------------------------------------------------------------------
	| ۶. حالت نمایشی درگاه
	|--------------------------------------------------------------------------
	|
	| پوسته‌ی درگاه در قالب عمداً تا نصب رابط رسمی دیجی‌پی نامرئی است. برای
	| اینکه فاز ۳ روی staging قابل آزمایش باشد، فقط «نمایش» درگاه باز می‌شود،
	| نه پرداخت. هیچ موفقیت جعلی و هیچ URL فرضی برگردانده نمی‌شود: تراکنش با
	| پیام روشن شکست می‌خورد و سفارش در وضعیت پرداخت‌نشده می‌ماند.
	|
	*/

	/**
	 * آیا حالت نمایشی فعال است؟
	 *
	 * پیش‌فرض خاموش. فعال‌سازی از ووکامرس ← پرداخت‌ها ← دیجی‌پی، تیک
	 * «حالت نمایشی (TS BNPL)». هیچ تغییری در wp-config لازم نیست.
	 *
	 * برای بستن قطعی روی سایت اصلی، صرف‌نظر از مقدار تیک:
	 *
	 *     define( 'TS_BNPL_TEST_GATEWAY', false );
	 *
	 * @return bool
	 */
	public static function is_test_mode() {
		if ( defined( 'TS_BNPL_TEST_GATEWAY' ) ) {
			return (bool) TS_BNPL_TEST_GATEWAY;
		}

		$settings = get_option( 'woocommerce_' . TS_BNPL_GATEWAY_ID . '_settings', array() );

		return is_array( $settings )
			&& isset( $settings[ self::TEST_MODE_FIELD ] )
			&& 'yes' === $settings[ self::TEST_MODE_FIELD ];
	}

	/**
	 * افزودن کلید حالت نمایشی به تنظیمات درگاه دیجی‌پی.
	 *
	 * عمداً به‌جای ثابت در wp-config، یک تیک در همان صفحه‌ای گذاشته شده که
	 * درگاه را فعال می‌کنید. پیش‌فرضش خاموش است و چون در دیتابیس همان سایت
	 * ذخیره می‌شود، با انتقال فایل‌ها به سایت اصلی منتقل نمی‌شود.
	 *
	 * @param array $form_fields فیلدهای تنظیمات درگاه.
	 *
	 * @return array
	 */
	public static function add_test_mode_field( $form_fields ) {
		if ( ! is_array( $form_fields ) ) {
			return $form_fields;
		}

		$form_fields[ self::TEST_MODE_FIELD ] = array(
			'title'       => __( 'حالت نمایشی (TS BNPL)', 'ts-bnpl' ),
			'type'        => 'checkbox',
			'label'       => __( 'درگاه را بدون رابط رسمی در تسویه‌حساب نمایش بده', 'ts-bnpl' ),
			'description' => __( 'فقط برای آزمایش رابط کاربری روی سایت staging. هیچ تراکنش واقعی انجام نمی‌شود: سفارش ثبت می‌شود اما پرداخت با پیام خطا شکست می‌خورد. روی سایت اصلی این تیک را نزنید.', 'ts-bnpl' ),
			'default'     => 'no',
			'desc_tip'    => false,
		);

		return $form_fields;
	}

	/**
	 * باز کردن نمایش درگاه در حالت نمایشی.
	 *
	 * اگر رابط رسمی نصب شده و خودش آمادگی را اعلام کرده باشد، دست نمی‌خوریم.
	 *
	 * @param bool               $ready   نتیجه‌ی فیلترهای قبلی.
	 * @param WC_Payment_Gateway $gateway درگاه.
	 *
	 * @return bool
	 */
	public static function declare_test_configured( $ready, $gateway ) {
		unset( $gateway );

		if ( $ready ) {
			return $ready;
		}

		return self::is_test_mode();
	}

	/**
	 * جلوگیری از تراکنش در حالت نمایشی، با پیام روشن.
	 *
	 * @param mixed    $result  نتیجه‌ی فیلترهای قبلی (رابط رسمی).
	 * @param WC_Order $order   سفارش.
	 * @param mixed    $gateway درگاه.
	 *
	 * @return mixed WP_Error در حالت نمایشی، در غیر این صورت نتیجه‌ی قبلی.
	 */
	public static function block_test_payment( $result, $order, $gateway ) {
		unset( $order, $gateway );

		// رابط رسمی پاسخ داده است؛ دخالت نمی‌کنیم.
		if ( null !== $result ) {
			return $result;
		}

		if ( ! self::is_test_mode() ) {
			return $result;
		}

		return new WP_Error(
			'ts_bnpl_test_mode',
			__( 'درگاه اقساطی دیجی‌پی در حالت آزمایشی است و هنوز به رابط رسمی وصل نشده. سفارش ثبت شد اما پرداختی انجام نگرفت.', 'ts-bnpl' )
		);
	}

	/**
	 * هشدار پیشخوان وقتی حالت نمایشی فعال است.
	 *
	 * @return void
	 */
	public static function render_test_mode_notice() {
		if ( ! self::is_test_mode() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id = $screen ? $screen->id : '';

		if ( ! in_array( $screen_id, array( 'woocommerce_page_wc-settings', 'plugins', 'dashboard' ), true ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'TS BNPL — حالت نمایشی درگاه فعال است.', 'ts-bnpl' ),
			esc_html__( 'درگاه دیجی‌پی در تسویه‌حساب نمایش داده می‌شود اما هیچ تراکنش واقعی انجام نمی‌دهد. پیش از انتقال به سایت اصلی، رابط رسمی دیجی‌پی را نصب کنید.', 'ts-bnpl' )
		);
	}

	/**
	 * افزودن نشان کوچک کنار شماره سفارش در لیست سفارش‌ها.
	 *
	 * این متد هم امضای حالت کلاسیک (شناسه‌ی پست) و هم HPOS (آبجکت سفارش) را
	 * پوشش می‌دهد و با اولویت ۲۰ پس از خروجی خود ووکامرس اجرا می‌شود.
	 *
	 * @param string       $column           نام ستون.
	 * @param WC_Order|int $order_or_post_id سفارش یا شناسه‌ی پست.
	 *
	 * @return void
	 */
	public static function render_order_column( $column, $order_or_post_id ) {
		if ( 'order_number' !== $column ) {
			return;
		}

		if ( ! self::is_bnpl_order( $order_or_post_id ) ) {
			return;
		}

		printf(
			'<span class="ts-bnpl-badge" title="%1$s">%2$s</span>',
			esc_attr__( 'این سفارش با درگاه اقساطی ثبت شده است', 'ts-bnpl' ),
			esc_html__( 'اقساطی', 'ts-bnpl' )
		);
	}
}
