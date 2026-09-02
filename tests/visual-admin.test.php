<?php
require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/includes/class-ts-bnpl-responsive-media.php';
require dirname( __DIR__ ) . '/includes/class-ts-bnpl-providers.php';
require dirname( __DIR__ ) . '/includes/class-ts-bnpl-visual-settings.php';

$admin_file = dirname( __DIR__ ) . '/includes/class-ts-bnpl-visual-admin.php';
if ( is_file( $admin_file ) ) {
	require $admin_file;
}

if ( ! class_exists( 'TS_BNPL_Visual_Admin' ) ) {
	$GLOBALS['ts_bnpl_test_failures'][] = 'TS_BNPL_Visual_Admin is missing';
	ts_test_finish();
}

TS_BNPL_Visual_Admin::init();
ts_test_assert_true( isset( $GLOBALS['ts_bnpl_test_hooks']['admin_menu'] ), 'admin menu hook is registered' );
ts_test_assert_true( isset( $GLOBALS['ts_bnpl_test_hooks']['admin_post_ts_bnpl_save_visual_landing'] ), 'admin-post save hook is registered' );

TS_BNPL_Visual_Admin::register_page();
$submenu = $GLOBALS['ts_bnpl_test_submenus'][0];
ts_test_assert_same( 'edit.php?post_type=product', $submenu['parent'], 'Visual manager stays under Products' );
ts_test_assert_same( 'manage_woocommerce', $submenu['capability'], 'Visual manager matches BNPL capability' );
ts_test_assert_same( 'ts-bnpl-visual-landing', $submenu['slug'], 'Visual manager has a stable submenu slug' );

TS_BNPL_Visual_Admin::enqueue_assets( 'product_page_ts-bnpl-visual-landing' );
ts_test_assert_true( $GLOBALS['ts_bnpl_test_media_enqueued'], 'Media Library is enqueued on the Visual manager' );
ts_test_assert_true( in_array( 'jquery-ui-sortable', $GLOBALS['ts_bnpl_test_scripts']['ts-bnpl-visual-admin']['deps'], true ), 'admin repeater uses core Sortable' );

$GLOBALS['ts_bnpl_test_media_enqueued'] = false;
TS_BNPL_Visual_Admin::enqueue_assets( 'product_page_unrelated' );
ts_test_assert_false( $GLOBALS['ts_bnpl_test_media_enqueued'], 'Media Library is not enqueued elsewhere' );

$admin_gateway              = new WC_Payment_Gateway();
$admin_gateway->id          = 'wbs_digipay';
$admin_gateway->enabled     = 'no';
$admin_gateway->available   = false;
$admin_gateway->installment = true;
$admin_gateway->title       = 'دیجی‌پی';
$admin_manager = new class( $admin_gateway ) {
	private $gateway;
	public function __construct( $gateway ) { $this->gateway = $gateway; }
	public function payment_gateways() { return array( $this->gateway->id => $this->gateway ); }
};
$GLOBALS['ts_bnpl_test_wc'] = new class( $admin_manager ) {
	private $manager;
	public function __construct( $manager ) { $this->manager = $manager; }
	public function payment_gateways() { return $this->manager; }
};

ob_start();
TS_BNPL_Visual_Admin::render_page();
$html = ob_get_clean();
foreach ( array( 'Top Banners', 'Hero', 'Providers', 'Eligibility', 'Conditions / Information', 'Final CTA' ) as $label ) {
	ts_test_assert_contains( $label, $html, 'manager renders the ' . $label . ' tab' );
}
ts_test_assert_contains( 'ts_bnpl_visual[banners]', $html, 'banner fields use the isolated option payload' );
ts_test_assert_contains( 'desktop_avif_id', $html, 'responsive media includes Desktop AVIF' );
ts_test_assert_contains( 'desktop_webp_id', $html, 'responsive media includes Desktop WebP' );
ts_test_assert_contains( 'mobile_avif_id', $html, 'responsive media includes Mobile AVIF' );
ts_test_assert_contains( 'mobile_webp_id', $html, 'responsive media includes Mobile WebP' );
ts_test_assert_contains( 'data-media-kind="desktop_avif_id"', $html, 'media fields expose their exact MIME slot to the picker' );
ts_test_assert_contains( 'data-ts-bnpl-add-banner', $html, 'banner rows can be added' );
ts_test_assert_contains( 'data-ts-bnpl-move-up', $html, 'rows have keyboard-accessible move up controls' );
ts_test_assert_contains( 'data-ts-bnpl-move-down', $html, 'rows have keyboard-accessible move down controls' );
ts_test_assert_false( false !== strpos( $html, 'type="url"' ), 'anchor and relative defaults are not blocked by native absolute-URL validation' );
ts_test_assert_contains( 'inputmode="url"', $html, 'URL-like text fields still receive an appropriate mobile keyboard hint' );
ts_test_assert_contains( 'درگاه در ووکامرس غیرفعال است', $html, 'an inactive configured provider receives an explicit admin warning' );

