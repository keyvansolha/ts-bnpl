<?php
/**
 * Plugin Name:       TS BNPL Digipay
 * Plugin URI:        https://www.tehranspeaker.com/
 * Description:       قیمت اقساطی محصولات و نمایش طرح ۴ قسطه دیجی‌پی روی فروشگاه تهران‌اسپیکر.
 * Version:           0.5.3
 * Author:            Keyvan Havestin
 * Author URI:        https://keyvansolha.ir
 * Text Domain:       ts-bnpl
 * Domain Path:       /languages
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 *
 * @package TS_BNPL
 */

defined( 'ABSPATH' ) || exit;

/*
|--------------------------------------------------------------------------
| ثابت‌ها
|--------------------------------------------------------------------------
|
| همه‌ی ثابت‌ها با defined() محافظت شده‌اند تا در صورت نیاز بتوان آن‌ها را
| از wp-config.php بازنویسی کرد، بدون دست زدن به کد افزونه.
|
*/

/*
 * نسخه از هدر خود افزونه خوانده می‌شود، نه دستی.
 *
 * این ثابت نسخه‌ی همه‌ی CSS و JS افزونه است. قبلاً یک عدد جدا بود و از هدر عقب
 * افتاده بود (0.1.0 در برابر 0.3.1)، یعنی مرورگرها با هر انتشار همان فایل
 * کش‌شده‌ی قدیمی را نگه می‌داشتند. با خواندن از هدر، دیگر نمی‌توانند از هم جدا
 * بیفتند و فقط کافی است هدر بالا را بالا ببرید.
 */
$ts_bnpl_header = get_file_data( __FILE__, array( 'version' => 'Version' ), 'plugin' );

define( 'TS_BNPL_VERSION', ! empty( $ts_bnpl_header['version'] ) ? $ts_bnpl_header['version'] : '0.0.0' );

unset( $ts_bnpl_header );
define( 'TS_BNPL_PATH', plugin_dir_path( __FILE__ ) );
define( 'TS_BNPL_URL', plugin_dir_url( __FILE__ ) );

/**
 * شناسه درگاه پرداخت اقساطی دیجی‌پی.
 *
 * مقدار از پوسته‌ی درگاه در قالب گرفته شده است:
 * themes/amazing/lib/Checkout/class-wbs-digipay-gateway.php
 * طبق سند نگهداری درگاه‌ها، این شناسه پس از استفاده در سفارش‌های واقعی نباید
 * تغییر کند. اگر لازم شد، از wp-config.php بازنویسی کنید.
 */
defined( 'TS_BNPL_GATEWAY_ID' ) || define( 'TS_BNPL_GATEWAY_ID', 'wbs_digipay' );

/** تعداد اقساط. */
defined( 'TS_BNPL_MONTHS' ) || define( 'TS_BNPL_MONTHS', 4 );

/*
 * سقف و کف مبلغ عمداً تعریف نشده‌اند.
 *
 * تنها شرط واجد شرایط بودن، داشتن قیمت اقساطی روی همه‌ی اقلام سبد است و آن
 * قیمت از حسابداری می‌آید. اگر بعداً دیجی‌پی محدودیت مبلغ اعلام کرد، تنها جای
 * اضافه کردنش TS_BNPL_Checkout::is_cart_eligible() است.
 */

/**
 * اعلام سازگاری با جدول سفارش‌های سفارشی ووکامرس (HPOS).
 *
 * این هوک پیش از plugins_loaded ووکامرس اجرا می‌شود، بنابراین باید در سطح
 * فایل ثبت شود نه داخل bootstrap.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * بارگذاری فایل ترجمه.
 *
 * @return void
 */
function ts_bnpl_load_textdomain() {
	load_plugin_textdomain( 'ts-bnpl', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'ts_bnpl_load_textdomain' );

/**
 * اعلان پیشخوان در صورت نبود ووکامرس.
 *
 * @return void
 */
function ts_bnpl_missing_woocommerce_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'افزونه «TS BNPL» برای کار کردن به ووکامرس نیاز دارد. تا زمانی که ووکامرس نصب و فعال نشود، این افزونه بارگذاری نمی‌شود.', 'ts-bnpl' )
	);
}

/**
 * بارگذاری کلاس‌ها و ثبت هوک‌ها.
 *
 * @return void
 */
function ts_bnpl_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'ts_bnpl_missing_woocommerce_notice' );

		return;
	}

	require_once TS_BNPL_PATH . 'includes/class-ts-bnpl-data.php';
	require_once TS_BNPL_PATH . 'includes/class-ts-bnpl-admin.php';
	require_once TS_BNPL_PATH . 'includes/class-ts-bnpl-report.php';
	require_once TS_BNPL_PATH . 'includes/class-ts-bnpl-checkout.php';
	require_once TS_BNPL_PATH . 'includes/class-ts-bnpl-display.php';
	require_once TS_BNPL_PATH . 'includes/class-ts-bnpl-landing.php';
	require_once TS_BNPL_PATH . 'includes/class-ts-bnpl-responsive-media.php';
	require_once TS_BNPL_PATH . 'includes/class-ts-bnpl-providers.php';
	require_once TS_BNPL_PATH . 'includes/class-ts-bnpl-visual-settings.php';
	require_once TS_BNPL_PATH . 'includes/class-ts-bnpl-visual-admin.php';
	require_once TS_BNPL_PATH . 'includes/class-ts-bnpl-visual-landing.php';

	TS_BNPL_Data::init();
	TS_BNPL_Admin::init();
	TS_BNPL_Report::init();
	TS_BNPL_Checkout::init();
	TS_BNPL_Display::init();
	TS_BNPL_Landing::init();
	TS_BNPL_Visual_Admin::init();
	TS_BNPL_Visual_Landing::init();
}
add_action( 'plugins_loaded', 'ts_bnpl_bootstrap', 20 );
