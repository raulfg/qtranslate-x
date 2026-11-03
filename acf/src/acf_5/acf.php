<?php

require_once ACF_QTRANSLATE_PLUGIN_DIR . 'src/acf_interface.php';

class acf_qtranslate_acf_5 implements acf_qtranslate_acf_interface {

	/**
	 * The plugin instance.
	 * @var \acf_qtranslate_plugin
	 */
	protected $plugin;


	/*
	 * Create an instance.
	 * @return void
	 */
	public function __construct($plugin) {
		$this->plugin = $plugin;

		add_filter('acf/format_value',                array($this, 'format_value'));
		add_action('acf/include_fields',              array($this, 'include_fields'));
		add_action('acf/input/admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
		// Merging runs first, so a front-end save the guard would have reported
		// as loss is already repaired by the time the guard looks at it.
		add_filter('acf/update_value',                array($this, 'merge_front_end_value'), 9, 3);
		add_filter('acf/update_value',                array($this, 'log_translation_loss'), 10, 3);
		add_filter('acf/validate_value/type=wysiwyg', array($this, 'validate_raw_value'), 10, 4);
	}

	/**
	 * Load javascript and stylesheets on admin pages.
	 */
	public function include_fields() {
		require_once ACF_QTRANSLATE_PLUGIN_DIR . 'src/acf_5/fields/file.php';
		require_once ACF_QTRANSLATE_PLUGIN_DIR . 'src/acf_5/fields/image.php';
		require_once ACF_QTRANSLATE_PLUGIN_DIR . 'src/acf_5/fields/post_object.php';
		require_once ACF_QTRANSLATE_PLUGIN_DIR . 'src/acf_5/fields/text.php';
		require_once ACF_QTRANSLATE_PLUGIN_DIR . 'src/acf_5/fields/textarea.php';
		require_once ACF_QTRANSLATE_PLUGIN_DIR . 'src/acf_5/fields/url.php';
		require_once ACF_QTRANSLATE_PLUGIN_DIR . 'src/acf_5/fields/wysiwyg.php';

		acf()->fields->register_field_type(new acf_qtranslate_acf_5_file($this->plugin));
		acf()->fields->register_field_type(new acf_qtranslate_acf_5_image($this->plugin));
		acf()->fields->register_field_type(new acf_qtranslate_acf_5_post_object($this->plugin));
		acf()->fields->register_field_type(new acf_qtranslate_acf_5_text($this->plugin));
		acf()->fields->register_field_type(new acf_qtranslate_acf_5_textarea($this->plugin));
		acf()->fields->register_field_type(new acf_qtranslate_acf_5_url($this->plugin));
		acf()->fields->register_field_type(new acf_qtranslate_acf_5_wysiwyg($this->plugin));
	}

	/**
	 * Load javascript and stylesheets on admin pages.
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_script('acf_qtranslate_main',   plugins_url('/assets/acf_5/main.js', ACF_QTRANSLATE_PLUGIN), array('acf-input','underscore'), qtranxf_fork_version());
	}

	/**
	 * This filter is applied to the $value after it is loaded from the db and
	 * before it is returned to the template via functions such as get_field().
	 */
	public function format_value($value) {
		if (is_string($value)) {
			$value = qtrans_useCurrentLanguageIfNotFoundUseDefaultLanguage($value);
			$value = maybe_unserialize($value);
		}

		return $value;
	}

	/**
	 * Log, never block, a save that replaces a multilingual value with a
	 * single-language one.
	 *
	 * PHP cannot tell "the editor bug swallowed the other languages" from "the
	 * editor removed a translation on purpose", so the value is never altered:
	 * this only turns a silent regression into a detectable one. It also covers
	 * wysiwyg fields set to "Text Only" or "Delay initialization", which get no
	 * JS hook at all.
	 *
	 * Fires for every field of every type on every save, so the cheap checks on
	 * the incoming value run first and the stored value is only read once they
	 * pass. An empty incoming value is how ACF clears a field legitimately and
	 * is not treated as loss. Field content is never logged, only its length.
	 *
	 * @param mixed $value   The value about to be saved.
	 * @param mixed $post_id The post (or option/user) id the value is saved to.
	 * @param array $field   The field array.
	 * @return mixed The unchanged $value.
	 */
	public function log_translation_loss($value, $post_id, $field) {
		try {
			if (!is_string($value) || $value === '') {
				return $value;
			}
			if (!function_exists('qtranxf_isMultilingual') || !function_exists('qtranxf_split_blocks')
				|| !function_exists('qtranxf_get_language_blocks') || !function_exists('acf_get_metadata') || !function_exists('acf_decode_post_id') || !function_exists('get_metadata_raw')) {
				return $value;
			}
			if (qtranxf_isMultilingual($value) || !is_array($field) || empty($field['name'])) {
				return $value;
			}

			$stored = $this->read_stored_value($post_id, $field);
			if (!is_string($stored) || !qtranxf_isMultilingual($stored)) {
				return $value;
			}

			$found = array();
			qtranxf_split_blocks(qtranxf_get_language_blocks($stored), $found);

			error_log(sprintf(
				'acf-qtranslate: translation loss post=%s field=%s languages=%s length_before=%d length_after=%d',
				is_scalar($post_id) ? $post_id : gettype($post_id),
				isset($field['key']) ? $field['key'] : $field['name'],
				implode(',', array_keys($found)),
				strlen($stored),
				strlen($value)
			));
		} catch (\Throwable $e) {
			// A guard that fails must never break the save it is watching.
		}

		return $value;
	}

	/**
	 * Read a field's stored value as it sits in the database.
	 *
	 * Goes through the metadata API rather than $wpdb->get_var(), which turns an
	 * empty string into NULL and so hides a present-but-empty row. qTranslate-X
	 * registers a get_*_metadata filter on the front end and in CLI that
	 * collapses the value to the current language; even get_metadata_raw() runs
	 * it, so it is lifted for this one read. The read itself is served from
	 * WordPress' per-object meta cache. Options have no such filter here, so
	 * they use ACF's own read.
	 *
	 * @param mixed $post_id The post (or option/user) id the value belongs to.
	 * @param array $field   The field array.
	 * @return string|null The stored value, or null when there is nothing to read.
	 */
	protected function read_stored_value($post_id, $field) {
		$decoded = acf_decode_post_id($post_id);
		if (empty($decoded['id'])) {
			return null;
		}

		if ($decoded['type'] === 'option') {
			return acf_get_metadata($post_id, $field['name']);
		}

		$hook   = 'get_' . $decoded['type'] . '_metadata';
		$lifted = array();
		foreach (array('qtranxf_filter_postmeta', 'qtranxf_filter_usermeta') as $callback) {
			$priority = has_filter($hook, $callback);
			if ($priority !== false) {
				remove_filter($hook, $callback, $priority);
				$lifted[$callback] = $priority;
			}
		}
		try {
			$rows = get_metadata_raw($decoded['type'], $decoded['id'], $field['name'], false);
		} finally {
			foreach ($lifted as $callback => $priority) {
				add_filter($hook, $callback, $priority, 4);
			}
		}

		return $rows ? $rows[0] : null;
	}

	/**
	 * Keep the other languages when a field is saved outside the admin.
	 *
	 * A front-end acf_form() has none of the multilingual machinery: the language
	 * comes from the URL, the field renders the one language that URL asks for,
	 * and the form posts that single string back. Saved as-is it replaces the
	 * whole multilingual value, so editing a page through /es/ silently discards
	 * English, French and Arabic.
	 *
	 * The submitted text is therefore merged into the slot for the language being
	 * browsed, leaving every other slot as it was. Editing through /en/ touches
	 * English and nothing else, which is what a per-language URL promises.
	 *
	 * The admin is deliberately excluded: there the value arrives already
	 * assembled from the per-language inputs, and a plain string genuinely means
	 * the editor emptied the other languages.
	 *
	 * @param mixed $value   The value about to be saved.
	 * @param mixed $post_id The post (or option/user) id the value is saved to.
	 * @param array $field   The field array.
	 * @return mixed The merged value, or the original when merging does not apply.
	 */
	public function merge_front_end_value($value, $post_id, $field) {
		try {
			if (is_admin() || !is_string($value)) {
				return $value;
			}
			if (!function_exists('qtranxf_isMultilingual') || !function_exists('qtranxf_split')
				|| !function_exists('qtranxf_join_b') || !function_exists('acf_decode_post_id')
				|| !function_exists('get_metadata_raw') || !function_exists('acf_get_metadata')) {
				return $value;
			}
			// Already assembled: nothing to merge into.
			if (qtranxf_isMultilingual($value) || !is_array($field) || empty($field['name'])) {
				return $value;
			}

			$stored = $this->read_stored_value($post_id, $field);
			if (!is_string($stored) || !qtranxf_isMultilingual($stored)) {
				return $value;
			}

			global $q_config;
			$language = isset($q_config['language']) ? $q_config['language'] : null;
			if (!$language || empty($q_config['enabled_languages'])
				|| !in_array($language, $q_config['enabled_languages'], true)) {
				return $value;
			}

			$texts = qtranxf_split($stored);
			if (!is_array($texts)) {
				return $value;
			}
			$texts[$language] = $value;

			return qtranxf_join_b($texts);
		} catch (\Throwable $e) {
			// Never break a save: without the merge the old behaviour returns,
			// and the guard that follows reports the loss.
			return $value;
		}
	}

	/**
	 * Accept a multilingual wysiwyg value in ACF's validation.
	 *
	 * qTranslate-X joins the per-language inputs into one marked string
	 * ("[:en]...[:es]...[:]") before ACF validates, and ACF has no notion of it.
	 * A string that carries real content in at least one language is valid; a value
	 * that is only markers has nothing to accept and is left to ACF's own verdict,
	 * so a required field cannot be satisfied by empty markers.
	 *
	 * @param true|string $valid  The validation result so far.
	 * @param mixed       $value  The value being validated.
	 * @param array       $field  The field array.
	 * @param string      $input  The input name.
	 * @return true|string
	 */
	public function validate_raw_value($valid, $value, $field, $input) {
		if (is_string($value) && function_exists('qtranxf_isMultilingual') && qtranxf_isMultilingual($value)) {
			$content = preg_replace('/\[:[a-z]{2}\]|\[:\]/', '', $value);
			if (trim($content) !== '') {
				return true;
			}
		}

		return $valid;
	}

}
