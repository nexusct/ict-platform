/**
 * AI Assistant JavaScript
 *
 * Provides AI-powered assistance on admin pages.
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const AIAssistant = {
		/**
		 * Initialize.
		 */
		init: function() {
			if (!this.isEnabled()) {
				return;
			}

			this.renderAssistant();
			this.bindEvents();
		},

		/**
		 * Check if AI features are enabled.
		 */
		isEnabled: function() {
			return typeof ictAIAssistant !== 'undefined' && ictAIAssistant.features;
		},

		/**
		 * Render AI assistant widget.
		 */
		renderAssistant: function() {
			const $assistant = $('<div id="ict-ai-assistant" class="ict-ai-assistant">' +
				'<div class="ai-assistant-toggle">' +
					'<span class="dashicons dashicons-superhero"></span>' +
					'<span class="ai-label">AI Assistant</span>' +
				'</div>' +
				'<div class="ai-assistant-panel" style="display:none;">' +
					'<div class="ai-assistant-header">' +
						'<h3>AI Assistant</h3>' +
						'<button class="ai-close">&times;</button>' +
					'</div>' +
					'<div class="ai-assistant-content">' +
						this.renderFeatures() +
					'</div>' +
				'</div>' +
			'</div>');

			$('body').append($assistant);
		},

		/**
		 * Render available features.
		 */
		renderFeatures: function() {
			let html = '';

			// Project analysis
			if (ictAIAssistant.features.project_analysis && this.hasProjectId()) {
				html += '<div class="ai-feature">' +
					'<button class="button button-primary ai-analyze-project" data-project-id="' + this.getProjectId() + '">' +
						'<span class="dashicons dashicons-chart-line"></span> Analyze This Project' +
					'</button>' +
				'</div>';
			}

			// Quote analysis
			if (ictAIAssistant.features.quote_analysis && this.hasQuoteData()) {
				html += '<div class="ai-feature">' +
					'<button class="button button-primary ai-analyze-quote">' +
						'<span class="dashicons dashicons-money"></span> Analyze Quote' +
					'</button>' +
				'</div>';
			}

			// Time entry suggestions
			if (ictAIAssistant.features.time_suggestions && this.canSuggestTime()) {
				html += '<div class="ai-feature">' +
					'<button class="button button-primary ai-suggest-time">' +
						'<span class="dashicons dashicons-clock"></span> Get Time Entry Suggestions' +
					'</button>' +
				'</div>';
			}

			// Inventory analysis
			if (ictAIAssistant.features.inventory_analysis) {
				html += '<div class="ai-feature">' +
					'<button class="button button-primary ai-analyze-inventory">' +
						'<span class="dashicons dashicons-archive"></span> Analyze Inventory Needs' +
					'</button>' +
				'</div>';
			}

			// Report summary
			if (ictAIAssistant.features.report_summaries && this.hasReportData()) {
				html += '<div class="ai-feature">' +
					'<button class="button button-primary ai-generate-summary">' +
						'<span class="dashicons dashicons-media-document"></span> Generate Summary' +
					'</button>' +
				'</div>';
			}

			// Results area
			html += '<div class="ai-results"></div>';

			return html || '<p>No AI features available on this page.</p>';
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			// Toggle panel
			$(document).on('click', '.ai-assistant-toggle', this.togglePanel.bind(this));
			$(document).on('click', '.ai-close', this.closePanel.bind(this));

			// Feature buttons
			$(document).on('click', '.ai-analyze-project', this.analyzeProject.bind(this));
			$(document).on('click', '.ai-analyze-quote', this.analyzeQuote.bind(this));
			$(document).on('click', '.ai-suggest-time', this.suggestTime.bind(this));
			$(document).on('click', '.ai-analyze-inventory', this.analyzeInventory.bind(this));
			$(document).on('click', '.ai-generate-summary', this.generateSummary.bind(this));
		},

		/**
		 * Toggle assistant panel.
		 */
		togglePanel: function(e) {
			e.preventDefault();
			$('.ai-assistant-panel').slideToggle(300);
			$('#ict-ai-assistant').toggleClass('active');
		},

		/**
		 * Close assistant panel.
		 */
		closePanel: function(e) {
			e.preventDefault();
			$('.ai-assistant-panel').slideUp(300);
			$('#ict-ai-assistant').removeClass('active');
		},

		/**
		 * Analyze project.
		 */
		analyzeProject: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const projectId = $button.data('project-id');
			const $results = $('.ai-results');

			if (!projectId) {
				return;
			}

			const originalText = $button.html();

			$button.html('<span class="dashicons dashicons-update spin"></span> Analyzing...').prop('disabled', true);
			$results.html('<div class="spinner is-active"></div>');

			$.ajax({
				url: ictAIAssistant.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ict_ai_analyze_project',
					nonce: ictAIAssistant.nonce,
					project_id: projectId
				},
				success: function(response) {
					if (response.success && response.data.analysis) {
						$results.html(
							'<div class="ai-analysis-result">' +
								'<h4>Project Analysis</h4>' +
								'<div class="analysis-content">' +
									response.data.analysis.replace(/\n/g, '<br>') +
								'</div>' +
								'<div class="analysis-meta">' +
									'<small>Tokens used: ' + (response.data.tokens.total_tokens || 0) + '</small>' +
								'</div>' +
							'</div>'
						);
					} else {
						$results.html(
							'<div class="notice notice-error"><p>' +
								(response.data.message || 'Analysis failed') +
							'</p></div>'
						);
					}
				},
				error: function() {
					$results.html('<div class="notice notice-error"><p>An error occurred</p></div>');
				},
				complete: function() {
					$button.html(originalText).prop('disabled', false);
				}
			});
		},

		/**
		 * Analyze quote.
		 */
		analyzeQuote: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const $results = $('.ai-results');
			const quoteData = this.extractQuoteData();

			if (!quoteData) {
				$results.html('<div class="notice notice-warning"><p>No quote data found</p></div>');
				return;
			}

			const originalText = $button.html();

			$button.html('<span class="dashicons dashicons-update spin"></span> Analyzing...').prop('disabled', true);
			$results.html('<div class="spinner is-active"></div>');

			$.ajax({
				url: ictAIAssistant.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ict_ai_analyze_quote',
					nonce: ictAIAssistant.nonce,
					quote_data: quoteData
				},
				success: function(response) {
					if (response.success && response.data.analysis) {
						$results.html(
							'<div class="ai-analysis-result">' +
								'<h4>Quote Analysis</h4>' +
								'<div class="analysis-content">' +
									response.data.analysis.replace(/\n/g, '<br>') +
								'</div>' +
							'</div>'
						);
					} else {
						$results.html(
							'<div class="notice notice-error"><p>' +
								(response.data.message || 'Analysis failed') +
							'</p></div>'
						);
					}
				},
				error: function() {
					$results.html('<div class="notice notice-error"><p>An error occurred</p></div>');
				},
				complete: function() {
					$button.html(originalText).prop('disabled', false);
				}
			});
		},

		/**
		 * Suggest time entry descriptions.
		 */
		suggestTime: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const $results = $('.ai-results');
			const projectId = this.getProjectId();
			const taskType = $('#task_type').val() || '';

			if (!projectId) {
				return;
			}

			const originalText = $button.html();

			$button.html('<span class="dashicons dashicons-update spin"></span> Generating...').prop('disabled', true);
			$results.html('<div class="spinner is-active"></div>');

			$.ajax({
				url: ictAIAssistant.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ict_ai_suggest_time_entry',
					nonce: ictAIAssistant.nonce,
					project_id: projectId,
					task_type: taskType
				},
				success: function(response) {
					if (response.success && response.data.suggestions) {
						let html = '<div class="ai-suggestions-result">' +
							'<h4>Suggested Descriptions</h4>' +
							'<ul class="ai-suggestion-list">';

						response.data.suggestions.forEach(function(suggestion) {
							html += '<li class="ai-suggestion-item">' +
								'<span class="suggestion-text">' + suggestion + '</span>' +
								'<button class="button button-small use-suggestion" data-suggestion="' +
									suggestion.replace(/"/g, '&quot;') + '">Use This</button>' +
							'</li>';
						});

						html += '</ul></div>';
						$results.html(html);

						// Bind use suggestion button
						$('.use-suggestion').on('click', function(ev) {
							ev.preventDefault();
							const suggestion = $(this).data('suggestion');
							$('#description, [name="description"]').val(suggestion);
							AIAssistant.closePanel();
						});
					} else {
						$results.html(
							'<div class="notice notice-error"><p>' +
								(response.data.message || 'Generation failed') +
							'</p></div>'
						);
					}
				},
				error: function() {
					$results.html('<div class="notice notice-error"><p>An error occurred</p></div>');
				},
				complete: function() {
					$button.html(originalText).prop('disabled', false);
				}
			});
		},

		/**
		 * Analyze inventory.
		 */
		analyzeInventory: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const $results = $('.ai-results');
			const projectIds = this.getUpcomingProjectIds();

			if (!projectIds.length) {
				$results.html('<div class="notice notice-warning"><p>No upcoming projects found</p></div>');
				return;
			}

			const originalText = $button.html();

			$button.html('<span class="dashicons dashicons-update spin"></span> Analyzing...').prop('disabled', true);
			$results.html('<div class="spinner is-active"></div>');

			$.ajax({
				url: ictAIAssistant.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ict_ai_inventory_analysis',
					nonce: ictAIAssistant.nonce,
					project_ids: projectIds
				},
				success: function(response) {
					if (response.success && response.data.recommendations) {
						$results.html(
							'<div class="ai-analysis-result">' +
								'<h4>Inventory Recommendations</h4>' +
								'<div class="analysis-content">' +
									response.data.recommendations.replace(/\n/g, '<br>') +
								'</div>' +
								'<div class="analysis-meta">' +
									'<small>Based on ' + response.data.project_count + ' projects</small>' +
								'</div>' +
							'</div>'
						);
					} else {
						$results.html(
							'<div class="notice notice-error"><p>' +
								(response.data.message || 'Analysis failed') +
							'</p></div>'
						);
					}
				},
				error: function() {
					$results.html('<div class="notice notice-error"><p>An error occurred</p></div>');
				},
				complete: function() {
					$button.html(originalText).prop('disabled', false);
				}
			});
		},

		/**
		 * Generate report summary.
		 */
		generateSummary: function(e) {
			e.preventDefault();
			// Implementation depends on report page structure
			console.log('Generate summary feature - to be implemented based on report structure');
		},

		/**
		 * Helper: Check if project ID exists.
		 */
		hasProjectId: function() {
			return this.getProjectId() !== null;
		},

		/**
		 * Helper: Get project ID from page.
		 */
		getProjectId: function() {
			// Try multiple methods to find project ID
			const urlParams = new URLSearchParams(window.location.search);
			const projectId = urlParams.get('project_id') || urlParams.get('id');

			if (projectId) {
				return parseInt(projectId);
			}

			// Try to find in form fields
			const $projectField = $('[name="project_id"], #project_id');
			if ($projectField.length) {
				return parseInt($projectField.val());
			}

			return null;
		},

		/**
		 * Helper: Check if quote data exists.
		 */
		hasQuoteData: function() {
			return window.location.href.indexOf('ict-quotewerks') !== -1;
		},

		/**
		 * Helper: Extract quote data from page.
		 */
		extractQuoteData: function() {
			// This would extract quote data from the page
			// Implementation depends on QuoteWerks page structure
			return {
				sample: true
			};
		},

		/**
		 * Helper: Check if we can suggest time entries.
		 */
		canSuggestTime: function() {
			return window.location.href.indexOf('time-tracking') !== -1 || this.hasProjectId();
		},

		/**
		 * Helper: Check if report data exists.
		 */
		hasReportData: function() {
			return window.location.href.indexOf('ict-reports') !== -1;
		},

		/**
		 * Helper: Get upcoming project IDs.
		 */
		getUpcomingProjectIds: function() {
			// This would extract project IDs from the page
			// Implementation depends on page structure
			return [];
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		AIAssistant.init();
	});

})(jQuery);
