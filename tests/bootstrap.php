<?php
/**
 * Minimal WordPress/WooCommerce test doubles for standalone plugin tests.
 *
 * @package TS_BNPL
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'TS_BNPL_URL', 'https://example.test/wp-content/plugins/ts-bnpl/' );
define( 'TS_BNPL_PATH', dirname( __DIR__ ) . '/' );
define( 'TS_BNPL_VERSION', 'test' );
define( 'TS_BNPL_MONTHS', 4 );

$GLOBALS['ts_bnpl_test_options']       = array();
$GLOBALS['ts_bnpl_test_filters']       = array();
$GLOBALS['ts_bnpl_test_page_id']       = 0;
$GLOBALS['ts_bnpl_test_post_statuses'] = array();
$GLOBALS['ts_bnpl_test_is_product']    = false;
$GLOBALS['ts_bnpl_test_product']       = null;
$GLOBALS['ts_bnpl_test_failures']      = array();

function ts_test_reset() {
	$GLOBALS['ts_bnpl_test_options']       = array();
	$GLOBALS['ts_bnpl_test_filters']       = array();
	$GLOBALS['ts_bnpl_test_page_id']       = 0;
	$GLOBALS['ts_bnpl_test_post_statuses'] = array();
	$GLOBALS['ts_bnpl_test_is_product']    = false;
	$GLOBALS['ts_bnpl_test_product']       = null;
	$GLOBALS['ts_bnpl_test_failures']      = array();
}

function ts_test_assert_true( $actual, $message ) {
	if ( true !== $actual ) {
		$GLOBALS['ts_bnpl_test_failures'][] = $message . ' (expected true)';
	}
}

function ts_test_assert_false( $actual, $message ) {
	if ( false !== $actual ) {
		$GLOBALS['ts_bnpl_test_failures'][] = $message . ' (expected false)';
	}
}

function ts_test_assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		$GLOBALS['ts_bnpl_test_failures'][] = sprintf(
			'%s (expected %s, got %s)',
			$message,
			var_export( $expected, true ),
			var_export( $actual, true )
		);
	}
}

function ts_test_assert_contains( $needle, $haystack, $message ) {
	if ( false === strpos( (string) $haystack, (string) $needle ) ) {
		$GLOBALS['ts_bnpl_test_failures'][] = $message . ' (missing ' . var_export( $needle, true ) . ')';
	}
}

function ts_test_finish() {
	if ( $GLOBALS['ts_bnpl_test_failures'] ) {
		fwrite( STDERR, "FAIL\n- " . implode( "\n- ", $GLOBALS['ts_bnpl_test_failures'] ) . "\n" );
		exit( 1 );
	}

	fwrite( STDOUT, "PASS\n" );
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['ts_bnpl_test_options'] )
		? $GLOBALS['ts_bnpl_test_options'][ $key ]
		: $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['ts_bnpl_test_options'][ $key ] = $value;
	return true;
}

function add_option( $key, $value, $deprecated = '', $autoload = 'yes' ) {
	if ( array_key_exists( $key, $GLOBALS['ts_bnpl_test_options'] ) ) {
		return false;
	}
	$GLOBALS['ts_bnpl_test_options'][ $key ] = $value;
	return true;
}

function apply_filters( $hook, $value ) {
	return isset( $GLOBALS['ts_bnpl_test_filters'][ $hook ] )
		? call_user_func( $GLOBALS['ts_bnpl_test_filters'][ $hook ], $value )
		: $value;
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_strip_all_tags( $value ) {
	return strip_tags( (string) $value );
}

function esc_attr( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function esc_html( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $value ) {
	return (string) $value;
}

function esc_html__( $value, $domain = null ) {
	return (string) $value;
}

function esc_html_e( $value, $domain = null ) {
	echo esc_html( $value );
}

function esc_attr_e( $value, $domain = null ) {
	echo esc_attr( $value );
}

function __( $value, $domain = null ) {
	return (string) $value;
}

function get_post_status( $post_id ) {
	return isset( $GLOBALS['ts_bnpl_test_post_statuses'][ $post_id ] )
		? $GLOBALS['ts_bnpl_test_post_statuses'][ $post_id ]
		: false;
}

function get_permalink( $post_id ) {
	return 'https://example.test/page/' . absint( $post_id ) . '/';
}

function is_page( $post_id ) {
	return absint( $post_id ) === absint( $GLOBALS['ts_bnpl_test_page_id'] );
}

function is_product() {
	return (bool) $GLOBALS['ts_bnpl_test_is_product'];
}

function wc_get_product( $product_id ) {
	return $GLOBALS['ts_bnpl_test_product'];
}

function get_queried_object_id() {
	return 99;
}

function get_woocommerce_currency_symbol() {
	return 'تومان';
}

class WC_Product {
	public function is_type( $type ) {
		return 'variable' === $type;
	}
}

class TS_BNPL_Data {
	public static function installment( $total ) {
		return (float) $total / TS_BNPL_MONTHS;
	}

	public static function get( $product ) {
		return 0;
	}
}
