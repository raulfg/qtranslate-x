<?php
/**
 * Bundled bootstrap for the Yoast SEO qTranslate companion.
 *
 * Converted from the standalone plugin `wp-seo-qtranslate-x` 1.1.1 ("Integration:
 * Yoast SEO & qTranslate-X") by the qTranslate Team
 * (https://github.com/qTranslate-Team/wp-seo-qtranslate-x/), GPL2. Every file in this
 * directory was copied here byte for byte, and all of the adaptation lives in this
 * loader, in the same spirit as `slug/loader.php` and `acf/loader.php`.
 *
 * The standalone plugin is kept on disk, deactivated, exactly as `qtranslate-slug` is
 * kept beside the bundled `slug/` module. It is not deleted: leaving it in place is
 * what makes this reversible without touching git, so an operator who hits trouble can
 * fall back by deactivating this plugin and activating that one. Deactivating it is
 * also all that was ever needed to avoid a clash, since WordPress never loads an
 * inactive plugin's files.
 *
 * BUT UNLIKE `acf/src/`, THIS DIRECTORY IS NOT A PRISTINE COPY OF ITS UPSTREAM.
 * Two of its files never existed upstream, and two more carry local changes. Do not
 * waste time looking for them in the qTranslate-Team repository. The provenance, file
 * by file:
 *
 * - `qwpseo-front.php`, `qwpseo-admin.php`, `readme.txt` and `LICENSE` came from
 *   upstream 1.1.1, imported in `d43987b7`.
 * - `i18n-config.json` also came from upstream, but it is NOT in this directory. It
 *   lives at `i18n-config/plugins/wordpress-seo/i18n-config.json`, and it has to.
 *   That file is what declares `yoast_wpseo_title`, `yoast_wpseo_metadesc`,
 *   `yoast_wpseo_focuskw`, `wpseosnippet_title`, `wpseo_title` and `wpseo_desc` as
 *   translatable, which is what puts the per-language inputs on Yoast's metabox.
 *   `qtranxf_search_config_files()` finds a config either at an active plugin's own
 *   root or at this plugin's `i18n-config/plugins/<that plugin's folder>/`, and it
 *   skips any folder whose name starts with `qtranslate-x` before it looks. So a copy
 *   under `seo/` is invisible to it: while the companion was a separate plugin its
 *   config was found at that plugin's root, and moving it to `seo/` would have left
 *   nothing declaring those fields. It would not have broken anything on the day of
 *   the move, because the merged result is cached in the `qtranslate_admin_config`
 *   option -- it would have broken silently the next time anything regenerated that
 *   option, with the metabox quietly dropping back to single-language input and no
 *   error anywhere. Keep that file where it is.
 * - `qwpseo-front.php` has since gained two lines (`f0d71cf4`): the `require_once` of
 *   `qwpseo-schema.php` below.
 * - `qwpseo-admin.php` has since gained thirty lines (`a76190b8`):
 *   `qwpseo_enqueue_admin_scripts()`, which enqueues `js/admin-yoast-snippet-sync.js`.
 *   Both files are otherwise untouched, so a diff against upstream still works; it
 *   just shows those additions. Nothing upstream wrote has been removed or rewritten.
 * - `qwpseo-schema.php` IS OURS (`f0d71cf4`). No upstream equivalent. It localizes the
 *   Schema/JSON-LD graph and the OpenGraph and Twitter card tags, seven filters in all.
 * - `qwpseo-media-admin.php` IS OURS (`a76190b8`). No upstream equivalent. It carries
 *   the adaptation to Yoast's React metabox, introduced in Yoast 14.
 * - `js/admin-yoast-snippet-sync.js` and `js/admin-media-parent-title.js` ARE OURS
 *   (`a76190b8`). Upstream 1.1.1 shipped no `js/` directory at all.
 *
 * WHICH IS THE REAL REASON TO BUNDLE IT. Upstream stopped at 1.1.1 and its
 * `readme.txt` still declares `Tested up to: 4.5` - WordPress 4.5, April 2016. Nearly
 * a decade without a release, while the Yoast SEO it glues to has reached 24.3. That
 * plugin is not coming back, and two of the five PHP files here prove we have been
 * maintaining its glue for a while already. Bundling it does not take over the
 * maintenance; it admits it. From here the fixes live in the same repository, and in
 * the same version number, as the code they patch.
 *
 * What this keeps from the original `wordpress-seo-qtranslate-x.php`:
 *
 * - `QWPSEO_VERSION`, used for asset cache-busting in `qwpseo-admin.php` and
 *   `qwpseo-media-admin.php`.
 * - `qwpseo_load_media_admin()` on `plugins_loaded` at priority 3, which pulls in the
 *   media-library admin patches. The early priority is the original's and is kept as
 *   it is; `qwpseo-media-admin.php` self-guards with `QWPSEO_MEDIA_ADMIN_LOADED`.
 * - `qwpseo_init_language()` on `qtranslate_init_language`, which is the actual entry
 *   point: it branches front end vs admin and loads `qwpseo-front.php` or
 *   `qwpseo-admin.php`.
 * - `qwpseo_no_page_analysis()`, the `wpseo_use_page_analysis` filter callback.
 *
 * AND IT KEEPS THREE HOOKS THAT YOAST NO LONGER FIRES. Counting `apply_filters()` and
 * `do_action()` definitions in the installed Yoast SEO tree, measured on 24.3 and
 * re-checked on 28.8 - every count below is identical in both, so a Yoast upgrade is
 * not what killed them:
 *
 * - `wpseo_meta`: zero. Registered at `qwpseo-admin.php:59`, and listed again under
 *   `front-config` in `i18n-config.json`.
 * - `wpseo_xmlsitemaps_config`: zero. Registered at `qwpseo-admin.php:165`, which is
 *   what makes `qwpseo_xmlsitemaps_config()` above it unreachable.
 * - `wpseo_metakeywords`: zero. Every reference to it is already inside a commented-out
 *   block, so nothing registers it.
 *
 * For contrast, the ones that are alive: `wpseo_use_page_analysis` has three
 * definitions, and `wpseo_title`, `wpseo_metadesc` and `wpseo_replacements` have one
 * each.
 *
 * THIS IS PRE-EXISTING DEAD CODE, NOT A REGRESSION OF THE BUNDLING, and it is left in
 * place deliberately. It is recorded here because the next Yoast upgrade is going to
 * put somebody in front of those three filters, and they will reasonably conclude the
 * upgrade broke them. It did not. They have been dead for years, which is the same
 * story the paragraph above tells: upstream was written against a Yoast that no longer
 * exists.
 *
 * What it drops:
 *
 * - Only the plugin header. The original bootstrap was three functions and two
 *   `add_action()` calls, with no activation, deactivation or uninstall hooks, so
 *   unlike the slug companion there is no plugin-lifecycle code to strip.
 *
 * Asset URLs need no adaptation, unlike the ACF module's. Both enqueues build their
 * URL with `plugins_url( 'js/...', __FILE__ )` where `__FILE__` is the enqueueing file
 * itself, and `js/` travels next to those files, so the URLs resolve to
 * `plugins/qtranslate-x/seo/js/...` on their own. Nothing here has to define a base
 * path constant.
 *
 * THE GUARD IS A FUNCTION, NOT A CLASS. The slug and ACF modules guard on
 * `class_exists()`, because their companions declare a class. This one declares no
 * class at all: it is functions in the global namespace from top to bottom. So both
 * the call site in qtranslate.php and the check below key off
 * `function_exists( 'qwpseo_init_language' )`. Loading this file while the standalone
 * plugin is also loaded would be a fatal function redeclaration, for the same reason
 * it would be a fatal class redeclaration there: `require_once` does not deduplicate
 * the same code reached through two different absolute paths.
 *
 * AND THE GUARD ONLY WORKS IN ONE DIRECTION. `acf-qtranslate` carries an early return
 * of its own, so that pair is safe whichever side loads first. `wp-seo-qtranslate-x`
 * carries nothing: it defines its functions unconditionally. WordPress loads plugins
 * in `active_plugins` order and appends newly activated ones at the end, so the
 * standalone plugin may load before or after this one. If it loads first, this bundle
 * stands down and nothing changes. If it loads second, it redeclares the functions and
 * the request dies. There is no way to fix that from this side, because by then our
 * functions already exist and the standalone has no check to fail.
 *
 * The operational conclusion: the standalone `wp-seo-qtranslate-x` plugin must stay
 * DEACTIVATED once this module ships. The guard is a safety net for the one ordering
 * where it helps, not a license to run both.
 *
 * One thing this loader does not own. The Yoast fields are registered as multilingual
 * by `i18n-config.json`, not by any code here, and that file lives at
 * `i18n-config/plugins/wordpress-seo/` rather than in this directory -- see the
 * provenance list above for why it has to. qTranslate reads it through the
 * `qtranslate_config_files` option and caches the merged result in
 * `qtranslate_admin_config` and `qtranslate_front_config`. See CHANGELOG 5.1.0.
 *
 * @since 5.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'QWPSEO_VERSION' ) ) {
	define( 'QWPSEO_VERSION', '1.1.1' );
}

/*
 * Loaded here, at plugin-load time, rather than from `qwpseo_init_language()` below.
 * Its front-end filters have to be in place before Yoast decides whether to persist an
 * indexable, and that can happen earlier in a request than `qtranslate_init_language`.
 */