$GLOBALS['ts_bnpl_test_options'][ TS_BNPL_Visual_Settings::OPTION ] = array(
	'schema_version' => 1,
	'banners'       => array(),
	'providers'     => array( array( 'provider_id' => 'future_credit', 'display_enabled' => true ) ),
);
ob_start();
TS_BNPL_Visual_Admin::render_page();
$missing_provider_html = ob_get_clean();
ts_test_assert_contains( 'ردیف برای ویرایش آینده حفظ می‌شود', $missing_provider_html, 'temporarily missing provider remains visible with an admin warning' );

$GLOBALS['ts_bnpl_test_options'][ TS_BNPL_Visual_Settings::OPTION ] = array(
	'schema_version' => 1,
	'banners'       => array(),
	'providers'     => array(),
	'hero'          => array( 'media' => array( 'desktop_avif_id' => 777 ) ),
);
ob_start();
TS_BNPL_Visual_Admin::render_page();
$stale_media_html = ob_get_clean();
ts_test_assert_contains( 'فایل رسانه‌ی نامعتبر یا حذف‌شده', $stale_media_html, 'stale stored attachments produce an explicit admin warning' );

$GLOBALS['ts_bnpl_test_capabilities']['manage_woocommerce'] = false;
$denied = TS_BNPL_Visual_Admin::save_request( array( '_wpnonce' => 'valid', 'ts_bnpl_visual' => array() ) );
ts_test_assert_true( is_wp_error( $denied ), 'save rejects users without WooCommerce management permission' );
ts_test_assert_same( 'ts_bnpl_visual_forbidden', $denied->get_error_code(), 'permission failure is stable' );

$GLOBALS['ts_bnpl_test_capabilities']['manage_woocommerce'] = true;
$GLOBALS['ts_bnpl_test_nonce_valid'] = false;
$bad_nonce = TS_BNPL_Visual_Admin::save_request( array( '_wpnonce' => 'bad', 'ts_bnpl_visual' => array() ) );
ts_test_assert_true( is_wp_error( $bad_nonce ), 'save rejects invalid nonce' );
ts_test_assert_same( 'ts_bnpl_visual_bad_nonce', $bad_nonce->get_error_code(), 'nonce failure is stable' );

$GLOBALS['ts_bnpl_test_nonce_valid'] = true;
$saved = TS_BNPL_Visual_Admin::save_request(
	array(
		'_wpnonce'      => 'valid',
		'ts_bnpl_visual' => array( 'banners' => array(), 'providers' => array() ),
	)
);
ts_test_assert_false( is_wp_error( $saved ), 'valid admin payload saves' );
ts_test_assert_same( array(), get_option( TS_BNPL_Visual_Settings::OPTION )['banners'], 'saved banners remain empty' );

$GLOBALS['ts_bnpl_test_capabilities']['upload_files'] = false;
$text_only_save = TS_BNPL_Visual_Admin::save_request(
	array(
		'_wpnonce'       => 'valid',
		'ts_bnpl_visual' => array( 'banners' => array(), 'providers' => array(), 'hero' => array( 'title' => 'متن تازه' ) ),
	)
);
ts_test_assert_false( is_wp_error( $text_only_save ), 'text-only changes do not require upload_files' );

$GLOBALS['ts_bnpl_test_attachments'][91] = array( 'mime' => 'image/avif', 'url' => 'https://example.test/new.avif', 'width' => 1000, 'height' => 1000 );
$media_payload = array(
	'banners' => array(),
	'providers' => array(),
	'hero' => array( 'media' => array( 'desktop_avif_id' => 91 ) ),
);
$upload_denied = TS_BNPL_Visual_Admin::save_request( array( '_wpnonce' => 'valid', 'ts_bnpl_visual' => $media_payload ) );
ts_test_assert_true( is_wp_error( $upload_denied ), 'attachment changes require upload_files' );

$GLOBALS['ts_bnpl_test_capabilities']['upload_files'] = true;
$GLOBALS['ts_bnpl_test_capabilities']['edit_post'] = false;
$attachment_denied = TS_BNPL_Visual_Admin::save_request( array( '_wpnonce' => 'valid', 'ts_bnpl_visual' => $media_payload ) );
ts_test_assert_true( is_wp_error( $attachment_denied ), 'new attachment IDs require edit permission' );

$GLOBALS['ts_bnpl_test_capabilities']['edit_post'] = true;
$GLOBALS['ts_bnpl_test_attachments'][92] = array( 'mime' => 'image/jpeg', 'url' => 'https://example.test/wrong.jpg', 'width' => 1000, 'height' => 1000 );
$media_payload['hero']['media']['desktop_avif_id'] = 92;
$mime_denied = TS_BNPL_Visual_Admin::save_request( array( '_wpnonce' => 'valid', 'ts_bnpl_visual' => $media_payload ) );
ts_test_assert_true( is_wp_error( $mime_denied ), 'a non-AVIF attachment in an AVIF slot produces a save error' );
if ( is_wp_error( $mime_denied ) ) {
	ts_test_assert_same( 'ts_bnpl_visual_invalid_media', $mime_denied->get_error_code(), 'invalid media has an actionable stable error code' );
}

ts_test_finish();
