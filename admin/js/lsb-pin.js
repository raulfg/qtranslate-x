/**
 * Pins the first Language Switcher Bar to the top of the edit screen.
 *
 * On a long post the bar scrolls out of reach: an editor working three
 * thousand pixels down has to scroll up to switch language and then hunt for
 * where they were. Pinning the topmost bar keeps the switch one click away
 * without moving the page.
 *
 * The bar cannot use position:sticky. It lives in #post-body-content, whose
 * box ends just below the editor, and a sticky element is released at the
 * bottom of its containing block — which is exactly where the problem starts.
 * So the bar is pinned with position:fixed instead.
 *
 * The pin is driven by an IntersectionObserver watching a sentinel placed
 * where the bar sits, never by a scroll handler. Writing styles on every
 * scroll event forces layout on a screen that may hold dozens of metaboxes
 * and TinyMCE instances; that thrash collapses the page height, the browser
 * clamps the scroll offset, and the editor is thrown back to the top.
 */
( function () {
	'use strict';

	var PINNED_CLASS = 'qtranxs-lsb-pinned';

	// Breathing room below whatever sits above the bar: pressed flush against
	// the admin bar the switcher reads as part of it.
	var GAP = 10;

	function init( bar ) {
		if ( ! bar || ! window.IntersectionObserver ) {
			return;
		}

		// Reserves the bar's space so the page does not jump when it is pinned.
		var spacer = document.createElement( 'div' );
		spacer.className = 'qtranxs-lsb-spacer';

		// Sits where the bar is: once it leaves the viewport, the bar is pinned.
		var sentinel = document.createElement( 'div' );
		sentinel.className = 'qtranxs-lsb-sentinel';

		bar.parentNode.insertBefore( sentinel, bar );
		bar.parentNode.insertBefore( spacer, bar );

		/**
		 * How far below the top of the viewport the pinned bar belongs.
		 *
		 * The admin bar is only fixed on the wide layout. Below 783px WordPress
		 * makes it absolute, so it scrolls away and reserving room for it would
		 * leave a band of content showing above the bar. Reading its computed
		 * position covers both layouts without guessing at breakpoints.
		 */
		function topOffset() {
			var admin = document.getElementById( 'wpadminbar' );
			if ( admin && window.getComputedStyle( admin ).position === 'fixed' ) {
				return admin.offsetHeight + GAP;
			}
			return GAP;
		}

		/**
		 * Keep the pinned bar on the content column.
		 *
		 * The spacer is measured rather than a named container: it sits in the
		 * flow exactly where the bar belongs, so it reports the right edges
		 * even while the bar itself is out of flow. Measuring the whole content
		 * area instead would stretch the bar over the sidebar widgets.
		 */
		function place() {
			if ( ! bar.classList.contains( PINNED_CLASS ) ) {
				return;
			}
			var box = spacer.getBoundingClientRect();
			bar.style.left = Math.round( box.left ) + 'px';
			bar.style.width = Math.round( box.width ) + 'px';
			bar.style.top = topOffset() + 'px';
		}

		function pin( on ) {
			if ( on === bar.classList.contains( PINNED_CLASS ) ) {
				return;
			}
			if ( on ) {
				spacer.style.height = bar.offsetHeight + 'px';
				bar.classList.add( PINNED_CLASS );
				place();
			} else {
				bar.classList.remove( PINNED_CLASS );
				bar.style.left = '';
				bar.style.width = '';
				spacer.style.height = '';
			}
		}

		// Treat the sentinel as gone once it reaches where the bar would pin,
		// so the swap happens exactly as the bar arrives at its resting place.
		new IntersectionObserver( function ( entries ) {
			pin( ! entries[0].isIntersecting );
		}, { rootMargin: '-' + topOffset() + 'px 0px 0px 0px', threshold: 0 } ).observe( sentinel );

		// The content column moves when the admin menu is folded or the window
		// is resized; both are rare, so recomputing on the event is enough.
		window.addEventListener( 'resize', place );
		document.addEventListener( 'wp-collapse-menu', place );
		var collapse = document.getElementById( 'collapse-menu' );
		if ( collapse ) {
			collapse.addEventListener( 'click', function () {
				window.setTimeout( place, 300 );
			} );
		}
	}

	/**
	 * The switcher bars are built by qTranslate's own admin script, which may
	 * run after this one, so the bar is not necessarily in the document yet.
	 * Watch for it rather than depending on script order.
	 */
	function waitForBar() {
		var selector = 'ul.qtranxs-lang-switch-wrap';
		var bar = document.querySelector( selector );
		if ( bar ) {
			init( bar );
			return;
		}
		if ( ! window.MutationObserver ) {
			return;
		}
		var observer = new MutationObserver( function () {
			var found = document.querySelector( selector );
			if ( found ) {
				observer.disconnect();
				init( found );
			}
		} );
		observer.observe( document.body, { childList: true, subtree: true } );

		// Stop watching if the bars never appear, so nothing is left running.
		window.setTimeout( function () {
			observer.disconnect();
		}, 10000 );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', waitForBar );
	} else {
		waitForBar();
	}
}() );
