/**
 * ICT Platform Setup Wizard JavaScript
 *
 * @package ICT_Platform
 * @since   1.1.0
 */

(function ($) {
	'use strict';

	/**
	 * Wizard Controller
	 */
	const ICTWizard = {
		/**
		 * Initialize the wizard
		 */
		init: function () {
			this.bindEvents();
			this.initToggles();
			this.initTabs();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			// Save and continue
			$( document ).on( 'click', '.wizard-save-next', this.saveAndContinue.bind( this ) );

			// Skip step
			$( document ).on( 'click', '.wizard-skip', this.skipStep.bind( this ) );

			// Test connection buttons
			$( document ).on( 'click', '.test-connection-btn', this.testConnection.bind( this ) );

			// AI suggestion buttons
			$( document ).on( 'click', '.ai-suggest-btn', this.getAISuggestion.bind( this ) );

			// AI chat input
			$( document ).on( 'click', '#ai-ask-btn', this.askAI.bind( this ) );
			$( document ).on(
				'keypress',
				'#ai-question-input',
				function (e) {
					if (e.which === 13) {
						this.askAI( e );
					}
				}.bind( this )
			);

			// Toggle integration fields
			$( document ).on( 'change', '.integration-card .toggle-switch input', this.toggleIntegrationFields );
			$( document ).on( 'change', 'input[name="teams_enabled"]', this.toggleTeamsFields );

			// Generate VAPID keys
			$( document ).on( 'click', '#generate-vapid-keys', this.generateVAPIDKeys.bind( this ) );
		},

		/**
		 * Initialize toggle switches
		 */
		initToggles: function () {
			$( '.integration-card .toggle-switch input' ).each(
				function () {
					const $card   = $( this ).closest( '.integration-card' );
					const $fields = $card.find( '.integration-fields' );

					if (this.checked) {
						$fields.show();
					} else {
						$fields.hide();
					}
				}
			);
		},

		/**
		 * Initialize tabs
		 */
		initTabs: function () {
			$( document ).on(
				'click',
				'.tab-btn',
				function (e) {
					const $btn  = $( this );
					const tabId = $btn.data( 'tab' );

					// Update buttons
					$btn.siblings( '.tab-btn' ).removeClass( 'active' );
					$btn.addClass( 'active' );

					// Update content
					$btn.closest( '.ict-wizard-instructions' )
					.find( '.tab-content' ).removeClass( 'active' )
					.filter( '#tab-' + tabId ).addClass( 'active' );
				}
			);
		},

		/**
		 * Toggle integration fields visibility
		 */
		toggleIntegrationFields: function () {
			const $card   = $( this ).closest( '.integration-card' );
			const $fields = $card.find( '.integration-fields' );

			if (this.checked) {
				$fields.slideDown( 200 );
				$card.addClass( 'connected' );
			} else {
				$fields.slideUp( 200 );
				$card.removeClass( 'connected' );
			}
		},

		/**
		 * Toggle Teams-specific fields
		 */
		toggleTeamsFields: function () {
			const $fields = $( this ).closest( 'form' ).find( '.teams-fields' );

			if (this.checked) {
				$fields.slideDown( 200 );
			} else {
				$fields.slideUp( 200 );
			}
		},

		/**
		 * Save current step and continue
		 */
		saveAndContinue: function (e) {
			e.preventDefault();

			const $btn     = $( e.currentTarget );
			const nextStep = $btn.data( 'next-step' );
			const $form    = $btn.closest( '.ict-wizard-wrap' ).find( 'form' );

			// Get form data
			const formData = this.getFormData( $form );

			// Show loading state
			const originalText = $btn.html();
			$btn.html( '<span class="loading-spinner"></span> ' + ictWizard.strings.saving );
			$btn.prop( 'disabled', true );

			// Save via AJAX
			$.ajax(
				{
					url: ictWizard.ajaxUrl,
					type: 'POST',
					data: {
						action: 'ict_wizard_save_step',
						nonce: ictWizard.nonce,
						step: this.getCurrentStep(),
						data: formData
					},
					success: function (response) {
						if (response.success) {
							// Redirect to next step
							window.location.href = this.addStepParam( window.location.href, nextStep );
						} else {
							this.showError( response.data.message );
							$btn.html( originalText );
							$btn.prop( 'disabled', false );
						}
					}.bind( this ),
					error: function () {
						this.showError( ictWizard.strings.error );
						$btn.html( originalText );
						$btn.prop( 'disabled', false );
					}.bind( this )
				}
			);
		},

		/**
		 * Skip current step
		 */
		skipStep: function (e) {
			e.preventDefault();

			if ( ! confirm( ictWizard.strings.confirmSkip )) {
				return;
			}

			const $btn     = $( e.currentTarget );
			const nextStep = $btn.data( 'next-step' );

			$.ajax(
				{
					url: ictWizard.ajaxUrl,
					type: 'POST',
					data: {
						action: 'ict_wizard_skip_step',
						nonce: ictWizard.nonce,
						next_step: nextStep
					},
					success: function (response) {
						if (response.success) {
							window.location.href = this.addStepParam( window.location.href, nextStep );
						}
					}.bind( this )
				}
			);
		},

		/**
		 * Test connection for a service
		 */
		testConnection: function (e) {
			e.preventDefault();

			const $btn    = $( e.currentTarget );
			const service = $btn.data( 'service' );
			const $status = $btn.siblings( '.connection-status' );

			// Show loading
			$btn.prop( 'disabled', true );
			$status.removeClass( 'success error' ).addClass( 'loading' ).text( ictWizard.strings.testing );

			$.ajax(
				{
					url: ictWizard.ajaxUrl,
					type: 'POST',
					data: {
						action: 'ict_wizard_test_connection',
						nonce: ictWizard.nonce,
						service: service
					},
					success: function (response) {
						$btn.prop( 'disabled', false );

						if (response.success) {
							$status.removeClass( 'loading error' ).addClass( 'success' ).text( response.data.message );
						} else {
							$status.removeClass( 'loading success' ).addClass( 'error' ).text( response.data.message );
						}
					},
					error: function () {
						$btn.prop( 'disabled', false );
						$status.removeClass( 'loading success' ).addClass( 'error' ).text( ictWizard.strings.testFailed );
					}
				}
			);
		},

		/**
		 * Get AI suggestion for current context
		 */
		getAISuggestion: function (e) {
			e.preventDefault();

			const $btn    = $( e.currentTarget );
			const context = $btn.data( 'context' );
			const $result = $btn.siblings( '.ai-suggestion-result' );

			// Show loading
			const originalText = $btn.html();
			$btn.html( '<span class="loading-spinner"></span> ' + ictWizard.strings.aiThinking );
			$btn.prop( 'disabled', true );

			$.ajax(
				{
					url: ictWizard.ajaxUrl,
					type: 'POST',
					data: {
						action: 'ict_wizard_get_ai_recommendation',
						nonce: ictWizard.nonce,
						context: context
					},
					success: function (response) {
						$btn.html( originalText );
						$btn.prop( 'disabled', false );

						if (response.success) {
							// Parse markdown-style formatting
							let html = this.formatMarkdown( response.data.recommendation );
							$result.html( html ).addClass( 'visible' );
						} else {
							$result.html( '<p>' + response.data.message + '</p>' ).addClass( 'visible' );
						}
					}.bind( this ),
					error: function () {
						$btn.html( originalText );
						$btn.prop( 'disabled', false );
						$result.html( '<p>' + ictWizard.strings.error + '</p>' ).addClass( 'visible' );
					}
				}
			);
		},

		/**
		 * Ask AI a question
		 */
		askAI: function (e) {
			e.preventDefault();

			const $input    = $( '#ai-question-input' );
			const $btn      = $( '#ai-ask-btn' );
			const $response = $( '#ai-response' );
			const question  = $input.val().trim();

			if ( ! question) {
				return;
			}

			// Show loading
			$btn.prop( 'disabled', true );
			$response.html( '<span class="loading-spinner"></span> ' + ictWizard.strings.aiThinking ).addClass( 'visible' );

			$.ajax(
				{
					url: ictWizard.ajaxUrl,
					type: 'POST',
					data: {
						action: 'ict_wizard_ai_assist',
						nonce: ictWizard.nonce,
						question: question
					},
					success: function (response) {
						$btn.prop( 'disabled', false );
						$input.val( '' );

						if (response.success) {
							let html = this.formatMarkdown( response.data.answer );
							$response.html( html );
						} else {
							$response.html( '<p>' + response.data.message + '</p>' );
						}
					}.bind( this ),
					error: function () {
						$btn.prop( 'disabled', false );
						$response.html( '<p>' + ictWizard.strings.error + '</p>' );
					}
				}
			);
		},

		/**
		 * Generate VAPID keys
		 */
		generateVAPIDKeys: function (e) {
			e.preventDefault();

			const $btn        = $( e.currentTarget );
			const $publicKey  = $( 'input[name="push_vapid_public_key"]' );
			const $privateKey = $( 'input[name="push_vapid_private_key"]' );

			// Generate keys client-side (simplified - in production use server-side)
			// This is a placeholder - real VAPID keys should be generated server-side
			const timestamp = Date.now().toString( 36 );
			const randomStr = Math.random().toString( 36 ).substring( 2, 15 );

			$publicKey.val( 'BK' + timestamp + randomStr + randomStr );
			$privateKey.val( 'SK' + timestamp + randomStr );

			$btn.text( 'Keys Generated!' );
			setTimeout(
				function () {
					$btn.text( 'Generate New Keys' );
				},
				2000
			);
		},

		/**
		 * Get current step from URL
		 */
		getCurrentStep: function () {
			const urlParams = new URLSearchParams( window.location.search );
			return urlParams.get( 'step' ) || 'welcome';
		},

		/**
		 * Add step parameter to URL
		 */
		addStepParam: function (url, step) {
			const urlObj = new URL( url );
			urlObj.searchParams.set( 'step', step );
			return urlObj.toString();
		},

		/**
		 * Get form data as object
		 */
		getFormData: function ($form) {
			const data = {};

			if ( ! $form || ! $form.length) {
				return data;
			}

			// Get all inputs
			$form.find( 'input, select, textarea' ).each(
				function () {
					const $input = $( this );
					const name   = $input.attr( 'name' );

					if ( ! name) {
						return;
					}

					// Handle different input types
					if ($input.attr( 'type' ) === 'checkbox') {
						if (name.endsWith( '[]' )) {
							// Checkbox group
							const baseName = name.replace( '[]', '' );
							if ( ! data[baseName]) {
								data[baseName] = [];
							}
							if ($input.is( ':checked' )) {
								data[baseName].push( $input.val() );
							}
						} else {
							// Single checkbox
							data[name] = $input.is( ':checked' ) ? 1 : 0;
						}
					} else if ($input.attr( 'type' ) === 'radio') {
						if ($input.is( ':checked' )) {
							data[name] = $input.val();
						}
					} else {
						data[name] = $input.val();
					}
				}
			);

			return data;
		},

		/**
		 * Format markdown-style text to HTML
		 */
		formatMarkdown: function (text) {
			if ( ! text) {
				return '';
			}

			// Escape HTML
			let html = text
				.replace( /&/g, '&amp;' )
				.replace( /</g, '&lt;' )
				.replace( />/g, '&gt;' );

			// Bold
			html = html.replace( /\*\*(.*?)\*\*/g, '<strong>$1</strong>' );

			// Links
			html = html.replace( /\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>' );

			// Lists
			html = html.replace( /^- (.+)$/gm, '<li>$1</li>' );
			html = html.replace( /(<li>.*<\/li>)/s, '<ul>$1</ul>' );

			// Numbered lists
			html = html.replace( /^\d+\. (.+)$/gm, '<li>$1</li>' );

			// Code
			html = html.replace( /`([^`]+)`/g, '<code>$1</code>' );

			// Paragraphs
			html = html.split( '\n\n' ).map(
				function (p) {
					p = p.trim();
					if ( ! p) {
						return '';
					}
					if (p.startsWith( '<ul>' ) || p.startsWith( '<li>' ) || p.startsWith( '<strong>' )) {
						return p;
					}
					return '<p>' + p.replace( /\n/g, '<br>' ) + '</p>';
				}
			).join( '' );

			return html;
		},

		/**
		 * Show error message
		 */
		showError: function (message) {
			alert( message );
		}
	};

	/**
	 * Initialize on document ready
	 */
	$( document ).ready(
		function () {
			if ($( '.ict-wizard-wrap' ).length) {
				ICTWizard.init();
			}
		}
	);

})( jQuery );
