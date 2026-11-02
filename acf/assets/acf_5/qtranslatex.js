
(function(){

	// Only proceed if qTranslate is loaded
	function qtxLoaded() {
		return typeof qTranslateConfig == 'object' && typeof qTranslateConfig.qtx == 'object';
	}

	// Ensure that translation of standard field types is enabled
	function standardFieldTypesEnabled() {
		return !!window.acf_qtranslate_translate_standard_field_types;
	}

	var post_type;

	// Whitelist fields for translation
	function isTranslatableField(field){
		if (post_type === undefined) {
			post_type = jQuery('#post_type').val();
		}
		if (post_type === 'acf-field-group') {
			if (field.id.match(/acf_fields-\d+-label/))         return true;
			if (field.id.match(/acf_fields-\d+-instructions/))  return true;
			if (field.id.match(/acf_fields-\d+-default_value/)) return true;
			return false;
		}
		return true;
	}

	// The wysiwyg is hooked here, not in the sweep below. This filter runs after
	// ACF has replaced the editor wrap (initializeEditor renames it with
	// acf.rename({destructive: true}), which creates a new textarea), so the id and
	// the element are the definitive ones. Hooking earlier registers a node ACF is
	// about to throw away and leaves the language tabs without an editor to drive.
	// Registered at script execution, before any editor initializes.
	acf.addFilter('wysiwyg_tinymce_settings', function (mceInit, id, field) {
		if (!qtxLoaded() || !standardFieldTypesEnabled()) return mceInit;
		if (!field || field.type !== 'wysiwyg') return mceInit;

		var textarea = field.$input()[0];
		if (!textarea || !isTranslatableField(textarea)) return mceInit;

		// refreshContentHook is the public, idempotent entry point: it drops a hook
		// left on this id (without losing a language) and hooks the element again.
		if (!qTranslateConfig.qtx.refreshContentHook(textarea)) return mceInit;

		// Bind TinyMCE to the hook once the editor exists. loadTinyMceHooks (common.js)
		// binds every editor that has a hook and is not bound yet.
		var previous = mceInit.init_instance_callback;
		mceInit.init_instance_callback = function (ed) {
			if (typeof previous === 'function') {
				previous.apply(this, arguments);
			}
			// ACF destroys and re-creates the editor under the same id when a repeater
			// row is reordered (disableEditor/enableEditor). setEditorHooks skips a hook
			// that already holds an editor, so release the destroyed one here and let
			// loadTinyMceHooks bind the new one; otherwise the language tabs stop
			// driving that editor.
			var hook = ed && qTranslateConfig.qtx.hasContentHook(ed.id);
			if (hook && hook.mce && hook.mce !== ed) {
				hook.mce = null;
			}
			if (typeof window.loadTinyMceHooks === 'function') {
				window.loadTinyMceHooks();
			}
		};

		return mceInit;
	});

	// ACF fires 'load' from a timeout after window.load, and the wysiwyg editors
	// are renamed during it. Running first keeps the sweep ahead of that rename.
	function onAcfLoad(callback) {
		if (acf.didAction('load')) {
			callback();
		} else {
			acf.addAction('load', callback, 1);
		}
	}

	onAcfLoad(function() {

		if (!qtxLoaded()) {
			return;
		}

		// Enable the language switching buttons
		qTranslateConfig.qtx.enableLanguageSwitchingButtons('block');

		if (!standardFieldTypesEnabled()) {
			return;
		}

		// Selectors for supported field types. The wysiwyg is not here (see the
		// filter above), and 'textarea' must not match the editors: these are
		// '.wp-editor-area' textareas, hooked by the filter instead.
		var field_types = {
			text:      'input:text',
			textarea:  'textarea:not(.wp-editor-area)',
		};

		// Remove content hooks from ACF Fields. qTranslate hooks every
		// '.wp-editor-area' at document ready, before ACF replaces those nodes;
		// the filter above hooks the replacements, so drop the doomed hooks here.
		jQuery('.acf-postbox .acf-field').find('.qtranxs-translatable').each(function() {
			qTranslateConfig.qtx.removeContentHook(this);
		});

		// Setup field types
		jQuery.each(field_types, function(field_type, selector) {

			// Add content hooks for existing fields
			var fields = acf.findFields ? acf.findFields({ type: field_type }) : acf.get_fields({ type: field_type });
			fields.each(function() {
				var field = jQuery(this).find(selector).get(0);
				if (!field || !isTranslatableField(field)) return;
				qTranslateConfig.qtx.addContentHookC(field);
			});

			// Watch and add content hooks when new fields are added
			acf.add_action('append_field/type=' + field_type, function($el) {
				var field = $el.find(selector).get(0);
				if (!field || !isTranslatableField(field)) return;
				qTranslateConfig.qtx.addContentHookC(field);

				// Run at higher integer priority than the default in case the ACF handlers
				// change the id of the underlying input
			}, 100);

		});

		// Remove the content hooks of a repeater row when it is deleted. ACF drops the
		// row's elements and editors by itself but knows nothing of the qTranslate
		// registry, so each deleted row would leave its entries behind, pointing at
		// detached nodes. ACF removes the elements early, so wrap its handler and
		// unhook first, while the row is still in the document.
		var repeater = acf.models ? acf.models.RepeaterField.prototype : acf.fields.repeater;
		var repeaterRemoveOriginal = repeater.remove;

		repeater.remove = function ($el) {
			var row = ($el.$el || $el).closest('.acf-row'); // support old versions of ACF5PRO as well
			row.find('input, textarea').each(function() {
				qTranslateConfig.qtx.removeContentHook(this);
			});
			// call the original handler
			return repeaterRemoveOriginal.apply(this, arguments);
		};
	});

})();
