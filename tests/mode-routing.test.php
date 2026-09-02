<?php
require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/includes/class-ts-bnpl-display.php';

ts_test_assert_true(
	defined( 'TS_BNPL_Display::MODE_VISUAL_LANDING' ),
	'Visual Landing has a stable mode constant'
);

$expected_modes = array( 'accordion', 'modal', 'landing', 'visual_landing' );
ts_test_assert_same( $expected_modes, TS_BNPL_Display::modes(), 'four allowed modes retain existing keys' );

TS_BNPL_Display::update_settings( 'visual_landing', '  Visual teaser  ', 42 );
$settings = TS_BNPL_Display::get_settings();
ts_test_assert_same( 'visual_landing', $settings['mode'], 'Visual mode round-trips' );
ts_test_assert_same( 'Visual teaser', $settings['teaser'], 'teaser remains normalized' );
ts_test_assert_same( 42, $settings['landing_page'], 'selected Page is retained' );

TS_BNPL_Display::update_settings( 'not-a-mode', '', 0 );
ts_test_assert_same( 'accordion', TS_BNPL_Display::get_settings()['mode'], 'invalid modes still fall back to Accordion' );

if ( method_exists( 'TS_BNPL_Display', 'is_landing_mode' ) ) {
	ts_test_assert_true( TS_BNPL_Display::is_landing_mode( 'landing' ), 'legacy Landing is a landing mode' );
	ts_test_assert_true( TS_BNPL_Display::is_landing_mode( 'visual_landing' ), 'Visual Landing is a landing mode' );
	ts_test_assert_false( TS_BNPL_Display::is_landing_mode( 'accordion' ), 'Accordion is not a landing mode' );
	ts_test_assert_false( TS_BNPL_Display::is_landing_mode( 'modal' ), 'Modal is not a landing mode' );
} else {
	$GLOBALS['ts_bnpl_test_failures'][] = 'is_landing_mode() is missing';
}

ts_test_finish();

