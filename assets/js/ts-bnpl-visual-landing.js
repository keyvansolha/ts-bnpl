( function ( window, document ) {
	'use strict';

	function initialize() {
		if ( ! window.Swiper ) {
			return;
		}

		var reducedMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

		document.querySelectorAll( '[data-ts-bnpl-visual-banner]' ).forEach( function ( banner ) {
			var slideCount = Number.parseInt( banner.dataset.slideCount || '0', 10 );

			if ( slideCount < 2 || 'true' === banner.dataset.initialized ) {
				return;
			}

			var options = {
				spaceBetween: 0,
				speed: 1000,
				slidesPerView: 1,
				parallax: true,
				resistanceRatio: 0,
				rewind: true,
				keyboard: {
					enabled: true,
					onlyInViewport: true
				},
				autoplay: reducedMotion ? false : {
					delay: 3000,
					disableOnInteraction: false,
					pauseOnMouseEnter: false
				},
				pagination: {
					el: banner.querySelector( '.ts-bnpl-visual-banner__pagination' ),
					type: 'bullets',
					clickable: true
				},
				navigation: {
					nextEl: banner.querySelector( '.ts-bnpl-visual-banner__next' ),
					prevEl: banner.querySelector( '.ts-bnpl-visual-banner__prev' )
				}
			};

			var swiper = new window.Swiper( banner, options );
			banner.dataset.initialized = 'true';

			if ( reducedMotion || ! swiper.autoplay ) {
				return;
			}

			var pointerInside = false;
			var focusInside = false;

			function pause() {
				swiper.autoplay.stop();
			}

			function resume() {
				if ( ! pointerInside && ! focusInside && ! document.hidden ) {
					swiper.autoplay.start();
				}
			}

			banner.addEventListener( 'mouseenter', function () {
				pointerInside = true;
				pause();
			} );
			banner.addEventListener( 'mouseleave', function () {
				pointerInside = false;
				resume();
			} );
			banner.addEventListener( 'focusin', function () {
				focusInside = true;
				pause();
			} );
			banner.addEventListener( 'focusout', function ( event ) {
				focusInside = banner.contains( event.relatedTarget );
				resume();
			} );
			document.addEventListener( 'visibilitychange', function () {
				if ( document.hidden ) {
					pause();
				} else {
					resume();
				}
			} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initialize );
	} else {
		initialize();
	}
} )( window, document );
