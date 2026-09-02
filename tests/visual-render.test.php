<?php
require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/includes/class-ts-bnpl-display.php';
require dirname( __DIR__ ) . '/includes/class-ts-bnpl-responsive-media.php';
require dirname( __DIR__ ) . '/includes/class-ts-bnpl-providers.php';
require dirname( __DIR__ ) . '/includes/class-ts-bnpl-visual-settings.php';

define( 'THEME_COMPONENTS', __DIR__ . '/fixtures/' );

class TS_BNPL_Landing {
	public static $assets_enqueued = false;
	public static function is_landing() { return 42 === $GLOBALS['ts_bnpl_test_page_id']; }
	public static function get_url() { return 'https://example.test/page/42/'; }
	public static function get_page_id() { return 42; }
	public static function get_eligible_product_ids() { return array( 101, 102 ); }
	public static function shop_url() { return 'https://example.test/shop/?ts_bnpl=1'; }
	public static function enqueue_theme_card_assets( $force_swiper = false ) {
		self::$assets_enqueued = true;
		if ( $force_swiper ) {
			wp_enqueue_script( 'swiper', 'https://example.test/theme/swiper-bundle.min.js', array(), '11', true );
		}
		return array( 'archive', 'products-carousel' );
	}
	public static function card_template() { return THEME_COMPONENTS . 'product-cards/simple-card.php'; }
	public static function hero_logo_url() { return 'https://example.test/theme/images/logo.svg'; }
}

function ts_render_faq_items( $faqs, $start = 1 ) {
	$html = '';
	foreach ( $faqs as $faq ) {
		$html .= '<article class="ts-faq-card"><button>' . esc_html( $faq['question'] ) . '</button><div>' . esc_html( $faq['answer'] ) . '</div></article>';
	}
	return $html;
}

$renderer_file = dirname( __DIR__ ) . '/includes/class-ts-bnpl-visual-landing.php';
if ( is_file( $renderer_file ) ) {
	require $renderer_file;
}

if ( ! class_exists( 'TS_BNPL_Visual_Landing' ) ) {
	$GLOBALS['ts_bnpl_test_failures'][] = 'TS_BNPL_Visual_Landing is missing';
	ts_test_finish();
}

$GLOBALS['ts_bnpl_test_page_id']       = 42;
$GLOBALS['ts_bnpl_test_post_statuses'] = array( 42 => 'publish' );
$GLOBALS['ts_bnpl_test_attachments']   = array(
	1 => array( 'mime' => 'image/avif', 'url' => 'https://example.test/desktop.avif', 'width' => 1326, 'height' => 400 ),
	2 => array( 'mime' => 'image/webp', 'url' => 'https://example.test/desktop.webp', 'width' => 1326, 'height' => 400 ),
	3 => array( 'mime' => 'image/avif', 'url' => 'https://example.test/mobile.avif', 'width' => 1300, 'height' => 975 ),
	4 => array( 'mime' => 'image/webp', 'url' => 'https://example.test/mobile.webp', 'width' => 1300, 'height' => 975 ),
);

$gateway              = new WC_Payment_Gateway();
$gateway->id          = 'wbs_digipay';
$gateway->enabled     = 'yes';
$gateway->available   = true;
$gateway->installment = true;
$gateway->title       = 'دیجی‌پی';
$manager = new class( $gateway ) {
	private $gateway;
	public function __construct( $gateway ) { $this->gateway = $gateway; }
	public function payment_gateways() { return array( $this->gateway->id => $this->gateway ); }
};
$GLOBALS['ts_bnpl_test_wc'] = new class( $manager ) {
	private $manager;
	public function __construct( $manager ) { $this->manager = $manager; }
	public function payment_gateways() { return $this->manager; }
};

foreach ( array( 101, 102 ) as $product_id ) {
	$product = new WC_Product();
	$product->test_id = $product_id;
	$GLOBALS['ts_bnpl_test_products'][ $product_id ] = $product;
}

