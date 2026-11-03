(function ($) {
	'use strict';

	function qwpseoGetActiveLanguage() {
		if (window.qTranslateConfig && qTranslateConfig.activeLanguage) {
			return qTranslateConfig.activeLanguage;
		}

		return '';
	}

	function qwpseoParseTitle(value, lang) {
		if (!value || typeof value !== 'string') {
			return '';
		}

		if (!/(\[:[a-z]{2}\]|\{:[a-z]{2}\}|<!--:[a-z]{2}-->)/i.test(value)) {
			return value;
		}

		if (typeof window.qtranxj_split === 'function') {
			var parts = qtranxj_split(value);
			var activeLang = lang || qwpseoGetActiveLanguage();

			if (activeLang && parts[activeLang]) {
				return parts[activeLang];
			}

			if (
				window.qTranslateConfig &&
				qTranslateConfig.default_language &&
				parts[qTranslateConfig.default_language]
			) {
				return parts[qTranslateConfig.default_language];
			}
		}

		if (window.qwpseoMedia && qwpseoMedia.parentTitleCurrent) {
			return qwpseoMedia.parentTitleCurrent;
		}

		return value;
	}

	function qwpseoUpdateUploadedToTitle(lang) {
		if (!window.qwpseoMedia || !qwpseoMedia.parentTitleRaw) {
			return false;
		}

		var $target = $('.misc-pub-uploadedto strong');

		if (!$target.length) {
			return false;
		}

		var title = qwpseoParseTitle(qwpseoMedia.parentTitleRaw, lang);

		if (title) {
			$target.text(title);
			return true;
		}

		return false;
	}

	function qwpseoRegisterQTranslateHooks() {
		if (!window.qTranslateConfig) {
			return;
		}

		var cfg = qTranslateConfig;

		if (!cfg.onTabSwitchFunctionsLoad) {
			cfg.onTabSwitchFunctionsLoad = [];
		}

		cfg.onTabSwitchFunctionsLoad.push(function (lang) {
			qwpseoUpdateUploadedToTitle(lang);
		});
	}

	$(document).ready(function () {
		if (!window.qwpseoMedia || !qwpseoMedia.parentTitleRaw) {
			return;
		}

		qwpseoRegisterQTranslateHooks();
		qwpseoUpdateUploadedToTitle();

		$(document).on('click', '.qtranxs-lang-switch-wrap li', function () {
			setTimeout(function () {
				qwpseoUpdateUploadedToTitle();
			}, 50);
		});
	});
})(jQuery);
