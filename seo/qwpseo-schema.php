<?php
/**
 * Yoast SEO schema + Open Graph: resolve qTranslate-X multilanguage strings on front-end.
 *
 * @package wp-seo-qtranslate-x
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a string contains qTranslate language markers.
 *
 * @param string $value Raw value.
 * @return bool
 */
function qwpseo_string_has_qtranslate_markers( $value ) {
	if ( ! is_string( $value ) || $value === '' ) {
		return false;
	}

	return (bool) preg_match( '/\[:(?:[a-z]{2})?\]|\{:(?:[a-z]{2})?\}/', $value );
}

/**
 * Resolve qTranslate-X encoded text for the current front-end language.
 *
 * @param mixed $raw Raw string (or passthrough).
 * @return mixed
 */
function qwpseo_resolve_qtranslate_string( $raw ) {
	if ( ! is_string( $raw ) || $raw === '' ) {
		return $raw;
	}

	if ( ! qwpseo_string_has_qtranslate_markers( $raw ) ) {
		return $raw;
	}

	if ( function_exists( 'qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage' ) ) {
		$resolved = qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage( $raw );
		if ( is_string( $resolved ) && trim( $resolved ) !== '' && ! qwpseo_string_has_qtranslate_markers( $resolved ) ) {
			return trim( html_entity_decode( $resolved, ENT_QUOTES, 'UTF-8' ) );
		}
	}

	if ( function_exists( 'qtranxf_getLanguage' ) && function_exists( 'qtranxf_use' ) ) {
		$lang     = (string) qtranxf_getLanguage();
		$resolved = qtranxf_use( $lang, $raw, false );
		if ( is_string( $resolved ) && trim( $resolved ) !== '' && ! qwpseo_string_has_qtranslate_markers( $resolved ) ) {
			return trim( html_entity_decode( $resolved, ENT_QUOTES, 'UTF-8' ) );
		}
	}

	if ( preg_match( '/\[:([a-z]{2})\](.*?)(?=\[:|$)/s', $raw, $matches ) ) {
		return trim( html_entity_decode( $matches[2], ENT_QUOTES, 'UTF-8' ) );
	}

	if ( preg_match( '/\{:(?:[a-z]{2})\}(.*?)(?=\{:[a-z]{2}\}|\{:\}|$)/s', $raw, $matches ) ) {
		return trim( html_entity_decode( $matches[2], ENT_QUOTES, 'UTF-8' ) );
	}

	return $raw;
}

/**
 * Convert a schema URL to the requested/current qTranslate language.
 *
 * @param string $url Absolute URL.
 * @return string
 */
function qwpseo_localize_schema_url( $url ) {
	if ( ! is_string( $url ) || $url === '' ) {
		return $url;
	}

	if ( ! function_exists( 'qtranxf_getLanguage' ) || ! function_exists( 'qtranxf_convertURL' ) ) {
		return $url;
	}

	$lang      = (string) qtranxf_getLanguage();
	$converted = qtranxf_convertURL( $url, $lang, false, true );

	if ( is_string( $converted ) && $converted !== '' ) {
		return $converted;
	}

	return $url;
}

/**
 * Resolve translatable string fields inside a schema array (recursive).
 *
 * @param mixed $data Schema node or scalar.
 * @return mixed
 */
function qwpseo_schema_resolve_node( $data ) {
	if ( is_string( $data ) ) {
		return qwpseo_resolve_qtranslate_string( $data );
	}

	if ( ! is_array( $data ) ) {
		return $data;
	}

	$url_keys = array( '@id', 'url', 'item', 'contentUrl' );

	foreach ( $data as $key => $value ) {
		if ( is_string( $value ) ) {
			if ( in_array( $key, $url_keys, true ) && filter_var( $value, FILTER_VALIDATE_URL ) ) {
				$data[ $key ] = qwpseo_localize_schema_url( qwpseo_resolve_qtranslate_string( $value ) );
			} else {
				$data[ $key ] = qwpseo_resolve_qtranslate_string( $value );
			}
			continue;
		}

		if ( $key === 'itemListElement' && is_array( $value ) ) {
			foreach ( $value as $index => $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				if ( isset( $item['name'] ) ) {
					$item['name'] = qwpseo_resolve_qtranslate_string( $item['name'] );
				}
				if ( ! empty( $item['item'] ) ) {
					if ( is_string( $item['item'] ) ) {
						$item['item'] = qwpseo_localize_schema_url( $item['item'] );
					} elseif ( is_array( $item['item'] ) && isset( $item['item']['@id'] ) ) {
						$item['item']['@id'] = qwpseo_localize_schema_url( $item['item']['@id'] );
					}
				}
				$value[ $index ] = $item;
			}
			$data[ $key ] = $value;
			continue;
		}

		$data[ $key ] = qwpseo_schema_resolve_node( $value );
	}

	return $data;
}

