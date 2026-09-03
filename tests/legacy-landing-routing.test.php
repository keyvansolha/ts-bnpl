<?php
require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/includes/class-ts-bnpl-display.php';
require dirname( __DIR__ ) . '/includes/class-ts-bnpl-landing.php';

$GLOBALS['ts_bnpl_test_page_id']        = 42;
$GLOBALS['ts_bnpl_test_post_statuses']  = array( 42 => 'publish' );
$GLOBALS['ts_bnpl_test_is_product']     = true;
$GLOBALS['ts_bnpl_test_product']        = new WC_Product();

ts_test_assert_true( is_callable( array( 'TS_BNPL_Landing', 'enqueue_theme_card_assets' ) ), 'theme card asset bridge is reusable by both landing modes' );
ts_test_assert_true( is_callable( array( 'TS_BNPL_Landing', 'card_template' ) ), 'canonical card resolver is reusable by both landing modes' );
ts_test_assert_true( is_callable( array( 'TS_BNPL_Landing', 'hero_logo_url' ) ), 'rotating SVG resolver is reusable by both landing modes' );

TS_BNPL_Display::update_settings( 'accordion', 'Accordion teaser', 42 );
$accordion_teaser = TS_BNPL_Display::teaser_html( 4000000 );
ts_test_assert_contains( 'ts-bnpl__offer--accordion', $accordion_teaser, 'Accordion teaser keeps the accordion variant' );
ts_test_assert_contains( 'data-ts-bnpl-toggle', $accordion_teaser, 'Accordion teaser exposes the inline-panel toggle control' );
ts_test_assert_contains( 'aria-expanded="false"', $accordion_teaser, 'Accordion teaser begins in its collapsed state' );
ts_test_assert_contains( '<div class="ts-bnpl__panel" hidden>', $accordion_teaser, 'Accordion teaser includes a hidden installment panel' );
ts_test_assert_contains( '۱,۰۰۰,۰۰۰', $accordion_teaser, 'Accordion panel carries the installment amount payload' );

TS_BNPL_Display::update_settings( 'modal', 'Modal teaser', 42 );
$modal_teaser = TS_BNPL_Display::teaser_html( 4000000 );
ts_test_assert_contains( 'ts-bnpl__offer--modal', $modal_teaser, 'Modal teaser keeps the modal variant' );
ts_test_assert_contains( 'data-ts-bnpl-open', $modal_teaser, 'Modal teaser exposes the modal-open control' );
ts_test_assert_contains( 'aria-haspopup="dialog"', $modal_teaser, 'Modal teaser identifies its dialog behavior' );
ts_test_assert_contains( 'aria-controls="ts-bnpl-modal"', $modal_teaser, 'Modal teaser targets the canonical modal' );
ts_test_assert_contains( '<div class="ts-bnpl__plan-source" hidden>', $modal_teaser, 'Modal teaser carries a hidden plan source' );
ts_test_assert_contains( '۱,۰۰۰,۰۰۰', $modal_teaser, 'Modal plan source carries the installment amount payload' );

TS_BNPL_Display::update_settings( 'landing', 'Legacy landing teaser', 42 );
$legacy_landing_teaser = TS_BNPL_Display::teaser_html( 4000000 );
ts_test_assert_contains( 'ts-bnpl__offer--landing', $legacy_landing_teaser, 'Legacy Landing teaser uses the shared navigation variant' );
ts_test_assert_contains( '<a class="ts-bnpl__teaser" href="https://example.test/page/42/">', $legacy_landing_teaser, 'Legacy Landing teaser links to the selected Page root' );

TS_BNPL_Display::update_settings( 'visual_landing', 'Visual landing teaser', 42 );
$visual_landing_teaser = TS_BNPL_Display::teaser_html( 4000000 );
ts_test_assert_contains( 'ts-bnpl__offer--landing', $visual_landing_teaser, 'Visual Landing teaser uses the shared navigation variant' );
ts_test_assert_contains( '<a class="ts-bnpl__teaser" href="https://example.test/page/42/">', $visual_landing_teaser, 'Visual Landing teaser links to the selected Page root' );

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

if ( method_exists( 'TS_BNPL_Display', 'landing_teaser_html' ) ) {
	$teaching_teaser = TS_BNPL_Display::landing_teaser_html( '#ts-bnpl-visual-products' );
	ts_test_assert_contains( 'ts-bnpl__offer--landing', $teaching_teaser, 'shared landing teaser keeps the canonical offer variant' );
	ts_test_assert_contains( 'href="#ts-bnpl-visual-products"', $teaching_teaser, 'shared landing teaser accepts a section-specific destination' );
	ts_test_assert_contains( 'بیشتر بدانید', $teaching_teaser, 'shared landing teaser keeps the canonical action copy' );
	ts_test_assert_same( '', TS_BNPL_Display::landing_teaser_html( '' ), 'shared landing teaser refuses an empty destination' );
} else {
	$GLOBALS['ts_bnpl_test_failures'][] = 'landing_teaser_html() is missing';
}

ob_start();
TS_BNPL_Display::render_modal();
$modal = ob_get_clean();
ts_test_assert_same( '', trim( $modal ), 'Visual Landing never renders the product modal' );

ts_test_finish();
