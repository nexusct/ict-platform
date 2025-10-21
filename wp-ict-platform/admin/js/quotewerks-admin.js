/**
 * QuoteWerks Admin JavaScript
 *
 * Handles QuoteWerks settings page interactions.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const QuoteWerksAdmin = {
		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
			this.initClipboard();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			// Test connection
			$('#test-connection').on('click', this.testConnection.bind(this));

			// Sync quotes now
			$('#sync-quotes-now').on('click', this.syncQuotes.bind(this));

			// Regenerate webhook secret
			$('#regenerate-webhook-secret').on('click', this.regenerateWebhookSecret.bind(this));

			// Add field mapping
			$('#add-field-mapping').on('click', this.addFieldMapping.bind(this));

			// Remove field mapping
			$(document).on('click', '.remove-mapping', this.removeFieldMapping.bind(this));

			// Copy buttons
			$(document).on('click', '.copy-webhook-url, .copy-webhook-secret', this.copyToClipboard.bind(this));
		},

		/**
		 * Test QuoteWerks connection.
		 */
		testConnection: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const originalText = $button.text();

			$button
				.text(ictQuoteWerks.strings.testing)
				.prop('disabled', true);

			$.ajax({
				url: ictQuoteWerks.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ict_test_quotewerks_connection',
					nonce: ictQuoteWerks.nonce
				},
				success: function(response) {
					if (response.success) {
						QuoteWerksAdmin.showNotice(
							ictQuoteWerks.strings.success,
							'success'
						);
						$('.connection-status')
							.removeClass('not-configured unknown error')
							.addClass('connected');
						$('.connection-status .status-text').text('Connected');
					} else {
						QuoteWerksAdmin.showNotice(
							response.data.message || ictQuoteWerks.strings.error,
							'error'
						);
						$('.connection-status')
							.removeClass('not-configured unknown connected')
							.addClass('error');
						$('.connection-status .status-text').text('Connection Failed');
					}
				},
				error: function(xhr) {
					QuoteWerksAdmin.showNotice(
						ictQuoteWerks.strings.error,
						'error'
					);
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
		 * Sync quotes manually.
		 */
		syncQuotes: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const originalHtml = $button.html();

			$button
				.html('<span class="dashicons dashicons-update spin"></span> ' + ictQuoteWerks.strings.syncing)
				.prop('disabled', true);

			$.ajax({
				url: ictQuoteWerks.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ict_sync_quotes_manual',
					nonce: ictQuoteWerks.nonce
				},
				success: function(response) {
					if (response.success) {
						QuoteWerksAdmin.showNotice(
							response.data.message || ictQuoteWerks.strings.syncSuccess,
							'success'
						);
						// Reload page to show updated stats
						setTimeout(function() {
							window.location.reload();
						}, 1500);
					} else {
						QuoteWerksAdmin.showNotice(
							response.data.message || ictQuoteWerks.strings.syncError,
							'error'
						);
					}
				},
				error: function(xhr) {
					QuoteWerksAdmin.showNotice(
						ictQuoteWerks.strings.syncError,
						'error'
					);
				},
				complete: function() {
					$button
						.html(originalHtml)
						.prop('disabled', false);
				}
			});
		},

		/**
		 * Regenerate webhook secret.
		 */
		regenerateWebhookSecret: function(e) {
			e.preventDefault();

			if (!confirm(ictQuoteWerks.strings.confirmRegenerate)) {
				return;
			}

			const $button = $(e.currentTarget);
			const originalText = $button.text();

			$button
				.text(ictQuoteWerks.strings.regenerating)
				.prop('disabled', true);

			$.ajax({
				url: ictQuoteWerks.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ict_regenerate_webhook_secret',
					nonce: ictQuoteWerks.nonce
				},
				success: function(response) {
					if (response.success) {
						QuoteWerksAdmin.showNotice(
							response.data.message || ictQuoteWerks.strings.regenerateSuccess,
							'success'
						);
						// Update the secret field
						$('.copy-webhook-secret').prev('input').val(response.data.secret);
						$('.copy-webhook-secret').data('clipboard', response.data.secret);
					} else {
						QuoteWerksAdmin.showNotice(
							response.data.message || ictQuoteWerks.strings.error,
							'error'
						);
					}
				},
				error: function(xhr) {
					QuoteWerksAdmin.showNotice(
						ictQuoteWerks.strings.error,
						'error'
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
		 * Add field mapping row.
		 */
		addFieldMapping: function(e) {
			e.preventDefault();

			const $tbody = $('#field-mappings');
			const $firstRow = $tbody.find('tr:first');
			const $newRow = $firstRow.clone();

			// Reset select values to first option
			$newRow.find('select').each(function() {
				$(this).prop('selectedIndex', 0);
			});

			$tbody.append($newRow);
		},

		/**
		 * Remove field mapping row.
		 */
		removeFieldMapping: function(e) {
			e.preventDefault();

			const $row = $(e.currentTarget).closest('tr');
			const $tbody = $row.closest('tbody');

			// Don't remove if it's the last row
			if ($tbody.find('tr').length <= 1) {
				QuoteWerksAdmin.showNotice(
					'At least one field mapping is required.',
					'error'
				);
				return;
			}

			$row.fadeOut(300, function() {
				$(this).remove();
			});
		},

		/**
		 * Copy to clipboard.
		 */
		copyToClipboard: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const text = $button.data('clipboard');

			// Create temporary textarea
			const $temp = $('<textarea>');
			$('body').append($temp);
			$temp.val(text).select();

			try {
				document.execCommand('copy');
				QuoteWerksAdmin.showNotice('Copied to clipboard!', 'success', 2000);

				// Visual feedback
				const originalText = $button.text();
				$button.text('Copied!');
				setTimeout(function() {
					$button.text(originalText);
				}, 2000);
			} catch (err) {
				QuoteWerksAdmin.showNotice('Failed to copy to clipboard', 'error');
			}

			$temp.remove();
		},

		/**
		 * Initialize clipboard for modern browsers.
		 */
		initClipboard: function() {
			if (navigator.clipboard) {
				// Modern clipboard API available
				$(document).on('click', '.copy-webhook-url, .copy-webhook-secret', function(e) {
					const text = $(this).data('clipboard');
					navigator.clipboard.writeText(text);
				});
			}
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
		QuoteWerksAdmin.init();
	});

})(jQuery);
