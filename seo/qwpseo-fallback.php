<?php
/**
 * Stops a missing translation being filled in with another language.
 *
 * qTranslate ships three resolvers for a multilingual string and they differ only in
 * what they do when the slot for the current language is empty:
 *
 * - `qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage()` falls back to the default
 *   language, and failing that to whatever content exists.
 * - `qtranxf_useCurrentLanguageIfNotFoundShowEmpty()` returns an empty string.
 * - `qtranxf_useCurrentLanguageIfNotFoundShowAvailable()` shows what is available.
 *
 * The first is registered on `wpseo_title` and `wpseo_metadesc` at priority 20, through
 * `i18n-config/plugins/wordpress-seo/i18n-config.json`.
 *
 * ONLY `wpseo_metadesc` IS SWAPPED, and the reason is measured, not assumed.
 *
 * A missing description is better off absent. Google then writes the snippet from the
 * page content, which is in the right language, and 3,057 of the 3,184 published pages
 * on this install already sit in exactly that state. A description in the wrong language
 * is the one that actively hurts: it shows French to a Spanish reader in the results page
 * and tells search engines the page's language is confused.
 *
 * THE TITLE WAS TRIED AND REVERTED. Swapping it too looked right on paper -- an empty SEO
 * title falls through to Yoast's template, `%%title%% %%page%% %%sep%% %%sitename%%`,
 * whose `%%title%%` is the post title, which is usually multilingual. On a page with a
 * translated post title that works: `/es/gracias/` went from `SHA` to `Gracias - SHA`.
 *
 * But measured across the 60-URL baseline, 11 titles changed and most of them got WORSE:
 *
 *     /ar/harvest/            HARVEST | SHA: Longevity & Wellness Experts Gathering
 *                          -> Harvest 2025 - SHA
 *     /ar/longevity-retreat/  Healthspan Optimization Retreat at SHA Mexico
 *                          -> Longevity Retreat - SHA
 *
 * The post titles of `harvest`, `longevity-retreat` and `leaders-health-performance` are
 * plain text, English only. So the template produced English anyway, just a mechanical
 * version of it, trading a written SEO title for a worse string in the same wrong
 * language. Nothing was gained. A `<title>` always renders something, so for that field
 * the choice is between two non-empty strings and the written one wins; for a description
 * the choice is between a wrong string and none, and none wins.
 *
 * WHAT THIS DOES NOT TOUCH, and why. The 22 `add_filter()` calls in
 * `qtranslate_hooks.php` that put the same resolver on `post_title`, `wp_title`,
 * `bloginfo`, `wp_list_categories`, `get_pages` and the RSS feeds all stay. Those must
 * keep falling back; an empty post title is a defect, not graceful degradation.
 *
 * OpenGraph and Twitter are handled separately, in `qwpseo-schema.php` at priority 25,
 * and they SPLIT BY FIELD rather than all going one way:
 *
 * - `og:description` and `twitter:description` go show-empty, through
 *   `qwpseo_filter_seo_description()`. Verified: when that filter returns an empty
 *   string Yoast omits the tag rather than emitting an empty one.
 * - `og:title` and `twitter:title` keep the anti-empty cascade, through
 *   `qwpseo_filter_seo_title()`, because `wpseo_title` above is NOT in the show-empty
 *   list -- so the document `<title>` already falls back to another language, and
 *   omitting `og:title` left the page contradicting itself. See the long note on
 *   `qwpseo_filter_seo_title()` for the measurement that settled it.
 *
 * Also removed as part of this change: three filters in
 * `themes/crepesandthemes/includes/qtranslate.php` that resolved `wpseo_metadesc`,
 * `wpseo_opengraph_title` and `wpseo_opengraph_desc` through `__()` at priority 10.
 * `__()` is intercepted by `qtranxf_gettext` at priority 0, which falls back to the
 * default language, so they resolved the string before anything here could see it --
 * which is why swapping this filter alone had no visible effect until they went.
 *
 * THIS IS A MITIGATION, NOT A FIX. The actual problem is content: of the 127 pages with a
 * meta description, only 14 have French and Arabic, and 26 have Spanish alone. The merit
 * of this change is not better output, it is that the gap becomes VISIBLE -- an editor
 * opening the French page now sees an empty field and Yoast's analysis flags it, instead
 * of seeing a filled-in description that is in the wrong language.
 *
 * @since 5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Swaps the default-language resolver for the show-empty one on the SEO meta hooks.
 */
function qwpseo_use_show_empty_fallback() {
	if ( ! function_exists( 'qtranxf_useCurrentLanguageIfNotFoundShowEmpty' ) ) {
		return;
	}

	$from = 'qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage';
	$to   = 'qtranxf_useCurrentLanguageIfNotFoundShowEmpty';

	foreach ( array( 'wpseo_metadesc' ) as $hook ) {
		$priority = has_filter( $hook, $from );
		if ( $priority === false ) {
			continue;
		}

		remove_filter( $hook, $from, $priority );
		add_filter( $hook, $to, $priority );
	}
}

/*
 * On `template_redirect`, which is front-end only and early enough that nothing has
 * rendered a head yet, but late enough that the i18n-config filters are already
 * registered -- they go on during qTranslate's own init, so swapping them any earlier
 * would find nothing to remove.
 */
add_action( 'template_redirect', 'qwpseo_use_show_empty_fallback', 1 );
