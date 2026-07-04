(function ($) {
	'use strict';

	$(function () {
		var frame;

		function showNotices(html) {
			var $notices = $('.adminvoro-notices');

			if (!$notices.length) {
				return;
			}

			$notices.html(html || '');
		}

		function toggleCustomBlockUrlField() {
			var $select = $('select[name="adminvoro_options[login_block_action]"]');
			var $field = $('.adminvoro-custom-block-url-field');
			var isCustom = $select.val() === 'custom_url';

			$field.toggleClass('is-hidden', !isCustom);
			$field.find('input').prop('required', isCustom);
		}

		function setButtonState($form, isSaving) {
			var $buttons = $form.find(':submit');
			var $primaryButton = $form.data('clicked-submit') ? $($form.data('clicked-submit')) : $buttons.first();

			$buttons.prop('disabled', isSaving);

			if (!$primaryButton.length) {
				return;
			}

			if (isSaving) {
				if (typeof $primaryButton.data('original-label') === 'undefined') {
					$primaryButton.data('original-label', $primaryButton.is('input') ? $primaryButton.val() : $primaryButton.text());
				}

				if ($primaryButton.is('input')) {
					$primaryButton.val(adminvoroSettingsAdmin.saving);
				} else {
					$primaryButton.text(adminvoroSettingsAdmin.saving);
				}
			} else if (typeof $primaryButton.data('original-label') !== 'undefined') {
				if ($primaryButton.is('input')) {
					$primaryButton.val($primaryButton.data('original-label'));
				} else {
					$primaryButton.text($primaryButton.data('original-label'));
				}
			}
		}

		function getSerializedFormData($form) {
			var data = $form.serializeArray();
			var clickedSubmit = $form.data('clicked-submit');

			if (clickedSubmit && clickedSubmit.name && !clickedSubmit.disabled) {
				data.push({
					name: clickedSubmit.name,
					value: clickedSubmit.value || '1'
				});
			}

			return $.param(data);
		}

		function syncOptions(options, responseData) {
			if (!options) {
				return;
			}

			$.each(options, function (key, value) {
				var $fields = $('[name="adminvoro_options[' + key + ']"]');
				var colorDefaults = {
					login_background_color: '#f0f0f1',
					login_text_color: '#3c434a',
					login_link_color: '#2271b1'
				};

				if (!$fields.length) {
					return;
				}

				if ($fields.first().is(':checkbox')) {
					$fields.prop('checked', value === true || value === 1 || value === '1');
					return;
				}

				if ($fields.first().is('[type="color"]') && !value && colorDefaults[key]) {
					value = colorDefaults[key];
				}

				$fields.val(value);
			});

			if (responseData && typeof responseData.currentLoginHtml !== 'undefined') {
				$('.adminvoro-current-login-wrap').html(responseData.currentLoginHtml);
			}

			if (responseData && typeof responseData.logoUrl !== 'undefined') {
				if (responseData.logoUrl) {
					$('.adminvoro-logo-preview')
						.removeClass('is-empty')
						.empty()
						.append($('<img />', {
							src: responseData.logoUrl,
							alt: ''
						}));
				} else {
					$('.adminvoro-logo-preview')
						.addClass('is-empty')
						.html('<span>' + adminvoroSettingsAdmin.noLogo + '</span>');
				}
			}

			toggleCustomBlockUrlField();
		}

		$(document).on('click', '.adminvoro-form :submit', function () {
			$(this).closest('form').data('clicked-submit', this);
		});

		$('.adminvoro-options-form').on('submit', function (event) {
			var $form = $(this);
			var data;

			event.preventDefault();

			data = getSerializedFormData($form) + '&action=adminvoro_save_options&nonce=' + encodeURIComponent(adminvoroSettingsAdmin.nonce);
			setButtonState($form, true);

			$.post(adminvoroSettingsAdmin.ajaxUrl, data)
				.done(function (response) {
					if (!response || !response.success) {
						showNotices(response && response.data && response.data.notices ? response.data.notices : '<div class="notice notice-error adminvoro-notice"><p>' + adminvoroSettingsAdmin.saveFailed + '</p></div>');
						return;
					}

					showNotices(response.data.notices);
					syncOptions(response.data.options, response.data);
				})
				.fail(function (xhr) {
					var notices = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.notices ? xhr.responseJSON.data.notices : '<div class="notice notice-error adminvoro-notice"><p>' + adminvoroSettingsAdmin.ajaxError + '</p></div>';
					showNotices(notices);
				})
				.always(function () {
					setButtonState($form, false);
					$form.removeData('clicked-submit');
				});
		});

		$('.adminvoro-redirects-form').on('submit', function (event) {
			var $form = $(this);
			var data;

			event.preventDefault();

			data = getSerializedFormData($form) + '&action=adminvoro_save_redirects&nonce=' + encodeURIComponent(adminvoroSettingsAdmin.nonce);
			setButtonState($form, true);

			$.post(adminvoroSettingsAdmin.ajaxUrl, data)
				.done(function (response) {
					if (!response || !response.success) {
						showNotices(response && response.data && response.data.notices ? response.data.notices : '<div class="notice notice-error adminvoro-notice"><p>' + adminvoroSettingsAdmin.saveFailed + '</p></div>');
						return;
					}

					showNotices(response.data.notices);
				})
				.fail(function (xhr) {
					var notices = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.notices ? xhr.responseJSON.data.notices : '<div class="notice notice-error adminvoro-notice"><p>' + adminvoroSettingsAdmin.ajaxError + '</p></div>';
					showNotices(notices);
				})
				.always(function () {
					setButtonState($form, false);
					$form.removeData('clicked-submit');
				});
		});

		$(document).on('change', 'select[name="adminvoro_options[login_block_action]"]', toggleCustomBlockUrlField);
		toggleCustomBlockUrlField();

		$('.adminvoro-upload-logo').on('click', function (event) {
			event.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: adminvoroSettingsAdmin.chooseLogo,
				button: {
					text: adminvoroSettingsAdmin.useLogo
				},
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var imageUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

				$('.adminvoro-logo-id').val(attachment.id);
				$('.adminvoro-logo-preview')
					.removeClass('is-empty')
					.empty()
					.append($('<img />', {
						src: imageUrl,
						alt: ''
					}));
			});

			frame.open();
		});

		$('.adminvoro-remove-logo').on('click', function (event) {
			event.preventDefault();
			$('.adminvoro-logo-id').val('');
			$('.adminvoro-logo-preview')
				.addClass('is-empty')
				.html('<span>' + adminvoroSettingsAdmin.noLogo + '</span>');
		});

		$('.adminvoro-add-redirect').on('click', function (event) {
			event.preventDefault();

			var templateEl = document.getElementById('adminvoro-redirect-row-template');
			var template = templateEl ? templateEl.innerHTML : '';
			var index = Date.now();

			$('.adminvoro-redirects-table tbody').append(template.replace(/__index__/g, index));
		});

		$(document).on('click', '.adminvoro-delete-row', function (event) {
			event.preventDefault();

			var $row = $(this).closest('tr');
			$row.find('.adminvoro-delete-value').val('1');
			$row.addClass('adminvoro-redirect-row-deleted');
		});
	});
})(jQuery);
