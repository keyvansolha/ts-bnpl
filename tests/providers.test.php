<?php
require __DIR__ . '/bootstrap.php';

$provider_file = dirname( __DIR__ ) . '/includes/class-ts-bnpl-providers.php';
if ( is_file( $provider_file ) ) {
	require $provider_file;
}

if ( ! class_exists( 'TS_BNPL_Providers' ) ) {
	$GLOBALS['ts_bnpl_test_failures'][] = 'TS_BNPL_Providers is missing';
	ts_test_finish();
}

function ts_test_gateway( $id, $enabled, $available, $installment = true ) {
	$gateway              = new WC_Payment_Gateway();
	$gateway->id          = $id;
	$gateway->enabled     = $enabled ? 'yes' : 'no';
	$gateway->available   = $available;
	$gateway->installment = $installment;
	$gateway->title       = 'Gateway ' . $id;
	return $gateway;
}

$digipay = ts_test_gateway( 'wbs_digipay', true, true );
$future  = ts_test_gateway( 'future_credit', true, true );
$normal  = ts_test_gateway( 'cash_gateway', true, true, false );

$registry = new class( array( $digipay, $future, $normal ) ) {
	private $gateways;
	public function __construct( $gateways ) {
		$this->gateways = array();
		foreach ( $gateways as $gateway ) {
			$this->gateways[ $gateway->id ] = $gateway;
		}
	}
	public function payment_gateways() {
		return $this->gateways;
	}
};
$GLOBALS['ts_bnpl_test_wc'] = new class( $registry ) {
	private $registry;
	public function __construct( $registry ) { $this->registry = $registry; }
	public function payment_gateways() { return $this->registry; }
};

$choices = TS_BNPL_Providers::choices();
ts_test_assert_true( isset( $choices['wbs_digipay'] ), 'registered DigiPay is selectable' );
ts_test_assert_true( isset( $choices['future_credit'] ), 'future registered installment gateway is selectable' );
ts_test_assert_false( isset( $choices['cash_gateway'] ), 'ordinary payment gateway is not selectable' );

$entries = array(
	array( 'provider_id' => 'wbs_digipay', 'display_enabled' => true, 'display_name' => 'دیجی‌پی' ),
	array( 'provider_id' => 'future_credit', 'display_enabled' => true, 'display_name' => 'آینده' ),
);
ts_test_assert_same( 2, count( TS_BNPL_Providers::public_entries( $entries ) ), 'enabled operational providers are public' );

$future->enabled = 'no';
ts_test_assert_same( 1, count( TS_BNPL_Providers::public_entries( $entries ) ), 'disabled providers are omitted' );

$digipay->available = false;
ts_test_assert_same( 0, count( TS_BNPL_Providers::public_entries( $entries ) ), 'unconfigured providers are omitted' );

$digipay->available              = true;
$GLOBALS['ts_bnpl_test_mode']    = true;
ts_test_assert_same( 0, count( TS_BNPL_Providers::public_entries( $entries ) ), 'test mode never advertises an active public provider' );

ts_test_finish();
