<?php
/**
 * Bundled bootstrap for the ACF qTranslate companion.
 *
 * Converted from the standalone plugin `acf-qtranslate` 1.7.25 by funkjedi
 * (https://github.com/funkjedi/acf-qtranslate), GPLv2 or later. Everything under
 * `src/`, `assets/` and `compatibility/` is a byte-for-byte copy of that plugin,
 * so it can still be diffed against the original. All of the adaptation lives
 * here, in the same spirit as `slug/loader.php`.
 *
 * What this keeps from the original `acf-qtranslate.php`:
 *
 * - `ACF_QTRANSLATE_PLUGIN` and `ACF_QTRANSLATE_PLUGIN_DIR`. Both are consumed
 *   throughout `src/`, for `require_once` paths and for `plugins_url()` asset
 *   URLs. Pointing them at this file resolves assets to
 *   `plugins/qtranslate-x/acf/assets/...`, which is where they now live.
 * - Instantiating `acf_qtranslate_plugin`, which registers every hook.
 *
 * What it drops:
 *
 * - Nothing else. The original bootstrap was four statements and carried no
 *   activation, deactivation or uninstall hooks, so unlike the slug companion
 *   there is no plugin-lifecycle code to strip.
 *
 * One behaviour does change, unavoidably. `acf_qtranslate_plugin::__construct()`
 * registers `plugin_action_links_<basename>` to put a Settings shortcut on the
 * plugin's own row in Plugins. Bundled, that basename resolves to
 * `qtranslate-x/acf/loader.php`, a row that does not exist, so the filter never
 * fires. The settings page itself is unaffected: it is registered through
 * `admin_menu` and stays reachable under Settings. The dead filter is left in
 * place deliberately rather than patched out, to keep `src/` identical to
 * upstream.
 *
 * Load order matters here. WordPress loads plugins in `active_plugins` order and
 * appends newly activated ones at the end, so the standalone plugin can load
 * either before or after this one depending on when it was last activated. Both
 * sides are therefore guarded on `class_exists( 'acf_qtranslate_plugin' )`: see
 * the call site in qtranslate.php and the early return in acf-qtranslate.php.
 * Without both guards, loading the two together is a fatal class redeclaration,
 * because `require_once` does not deduplicate the same class reached through two
 * different absolute paths.
 *
 * @since 4.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ACF_QTRANSLATE_PLUGIN' ) ) {
	define( 'ACF_QTRANSLATE_PLUGIN', __FILE__ );
}

if ( ! defined( 'ACF_QTRANSLATE_PLUGIN_DIR' ) ) {
	define( 'ACF_QTRANSLATE_PLUGIN_DIR', plugin_dir_path( ACF_QTRANSLATE_PLUGIN ) );
}

require_once __DIR__ . '/src/plugin.php';

new acf_qtranslate_plugin();
