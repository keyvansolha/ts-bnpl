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
ts_test_assert_contains( 'data-ts-bnpl-add-banner', $html, 'banner rows can be added' );
ts_test_assert_contains( 'data-ts-bnpl-move-up', $html, 'rows have keyboard-accessible move up controls' );
ts_test_assert_contains( 'data-ts-bnpl-move-down', $html, 'rows have keyboard-accessible move down controls' );

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

ts_test_finish();
