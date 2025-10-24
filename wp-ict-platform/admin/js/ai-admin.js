/**
 * AI Admin JavaScript
 *
 * Handles AI settings page interactions.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const AIAdmin = {
		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			// Test connection
			$('#test-ai-connection').on('click', this.testConnection.bind(this));

			// Demo: Generate description
			$('#demo-generate-description').on('click', this.demoGenerateDescription.bind(this));

			// Demo: Suggest time entry
			$('#demo-suggest-time').on('click', this.demoSuggestTime.bind(this));
		},

		/**
		 * Test OpenAI connection.
		 */
		testConnection: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const originalText = $button.text();

			$button
				.text(ictAI.strings.testing)
				.prop('disabled', true);

			$.ajax({
				url: ictAI.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ict_test_openai_connection',
					nonce: ictAI.nonce
				},
				success: function(response) {
					if (response.success) {
						AIAdmin.showNotice(
							response.data.message || ictAI.strings.success,
							'success'
						);
						$('.connection-status')
							.removeClass('not-configured unknown error')
							.addClass('connected');
						$('.connection-status .status-text').text('Connected');
					} else {
						AIAdmin.showNotice(
							response.data.message || ictAI.strings.error,
							'error'
						);
						$('.connection-status')
							.removeClass('not-configured unknown connected')
							.addClass('error');
						$('.connection-status .status-text').text('Connection Failed');
					}
				},
				error: function() {
					AIAdmin.showNotice(ictAI.strings.error, 'error');
					$('.connection-status')
						.removeClass('not-configured unknown connected')
						.addClass('error');
					$('.connection-status .status-text').text('Connection Failed');
				},
				complete: function() {
					$button
						.text(originalText)
						.prop('disabled', false);
				}
			});
		},

		/**
		 * Demo: Generate project description.
		 */
		demoGenerateDescription: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const $input = $('#demo-project-input');
			const $result = $('#demo-description-result');
			const details = $input.val().trim();

			if (!details) {
				AIAdmin.showNotice('Please enter project details', 'warning');
				return;
			}

			const originalText = $button.text();

			$button
				.text(ictAI.strings.generating)
				.prop('disabled', true);

			$result.html('<div class="spinner is-active" style="float:none;"></div>');

			$.ajax({
				url: ictAI.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ict_ai_generate_description',
					nonce: ictAI.nonce,
					details: details
				},
				success: function(response) {
					if (response.success) {
						$result.html(
							'<div class="ai-result-box">' +
							'<strong>Generated Description:</strong><br>' +
							response.data.description +
							'</div>'
						);
					} else {
						$result.html(
							'<div class="notice notice-error"><p>' +
							(response.data.message || 'Generation failed') +
							'</p></div>'
						);
					}
				},
				error: function() {
					$result.html(
						'<div class="notice notice-error"><p>An error occurred</p></div>'
					);
				},
				complete: function() {
					$button
						.text(originalText)
						.prop('disabled', false);
				}
			});
		},

		/**
		 * Demo: Suggest time entry descriptions.
		 */
		demoSuggestTime: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const $result = $('#demo-time-suggestions-result');
			const projectId = $('#demo-project-select').val();
			const taskType = $('#demo-task-type').val();

			if (!projectId) {
				AIAdmin.showNotice('Please select a project', 'warning');
				return;
			}

			const originalText = $button.text();

			$button
				.text(ictAI.strings.generating)
				.prop('disabled', true);

			$result.html('<div class="spinner is-active" style="float:none;"></div>');

			$.ajax({
				url: ictAI.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ict_ai_suggest_time_entry',
					nonce: ictAI.nonce,
					project_id: projectId,
					task_type: taskType
				},
				success: function(response) {
					if (response.success && response.data.suggestions) {
						let html = '<div class="ai-result-box"><strong>Suggestions:</strong><ul>';
						response.data.suggestions.forEach(function(suggestion) {
							html += '<li>' + suggestion + '</li>';
						});
						html += '</ul></div>';
						$result.html(html);
					} else {
						$result.html(
							'<div class="notice notice-error"><p>' +
							(response.data.message || 'Generation failed') +
							'</p></div>'
						);
					}
				},
				error: function() {
					$result.html(
						'<div class="notice notice-error"><p>An error occurred</p></div>'
					);
				},
				complete: function() {
					$button
						.text(originalText)
						.prop('disabled', false);
				}
			});
		},

		/**
		 * Show admin notice.
		 */
		showNotice: function(message, type, duration) {
			type = type || 'info';
			duration = duration || 5000;

			// Remove existing notices
			$('.ict-admin-notice').remove();

			const $notice = $('<div>')
				.addClass('notice notice-' + type + ' is-dismissible ict-admin-notice')
				.html('<p>' + message + '</p>');

			$('.wrap').prepend($notice);

			// Auto-dismiss
			if (duration > 0) {
				setTimeout(function() {
					$notice.fadeOut(300, function() {
						$(this).remove();
					});
				}, duration);
			}

			// Make dismissible
			$notice.on('click', '.notice-dismiss', function() {
				$notice.fadeOut(300, function() {
					$(this).remove();
				});
			});

			// Scroll to notice
			$('html, body').animate({
				scrollTop: $notice.offset().top - 50
			}, 300);
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		AIAdmin.init();
	});

})(jQuery);
