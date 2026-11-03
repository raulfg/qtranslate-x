/**
 * Restores the getPostType selector that Yoast 27.6+ needs but does not have on the
 * classic post editor.
 *
 * Yoast 27.6 added the AI Content Planner banner, and with it a component that opens:
 *
 *     const { isTerm, isPost, isAiFeatureActive } = useSelect( ( select ) => ( {
 *         isTerm: select( 'yoast-seo/editor' ).getIsTerm(),
 *         isPost: 'post' === select( 'yoast-seo/editor' ).getPostType(),
 *         isAiFeatureActive: select( 'yoast-seo/editor' ).getPreference( 'isAiFeatureActive' ),
 *     } ), [] );
 *     const isBlockEditor = window.wpseoScriptData && '1' === window.wpseoScriptData.isBlockEditor;
 *
 * That is the metabox's own root component, and the useSelect runs BEFORE the
 * isBlockEditor check below it. When getPostType is missing from the store the call
 * throws "getPostType is not a function", React unmounts the whole metabox, and the
 * editor shows an error panel with no SEO fields at all.
 *
 * Yoast's own PHP guards the same feature properly -- class-metabox.php only renders
 * the content_planner fields when `$screen->is_block_editor()` -- so the JS side
 * missing the same guard is an upstream oversight rather than a deliberate drop of
 * classic support. Still present in 28.8.
 *
 * WHY THIS FILE EXISTS EVEN THOUGH THE SELECTOR IS NORMALLY THERE. It was removed once,
 * on the strength of a measurement that did not reproduce the failure: on the
 * development copy `getPostType` IS registered on a classic post screen, by
 * js/dist/externals-redux.js, and returns the post type correctly on 27.5, 27.6 and
 * 28.8 with this file absent. That measurement was right about the development copy and
 * wrong as a conclusion, because production crashed with exactly this error while
 * serving byte-identical bundles -- externals-redux.js there contains getPostType too.
 *
 * So the selector goes missing at RUNTIME for a reason that is environmental rather than
 * a property of the Yoast version: that bundle is not reaching the page. The production
 * install carries performance and asset-handling plugins the development copy does not,
 * and any of them dropping or reordering that script produces this. The underlying cause
 * is still open.
 *
 * Which is the argument for keeping this file regardless of which version is installed.
 * It does not depend on knowing why the selector is absent -- it only restores it when
 * it is. Do not remove it again on the evidence of a development copy alone; the only
 * measurement that counts is the admin screen of the install that broke.
 *
 * HOW THIS PATCHES, AND WHY NOT THE OBVIOUS WAY. The first version of this file
 * wrapped the registry with `wp.data.use()` and returned a patched COPY of the
 * selectors. It stopped the crash and broke something worse: `useSelect` memoises on
 * the identity of the object `select()` hands back, so returning a fresh copy on every
 * call made every subscriber think the store had changed. The metabox rendered and
 * then came up empty -- all the SEO fields lost their values.
 *
 * So this mutates the store's own selectors object instead, exactly once. That object
 * is a stable, per-store reference, which is the property the memoisation depends on,
 * and adding one key to it leaves every other selector and its identity alone.
 *
 * Yoast's own selector is `( state ) => get( state, 'editorContext.postType' )`, so the
 * shim reads the same place through getEditorContext() and falls back to the
 * `post_type` input WordPress always renders on post.php.
 *
 * Remove this file when Yoast ships the guard. Checked 27.6 through 28.8: still needed.
 */
( function () {
	'use strict';

	var STORE = 'yoast-seo/editor';
	var MAX_TRIES = 60;
	var tries = 0;

	function postTypeFromPage() {
		var input = document.getElementById( 'post_type' );
		if ( input && input.value ) {
			return input.value;
		}

		var data = window.wpseoScriptData;
		if ( data && data.metabox && data.metabox.post_type ) {
			return data.metabox.post_type;
		}

		return '';
	}

	/**
	 * Adds getPostType to the store's selectors if it is missing.
	 *
	 * @return {boolean} True once the store exists, whether or not it needed patching.
	 */
	function patch() {
		if ( ! window.wp || ! wp.data || typeof wp.data.select !== 'function' ) {
			return false;
		}

		var selectors;
		try {
			selectors = wp.data.select( STORE );
		} catch ( e ) {
			return false;
		}

		if ( ! selectors ) {
			return false;
		}

		if ( typeof selectors.getPostType === 'function' ) {
			return true;
		}

		try {
			selectors.getPostType = function () {
				var context = typeof selectors.getEditorContext === 'function'
					? selectors.getEditorContext()
					: null;

				if ( context && context.postType ) {
					return context.postType;
				}

				return postTypeFromPage();
			};
		} catch ( e ) {
			// A frozen selectors object: nothing more to try, and better than throwing.
			return true;
		}

		return true;
	}

	/*
	 * The store is registered by Yoast's own bundle, which may not have run yet when
	 * this file executes -- it is loaded early on purpose, to be in place before the
	 * metabox renders. So try now, and keep trying on a short timer until the store
	 * appears. The cap stops this spinning forever on a screen that never registers it.
	 */
	if ( patch() ) {
		return;
	}

	var timer = setInterval( function () {
		tries++;
		if ( patch() || tries >= MAX_TRIES ) {
			clearInterval( timer );
		}
	}, 50 );
} )();
