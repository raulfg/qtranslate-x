<?php
/**
 * Loads the classic-editor getPostType shim.
 *
 * See `js/classic-editor-getposttype-shim.js` for what it repairs and why. In short:
 * from Yoast 27.6 the metabox's root component selects `getPostType` from the Yoast
 * editor store before it checks whether it is running in the block editor, and that
 * selector is not on the store registered for a classic post screen. The call throws,
 * React unmounts, and the whole SEO metabox is replaced by an error panel.
 *
 * The shim only has to beat Yoast's own bundle to the registry, so it is enqueued in
 * the header at an early priority rather than in the footer with the module's other
 * scripts. `wp.data.use()` applies to every later `select()`, including the `useSelect`
 * inside Yoast's components, so ordering is the only requirement.
 *
 * It is a no-op on a Yoast that does not need it: the shim returns the store untouched
 * when `getPostType` is already a function, so the file can stay in place across
 * upgrades and downgrades without being version-gated here.
 *
 * @since 5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues the shim on the screens that render Yoast's classic metabox.
 *
 * @param string $hook_suffix Current admin page.
 */
function qwpseo_enqueue_editor_shim( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php', 'term.php', 'edit-tags.php' ), true ) ) {
		return;
	}

	$file = __DIR__ . '/js/classic-editor-getposttype-shim.js';
	if ( ! file_exists( $file ) ) {
		return;
	}

	$version = defined( 'QWPSEO_VERSION' ) ? QWPSEO_VERSION : '1.1.1';
	$version .= '.' . filemtime( $file );

	wp_enqueue_script(
		'qwpseo-classic-editor-shim',
		plugins_url( 'js/classic-editor-getposttype-shim.js', __FILE__ ),
		array( 'wp-data' ),
		$version,
		false
	);
}

add_action( 'admin_enqueue_scripts', 'qwpseo_enqueue_editor_shim', 1 );
