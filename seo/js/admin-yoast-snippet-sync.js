(function ($) {
	'use strict';

	function yoastQtxIsIntegrated() {
		return $('input[name*="qtranslate-fields[yoast_wpseo_title]"]').length > 0;
	}

	function yoastQtxGetActiveLanguage() {
		if (window.qTranslateConfig && qTranslateConfig.activeLanguage) {
			return qTranslateConfig.activeLanguage;
		}

		return '';
	}

	function yoastQtxParseIfEncoded(value, lang) {
		if (!value || typeof value !== 'string') {
			return '';
		}

		if (!/(\[:[a-z]{2}\]|\{:[a-z]{2}\}|<!--:[a-z]{2}-->)/i.test(value)) {
			return value;
		}

		if (typeof window.qtranxj_split === 'function') {
			var parts = qtranxj_split(value);
			var activeLang = lang || yoastQtxGetActiveLanguage();

			if (activeLang && parts[activeLang]) {
				return parts[activeLang];
			}

			if (window.qTranslateConfig && qTranslateConfig.default_language && parts[qTranslateConfig.default_language]) {
				return parts[qTranslateConfig.default_language];
			}
		}

		return value;
	}

	function yoastQtxGetHiddenValues() {
		var lang = yoastQtxGetActiveLanguage();

		return {
			title: yoastQtxParseIfEncoded($('#yoast_wpseo_title').val() || '', lang),
			description: yoastQtxParseIfEncoded($('#yoast_wpseo_metadesc').val() || '', lang),
		};
	}

	function yoastQtxCanUseYoastStore() {
		return (
			window.wp &&
			wp.data &&
			wp.data.select('yoast-seo/editor') &&
			wp.data.dispatch('yoast-seo/editor') &&
			typeof wp.data.dispatch('yoast-seo/editor').updateData === 'function'
		);
	}

	function yoastQtxPushToYoastEditor(title, description) {
		if (!yoastQtxCanUseYoastStore()) {
			return false;
		}

		var dispatch = wp.data.dispatch('yoast-seo/editor');
		var data = {};

		if (typeof title === 'string') {
			data.title = title;
		}

		if (typeof description === 'string') {
			data.description = description;
		}

		if (!Object.keys(data).length) {
			return false;
		}

		dispatch.updateData(data);

		if (typeof dispatch.refreshSnippetEditor === 'function') {
			dispatch.refreshSnippetEditor();
		}

		return true;
	}

	function yoastQtxSyncFromHiddenFields() {
		if (!yoastQtxIsIntegrated()) {
			return;
		}

		var values = yoastQtxGetHiddenValues();
		yoastQtxPushToYoastEditor(values.title, values.description);
	}

	function yoastQtxSaveVisibleYoastToHiddenFields() {
		if (!yoastQtxIsIntegrated() || !yoastQtxCanUseYoastStore()) {
			return;
		}

		var snippet = wp.data.select('yoast-seo/editor').getSnippetEditorData();

		if (!snippet) {
			return;
		}

		var lang = yoastQtxGetActiveLanguage();
		var title = yoastQtxParseIfEncoded(snippet.title || '', lang);
		var description = yoastQtxParseIfEncoded(snippet.description || '', lang);

		$('#yoast_wpseo_title').val(title);
		$('#yoast_wpseo_metadesc').val(description);

		if (lang) {
			$("input[name='qtranslate-fields[yoast_wpseo_title][" + lang + "]']").val(title);
			$("input[name='qtranslate-fields[yoast_wpseo_metadesc][" + lang + "]']").val(description);
		}
	}

	function yoastQtxRegisterQTranslateHooks() {
		if (!window.qTranslateConfig) {
			return;
		}

		var cfg = qTranslateConfig;

		if (!cfg.onTabSwitchFunctionsSave) {
			cfg.onTabSwitchFunctionsSave = [];
		}

		if (!cfg.onTabSwitchFunctionsLoad) {
			cfg.onTabSwitchFunctionsLoad = [];
		}

		cfg.onTabSwitchFunctionsSave.unshift(function () {
			yoastQtxSaveVisibleYoastToHiddenFields();
			return true;
		});

		cfg.onTabSwitchFunctionsLoad.push(function () {
			setTimeout(yoastQtxSyncFromHiddenFields, 0);
		});
	}

	function yoastQtxWaitForYoast(callback, attempts) {
		attempts = attempts || 0;

		if (yoastQtxCanUseYoastStore()) {
			callback();
			return;
		}

		if (attempts < 50) {
			setTimeout(function () {
				yoastQtxWaitForYoast(callback, attempts + 1);
			}, 200);
		}
	}

	$(document).ready(function () {
		if (!yoastQtxIsIntegrated()) {
			return;
		}

		yoastQtxRegisterQTranslateHooks();

		yoastQtxWaitForYoast(function () {
			yoastQtxSyncFromHiddenFields();
		});

		$(document).on('click', '.qtranxs-lang-switch-wrap li', function () {
			yoastQtxWaitForYoast(function () {
				setTimeout(yoastQtxSyncFromHiddenFields, 50);
			});
		});
	});
})(jQuery);
