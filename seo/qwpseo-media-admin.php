<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'QWPSEO_MEDIA_ADMIN_LOADED' ) ) {
	return;
}

define( 'QWPSEO_MEDIA_ADMIN_LOADED', true );

/**
 * Obtiene el titulo crudo (sin filtros) de un post.
 *
 * @param int $post_id ID del post.
 * @return string
 */
function qwpseo_get_raw_post_title( $post_id ) {
	global $wpdb;

	return (string) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_title FROM {$wpdb->posts} WHERE ID = %d",
			(int) $post_id
		)
	);
}

/**
 * @param string $title Titulo a comprobar.
 * @return bool
 */
function qwpseo_is_qtranslate_encoded_title( $title ) {
	return (bool) preg_match( '#(\[:[a-z]{2}\]|\{:[a-z]{2}\})#i', $title );
}

/**
 * Contexto: ID del post padre mientras se renderiza la metabox del adjunto.
 */
function qwpseo_set_attachment_parent_context() {
	global $post, $qwpseo_media_parent_id;

	$qwpseo_media_parent_id = 0;

	if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type || ! $post->post_parent ) {
		return;
	}

	$qwpseo_media_parent_id = (int) $post->post_parent;
}
add_action( 'attachment_submitbox_misc_actions', 'qwpseo_set_attachment_parent_context', 5 );

/**
 * WordPress muestra "Uploaded to" con $post_parent->post_title sin pasar por the_title.
 *
 * @param WP_Post|null $post    Post object.
 * @param string       $context Contexto de get_post().
 * @return WP_Post|null
 */
function qwpseo_translate_parent_post_for_attachment_admin( $post, $context ) {
	global $qwpseo_media_parent_id;

	if ( ! $post instanceof WP_Post || ! $qwpseo_media_parent_id ) {
		return $post;
	}

	if ( (int) $post->ID !== (int) $qwpseo_media_parent_id ) {
		return $post;
	}

	if ( ! function_exists( 'qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage' ) ) {
		return $post;
	}

	$post->post_title = qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage( $post->post_title );

	return $post;
}
add_filter( 'get_post', 'qwpseo_translate_parent_post_for_attachment_admin', 15, 2 );

/**
 * @param string $raw_title Titulo multilingue.
 * @param string $lang      Codigo de idioma opcional.
 * @return string
 */
function qwpseo_parse_title_for_active_language( $raw_title, $lang = '' ) {
	if ( ! function_exists( 'qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage' ) ) {
		return $raw_title;
	}

	if ( $lang && function_exists( 'qtranxf_use_language' ) ) {
		return qtranxf_use_language( $lang, $raw_title );
	}

	return qtranxf_useCurrentLanguageIfNotFoundUseDefaultLanguage( $raw_title );
}

/**
 * @param WP_Post|null $post Adjunto en edicion.
 * @return array|null
 */
function qwpseo_attachment_edit_parent_data( $post ) {
	if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type || ! $post->post_parent ) {
		return null;
	}

	$raw_title = qwpseo_get_raw_post_title( $post->post_parent );

	if ( ! qwpseo_is_qtranslate_encoded_title( $raw_title ) ) {
		return null;
	}

	return array(
		'parentTitleRaw'     => $raw_title,
		'parentTitleCurrent' => qwpseo_parse_title_for_active_language( $raw_title ),
	);
}

/**
 * Corrige el titulo justo despues de que WordPress renderice la metabox.
 */
function qwpseo_fix_uploaded_to_after_metadata() {
	global $post;

	$data = qwpseo_attachment_edit_parent_data( $post );

	if ( null === $data ) {
		return;
	}
	?>
	<script>
	(function () {
		var raw = <?php echo wp_json_encode( $data['parentTitleRaw'] ); ?>;
		var current = <?php echo wp_json_encode( $data['parentTitleCurrent'] ); ?>;

		function applyTitle(title) {
			var el = document.querySelector('.misc-pub-uploadedto strong');

			if (el) {
				el.textContent = title;
			}
		}

		function parseTitle(lang) {
			if (typeof window.qtranxj_split === 'function' && raw) {
				var parts = qtranxj_split(raw);

				if (lang && parts[lang]) {
					return parts[lang];
				}

				if (window.qTranslateConfig && qTranslateConfig.default_language && parts[qTranslateConfig.default_language]) {
					return parts[qTranslateConfig.default_language];
				}
			}

			return current;
		}

		function fix(lang) {
			applyTitle(parseTitle(lang));
		}

		fix(window.qTranslateConfig ? qTranslateConfig.activeLanguage : '');

		if (window.qTranslateConfig) {
			if (!qTranslateConfig.onTabSwitchFunctionsLoad) {
				qTranslateConfig.onTabSwitchFunctionsLoad = [];
			}

			qTranslateConfig.onTabSwitchFunctionsLoad.push(function (lang) {
				fix(lang);
			});
		}

		if (window.jQuery) {
			jQuery(document).on('click', '.qtranxs-lang-switch-wrap li', function () {
				setTimeout(function () {
					fix(window.qTranslateConfig ? qTranslateConfig.activeLanguage : '');
				}, 50);
			});
		}
	})();
	</script>
	<?php
}
add_action( 'attachment_submitbox_misc_actions', 'qwpseo_fix_uploaded_to_after_metadata', 11 );

/**
 * @param string $hook Pantalla admin actual.
 */
function qwpseo_enqueue_media_admin_scripts( $hook ) {
	if ( 'post.php' !== $hook || ! isset( $_GET['post'] ) ) {
		return;
	}

	$post_obj = get_post( (int) $_GET['post'] );
	$data     = qwpseo_attachment_edit_parent_data( $post_obj );

	if ( null === $data ) {
		return;
	}

	$file = dirname( __FILE__ ) . '/js/admin-media-parent-title.js';
	$ver  = defined( 'QWPSEO_VERSION' ) ? QWPSEO_VERSION : '1.1.1';

	if ( file_exists( $file ) ) {
		$ver .= '.' . filemtime( $file );
	}

	wp_enqueue_script(
		'qwpseo-media-parent-title',
		plugins_url( 'js/admin-media-parent-title.js', __FILE__ ),
		array( 'jquery' ),
		$ver,
		true
	);

	wp_localize_script( 'qwpseo-media-parent-title', 'qwpseoMedia', $data );
}
add_action( 'admin_enqueue_scripts', 'qwpseo_enqueue_media_admin_scripts', 25 );