$media = array(
	'desktop_avif_id' => 1,
	'desktop_webp_id' => 2,
	'mobile_avif_id'  => 3,
	'mobile_webp_id'  => 4,
	'alt'             => 'تصویر آزمایشی',
);
$settings = TS_BNPL_Visual_Settings::defaults();
$settings['banners'] = array(
	array( 'media' => $media, 'url' => '/offer-one' ),
	array( 'media' => $media, 'url' => '/offer-two' ),
);
$settings['hero']['media'] = $media;
$settings['eligibility']['media'] = $media;
$settings['conditions']['media'] = $media;
$settings['final_cta']['media'] = $media;
$GLOBALS['ts_bnpl_test_options'][ TS_BNPL_Visual_Settings::OPTION ] = $settings;

TS_BNPL_Display::update_settings( 'accordion', 'Teaser', 42 );
ts_test_assert_false( TS_BNPL_Visual_Landing::is_active(), 'Visual renderer is inactive in Accordion mode' );
ts_test_assert_same( 'editor content', TS_BNPL_Visual_Landing::render( 'editor content' ), 'inactive mode preserves editor content' );

TS_BNPL_Display::update_settings( 'visual_landing', 'Teaser', 42 );
ts_test_assert_true( TS_BNPL_Visual_Landing::is_active(), 'Visual renderer activates only on the selected Page and mode' );

$classes = TS_BNPL_Visual_Landing::body_class( array( 'base' ) );
ts_test_assert_true( in_array( 'ts-bnpl-visual-landing-page', $classes, true ), 'Visual request receives an isolated body class' );

$html = TS_BNPL_Visual_Landing::render( 'editor content' );
ts_test_assert_false( false !== strpos( $html, 'editor content' ), 'Visual mode replaces editor content so banner comes first' );
$second_render = TS_BNPL_Visual_Landing::render( 'editor content again' );
ts_test_assert_contains( 'ts-bnpl-visual-landing', $second_render, 'content filtering remains re-entrant for SEO and preview consumers' );

$ordered = array(
	'<section class="ts-bnpl-visual-banner',
	'<section class="ts-bnpl-visual-hero',
	'<section class="ts-bnpl-visual-providers',
	'<section class="ts-bnpl-visual-how',
	'<section class="ts-bnpl-visual-eligibility',
	'<section class="ts-bnpl-visual-products',
	'<section class="ts-bnpl-visual-conditions',
	'<section class="ts-bnpl-visual-faq',
	'<section class="ts-bnpl-visual-final',
);
$last_position = -1;
foreach ( $ordered as $marker ) {
	$position = strpos( $html, $marker );
	ts_test_assert_true( false !== $position && $position > $last_position, $marker . ' renders in required order' );
	$last_position = false === $position ? $last_position : $position;
}

ts_test_assert_contains( 'data-slide-count="2"', $html, 'multiple valid top banners form one carousel' );
ts_test_assert_same( 1, substr_count( $html, 'data-ts-bnpl-visual-banner' ), 'only the top marketing media is a slider' );
ts_test_assert_contains( 'theme/images/logo.svg', $html, 'Hero reuses the existing rotating SVG' );
ts_test_assert_same( 4, substr_count( $html, 'ts-bnpl-visual-steps__item' ), 'purchase flow keeps exactly four steps' );
ts_test_assert_contains( 'درباره‌ی مبلغ نهایی', $html, 'conditions retain the current factual subject' );
ts_test_assert_contains( 'بر اساس روش اعتباری‌ای که انتخاب می‌کنید مشخص و به شما نمایش داده می‌شود.', $html, 'conditions preserve the existing factual pricing explanation' );
ts_test_assert_contains( 'آیا همه‌ی کالاهای سایت را می‌شود اعتباری خرید؟', $html, 'FAQ retains current content' );
ts_test_assert_contains( 'اگر کالایی در سبد این امکان را نداشته باشد، روش‌های اعتباری در مرحله‌ی پرداخت نمایش داده نمی‌شوند.', $html, 'FAQ keeps the existing multi-item eligibility answer intact' );
ts_test_assert_contains( 'تنها تفاوت، روش پرداخت است.', $html, 'FAQ keeps the existing fulfilment answer intact' );
ts_test_assert_contains( 'دیجی‌پی', $html, 'active DigiPay provider is rendered' );
ts_test_assert_contains( 'data-product-id="101"', $html, 'first eligible product uses the canonical card boundary' );
ts_test_assert_contains( 'data-product-id="102"', $html, 'second eligible product stays dynamic' );

