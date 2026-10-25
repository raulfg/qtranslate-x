<?php
/**
 * Bundled loader for the qTranslate Slug companion plugin.
 *
 * The companion plugin shipped as a separate WordPress plugin that depended on this one
 * being loaded first. Bundling it here removes that ordering fragility and leaves a single
 * plugin owning every multilingual URL behaviour.
 *
 * This file replaces the companion's own bootstrap (`qtranslate-slug.php`). It keeps the
 * same includes, the same constants, the same global and the same template functions, so
 * the code and the stored data (`_qts_slug_*` meta, `qts_options`) are untouched. What it
 * deliberately drops:
 *
 * - `register_activation_hook()` / `register_deactivation_hook()`: these only fire for a
 *   real plugin file. `install()` is instead run from a version check, see below.
 * - `register_uninstall_hook()` and `qts_uninstall()`: uninstalling belongs to the parent
 *   plugin's lifecycle, and dropping the slug meta on an uninstall of qTranslate-X would
 *   be a destructive surprise.
 * - `qts_add_settings_link()`: it pointed at the companion's own row on the plugins page,
 *   which no longer exists. The settings page registers itself through `add_options_page()`
 *   in `includes/qtranslate-slug-settings.php`, so it stays reachable under
 *   Settings -> Slug options.
 *
 * MIGRATION. This file is only reached when the standalone companion plugin is NOT active;
 * see the `class_exists()` guard at the call site in qtranslate.php. That makes the switch
 * order-independent: deploying this code while the standalone plugin is still active changes
 * nothing, and deactivating the standalone plugin hands over on the next request. Reactivating
 * it reverses the change. After the switch, flush the rewrite rules once from
 * Settings -> Permalinks, because the slug code hooks `generate_rewrite_rules`.
 *
 * @since 4.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-qtranslate-slug-widget.php';
require_once __DIR__ . '/includes/class-qtranslate-slug.php';
require_once __DIR__ . '/includes/termmeta-core.php';

if ( ! defined( 'QTS_VERSION' ) ) {
	define( 'QTS_VERSION', '1.1.18' );
}
if ( ! defined( 'QTS_PREFIX' ) ) {
	define( 'QTS_PREFIX', '_qts_' );
}
if ( ! defined( 'QTS_PAGE_BASENAME' ) ) {
	define( 'QTS_PAGE_BASENAME', 'qtranslate-slug-settings' );
}
if ( ! defined( 'QTS_OPTIONS_NAME' ) ) {
	define( 'QTS_OPTIONS_NAME', 'qts_options' );
}

global $qtranslate_slug;

$qtranslate_slug = new QtranslateSlug();

add_action( 'plugins_loaded', array( $qtranslate_slug, 'init' ) );

/**
 * Stands in for the activation hook, which never fires for bundled code.
 *
 * Runs `install()` only when the stored version differs from the bundled one. On a switch
 * from the standalone plugin the versions match, so nothing runs and the existing data is
 * inherited as it is: the quietest possible transition.
 *
 * @since 4.1.0
 */
function qtx_slug_maybe_install() {
	global $qtranslate_slug;

	if ( ! $qtranslate_slug instanceof QtranslateSlug ) {
		return;
	}

	if ( get_option( 'qts_version' ) === QTS_VERSION ) {
		return;
	}

	$qtranslate_slug->install();
}
add_action( 'plugins_loaded', 'qtx_slug_maybe_install', 20 );

/**
 * Language selector for templates.
 *
 * @since 1.0
 */
function qts_language_menu( $type = 'text', $args = array() ) {
	global $qtranslate_slug;

	$qtranslate_slug->language_menu( $type, $args );
}

/**
 * Translated slug of a post.
 *
 * @since 1.1.13
 */
function qts_get_slug( $id, $lang ) {
	global $qtranslate_slug;

	return $qtranslate_slug->get_slug( $id, $lang );
}

/**
 * Kept for backward compatibility with the original plugin's naming.
 *
 * @since 1.1.5
 */
function qTranslateSlug_getSelfUrl( $lang = false ) {
	return qts_get_url( $lang );
}

/**
 * Current URL for a language, carrying the request's query string.
 *
 * @since 1.0
 */
function qts_get_url( $lang = false ) {
	global $qtranslate_slug;

	return $qtranslate_slug->get_current_url( $lang );
}

/**
 * Canonical URL for a language: no query string.
 *
 * Preferable to qts_get_url() for any link a search engine will crawl, because
 * qts_get_url() carries the current request's query string and turns every value of _gl,
 * utm_* or gclid into a separate indexable URL.
 *
 * Mark the anchors with data-qts-lang="<language>" so that
 * slug/assets/js/qts-lang-link-context.js restores the visitor's context on click.
 *
 * @since 4.1.0
 */
function qts_get_canonical_url( $lang = false ) {
	global $qtranslate_slug;

	return $qtranslate_slug->qts_canonical_url_for_language( $lang );
}
