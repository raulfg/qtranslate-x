/**
 * Language link context.
 *
 * qts_get_canonical_url() emits canonical hrefs with no query string, so a search
 * engine discovers one URL per page and language instead of one per value of _gl,
 * utm_* or gclid. A person switching language still expects their context to
 * survive, so the current query string is appended at interaction time.
 *
 * This MUST happen on interaction and never on load: search engines render
 * JavaScript, so rewriting the hrefs on load would hand the parameterised URLs
 * straight back to the crawler and defeat the purpose.
 *
 * The href is rewritten without preventDefault, so the browser performs the
 * navigation itself and middle-click, ctrl-click and "copy link" keep working.
 *
 * @since 4.0.0
 */
(function () {
	'use strict';

	var SELECTOR = 'a[data-qts-lang]';

	function applyContext(link) {
		if (!link || link.getAttribute('data-qts-context') === '1') {
			return;
		}

		link.setAttribute('data-qts-context', '1');

		var search = window.location.search;
		if (!search || search === '?') {
			return;
		}

		var href = link.getAttribute('href');
		if (!href || href.indexOf('?') !== -1 || href.indexOf('#') !== -1) {
			return;
		}

		link.setAttribute('href', href + search);
	}

	function fromEvent(event) {
		var target = event.target;
		if (!target || typeof target.closest !== 'function') {
			return;
		}

		applyContext(target.closest(SELECTOR));
	}

	document.addEventListener('pointerdown', fromEvent, true);

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
			fromEvent(event);
		}
	}, true);
})();