TS_BNPL_Visual_Landing::enqueue_assets();
ts_test_assert_true( TS_BNPL_Landing::$assets_enqueued, 'renderer reuses the existing theme card/carousel bridge' );
ts_test_assert_true( isset( $GLOBALS['ts_bnpl_test_styles']['ts-bnpl-visual-landing'] ), 'Visual stylesheet is page-scoped' );
ts_test_assert_true( isset( $GLOBALS['ts_bnpl_test_scripts']['ts-bnpl-visual-landing'] ), 'multi-banner page receives the Visual initializer' );
ts_test_assert_true( isset( $GLOBALS['ts_bnpl_test_scripts']['swiper'] ), 'top banner forces the existing Swiper bundle on every viewport' );

$text_only_settings = TS_BNPL_Visual_Settings::defaults();
$text_only_settings['banners'] = array( array( 'media' => $media, 'url' => '' ) );
$GLOBALS['ts_bnpl_test_options'][ TS_BNPL_Visual_Settings::OPTION ] = $text_only_settings;
$text_only_html = TS_BNPL_Visual_Landing::landing_html();

ts_test_assert_contains( 'data-slide-count="1"', $text_only_html, 'one valid banner renders as a static banner' );
ts_test_assert_false( false !== strpos( $text_only_html, 'ts-bnpl-visual-banner__control' ), 'one banner has no slider controls' );
ts_test_assert_contains( 'ts-bnpl-visual-hero--text-only', $text_only_html, 'Hero rebalances when its optional visual is absent' );
ts_test_assert_contains( 'ts-bnpl-visual-split--text-only', $text_only_html, 'split sections rebalance when optional visuals are absent' );
ts_test_assert_contains( 'ts-bnpl-visual-final--text-only', $text_only_html, 'final CTA rebalances when its optional visual is absent' );

$visual_css = file_get_contents( dirname( __DIR__ ) . '/assets/css/ts-bnpl-visual-landing.css' );
ts_test_assert_contains( '.ts-bnpl-visual-hero.ts-bnpl-visual-hero--text-only', $visual_css, 'tablet Hero rules cannot override the text-only layout' );
ts_test_assert_contains( 'font-variation-settings: normal !important;', $visual_css, 'Page heading variation overrides are neutralized for canonical components' );

$linked_media = $media;
$linked_media['alt'] = '';
$text_only_settings['banners'] = array( array( 'media' => $linked_media, 'url' => '/linked-offer' ) );
$GLOBALS['ts_bnpl_test_options'][ TS_BNPL_Visual_Settings::OPTION ] = $text_only_settings;
$linked_banner_html = TS_BNPL_Visual_Landing::landing_html();
ts_test_assert_contains( 'aria-label="مشاهده پیشنهاد خرید اعتباری"', $linked_banner_html, 'a linked decorative banner always has an accessible name' );

$text_only_settings['banners'] = array();
$text_only_settings['hero']['media'] = $media;
$GLOBALS['ts_bnpl_test_options'][ TS_BNPL_Visual_Settings::OPTION ] = $text_only_settings;
$no_banner_html = TS_BNPL_Visual_Landing::landing_html();
ts_test_assert_false( false !== strpos( $no_banner_html, '<section class="ts-bnpl-visual-banner' ), 'zero valid banners omit the banner section' );
ts_test_assert_contains( 'class="ts-bnpl-visual-hero__image"', $no_banner_html, 'Hero visual becomes the first artwork when no banner is configured' );
ts_test_assert_contains( 'loading="eager"', $no_banner_html, 'above-fold Hero visual is not lazy-loaded when no banner precedes it' );
ts_test_assert_contains( 'fetchpriority="high"', $no_banner_html, 'above-fold Hero visual receives LCP priority when no banner precedes it' );

ts_test_finish();
