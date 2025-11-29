/**
 * File: assets/js/ssm-debug-toggle.js
 * کہاں پیسٹ کریں: فائل کے آخر میں
 * Part 1 — Core UI and AJAX logic
 */

(function ($) {
	'use strict';

	// ==============================
	// Utilities
	// ==============================

	/**
	 * سادہ (escapeHtml) فنکشن تاکہ اگر کہیں (HTML) ٹیکسٹ کی صورت میں ڈالنا ہو
	 * تو محفوظ طریقے سے ڈالا جا سکے۔
	 *
	 * @param {string} str
	 * @returns {string}
	 */
	function escapeHtml(str) {
		if (typeof str !== 'string') {
			return '';
		}
		return str
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	/**
	 * جنرل (AJAX) ہیلپر جو (Promise) ریٹرن کرتا ہے۔
	 *
	 * @param {string} action
	 * @param {object} data
	 * @returns {Promise<any>}
	 */
	function wpAjax(action, data) {
		if (!window.ssmDebugToggleData || !window.ssmDebugToggleData.ajaxUrl) {
			console.warn('ssm-debug-toggle: ssmDebugToggleData.ajaxUrl موجود نہیں ہے۔');
			return Promise.reject(new Error('ajaxUrl missing'));
		}

		var payload = $.extend(
			{},
			{
				action: action,
				nonce: window.ssmDebugToggleData.nonce || ''
			},
			data || {}
		);

		return new Promise(function (resolve, reject) {
			$.ajax({
				url: window.ssmDebugToggleData.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: payload
			})
				.done(function (response) {
					if (response && response.success) {
						resolve(response.data || {});
					} else {
						reject(response && response.data ? response.data : {});
					}
				})
				.fail(function (jqXHR, textStatus) {
					reject({
						message: (textStatus || 'AJAX error'),
						code: 'ajax_fail'
					});
				});
		});
	}

	// ==============================
	// UI Helpers
	// ==============================

	/**
	 * پیغام دکھانے کیلئے مددگار فنکشن۔
	 *
	 * @param {jQuery} $root
	 * @param {string} message
	 * @param {'success'|'error'|'info'} type
	 */
	function showMessage($root, message, type) {
		var $msg = $root.find('.ssm-debug-message');
		if (!$msg.length) {
			return;
		}

		var clsBase = 'ssm-debug-message';
		var clsType = type === 'success'
			? ' ssm-debug-message-success'
			: type === 'error'
				? ' ssm-debug-message-error'
				: ' ssm-debug-message-info';

		$msg
			.removeClass('ssm-debug-message-success ssm-debug-message-error ssm-debug-message-info')
			.addClass(clsBase + clsType)
			.text(message || '');
	}

	/**
	 * موجودہ (Debug) اسٹیٹ کے مطابق اسٹیٹس لیبل اپ ڈیٹ کریں۔
	 *
	 * @param {jQuery} $root
	 * @param {object} state
	 * @param {string|null} configPath
	 */
	function renderState($root, state, configPath) {
		state = state || {};
		var mode = state.mode || 'custom';

		var messages = (window.ssmDebugToggleData && window.ssmDebugToggleData.messages) || {};
		var statusText;

		if (mode === 'on') {
			statusText = messages.onLabel || 'Debug آن ہے';
		} else if (mode === 'off') {
			statusText = messages.offLabel || 'Debug آف ہے';
		} else {
			statusText = messages.customLabel || 'Debug کسٹم سیٹنگ پر ہے';
		}

		var $statusLabel = $root.find('.ssm-debug-status-label');
		var $statusPath = $root.find('.ssm-debug-status-path');

		if ($statusLabel.length) {
			$statusLabel.text(statusText);
		}

		if ($statusPath.length) {
			if (configPath) {
				$statusPath
					.text($statusPath.data('label-prefix') || '')
					.append(' ' + configPath);
			} else {
				$statusPath.text('');
			}
		}
	}

	/**
	 * بٹنوں کو (enabled/disabled) کریں اور (busy) اسٹیٹ سیٹ کریں۔
	 *
	 * @param {jQuery} $root
	 * @param {boolean} isBusy
	 */
	function setBusy($root, isBusy) {
		var $buttons = $root.find('.ssm-debug-btn-on, .ssm-debug-btn-off');

		$buttons.prop('disabled', !!isBusy);

		if (isBusy) {
			$root.addClass('ssm-debug-busy');
		} else {
			$root.removeClass('ssm-debug-busy');
		}
	}

	// ==============================
	// Init / Main Logic
	// ==============================

	/**
	 * مین انیشیالائزیشن فنکشن۔
	 */
	function initDebugToggle() {
		if (!window.ssmDebugToggleData) {
			console.warn('ssm-debug-toggle: global ssmDebugToggleData موجود نہیں، (wp_localize_script) چیک کریں۔');
			return;
		}

		var data = window.ssmDebugToggleData;
		var messages = data.messages || {};

		var $root = $('#ssm-debug-toggle-root');
		if (!$root.length) {
			// اگر ٹیمپلیٹ یا روٹ عنصر نہ ملے تو خاموشی سے باہر
			console.warn('ssm-debug-toggle: #ssm-debug-toggle-root نہیں ملا۔');
			return;
		}

		//Config path label prefix کو محفوظ رکھیں، تاکہ بعد میں ٹیکسٹ دوبارہ بنا سکیں۔
		var $statusPath = $root.find('.ssm-debug-status-path');
		if ($statusPath.length && !$statusPath.data('label-prefix')) {
			$statusPath.data('label-prefix', $statusPath.text());
		}

		// ابتدائی اسٹیٹ
		renderState($root, data.state || {}, data.configFound ? (data.state.configPath || null) : null);

		// اگر config ہی نہ ملے تو بٹن disable کر دیں۔
		if (!data.configFound) {
			setBusy($root, true);
			showMessage(
				$root,
				messages.unknownConfig || 'wp-config.php نہیں ملا، براہِ کرم سرور سیٹنگ چیک کریں۔',
				'error'
			);
			return;
		}

		// بٹن کلک ہینڈلر
		var $btnOn = $root.find('.ssm-debug-btn-on');
		var $btnOff = $root.find('.ssm-debug-btn-off');

		if (!$btnOn.length || !$btnOff.length) {
			console.warn('ssm-debug-toggle: بٹن نہیں ملے، مارک اپ چیک کریں۔');
			return;
		}

		$btnOn.on('click', function (e) {
			e.preventDefault();
			handleToggleClick($root, 'on', $btnOn);
		});

		$btnOff.on('click', function (e) {
			e.preventDefault();
			handleToggleClick($root, 'off', $btnOff);
		});
	}

	/**
	 * (Debug ON/OFF) بٹن کلک پر مکمل (AJAX) لاجک۔
	 *
	 * @param {jQuery} $root
	 * @param {'on'|'off'} mode
	 * @param {jQuery} $sourceBtn
	 */
	function handleToggleClick($root, mode, $sourceBtn) {
		var messages = (window.ssmDebugToggleData && window.ssmDebugToggleData.messages) || {};

		setBusy($root, true);

		showMessage(
			$root,
			messages.updating || 'برائے مہربانی انتظار کریں، سیٹنگ اپ ڈیٹ ہو رہی ہے…',
			'info'
		);

		wpAjax('ssm_debug_toggle_set_state', { mode: mode })
			.then(function (data) {
				var msg = (data && data.message) || messages.success || 'سیٹنگ کامیابی سے اپ ڈیٹ ہو گئی۔';

				renderState($root, data && data.state ? data.state : {}, data && data.configPath ? data.configPath : null);
				showMessage($root, msg, 'success');

				setBusy($root, false);

				// فوکس واپس اسی بٹن پر لوٹائیں تاکہ (keyboard users) کیلئے سہولت رہے۔
				if ($sourceBtn && $sourceBtn.length) {
					$sourceBtn.focus();
				}
			})
			.catch(function (err) {
				var msg = (err && err.message) || messages.genericError || 'کوئی مسئلہ پیش آ گیا، براہِ کرم دوبارہ کوشش کریں۔';

				// اگر مخصوص (no_permission) ایرر ہو تو وہی پیغام دکھائیں۔
				if (err && err.code === 'no_permission' && messages.noPermission) {
					msg = messages.noPermission;
				}

				showMessage($root, msg, 'error');
				setBusy($root, false);

				if (window.console && console.error) {
					console.error('ssm-debug-toggle AJAX error:', err);
				}
			});
	}

	// ==============================
	// Document Ready
	// ==============================

	$(document).ready(function () {
		initDebugToggle();
	});

})(jQuery);
