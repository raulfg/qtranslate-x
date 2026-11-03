<?php
/**
 * Keeps Yoast's indexable table holding raw multilingual values.
 *
 * Yoast caches a computed title, description and social tags per object in its
 * `yoast_indexable` table, and reads that cache when it renders the head. On a
 * multilingual site that cache is a trap: it has one row per object, but the value it
 * stores depends on which language was active when the row was written.
 *
 * The arrangement that works, and that the rest of this module depends on, is for the
 * row to hold the UNRESOLVED value -- `[:en]Policies[:es]Políticas[:]` -- and for the
 * `wpseo_title` and `wpseo_metadesc` filters to resolve it per request. One row then
 * serves all four languages.
 *
 * Yoast also indexes from the front end, and a front-end save writes the value already
 * resolved into whichever language that visitor was browsing. The row then holds, say,
 * the Spanish description with no markers left in it, the filters find nothing to
 * resolve, and every language gets served Spanish. Nothing errors: the filters work
 * correctly on a value that was already wrong, so this fails silently and stays broken.
 *
 * Measured on this install before the fix: of 174 rows whose postmeta was multilingual,
 * 74 had been frozen into a single language, across 73 published objects -- 292 URLs
 * serving a wrong-language or empty meta description.
 *
 * So, two halves, and they only work together:
 *
 * - On the front end, never persist an indexable. This stops new damage. It does NOT
 *   repair existing rows, because Yoast reads a row that already exists rather than
 *   rebuilding it (see `Indexable_Repository::find_by_id_and_type()`, which only calls
 *   the builder when `find_one()` comes back empty).
 * - In the admin, let Yoast save as usual, then put the raw values back over whatever
 *   it computed. This is what repairs a row, and it runs on every subsequent edit.
 *
 * Adapted from qTranslate-XT's `src/modules/wp-seo/`, which solved the same problem.
 *
 * @since 5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * The front end must never write an indexable.
 *
 * Both filters are needed and they sit at different depths: `should_index_indexables`
 * gates indexable creation for the whole site, and `wpseo_should_save_indexable` gates
 * the individual save. Yoast 28.8 declares both in
 * `src/helpers/indexable-helper.php`; the first has existed since Yoast 18.2.
 *
 * `save_indexable()` returns the indexable unsaved when the gate closes, so the request
 * still renders from the in-memory object. Nothing is lost for the visitor.
 */
if ( ! is_admin() ) {
	add_filter( 'Yoast\WP\SEO\should_index_indexables', '__return_false' );
	add_filter( 'wpseo_should_save_indexable', '__return_false' );
}

/**
 * Maps indexable columns to the post meta keys that hold their raw multilingual value.
 *
 * @return array<string,string> Meta key => indexable field.
 */
function qwpseo_indexable_post_meta_map() {
	return array(
		'_yoast_wpseo_title'                 => 'title',
		'_yoast_wpseo_metadesc'              => 'description',
		'_yoast_wpseo_bctitle'               => 'breadcrumb_title',
		'_yoast_wpseo_opengraph-title'       => 'open_graph_title',
		'_yoast_wpseo_opengraph-description' => 'open_graph_description',
		'_yoast_wpseo_twitter-title'         => 'twitter_title',
		'_yoast_wpseo_twitter-description'   => 'twitter_description',
	);
}

/**
 * Reads a post meta value with qTranslate's read filter lifted.
 *
 * This matters more than it looks. `get_post_meta()` passes through
 * `qtranxf_filter_postmeta`, hooked on `get_post_metadata`, which resolves a
 * multilingual value down to the ONE language active for the request. Reading that way
 * and writing the result into the indexable is how the other three languages get
 * destroyed -- it is the very damage this file exists to prevent, so reading raw here is
 * not a detail, it is the whole point.
 *
 * Measured on this install: `get_post_meta( 363, '_yoast_wpseo_title', true )` returns
 * `Presidential Suite | SHA`, while the stored row holds
 * `[:en]Presidential Suite | SHA[:es]Suite Presidencial | SHA[:fr]...`.
 *
 * Same technique as `acf/src/acf_5/acf.php::read_stored_value()`, which lifts the same
 * filter for the same reason.
 *
 * @param int    $post_id  Post ID.
 * @param string $meta_key Meta key.
 * @return string Raw stored value, or '' when there is none.
 */
