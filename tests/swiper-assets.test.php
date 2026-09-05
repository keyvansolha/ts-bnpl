<?php
/**
 * جای‌گزینی هندل «swiper» با بیلد bundle.
 *
 * مسیر Page قالب هم شیت و هم اسکریپت را روی بیلد لاغر می‌گذارد. آن بیلد نه
 * ماژول Navigation دارد و نه قاعده‌ای برای pagination، پس افزونه باید هر دو
 * هندل را جای‌گزین کند، نه اینکه چون «از قبل در صف است» رد شود.
 *
 * @package TS_BNPL
 */

require __DIR__ . '/bootstrap.php';

$theme_dir = dirname( dirname( dirname( __DIR__ ) ) ) . '/themes/amazing';

if ( ! is_dir( $theme_dir ) ) {
	echo "SKIP: theme not present\n";
	exit( 0 );
}

define( 'THEME_ASSETS', 'https://example.test/theme/assets/' );
define( 'THEME_LIB', 'https://example.test/theme/lib/' );
define( 'IS_MOBILE', false );

$GLOBALS['ts_bnpl_test_theme_dir'] = $theme_dir;

require dirname( __DIR__ ) . '/includes/class-ts-bnpl-landing.php';

// مسیر Page قالب: هر دو هندل روی بیلد لاغر.
$slim_css = 'https://example.test/theme/assets/plugins/swiper/swiper.min.css';
$slim_js  = 'https://example.test/theme/assets/plugins/swiper/swiper.min.js';

wp_register_style( 'swiper', $slim_css );
wp_enqueue_style( 'swiper' );
wp_register_script( 'swiper', $slim_js, array( 'jquery' ), '1', true );
wp_enqueue_script( 'swiper' );

ts_test_assert_same( $slim_css, wp_styles()->query( 'swiper', 'registered' )->src, 'the theme page route starts on the slim stylesheet' );

TS_BNPL_Landing::enqueue_theme_card_assets( true );

$style  = wp_styles()->query( 'swiper', 'registered' );
$script = wp_scripts()->query( 'swiper', 'registered' );

ts_test_assert_true( false !== strpos( $style->src, 'swiper-bundle.min.css' ), 'the swiper stylesheet handle is moved onto the bundle build' );
ts_test_assert_true( false !== strpos( $script->src, 'swiper-bundle.min.js' ), 'the swiper script handle is moved onto the bundle build' );
ts_test_assert_true( wp_style_is( 'swiper', 'enqueued' ), 'the bundle stylesheet is actually enqueued' );
ts_test_assert_true( wp_script_is( 'swiper', 'enqueued' ), 'the bundle script is actually enqueued' );

/*
 * چرا این مهم است: بیلد لاغر هیچ قاعده‌ای برای pagination ندارد، پس
 * `.swiper-pagination-bullet` بدون `display: inline-block` می‌ماند و نقطه‌ها
 * ناپدید می‌شوند.
 */
$slim_file   = $theme_dir . '/assets/plugins/swiper/swiper.min.css';
$bundle_file = $theme_dir . '/lib/Archive/assets/plugins/swiper/swiper-bundle.min.css';

if ( file_exists( $slim_file ) && file_exists( $bundle_file ) ) {
	ts_test_assert_false( false !== strpos( file_get_contents( $slim_file ), 'swiper-pagination-bullet' ), 'the slim build is the one without pagination rules' );
	ts_test_assert_true( false !== strpos( file_get_contents( $bundle_file ), 'swiper-pagination-bullet' ), 'the bundle build is the one that carries them' );
}

// اجرای دوباره نباید فایل را دو بار ثبت یا هندل را دوباره جابه‌جا کند.
$before = $style->src;
TS_BNPL_Landing::enqueue_theme_card_assets( true );
ts_test_assert_same( $before, wp_styles()->query( 'swiper', 'registered' )->src, 'a second pass leaves the bundle handle alone' );

ts_test_finish();
