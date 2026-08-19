/**
 * Nectar GEO - Admin Dashboard Scripts
 * Handles AJAX requests, UI state changes, engine scans, and modals.
 */

(function(jQuery) {
	'use strict';

	jQuery(document).ready(function() {

		// ---- %s placeholder formatter for localized strings ----
		function formatString( str, value ) {
			return str.replace( '%s', value );
		}

		// ---- Inline notice helper (replaces alert()) ----
		function showNotice( msg, type ) {
			var $notice = jQuery('<div class="notice notice-' + ( type || 'error' ) + ' is-dismissible nectar-geo-inline-notice"><p>' + msg + '</p></div>');
			jQuery('.wrap h1').first().after( $notice);
			setTimeout(function() { $notice.fadeOut(400, function() { jQuery(this).remove(); }); }, 5000);
		}

		// Elements Cache
		var $dashboard = jQuery('#nectar-geo-dashboard');
		if (!$dashboard.length) return;

		var $tabs = $dashboard.find('.custom-tabs .nav-tab');
		var $panes = $dashboard.find('.tab-pane');
		
		var $runScanBtn = jQuery('#run-ai-scan-btn');
		var $autofixRobotsBtn = jQuery('#autofix-robots-btn');
		var $generateAitxtBtn = jQuery('#generate-aitxt-btn');
		
		var $modal = jQuery('#transcript-modal');
		var $modalTitle = jQuery('#modal-title');
		var $modalEngineBadge = jQuery('#modal-engine-badge');
		var $modalContent = jQuery('#modal-transcript-content');
		
		var engines = nectarGeoData.engines; // ['openai', 'gemini', 'perplexity', 'claude', 'siri']
		var i18n = nectarGeoData.i18n;
		var scanResults = {};

		/* ========================================================
		 * TAB NAVIGATION
		 * ======================================================== */
		$tabs.on('click', function(e) {
			e.preventDefault();
			var targetTab = jQuery(this).data('tab');
			
			// Update active tab class
			$tabs.removeClass('nav-tab-active');
			jQuery(this).addClass('nav-tab-active');
			
			// Show/hide panes
			$panes.removeClass('active-pane');
			jQuery('#' + targetTab).addClass('active-pane');
			
			// Save active tab in hash
			window.location.hash = targetTab;
		});

		// Go to Settings tab from shortcut link
		$dashboard.on('click', '.go-to-settings-tab', function(e) {
			e.preventDefault();
			$tabs.filter('[data-tab="tab-settings"]').trigger('click');
		});

		// Handle page load hash navigation
		if (window.location.hash) {
			var hash = window.location.hash.substring(1);
			var targetTab = $tabs.filter('[data-tab="' + hash + '"]');
			if (targetTab.length) {
				targetTab.trigger('click');
			}
		}

		/* ========================================================
		 * PROVIDER SWITCHER
		 * ======================================================== */
		jQuery('#api_provider').on('change', function() {
			var selected = jQuery(this).val();
			// Hide all provider rows first
			jQuery('.provider-key-row, .provider-model-row').hide();
			// Show rows matching selected provider
			jQuery('.provider-row-' + selected).show();
		});

		/* ========================================================
		 * CONNECTION TESTER (per-provider)
		 * ======================================================== */
		$dashboard.on('click', '.test-conn-btn', function(e) {
			e.preventDefault();
			var $btn      = jQuery(this);
			var provider  = $btn.data('provider');
			var apiKey    = jQuery('#' + provider + '_key').val().trim();
			var model     = jQuery('#' + provider + '_model').val();
			var $feedback = jQuery('.test-conn-feedback-' + provider);

			$feedback.removeClass('success error').text('');

			if (!apiKey) {
				$feedback.addClass('error').text(i18n.enterApiKeyFirst);
				return;
			}

			$btn.prop('disabled', true).text(i18n.testing);

			jQuery.ajax({
				url: nectarGeoData.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'nectar_geo_test_connection',
					nonce: nectarGeoData.nonce,
					api_key: apiKey,
					provider: provider,
					model: model || ''
				},
				success: function(response) {
					if (response.success) {
						$feedback.addClass('success').text(response.data.message);
						showNotice(response.data.message, 'success');
					} else {
						$feedback.addClass('error').text(response.data.message);
						showNotice(response.data.message, 'error');
					}
				},
				error: function() {
					$feedback.addClass('error').text(i18n.networkError);
					showNotice(i18n.networkError, 'error');
				},
				complete: function() {
					$btn.prop('disabled', false).text(i18n.testConnection);
				}
			});
		});

		/* ========================================================
		 * AUTO-FIX ROBOTS.TXT
		 * ======================================================== */
		$autofixRobotsBtn.on('click', function(e) {
			e.preventDefault();
			$autofixRobotsBtn.prop('disabled', true).text(i18n.fixing);

			jQuery.ajax({
				url: nectarGeoData.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'nectar_geo_autofix_robots',
					nonce: nectarGeoData.nonce
				},
				success: function(response) {
					if (response.success) {
						jQuery('#robots-warning-banner').fadeOut(300, function() {
							jQuery(this).addClass('hide-alert').removeClass('show-alert');
						});
					} else {
						showNotice(formatString(i18n.errorPrefix, response.data.message));
						$autofixRobotsBtn.prop('disabled', false).text(i18n.autofixBlocker);
					}
				},
				error: function() {
					showNotice(i18n.robotsFixFailed);
					$autofixRobotsBtn.prop('disabled', false).text(i18n.autofixBlocker);
				}
			});
		});

		/* ========================================================
		 * GENERATE AI.TXT
		 * ======================================================== */
		$generateAitxtBtn.on('click', function(e) {
			e.preventDefault();
			var $feedback = jQuery('#aitxt-feedback');
			$feedback.removeClass('success error').text('');
			$generateAitxtBtn.prop('disabled', true).text(i18n.generating);

			jQuery.ajax({
				url: nectarGeoData.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'nectar_geo_generate_aitxt',
					nonce: nectarGeoData.nonce
				},
				success: function(response) {
					if (response.success) {
						$feedback.text(response.data.message);
					} else {
						$feedback.addClass('error').text(response.data.message);
					}
				},
				error: function() {
					$feedback.addClass('error').text(i18n.generateFailed);
				},
				complete: function() {
					$generateAitxtBtn.prop('disabled', false).text(i18n.generateAitxt);
				}
			});
		});

		/* ========================================================
		 * CORE SCAN RUNNER (SEQUENTIAL AJAX QUEUE)
		 * ======================================================== */
		$runScanBtn.on('click', function(e) {
			e.preventDefault();
			
			// Disable button during execution
			$runScanBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + i18n.scanning);
			
			// Reset indicators and score display
			jQuery('#total-mentions-ratio').text('0');
			setRadialScore(0);

			// Put all engine cards into scanning state
			jQuery('.engine-card').each(function() {
				var card = jQuery(this);
				card.removeClass('status-mentioned status-missing not-scanned')
					.addClass('scanning')
					.attr('data-has-data', 'no');
				
				card.find('.engine-status-tag').html('<span class="tag tag-gray"><span class="pulse-dot"></span> ' + i18n.scanning + '</span>');
				card.find('.transcript-preview').text(formatString(i18n.queryingModelVia, nectarGeoData.active_provider_label));
				card.find('.view-transcript-btn').prop('disabled', true);
				card.find('.hidden-transcript').text('');
			});

			scanResults = {};
			runEngineScanQueue(0);
		});

		function runEngineScanQueue(index) {
			if (index >= engines.length) {
				// Scan sequence complete, now save results and compute checklist
				saveScanResultsAndReload();
				return;
			}

			var engineId = engines[index];
			var card = jQuery('.engine-card[data-engine="' + engineId + '"]');

			jQuery.ajax({
				url: nectarGeoData.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'nectar_geo_run_engine_scan',
					nonce: nectarGeoData.nonce,
					engine_id: engineId
				},
				success: function(response) {
					if (response.success) {
						var data = response.data;
						scanResults[engineId] = {
							mentioned: data.mentioned,
							transcript: data.transcript
						};
						updateEngineCardUI(card, data.mentioned, data.transcript);
					} else {
						// Record failure gracefully
						scanResults[engineId] = {
							mentioned: false,
							transcript: formatString(i18n.scanFailedPrefix, response.data.message)
						};
						updateEngineCardUI(card, false, scanResults[engineId].transcript, true);
					}
				},
				error: function() {
					scanResults[engineId] = {
						mentioned: false,
						transcript: i18n.scanTimedOut
					};
					updateEngineCardUI(card, false, scanResults[engineId].transcript, true);
				},
				complete: function() {
					// Update running stats count
					var mentions = 0;
					Object.keys(scanResults).forEach(function(key) {
						if (scanResults[key].mentioned) mentions++;
					});
					jQuery('#total-mentions-ratio').text(mentions);

					// Recurse to next engine
					runEngineScanQueue(index + 1);
				}
			});
		}

		function updateEngineCardUI(card, mentioned, transcript, isError) {
			card.removeClass('scanning');
			
			var words = transcript.split(/\s+/).slice(0, 18).join(' ') + '...';
			card.find('.transcript-preview').text(words);
			card.find('.hidden-transcript').text(transcript);
			card.find('.view-transcript-btn').prop('disabled', false);
			card.attr('data-has-data', 'yes');

			if (mentioned) {
				card.addClass('status-mentioned');
				card.find('.engine-status-tag').html('<span class="tag tag-green"><span class="pulse-dot green-dot"></span> ' + i18n.mentioned + '</span>');
			} else {
				card.addClass('status-missing');
				var tagHtml = isError
					? '<span class="tag tag-red"><span class="pulse-dot red-dot"></span> ' + i18n.failed + '</span>'
					: '<span class="tag tag-red"><span class="pulse-dot red-dot"></span> ' + i18n.missing + '</span>';
				card.find('.engine-status-tag').html(tagHtml);
			}
		}

		function saveScanResultsAndReload() {
			$runScanBtn.html('<span class="dashicons dashicons-update spin"></span> ' + i18n.rebuildingScore);

			jQuery.ajax({
				url: nectarGeoData.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'nectar_geo_save_scan_results',
					nonce: nectarGeoData.nonce,
					results: scanResults
				},
				success: function(response) {
					if (response.success) {
						setRadialScore(response.data.score);
						$runScanBtn.html('<span class="dashicons dashicons-yes-alt"></span> ' + i18n.doneReloading);

						// Delay reload briefly so the user sees completion states
						setTimeout(function() {
							window.location.reload();
						}, 1200);
					} else {
						showNotice(formatString(i18n.errorSavingResults, response.data.message));
						resetScanButton();
					}
				},
				error: function() {
					showNotice(i18n.failedToConnectScores);
					resetScanButton();
				}
			});
		}

		function resetScanButton() {
			$runScanBtn.prop('disabled', false).html('<span class="dashicons dashicons-performance"></span> ' + i18n.runAiScan);
		}

		function setRadialScore(score) {
			var radius = 52;
			var circumference = 2 * Math.PI * radius; // ~326.72
			var offset = circumference - (score / 100) * circumference;
			
			jQuery('#overall-score-circle').css('stroke-dashoffset', offset);
			jQuery('#overall-score-text').text(score + '%');
		}

		/* ========================================================
		 * MODAL POPUPS FOR TRANSCRIPT
		 * ======================================================== */
		$dashboard.on('click', '.view-transcript-btn', function(e) {
			e.preventDefault();
			var card = jQuery(this).closest('.engine-card');
			
			if (card.attr('data-has-data') !== 'yes') return;

			var engineName = card.find('.engine-info h4').text();
			var modelName = card.find('.model-id').text();
			var transcript = card.find('.hidden-transcript').text();
			
			$modalTitle.text(formatString(i18n.transcriptModalTitle, engineName));
			$modalEngineBadge.text(modelName);
			$modalContent.val(transcript);
			
			$modal.addClass('modal-open');
			jQuery('body').addClass('modal-open-blur');
		});

		$dashboard.on('click', '.modal-close-btn, .modal-close-btn-bottom', function(e) {
			e.preventDefault();
			closeTranscriptModal();
		});

		// Close modal clicking outside
		$modal.on('click', function(e) {
			if (jQuery(e.target).is($modal)) {
				closeTranscriptModal();
			}
		});

		function closeTranscriptModal() {
			$modal.removeClass('modal-open');
			jQuery('body').removeClass('modal-open-blur');
		}

		// Copy snippet functionality
		$dashboard.on('click', '.copy-snippet-btn', function(e) {
			e.preventDefault();
			var targetSelector = jQuery(this).data('clipboard-target');
			var $btn = jQuery(this);
			var textToCopy = jQuery(targetSelector).text();

			navigator.clipboard.writeText(textToCopy).then(function() {
				var originalText = $btn.text();
				$btn.text(i18n.copied);
				setTimeout(function() {
					$btn.text(originalText);
				}, 2000);
			}, function() {
				showNotice(i18n.copyFailed);
			});
		});

	});
})(jQuery);



