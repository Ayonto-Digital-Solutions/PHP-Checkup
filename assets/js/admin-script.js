/**
 * AS PHP Checkup Admin JavaScript
 *
 * @package AS_PHP_Checkup
 * @since 1.0.0
 * @version 1.3.0
 */

(function($) {
	'use strict';

	/**
	 * PHP Checkup Admin Handler
	 */
	var ASPhpCheckupAdmin = {
		
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
			this.initTabs();
			this.initHealthScore();
			this.initSolutionModal();
			this.initTooltips();
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			// Tab navigation
			$(document).on('click', '.nav-tab', this.handleTabClick.bind(this));
			
			// Refresh and analysis buttons
			$(document).on('click', '.refresh-check', this.refreshCheck.bind(this));
			$(document).on('click', '.analyze-plugins', this.analyzePlugins.bind(this));
			
			// Export buttons
			$(document).on('click', '.export-report', this.exportReport.bind(this));
			$(document).on('click', '.export-full-report', this.exportFullReport.bind(this));
			$(document).on('click', '.export-plugin-report', this.exportPluginReport.bind(this));
			
			// Solution buttons
			$(document).on('click', '.show-solution', this.showSolutionModal.bind(this));
			$(document).on('click', '.download-config', this.downloadConfig.bind(this));
			$(document).on('click', '.auto-apply', this.autoApplySolution.bind(this));
			$(document).on('click', '.generate-config', this.generateConfig.bind(this));
			$(document).on('click', '.download-generated-config', this.downloadGeneratedConfig.bind(this));
			
			// Copy buttons
			$(document).on('click', '.copy-system-info', this.copySystemInfo.bind(this));
			$(document).on('click', '.copy-config', this.copyConfig.bind(this));
			
			// Cache management
			$(document).on('click', '.clear-cache', this.clearCache.bind(this));
			
			// Tools
			$(document).on('click', '.run-scheduled-task', this.runScheduledTask.bind(this));
			$(document).on('click', '.view-phpinfo', this.viewPhpInfo.bind(this));

			// Check configuration settings - New in 1.4.0
			$(document).on('change', 'input[name="check_profile"]', this.handleProfileChange.bind(this));
			$(document).on('change', '.check-toggle-input', this.handleCheckToggle.bind(this));
			$(document).on('click', '.save-check-config', this.saveCheckConfiguration.bind(this));
			$(document).on('click', '.reset-to-defaults', this.resetToDefaults.bind(this));

			// Solution modal
			$(document).on('click', '#solution-modal .close', this.closeSolutionModal.bind(this));
			$(document).on('click', '#solution-modal', this.handleModalBackgroundClick.bind(this));

			// ESC key to close modals
			$(document).on('keyup', this.handleEscKey.bind(this));
		},

		/**
		 * Initialize tabs
		 */
		initTabs: function() {
			// Check for hash in URL
			var hash = window.location.hash;
			if (hash) {
				var $tab = $('.nav-tab[href="' + hash + '"]');
				if ($tab.length) {
					$tab.trigger('click');
				}
			}
		},

		/**
		 * Handle tab click
		 */
		handleTabClick: function(e) {
			e.preventDefault();
			
			var $tab = $(e.currentTarget);
			var tabId = $tab.data('tab');
			
			// Update active tab
			$('.nav-tab').removeClass('nav-tab-active');
			$tab.addClass('nav-tab-active');
			
			// Show corresponding content
			$('.tab-content').hide();
			$('#tab-' + tabId).fadeIn();
			
			// Update URL hash
			window.location.hash = tabId;
		},

		/**
		 * Initialize health score animation
		 */
		initHealthScore: function() {
			var $scoreCircle = $('.score-circle');
			if ($scoreCircle.length) {
				var $score = $scoreCircle.find('.score');
				var targetScore = parseInt($score.text());
				var currentScore = 0;
				var increment = targetScore / 30;
				
				// Animate score number
				$score.text('0%');
				var scoreInterval = setInterval(function() {
					currentScore += increment;
					if (currentScore >= targetScore) {
						currentScore = targetScore;
						clearInterval(scoreInterval);
					}
					$score.text(Math.round(currentScore) + '%');
				}, 50);
			}
		},

		/**
		 * Initialize solution modal
		 */
		initSolutionModal: function() {
			// Test write permissions on load
			this.testWritePermissions();
		},

		/**
		 * Test write permissions
		 */
		testWritePermissions: function() {
			$.ajax({
				url: asPhpCheckup.ajaxurl,
				type: 'POST',
				data: {
					action: 'as_php_checkup_test_write',
					nonce: asPhpCheckup.nonce
				},
				success: function(response) {
					if (response.success && response.data) {
						// Update UI based on permissions
						$.each(response.data, function(type, canWrite) {
							if (!canWrite) {
								$('.auto-apply[data-type="' + type + '"]')
									.prop('disabled', true)
									.attr('title', asPhpCheckup.strings.no_write_permission);
							}
						});
					}
				}
			});
		},

		/**
		 * Initialize tooltips
		 */
		initTooltips: function() {
			$('[title]').each(function() {
				var $this = $(this);
				var title = $this.attr('title');
				if (title && title.length > 20) {
					$this.addClass('has-tooltip');
				}
			});
		},

		/**
		 * Refresh check via AJAX
		 */
		refreshCheck: function(e) {
			e.preventDefault();
			
			var $button = $(e.currentTarget);
			var originalHtml = $button.html();
			
			// Update button state
			$button.prop('disabled', true)
				.html('<span class="dashicons dashicons-update spinning"></span> ' + asPhpCheckup.strings.refreshing);
			
			// Send AJAX request
			$.ajax({
				url: asPhpCheckup.ajaxurl,
				type: 'POST',
				data: {
					action: 'as_php_checkup_refresh',
					nonce: asPhpCheckup.nonce
				},
				success: function(response) {
					if (response.success) {
						ASPhpCheckupAdmin.showNotice(asPhpCheckup.strings.refresh_complete, 'success');
						// Reload page to show updated results
						setTimeout(function() {
							location.reload();
						}, 1000);
					} else {
						ASPhpCheckupAdmin.showNotice(asPhpCheckup.strings.refresh_error, 'error');
						$button.prop('disabled', false).html(originalHtml);
					}
				},
				error: function() {
					ASPhpCheckupAdmin.showNotice(asPhpCheckup.strings.refresh_error, 'error');
					$button.prop('disabled', false).html(originalHtml);
				}
			});
		},

		/**
		 * Analyze plugins via AJAX
		 */
		analyzePlugins: function(e) {
			e.preventDefault();
			
			var $button = $(e.currentTarget);
			var originalHtml = $button.html();
			
			// Update button state
			$button.prop('disabled', true)
				.html('<span class="dashicons dashicons-search spinning"></span> ' + asPhpCheckup.strings.analyzing);
			
			// Send AJAX request
			$.ajax({
				url: asPhpCheckup.ajaxurl,
				type: 'POST',
				data: {
					action: 'as_php_checkup_analyze_plugins',
					nonce: asPhpCheckup.nonce
				},
				success: function(response) {
					if (response.success) {
						ASPhpCheckupAdmin.showNotice(asPhpCheckup.strings.analysis_complete, 'success');
						// Reload page to show updated results
						setTimeout(function() {
							location.reload();
						}, 1000);
					} else {
						ASPhpCheckupAdmin.showNotice(asPhpCheckup.strings.analysis_error, 'error');
						$button.prop('disabled', false).html(originalHtml);
					}
				},
				error: function() {
					ASPhpCheckupAdmin.showNotice(asPhpCheckup.strings.analysis_error, 'error');
					$button.prop('disabled', false).html(originalHtml);
				}
			});
		},

		/**
		 * Show solution modal
		 */
		showSolutionModal: function(e) {
			e.preventDefault();
			
			var $button = $(e.currentTarget);
			var setting = $button.data('setting');
			var recommended = $button.data('recommended');
			var current = $button.data('current');
			
			// Build modal content
			var modalContent = '<h3>' + setting + '</h3>';
			modalContent += '<p><strong>Current Value:</strong> <code>' + current + '</code></p>';
			modalContent += '<p><strong>Recommended Value:</strong> <code>' + recommended + '</code></p>';
			modalContent += '<h4>Available Solutions:</h4>';
			modalContent += '<div class="solution-options">';
			
			// Add solution options based on available solutions
			modalContent += '<button class="button button-primary apply-solution-modal" data-setting="' + setting + '" data-value="' + recommended + '" data-type="user_ini">';
			modalContent += '<span class="dashicons dashicons-admin-generic"></span> Apply via .user.ini';
			modalContent += '</button>';
			
			modalContent += '<button class="button download-solution-config" data-setting="' + setting + '" data-value="' + recommended + '">';
			modalContent += '<span class="dashicons dashicons-download"></span> Download Configuration';
			modalContent += '</button>';
			
			modalContent += '</div>';
			
			// Show modal
			$('#solution-modal .solution-details').html(modalContent);
			$('#solution-modal').fadeIn();
			
			// Bind solution buttons
			$('.apply-solution-modal').on('click', this.applySolutionFromModal.bind(this));
			$('.download-solution-config').on('click', this.downloadSolutionConfig.bind(this));
		},

		/**
		 * Close solution modal
		 */
		closeSolutionModal: function(e) {
			e.preventDefault();
			$('#solution-modal').fadeOut();
		},

		/**
		 * Handle modal background click
		 */
		handleModalBackgroundClick: function(e) {
			if ($(e.target).is('#solution-modal')) {
				this.closeSolutionModal(e);
			}
		},

		/**
		 * Apply solution from modal
		 */
		applySolutionFromModal: function(e) {
			e.preventDefault();
			
			if (!confirm(asPhpCheckup.strings.confirm_apply)) {
				return;
			}
			
			var $button = $(e.currentTarget);
			var setting = $button.data('setting');
			var value = $button.data('value');
			var type = $button.data('type');
			
			$button.prop('disabled', true)
				.html('<span class="dashicons dashicons-update spinning"></span> ' + asPhpCheckup.strings.apply_solution);
			
			$.ajax({
				url: asPhpCheckup.ajaxurl,
				type: 'POST',
				data: {
					action: 'as_php_checkup_apply_solution',
					nonce: asPhpCheckup.nonce,
					setting: setting,
					value: value,
					solution_type: type
				},
				success: function(response) {
					if (response.success) {
						ASPhpCheckupAdmin.showNotice(asPhpCheckup.strings.solution_applied, 'success');
						$('#solution-modal').fadeOut();
						setTimeout(function() {
							location.reload();
						}, 1500);
					} else {
						ASPhpCheckupAdmin.showNotice(response.data.message || asPhpCheckup.strings.solution_error, 'error');
						$button.prop('disabled', false)
							.html('<span class="dashicons dashicons-admin-generic"></span> Apply via .user.ini');
					}
				},
				error: function() {
					ASPhpCheckupAdmin.showNotice(asPhpCheckup.strings.solution_error, 'error');
					$button.prop('disabled', false)
						.html('<span class="dashicons dashicons-admin-generic"></span> Apply via .user.ini');
				}
			});
		},

		/**
		 * Download solution config
		 */
		downloadSolutionConfig: function(e) {
			e.preventDefault();
			
			var $button = $(e.currentTarget);
			var setting = $button.data('setting');
			var value = $button.data('value');
			
			// Create form and submit
			var $form = $('<form>', {
				method: 'POST',
				action: asPhpCheckup.ajaxurl
			});
			
			$form.append($('<input>', {
				type: 'hidden',
				name: 'action',
				value: 'as_php_checkup_download_config'
			}));
			
			$form.append($('<input>', {
				type: 'hidden',
				name: 'nonce',
				value: asPhpCheckup.nonce
			}));
			
			$form.append($('<input>', {
				type: 'hidden',
				name: 'config_type',
				value: 'user_ini'
			}));
			
			$form.appendTo('body').submit().remove();
		},

		/**
		 * Download config
		 */
		downloadConfig: function(e) {
			e.preventDefault();
			
			var $button = $(e.currentTarget);
			var type = $button.data('type');
			
			// Create form and submit
			var $form = $('<form>', {
				method: 'POST',
				action: asPhpCheckup.ajaxurl
			});
			
			$form.append($('<input>', {
				type: 'hidden',
				name: 'action',
				value: 'as_php_checkup_download_config'
			}));
			
			$form.append($('<input>', {
				type: 'hidden',
				name: 'nonce',
				value: asPhpCheckup.nonce
			}));
			
			$form.append($('<input>', {
				type: 'hidden',
				name: 'config_type',
				value: type
			}));
			
			$form.appendTo('body').submit().remove();
		},

		/**
		 * Auto-apply solution
		 */
		autoApplySolution: function(e) {
			e.preventDefault();
			
			if (!confirm(asPhpCheckup.strings.confirm_apply)) {
				return;
			}
			
			var $button = $(e.currentTarget);
			var type = $button.data('type');
			var originalHtml = $button.html();
			
			$button.prop('disabled', true)
				.html('<span class="dashicons dashicons-update spinning"></span> ' + asPhpCheckup.strings.apply_solution);
			
			// Get all warnings to fix
			var issues = [];
			$('.status-warning').each(function() {
				var $row = $(this);
				issues.push({
					setting: $row.find('.setting-name strong').text(),
					value: $row.find('.recommended-value code').text()
				});
			});
			
			// Apply solutions one by one
			var applyNext = function(index) {
				if (index >= issues.length) {
					ASPhpCheckupAdmin.showNotice(asPhpCheckup.strings.solution_applied, 'success');
					setTimeout(function() {
						location.reload();
					}, 1500);
					return;
				}
				
				var issue = issues[index];
				
				$.ajax({
					url: asPhpCheckup.ajaxurl,
					type: 'POST',
					data: {
						action: 'as_php_checkup_apply_solution',
						nonce: asPhpCheckup.nonce,
						setting: issue.setting,
						value: issue.value,
						solution_type: type
					},
					success: function(response) {
						if (response.success) {
							applyNext(index + 1);
						} else {
							ASPhpCheckupAdmin.showNotice(response.data.message || asPhpCheckup.strings.solution_error, 'error');
							$button.prop('disabled', false).html(originalHtml);
						}
					},
					error: function() {
						ASPhpCheckupAdmin.showNotice(asPhpCheckup.strings.solution_error, 'error');
						$button.prop('disabled', false).html(originalHtml);
					}
				});
			};
			
			applyNext(0);
		},

		/**
		 * Generate config
		 */
		generateConfig: function(e) {
			e.preventDefault();
			
			var configType = $('#config-type').val();
			
			$.ajax({
				url: asPhpCheckup.ajaxurl,
				type: 'POST',
				data: {
					action: 'as_php_checkup_generate_config',
					nonce: asPhpCheckup.nonce,
					config_type: configType
				},
				success: function(response) {
					if (response.success) {
						$('#config-output').text(response.data.content);
						$('#generated-config').fadeIn();
					} else {
						ASPhpCheckupAdmin.showNotice(response.data.message || 'Error generating configuration', 'error');
					}
				},
				error: function() {
					ASPhpCheckupAdmin.showNotice('Error generating configuration', 'error');
				}
			});
		},

		/**
		 * Download generated config
		 */
		downloadGeneratedConfig: function(e) {
			e.preventDefault();
			
			var content = $('#config-output').text();
			var configType = $('#config-type').val();
			var filename = 'php-config-' + configType + '.txt';
			
			this.downloadFile(content, filename, 'text/plain');
		},

		/**
		 * Export report
		 */
		exportReport: function(e) {
			e.preventDefault();
			
			var $button = $(e.currentTarget);
			var format = $button.data('format') || 'txt';
			
			this.doExport('as_php_checkup_export', format);
		},

		/**
		 * Export full report
		 */
		exportFullReport: function(e) {
			e.preventDefault();
			
			var format = $('#export-format').val();
			this.doExport('as_php_checkup_export', format);
		},

		/**
		 * Export plugin report
		 */
		exportPluginReport: function(e) {
			e.preventDefault();
			
			this.doExport('as_php_checkup_export', 'json');
		},

		/**
		 * Do export
		 */
		doExport: function(action, format) {
			// Create form and submit
			var $form = $('<form>', {
				method: 'POST',
				action: asPhpCheckup.ajaxurl
			});
			
			$form.append($('<input>', {
				type: 'hidden',
				name: 'action',
				value: action
			}));
			
			$form.append($('<input>', {
				type: 'hidden',
				name: 'nonce',
				value: asPhpCheckup.nonce
			}));
			
			$form.append($('<input>', {
				type: 'hidden',
				name: 'format',
				value: format
			}));
			
			$form.appendTo('body').submit().remove();
		},

		/**
		 * Copy system info
		 */
		copySystemInfo: function(e) {
			e.preventDefault();
			
			var info = '';
			$('.system-info-grid .info-table tr').each(function() {
				var label = $(this).find('td:first').text();
				var value = $(this).find('td:last').text();
				info += label + ' ' + value + '\n';
			});
			
			this.copyToClipboard(info, $(e.currentTarget));
		},

		/**
		 * Copy config
		 */
		copyConfig: function(e) {
			e.preventDefault();
			
			var content = $('#config-output').text();
			this.copyToClipboard(content, $(e.currentTarget));
		},

		/**
		 * Clear cache
		 */
		clearCache: function(e) {
			e.preventDefault();
			
			var $button = $(e.currentTarget);
			var originalHtml = $button.html();
			
			$button.prop('disabled', true)
				.html('<span class="dashicons dashicons-update spinning"></span> Clearing...');
			
			$.ajax({
				url: asPhpCheckup.ajaxurl,
				type: 'POST',
				data: {
					action: 'as_php_checkup_clear_cache',
					nonce: asPhpCheckup.nonce
				},
				success: function(response) {
					if (response.success) {
						ASPhpCheckupAdmin.showNotice('Cache cleared successfully', 'success');
						setTimeout(function() {
							location.reload();
						}, 1000);
					} else {
						ASPhpCheckupAdmin.showNotice('Error clearing cache', 'error');
						$button.prop('disabled', false).html(originalHtml);
					}
				},
				error: function() {
					ASPhpCheckupAdmin.showNotice('Error clearing cache', 'error');
					$button.prop('disabled', false).html(originalHtml);
				}
			});
		},

		/**
		 * Run scheduled task
		 */
		runScheduledTask: function(e) {
			e.preventDefault();
			
			var $button = $(e.currentTarget);
			var originalHtml = $button.html();
			
			$button.prop('disabled', true)
				.html('<span class="dashicons dashicons-update spinning"></span> Running...');
			
			$.ajax({
				url: asPhpCheckup.ajaxurl,
				type: 'POST',
				data: {
					action: 'as_php_checkup_run_scheduled',
					nonce: asPhpCheckup.nonce
				},
				success: function(response) {
					if (response.success) {
						ASPhpCheckupAdmin.showNotice('Scheduled task completed', 'success');
						$button.prop('disabled', false).html(originalHtml);
					} else {
						ASPhpCheckupAdmin.showNotice('Error running scheduled task', 'error');
						$button.prop('disabled', false).html(originalHtml);
					}
				},
				error: function() {
					ASPhpCheckupAdmin.showNotice('Error running scheduled task', 'error');
					$button.prop('disabled', false).html(originalHtml);
				}
			});
		},

		/**
		 * View PHP info
		 */
		viewPhpInfo: function(e) {
			e.preventDefault();
			
			window.open(asPhpCheckup.ajaxurl + '?action=as_php_checkup_phpinfo&nonce=' + asPhpCheckup.nonce, 'phpinfo', 'width=800,height=600,scrollbars=yes');
		},

		/**
		 * Handle ESC key
		 */
		handleEscKey: function(e) {
			if (e.key === 'Escape' || e.keyCode === 27) {
				if ($('#solution-modal').is(':visible')) {
					this.closeSolutionModal(e);
				}
			}
		},

		/**
		 * Copy to clipboard
		 */
		copyToClipboard: function(text, $button) {
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function() {
					ASPhpCheckupAdmin.showCopyFeedback($button);
				});
			} else {
				// Fallback
				var $temp = $('<textarea>');
				$('body').append($temp);
				$temp.val(text).select();
				document.execCommand('copy');
				$temp.remove();
				ASPhpCheckupAdmin.showCopyFeedback($button);
			}
		},

		/**
		 * Show copy feedback
		 */
		showCopyFeedback: function($button) {
			var originalHtml = $button.html();
			$button.html('<span class="dashicons dashicons-yes"></span> Copied!');
			setTimeout(function() {
				$button.html(originalHtml);
			}, 2000);
		},

		/**
		 * Download file
		 */
		downloadFile: function(content, filename, mimeType) {
			var blob = new Blob([content], { type: mimeType + ';charset=utf-8;' });
			var link = document.createElement('a');
			
			if (navigator.msSaveBlob) {
				navigator.msSaveBlob(blob, filename);
			} else {
				var url = URL.createObjectURL(blob);
				link.setAttribute('href', url);
				link.setAttribute('download', filename);
				link.style.display = 'none';
				
				document.body.appendChild(link);
				link.click();
				document.body.removeChild(link);
				
				setTimeout(function() {
					URL.revokeObjectURL(url);
				}, 100);
			}
		},

		/**
		 * Show admin notice
		 */
		showNotice: function(message, type) {
			var noticeClass = 'notice notice-' + (type || 'info') + ' is-dismissible';
			var $notice = $('<div class="' + noticeClass + '"><p>' + message + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button></div>');

			// Remove existing notices
			$('.as-php-checkup-wrap .notice').remove();

			// Insert after page title
			$('.as-php-checkup-wrap h1').after($notice);

			// Auto-hide after 5 seconds
			setTimeout(function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);

			// Make dismissible
			$notice.on('click', '.notice-dismiss', function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			});
		},

		/**
		 * Handle profile change
		 *
		 * @since 1.4.0
		 */
		handleProfileChange: function(e) {
			var $radio = $(e.currentTarget);
			var profileName = $radio.val();

			// Update UI to show selected profile
			$('.profile-option').removeClass('selected');
			$radio.closest('.profile-option').addClass('selected');

			// Load profile configuration via AJAX
			$.ajax({
				url: asPhpCheckup.ajaxurl,
				type: 'POST',
				data: {
					action: 'as_php_checkup_load_profile',
					nonce: asPhpCheckup.nonce,
					profile: profileName
				},
				success: function(response) {
					if (response.success && response.data) {
						// Update checkboxes based on profile
						var enabledChecks = response.data.enabled_checks || [];

						$('.check-toggle-input').each(function() {
							var $checkbox = $(this);
							var checkKey = $checkbox.data('check');
							var shouldBeEnabled = enabledChecks.indexOf(checkKey) !== -1;
							$checkbox.prop('checked', shouldBeEnabled);
						});

						ASPhpCheckupAdmin.showNotice('Profile "' + profileName + '" loaded. Click Save to apply changes.', 'info');
					} else {
						ASPhpCheckupAdmin.showNotice('Error loading profile', 'error');
					}
				},
				error: function() {
					ASPhpCheckupAdmin.showNotice('Error loading profile', 'error');
				}
			});
		},

		/**
		 * Handle individual check toggle
		 *
		 * @since 1.4.0
		 */
		handleCheckToggle: function(e) {
			// Mark profile as custom when individual checks are modified
			var $customRadio = $('input[name="check_profile"][value="custom"]');
			if ($customRadio.length && !$customRadio.is(':checked')) {
				$customRadio.prop('disabled', false).prop('checked', true);
				$('.profile-option').removeClass('selected');
				$customRadio.closest('.profile-option').addClass('selected');
			}
		},

		/**
		 * Save check configuration
		 *
		 * @since 1.4.0
		 */
		saveCheckConfiguration: function(e) {
			e.preventDefault();

			var $button = $(e.currentTarget);
			var originalHtml = $button.html();

			// Get selected profile
			var profile = $('input[name="check_profile"]:checked').val();

			// Get enabled checks
			var enabledChecks = [];
			$('.check-toggle-input:checked').each(function() {
				enabledChecks.push($(this).val());
			});

			// Also include all critical checks (mandatory)
			$('.check-row.severity-critical').each(function() {
				var checkKey = $(this).find('.check-name strong').text().replace(/\./g, '_');
				if (enabledChecks.indexOf(checkKey) === -1) {
					// Critical checks are always enabled
					var $input = $(this).find('.check-toggle-input');
					if ($input.length) {
						enabledChecks.push($input.val());
					}
				}
			});

			$button.prop('disabled', true)
				.html('<span class="dashicons dashicons-update spinning"></span> Saving...');

			$.ajax({
				url: asPhpCheckup.ajaxurl,
				type: 'POST',
				data: {
					action: 'as_php_checkup_save_config',
					nonce: asPhpCheckup.nonce,
					profile: profile,
					enabled_checks: enabledChecks
				},
				success: function(response) {
					if (response.success) {
						ASPhpCheckupAdmin.showNotice('Configuration saved successfully!', 'success');
						$('.save-status').html('<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> Saved');

						// Clear cache and refresh checks
						setTimeout(function() {
							location.reload();
						}, 1500);
					} else {
						ASPhpCheckupAdmin.showNotice(response.data.message || 'Error saving configuration', 'error');
						$button.prop('disabled', false).html(originalHtml);
					}
				},
				error: function() {
					ASPhpCheckupAdmin.showNotice('Error saving configuration', 'error');
					$button.prop('disabled', false).html(originalHtml);
				}
			});
		},

		/**
		 * Reset to defaults
		 *
		 * @since 1.4.0
		 */
		resetToDefaults: function(e) {
			e.preventDefault();

			if (!confirm('Are you sure you want to reset to default configuration? This will set the profile to "Balanced" and enable all recommended checks.')) {
				return;
			}

			var $button = $(e.currentTarget);
			var originalHtml = $button.html();

			$button.prop('disabled', true)
				.html('<span class="dashicons dashicons-update spinning"></span> Resetting...');

			// Load balanced profile
			$('input[name="check_profile"][value="balanced"]').prop('checked', true).trigger('change');

			setTimeout(function() {
				// Trigger save after profile loads
				$('.save-check-config').trigger('click');
			}, 1000);
		}
	};

	/**
	 * Add spinning animation CSS
	 */
	if (!$('#as-php-checkup-spinning-style').length) {
		$('head').append('<style id="as-php-checkup-spinning-style">' +
			'@keyframes as-spin {' +
				'0% { transform: rotate(0deg); }' +
				'100% { transform: rotate(360deg); }' +
			'}' +
			'.dashicons.spinning {' +
				'animation: as-spin 1s linear infinite;' +
			'}' +
		'</style>');
	}

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		if ($('.as-php-checkup-wrap').length) {
			ASPhpCheckupAdmin.init();
		}
	});

	/**
	 * Export to global scope for debugging
	 */
	window.ASPhpCheckupAdmin = ASPhpCheckupAdmin;

})(jQuery);