/**
 * Dismissal for the FreeMyInternet protest overlay.
 *
 * Vanilla, no jQuery. The dismissal is remembered against a signature of the
 * current notice, so editing the protest text shows it again to everyone.
 */

( function() {
	const SELECTOR = '.freemyinternet-overlay';
	const PREFIX = 'freemyinternet-dismissed:';

	function storageKey( overlay ) {
		return PREFIX + ( overlay.getAttribute( 'data-freemyinternet-key' ) || '' );
	}

	function wasDismissed( overlay ) {
		try {
			return null !== window.localStorage.getItem( storageKey( overlay ) );
		} catch {
			// Storage disabled: treat the notice as not yet dismissed.
			return false;
		}
	}

	function remember( overlay ) {
		try {
			window.localStorage.setItem( storageKey( overlay ), '1' );
		} catch {
			// Private browsing or a full quota. Dismissing still works for this page view.
		}
	}

	function remove( overlay ) {
		if ( overlay && overlay.parentNode ) {
			overlay.parentNode.removeChild( overlay );
		}
	}

	function onClick( event ) {
		if ( ! ( event.target instanceof Element ) ) {
			return;
		}

		const button = event.target.closest( '[data-freemyinternet-dismiss]' );

		if ( ! button ) {
			return;
		}

		event.preventDefault();

		const overlay = button.closest( SELECTOR );

		remember( overlay );
		remove( overlay );
	}

	function init() {
		const overlay = document.querySelector( SELECTOR );

		if ( ! overlay ) {
			return;
		}

		if ( wasDismissed( overlay ) ) {
			remove( overlay );
			return;
		}

		document.addEventListener( 'click', onClick );
	}

	/*
	 * The script is enqueued in the footer, after the markup it operates on, so the
	 * overlay is already parsed and the dismissed case is removed before it paints.
	 * The readyState guard only matters if something moves the script into the head.
	 */
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
