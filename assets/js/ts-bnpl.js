/**
 * TS BNPL Digipay — رفتار فرانت‌اند.
 *
 * ۱. مودال راهنما: باز شدن با کلیک، بسته شدن با بک‌دراپ/ضربدر/Escape،
 *    تله‌ی فوکوس و بازگرداندن فوکوس به دکمه‌ی بازکننده.
 * ۲. همگام‌سازی ردیف اقساط با انتخاب variation.
 */

( function ( $ ) {
	'use strict';

	var MODAL_ID = 'ts-bnpl-modal';
	var CONTAINER_ID = 'ts-bnpl';

	var FOCUSABLE = [
		'a[href]',
		'area[href]',
		'button:not([disabled])',
		'input:not([disabled]):not([type="hidden"])',
		'select:not([disabled])',
		'textarea:not([disabled])',
		'[tabindex]:not([tabindex="-1"])'
	].join( ', ' );

	var modal = null;
	var dialog = null;
	var lastFocused = null;

	/**
	 * عناصر قابل فوکوس داخل مودال.
	 *
	 * @return {Array} فهرست عناصر.
	 */
	function getFocusable() {
		if ( ! dialog ) {
			return [];
		}

		return Array.prototype.filter.call(
			dialog.querySelectorAll( FOCUSABLE ),
			function ( element ) {
				return element.offsetWidth > 0 || element.offsetHeight > 0 || element === document.activeElement;
			}
		);
	}

	/**
	 * آیا مودال باز است؟
	 *
	 * @return {boolean} وضعیت.
	 */
	function isOpen() {
		return !! modal && ! modal.hasAttribute( 'hidden' );
	}

	/**
	 * باز کردن مودال.
	 *
	 * @param {Element} opener دکمه‌ای که مودال را باز کرده.
	 */
	function openModal( opener ) {
		if ( ! modal || ! dialog || isOpen() ) {
			return;
		}

		lastFocused = opener || document.activeElement;

		modal.removeAttribute( 'hidden' );
		document.body.classList.add( 'ts-bnpl-modal-open' );
		document.addEventListener( 'keydown', onKeydown, true );

		var focusable = getFocusable();
		( focusable.length ? focusable[ 0 ] : dialog ).focus();
	}

	/**
	 * بستن مودال و بازگرداندن فوکوس.
	 */
	function closeModal() {
		if ( ! isOpen() ) {
			return;
		}

		modal.setAttribute( 'hidden', '' );
		document.body.classList.remove( 'ts-bnpl-modal-open' );
		document.removeEventListener( 'keydown', onKeydown, true );

		// دکمه‌ی بازکننده ممکن است در پی تغییر variation از DOM حذف شده باشد.
		if ( lastFocused && typeof lastFocused.focus === 'function' && document.contains( lastFocused ) ) {
			lastFocused.focus();
		}

		lastFocused = null;
	}

	/**
	 * مدیریت کلیدها: Escape برای بستن و Tab برای تله‌ی فوکوس.
	 *
	 * @param {KeyboardEvent} event رویداد.
	 */
	function onKeydown( event ) {
		if ( ! isOpen() ) {
			return;
		}

		if ( 'Escape' === event.key || 'Esc' === event.key ) {
			event.preventDefault();
			closeModal();

			return;
		}

		if ( 'Tab' !== event.key ) {
			return;
		}

		var focusable = getFocusable();

		if ( ! focusable.length ) {
			event.preventDefault();
			dialog.focus();

			return;
		}

		var first = focusable[ 0 ];
		var last = focusable[ focusable.length - 1 ];
		var active = document.activeElement;

		if ( event.shiftKey ) {
			if ( active === first || active === dialog || ! dialog.contains( active ) ) {
				event.preventDefault();
				last.focus();
			}

			return;
		}

		if ( active === last || ! dialog.contains( active ) ) {
			event.preventDefault();
			first.focus();
		}
	}

	// دکمه‌ی «؟» با هر تغییر variation دوباره ساخته می‌شود، پس رویداد واگذارشده است.
	$( document ).on( 'click.tsBnpl', '.ts-bnpl__help', function ( event ) {
		event.preventDefault();
		openModal( this );
	} );

	$( document ).on( 'click.tsBnpl', '[data-ts-bnpl-close]', function ( event ) {
		event.preventDefault();
		closeModal();
	} );

	$( function () {
		modal = document.getElementById( MODAL_ID );
		dialog = modal ? modal.querySelector( '.ts-bnpl-modal__dialog' ) : null;

		var container = document.getElementById( CONTAINER_ID );
		var $form = $( 'form.variations_form' );

		if ( ! container || ! $form.length ) {
			return;
		}

		/**
		 * پنهان کردن ردیف اقساط.
		 */
		function hideInstallment() {
			container.innerHTML = '';
			container.setAttribute( 'hidden', '' );
		}

		$form.on( 'found_variation.tsBnpl', function ( event, variation ) {
			var html = variation && variation.bnpl_html ? variation.bnpl_html : '';

			if ( ! html ) {
				hideInstallment();

				return;
			}

			container.innerHTML = html;
			container.removeAttribute( 'hidden' );
		} );

		$form.on( 'reset_data.tsBnpl', hideInstallment );
	} );

	/*
	 * تسویه‌حساب: بازمحاسبه‌ی مبالغ هنگام تعویض درگاه.
	 *
	 * ووکامرس با تعویض روش پرداخت، update_checkout را صدا نمی‌زند؛ فقط باکس
	 * درگاه را باز و بسته می‌کند. برای همین قیمت اقساطی تا وقتی کاربر آدرس را
	 * دست نمی‌زد اعمال نمی‌شد. رویداد payment_method_selected را که خود
	 * ووکامرس روی body می‌زند می‌گیریم و بازمحاسبه را اجرا می‌کنیم.
	 *
	 * چون update_checkout دوباره باعث انتخاب درگاه و شلیک همین رویداد
	 * می‌شود، مقدار قبلی نگه داشته می‌شود تا حلقه ایجاد نشود.
	 */
	$( function () {
		var $checkout = $( 'form.checkout' );

		if ( ! $checkout.length ) {
			return;
		}

		function chosenMethod() {
			return $( 'input[name="payment_method"]:checked' ).val() || '';
		}

		var lastMethod = chosenMethod();

		$( document.body ).on( 'payment_method_selected.tsBnpl', function () {
			var current = chosenMethod();

			if ( current === lastMethod ) {
				return;
			}

			lastMethod = current;
			$( document.body ).trigger( 'update_checkout' );
		} );

		// پس از هر بازمحاسبه، مرجع را با وضعیت واقعی DOM هم‌تراز نگه می‌داریم.
		$( document.body ).on( 'updated_checkout.tsBnpl', function () {
			lastMethod = chosenMethod();
		} );
	} );
}( jQuery ) );
