<?php
require __DIR__ . '/bootstrap.php';

$settings_file = dirname( __DIR__ ) . '/includes/class-ts-bnpl-visual-settings.php';
$media_file    = dirname( __DIR__ ) . '/includes/class-ts-bnpl-responsive-media.php';
$provider_file = dirname( __DIR__ ) . '/includes/class-ts-bnpl-providers.php';

if ( is_file( $media_file ) ) {
	require $media_file;
}
if ( is_file( $provider_file ) ) {
	require $provider_file;
}
if ( is_file( $settings_file ) ) {
	require $settings_file;
}

if ( ! class_exists( 'TS_BNPL_Visual_Settings' ) ) {
	$GLOBALS['ts_bnpl_test_failures'][] = 'TS_BNPL_Visual_Settings is missing';
	ts_test_finish();
}

$defaults = TS_BNPL_Visual_Settings::get();
ts_test_assert_same( 1, $defaults['schema_version'], 'settings schema is versioned' );
ts_test_assert_same( array(), $defaults['banners'], 'default banners are intentionally empty' );
ts_test_assert_same( 'wbs_digipay', $defaults['providers'][0]['provider_id'], 'DigiPay is the presentation default' );
ts_test_assert_false( array_key_exists( TS_BNPL_Visual_Settings::OPTION, $GLOBALS['ts_bnpl_test_options'] ), 'reading defaults does not write an option' );

$empty = TS_BNPL_Visual_Settings::sanitize(
	array(
		'schema_version' => 1,
		'banners'       => array(),
		'providers'     => array(),
	)
);
ts_test_assert_false( is_wp_error( $empty ), 'valid empty lists are accepted' );
ts_test_assert_same( array(), $empty['banners'], 'removed banners stay removed' );
ts_test_assert_same( array(), $empty['providers'], 'removed providers stay removed' );

$many_banners = array();
for ( $i = 0; $i < 25; $i++ ) {
	$many_banners[] = array( 'url' => '/offer-' . $i );
}
$normalized = TS_BNPL_Visual_Settings::sanitize(
	array(
		'schema_version' => 1,
		'banners'       => $many_banners,
		'providers'     => array(
			array( 'provider_id' => 'wbs_digipay', 'display_enabled' => '1' ),
			array( 'provider_id' => 'wbs_digipay', 'display_enabled' => '1' ),
		),
		'unknown'       => 'discard me',
	)
);
ts_test_assert_same( 20, count( $normalized['banners'] ), 'banner input is bounded to twenty rows' );
ts_test_assert_same( 1, count( $normalized['providers'] ), 'provider IDs are deduplicated' );
ts_test_assert_false( isset( $normalized['unknown'] ), 'unknown top-level fields are discarded' );

$GLOBALS['ts_bnpl_test_options'][ TS_BNPL_Visual_Settings::OPTION ] = array( 'schema_version' => 1, 'banners' => array( array( 'url' => '/keep' ) ) );
$error = TS_BNPL_Visual_Settings::save( 'malformed' );
ts_test_assert_true( is_wp_error( $error ), 'malformed payload is rejected' );
ts_test_assert_same( '/keep', $GLOBALS['ts_bnpl_test_options'][ TS_BNPL_Visual_Settings::OPTION ]['banners'][0]['url'], 'failed save preserves the previous option' );

$schema_error = TS_BNPL_Visual_Settings::save( array( 'schema_version' => 999 ) );
ts_test_assert_true( is_wp_error( $schema_error ), 'unsupported future schemas are rejected' );
ts_test_assert_same( '/keep', $GLOBALS['ts_bnpl_test_options'][ TS_BNPL_Visual_Settings::OPTION ]['banners'][0]['url'], 'unsupported schema preserves the previous option' );

$missing_schema_error = TS_BNPL_Visual_Settings::save( array( 'banners' => array() ) );
ts_test_assert_true( is_wp_error( $missing_schema_error ), 'missing schema versions are rejected' );
ts_test_assert_same( '/keep', $GLOBALS['ts_bnpl_test_options'][ TS_BNPL_Visual_Settings::OPTION ]['banners'][0]['url'], 'missing schema preserves the previous option' );

$GLOBALS['ts_bnpl_test_options'][ TS_BNPL_Visual_Settings::OPTION ] = array(
	'schema_version' => 1,
	'banners'       => array(),
	'providers'     => array(
		array( 'provider_id' => 'future_credit', 'display_enabled' => true, 'display_name' => 'آینده' ),
	),
);
$temporarily_missing = TS_BNPL_Visual_Settings::get();
ts_test_assert_same( 'future_credit', $temporarily_missing['providers'][0]['provider_id'], 'a previously trusted provider survives a temporary gateway outage' );

$temporarily_missing['providers'][] = array( 'provider_id' => 'invented_gateway', 'display_enabled' => true );
$resaved = TS_BNPL_Visual_Settings::save( $temporarily_missing );
ts_test_assert_same( 1, count( $resaved['providers'] ), 'a new unregistered provider ID is still rejected' );
ts_test_assert_same( 'future_credit', $resaved['providers'][0]['provider_id'], 'trusted unavailable provider remains editable across an atomic save' );

ts_test_finish();