/**
 * @param array $piece WebPage schema piece.
 * @return array
 */
function qwpseo_schema_filter_webpage( $piece ) {
	return qwpseo_schema_resolve_node( $piece );
}

/**
 * @param array $piece BreadcrumbList schema piece.
 * @return array
 */
function qwpseo_schema_filter_breadcrumb( $piece ) {
	return qwpseo_schema_resolve_node( $piece );
}

/**
 * @param array $graph Full Yoast schema graph.
 * @return array
 */
function qwpseo_schema_filter_graph( $graph ) {
	if ( ! is_array( $graph ) ) {
		return $graph;
	}

	foreach ( $graph as $index => $piece ) {
		$graph[ $index ] = qwpseo_schema_resolve_node( $piece );
	}

	return $graph;
}

/**
 * Resolves a social DESCRIPTION, show-empty.
 *
 * An og:description written only in French must not be served to English, Spanish and
 * Arabic readers. Omitting the tag is the right answer: a description has no correct
 * substitute in another language, and a consumer without one simply shows none.
 *
 * This is the opposite of `qwpseo_resolve_qtranslate_string()`, which is deliberately
 * anti-empty because it serves the schema graph, where a BreadcrumbList with an empty
 * name is broken structured data rather than a missing nicety.
 *
 * @param string $value Filtered SEO string.
 * @return string
 */
function qwpseo_filter_seo_description( $value ) {
	if ( ! is_string( $value ) || $value === '' ) {
		return $value;
	}

	if ( ! qwpseo_string_has_qtranslate_markers( $value ) ) {
		return $value;
	}

	if ( function_exists( 'qtranxf_useCurrentLanguageIfNotFoundShowEmpty' ) ) {
		$resolved = qtranxf_useCurrentLanguageIfNotFoundShowEmpty( $value );
		if ( is_string( $resolved ) ) {
			$resolved = trim( $resolved );
			if ( $resolved === '' ) {
				return '';
			}
			if ( ! qwpseo_string_has_qtranslate_markers( $resolved ) ) {
				return trim( html_entity_decode( $resolved, ENT_QUOTES, 'UTF-8' ) );
			}
		}
	}

	return qwpseo_resolve_qtranslate_string( $value );
}

/**
 * Resolves a social TITLE, never empty.
 *
 * A TITLE IS NOT A DESCRIPTION, AND THIS IS THE WHOLE POINT OF THE SPLIT. Both used to
 * go through the show-empty path above, on the reasoning that a consumer without an
 * og:title falls back to the document <title>, and that the <title> is correct per
 * language because Yoast builds it from the multilingual post title.
 *
 * Measured on this install, that premise is false. `wpseo_title` is NOT switched to
 * show-empty -- `qwpseo-fallback.php` lists only `wpseo_metadesc` -- so a page with no
 * Yoast title in the active language serves the title of another language. On post
 * 77194, `/fr/` and `/ar/` both render the ENGLISH <title> while omitting og:title
 * entirely. The page then contradicts itself: an English <title> and no og:title at all.
 *
 * Measured across the 60-URL baseline: 11 URLs had a <title> but no og:title. Before the
 * module, the count was 0.
 *
 * So titles take the anti-empty cascade, matching what `wpseo_title` already does, and
 * descriptions keep show-empty. One policy per field, each consistent with the tag it
 * governs.
 *
 * @param string $value Filtered SEO string.
 * @return string
 */
function qwpseo_filter_seo_title( $value ) {
	if ( ! is_string( $value ) || $value === '' ) {
		return $value;
	}

	if ( ! qwpseo_string_has_qtranslate_markers( $value ) ) {
		return $value;
	}

	return qwpseo_resolve_qtranslate_string( $value );
}

function qwpseo_register_schema_filters() {
	add_filter( 'wpseo_schema_webpage', 'qwpseo_schema_filter_webpage', 20, 1 );
	add_filter( 'wpseo_schema_breadcrumb', 'qwpseo_schema_filter_breadcrumb', 20, 1 );
	add_filter( 'wpseo_schema_graph', 'qwpseo_schema_filter_graph', 20, 1 );

	add_filter( 'wpseo_opengraph_title', 'qwpseo_filter_seo_title', 25, 1 );
	add_filter( 'wpseo_twitter_title', 'qwpseo_filter_seo_title', 25, 1 );

	add_filter( 'wpseo_opengraph_desc', 'qwpseo_filter_seo_description', 25, 1 );
	add_filter( 'wpseo_twitter_description', 'qwpseo_filter_seo_description', 25, 1 );
}

qwpseo_register_schema_filters();
