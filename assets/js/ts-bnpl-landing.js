/**
 * کاروسل کالاهای واجد شرایط در صفحه‌ی فرود خرید اعتباری.
 *
 * تنظیمات عمداً با کاروسل‌های موجود سایت یکی است تا اندازه و فاصله‌ی کارت‌ها
 * فرقی نکند. روی موبایل اصلاً سوایپر ساخته نمی‌شود؛ آنجا مثل بقیه‌ی سایت یک
 * اسکرول افقی ساده است.
 */
( function () {
	'use strict';

	var SLIDER_ID = 'ts-bnpl-products-carousel';

	function init() {
		var element = document.getElementById( SLIDER_ID );

		if ( ! element || typeof window.Swiper !== 'function' ) {
			return;
		}

		new window.Swiper( '#' + SLIDER_ID, {
			speed: 250,
			slidesPerView: 4,
			spaceBetween: 20,
			navigation: {
				nextEl: '#' + SLIDER_ID + '-next',
				prevEl: '#' + SLIDER_ID + '-prev'
			},
			breakpoints: {
				1300: {
					slidesPerView: 5,
					spaceBetween: 20
				}
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
