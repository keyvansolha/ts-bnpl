<?php
require __DIR__ . '/bootstrap.php';

$media_file = dirname( __DIR__ ) . '/includes/class-ts-bnpl-responsive-media.php';
if ( is_file( $media_file ) ) {
	require $media_file;
}

if ( ! class_exists( 'TS_BNPL_Responsive_Media' ) ) {
	$GLOBALS['ts_bnpl_test_failures'][] = 'TS_BNPL_Responsive_Media is missing';
	ts_test_finish();
}

$GLOBALS['ts_bnpl_test_attachments'] = array(
	1 => array( 'mime' => 'image/avif', 'url' => 'https://example.test/desktop.avif', 'width' => 1326, 'height' => 400 ),
	2 => array( 'mime' => 'image/webp', 'url' => 'https://example.test/desktop.webp', 'width' => 1326, 'height' => 400 ),
	3 => array( 'mime' => 'image/avif', 'url' => 'https://example.test/mobile.avif', 'width' => 1300, 'height' => 975 ),
	4 => array( 'mime' => 'image/webp', 'url' => 'https://example.test/mobile.webp', 'width' => 1300, 'height' => 975 ),
	5 => array( 'mime' => 'image/jpeg', 'url' => 'https://example.test/wrong.jpg', 'width' => 1326, 'height' => 400 ),
);

$media = TS_BNPL_Responsive_Media::normalize(
	array(
		'desktop_avif_id' => 1,
		'desktop_webp_id' => 2,
		'mobile_avif_id'  => 3,
		'mobile_webp_id'  => 4,
		'alt'             => 'پیشنهاد خرید اعتباری',
	)
);
ts_test_assert_same( 1, $media['desktop_avif_id'], 'AVIF field keeps an AVIF attachment' );
ts_test_assert_same( 2, $media['desktop_webp_id'], 'WebP field keeps a WebP attachment' );

$invalid = TS_BNPL_Responsive_Media::normalize( array( 'desktop_avif_id' => 5, 'desktop_webp_id' => 5 ) );
ts_test_assert_same( 0, $invalid['desktop_avif_id'], 'AVIF field rejects JPEG' );
ts_test_assert_same( 0, $invalid['desktop_webp_id'], 'WebP field rejects JPEG' );

$html = TS_BNPL_Responsive_Media::render(
	$media,
	array(
		'class'         => 'hero-image',
		'loading'       => 'eager',
		'fetchpriority' => 'high',
	)
);
ts_test_assert_true( strpos( $html, 'mobile.avif' ) < strpos( $html, 'mobile.webp' ), 'mobile AVIF precedes mobile WebP' );
ts_test_assert_true( strpos( $html, 'mobile.webp' ) < strpos( $html, 'desktop.avif' ), 'mobile sources precede desktop sources' );
ts_test_assert_true( strpos( $html, 'desktop.avif' ) < strpos( $html, 'desktop.webp' ), 'desktop AVIF precedes desktop WebP' );
ts_test_assert_contains( 'width="1326"', $html, 'fallback image has intrinsic width' );
ts_test_assert_contains( 'height="400"', $html, 'fallback image has intrinsic height' );
ts_test_assert_contains( 'loading="eager"', $html, 'LCP image is eager' );
ts_test_assert_contains( 'fetchpriority="high"', $html, 'LCP image has high priority' );

$desktop_only = TS_BNPL_Responsive_Media::render(
	array( 'desktop_webp_id' => 2, 'alt' => '', 'desktop_avif_id' => 0, 'mobile_avif_id' => 0, 'mobile_webp_id' => 0 )
);
ts_test_assert_contains( 'desktop.webp', $desktop_only, 'desktop media is a controlled mobile fallback' );

$avif_only = TS_BNPL_Responsive_Media::render( array( 'desktop_avif_id' => 1, 'desktop_webp_id' => 0 ) );
ts_test_assert_same( '', $avif_only, 'AVIF-only media never emits a broken img fallback' );

ts_test_finish();