function qwpseo_read_raw_post_meta( $post_id, $meta_key ) {
	$hook   = 'get_post_metadata';
	$lifted = array();

	foreach ( array( 'qtranxf_filter_postmeta', 'qtranxf_filter_usermeta' ) as $callback ) {
		$priority = has_filter( $hook, $callback );
		if ( $priority !== false ) {
			remove_filter( $hook, $callback, $priority );
			$lifted[ $callback ] = $priority;
		}
	}

	try {
		$rows = get_metadata_raw( 'post', $post_id, $meta_key, false );
	} finally {
		foreach ( $lifted as $callback => $priority ) {
			add_filter( $hook, $callback, $priority, 4 );
		}
	}

	if ( ! is_array( $rows ) || $rows === array() ) {
		return '';
	}

	return is_string( $rows[0] ) ? $rows[0] : '';
}

/**
 * Computes what an indexable's fields should hold, reading the raw post meta.
 *
 * Kept separate from the write below so that a one-off repair pass can report what it
 * would change without writing, and so there is only one definition of what counts as
 * empty and how the breadcrumb title falls back.
 *
 * @param object $indexable The indexable to compute values for.
 * @return array<string,string|null>|null Field => value, or null when not applicable.
 */
function qwpseo_raw_indexable_values( $indexable ) {
	if ( ! is_object( $indexable ) || empty( $indexable->object_id ) ) {
		return null;
	}

	if ( ! isset( $indexable->object_type ) || $indexable->object_type !== 'post' ) {
		return null;
	}

	$post = get_post( $indexable->object_id );
	if ( ! $post ) {
		return null;
	}

	$values = array();
	foreach ( qwpseo_indexable_post_meta_map() as $meta_key => $field ) {
		$value = qwpseo_read_raw_post_meta( $indexable->object_id, $meta_key );

		/*
		 * An absent value is stored as null, never as an empty string. Yoast treats
		 * null as "nothing set here" and falls back to its title template and its
		 * normal hierarchy; an empty string is a value, and would have it render an
		 * empty tag instead.
		 */
		$values[ $field ] = ( is_string( $value ) && $value !== '' ) ? $value : null;
	}

	/*
	 * Breadcrumb title is the one field with no useful fallback inside Yoast, so it
	 * takes the post's own untranslated title. The title still carries its language
	 * markers at this point, which is what the breadcrumb filters expect.
	 */
	if ( $values['breadcrumb_title'] === null ) {
		$values['breadcrumb_title'] = wp_strip_all_tags( $post->post_title, true );
	}

	return $values;
}

/**
 * Writes the raw multilingual values from post meta back over a saved indexable.
 *
 * @param object $indexable The indexable Yoast has just saved.
 */
function qwpseo_restore_raw_indexable_values( $indexable ) {
	$values = qwpseo_raw_indexable_values( $indexable );
	if ( $values === null ) {
		return;
	}

	$changed = false;
	foreach ( $values as $field => $value ) {
		if ( ! property_exists( $indexable, $field ) && ! isset( $indexable->{$field} ) ) {
			continue;
		}
		if ( $indexable->{$field} === $value ) {
			continue;
		}
		$indexable->{$field} = $value;
		$changed             = true;
	}

	if ( ! $changed ) {
		return;
	}

	/*
	 * The model's own save(), not `Indexable_Helper::save_indexable()`. The helper is
	 * what fires `wpseo_saved_indexable`, so going through it here would call this
	 * function again, forever. Verified against Yoast 28.8: the action appears once,
	 * in `src/helpers/indexable-helper.php`, and not in `src/models/indexable.php`.
	 */
	$indexable->save();
}

/*
 * Both actions, because they are not available in the same Yoast versions and the
 * module has to work either side of an upgrade.
 *
 * Yoast 24.3 fires only `wpseo_save_indexable`. `wpseo_saved_indexable` does not exist
 * there at all -- checked across the whole 24.3 tree, not just the helper. Yoast 28.8
 * fires both, from `Indexable_Helper::save_indexable()`:
 *
 *     $indexable->save();
 *     if ( $indexable_before ) {
 *         do_action( 'wpseo_save_indexable', $indexable, $indexable_before );
 *     }
 *     do_action( 'wpseo_saved_indexable', $indexable );
 *
 * They differ in coverage, which is why both are needed rather than either: the first
 * only fires when a previous indexable existed, so a newly created one would miss it;
 * the second always fires but is absent before 28.x. Hooking both covers every case on
 * both versions.
 *
 * Running twice for one save is harmless. The callback writes only when a field
 * actually differs and the second pass finds nothing left to change, so it returns
 * before touching the database.
 */
add_action( 'wpseo_saved_indexable', 'qwpseo_restore_raw_indexable_values', 10, 1 );
add_action( 'wpseo_save_indexable', 'qwpseo_restore_raw_indexable_values', 10, 1 );
