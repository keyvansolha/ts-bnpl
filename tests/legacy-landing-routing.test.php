<?php
require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/includes/class-ts-bnpl-display.php';
require dirname( __DIR__ ) . '/includes/class-ts-bnpl-landing.php';

$GLOBALS['ts_bnpl_test_page_id']        = 42;
$GLOBALS['ts_bnpl_test_post_statuses']  = array( 42 => 'publish' );
$GLOBALS['ts_bnpl_test_is_product']     = true;
$GLOBALS['ts_bnpl_test_product']        = new WC_Product();

foreach ( array( 'accordion', 'modal', 'landing' ) as $mode ) {
	TS_BNPL_Display::update_settings( $mode, 'Teaser', 42 );
	if ( method_exists( 'TS_BNPL_Landing', 'should_render_legacy' ) ) {
		ts_test_assert_true( TS_BNPL_Landing::should_render_legacy(), 'legacy renderer remains active for ' . $mode );
	}
	$classes = TS_BNPL_Landing::body_class( array( 'base' ) );
	ts_test_assert_true( in_array( 'ts-bnpl-landing-page', $classes, true ), 'legacy body class remains for ' . $mode );
}

TS_BNPL_Display::update_settings( 'visual_landing', 'Teaser', 42 );

if ( method_exists( 'TS_BNPL_Landing', 'should_render_legacy' ) ) {
	ts_test_assert_false( TS_BNPL_Landing::should_render_legacy(), 'legacy renderer is suppressed for Visual Landing' );
} else {
	$GLOBALS['ts_bnpl_test_failures'][] = 'should_render_legacy() is missing';
}

$classes = TS_BNPL_Landing::body_class( array( 'base' ) );
ts_test_assert_false( in_array( 'ts-bnpl-landing-page', $classes, true ), 'legacy body class is suppressed for Visual Landing' );

$teaser = TS_BNPL_Display::teaser_html( 4000000 );
ts_test_assert_contains( '<a', $teaser, 'Visual Landing teaser is a navigation link' );
ts_test_assert_contains( 'https://example.test/page/42/', $teaser, 'Visual Landing teaser uses the selected Page' );

ob_start();
TS_BNPL_Display::render_modal();
$modal = ob_get_clean();
ts_test_assert_same( '', trim( $modal ), 'Visual Landing never renders the product modal' );

ts_test_finish();