require_once __DIR__ . '/qwpseo-indexables.php';
require_once __DIR__ . '/qwpseo-fallback.php';
require_once __DIR__ . '/qwpseo-editor-shim.php';

/**
 * Loads the media-library admin patches.
 *
 * @since 5.1.0
 */
function qwpseo_load_media_admin() {
	if ( ! is_admin() || defined( 'QWPSEO_MEDIA_ADMIN_LOADED' ) ) {
		return;
	}

	require_once __DIR__ . '/qwpseo-media-admin.php';
}
add_action( 'plugins_loaded', 'qwpseo_load_media_admin', 3 );

/**
 * Entry point: loads the front-end or the admin half once qTranslate knows the language.
 *
 * @since 5.1.0
 */
function qwpseo_init_language( $url_info ) {
	global $q_config;

	if ( $url_info['doing_front_end'] ) {
		add_filter( 'wpseo_use_page_analysis', 'qwpseo_no_page_analysis' );
		require_once __DIR__ . '/qwpseo-front.php';
	} else {
		if ( $q_config['editor_mode'] != QTX_EDITOR_MODE_SINGLGE ) {
			//Disable "Page Analysis" unless Single Language Editor Mode is in use.
			add_filter( 'wpseo_use_page_analysis', 'qwpseo_no_page_analysis' );
		}
		require_once __DIR__ . '/qwpseo-admin.php';
	}
}
add_action( 'qtranslate_init_language', 'qwpseo_init_language' );

/**
 * Disable "Page Analysis".
 *
 * @since 5.1.0
 */
function qwpseo_no_page_analysis( $a ) {
	return false;
}
