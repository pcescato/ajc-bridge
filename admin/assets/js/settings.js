/**
 * AJC Bridge - Settings Page JavaScript
 * 
 * Handles bulk sync, stats refresh, sync history functionality,
 * and SSG type conditional field visibility.
 * 
 * @package AjcBridge
 */

(function($) {
	'use strict';
	
	/**
	 * Initialize SSG type field visibility
	 */
	function initSSGTypeToggle() {
		const ssgTypeSelect = $('select[name="ajc_bridge_settings[ssg_type]"]');
		
		if (ssgTypeSelect.length === 0) {
			return;
		}
		
		function updateSSGFieldsVisibility() {
			const selectedSSG = ssgTypeSelect.val();
			
			// Hide all SSG-specific fields
			$('.ssg-hugo-field').closest('tr').hide();
			$('.ssg-astro-field').closest('tr').hide();
			$('.ssg-jekyll-field').closest('tr').hide();
			$('.ssg-eleventy-field').closest('tr').hide();
			
			// Show fields for selected SSG
			if (selectedSSG === 'hugo') {
				$('.ssg-hugo-field').closest('tr').show();
			} else if (selectedSSG === 'astro') {
				$('.ssg-astro-field').closest('tr').show();
			} else if (selectedSSG === 'jekyll') {
				$('.ssg-jekyll-field').closest('tr').show();
			} else if (selectedSSG === 'eleventy') {
				$('.ssg-eleventy-field').closest('tr').show();
			}
		}
		
		// Initialize visibility on page load
		updateSSGFieldsVisibility();
		
		// Update on dropdown change
		ssgTypeSelect.on('change', updateSSGFieldsVisibility);
	}
	
	/**
	 * Load and display sync statistics
	 */
	function loadStats() {
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ajc_bridge_get_stats',
				nonce: ajcBridgeSettings.statsNonce
			},
			success: function(response) {
				if (response.success) {
					updateStatsDisplay(response.data);
				}
			}
		});
	}
	
	/**
	 * Update stats display in the DOM
	 */
	function updateStatsDisplay(stats) {
		// Update stat values if elements exist
		if ($('#ajc-stats-total').length) {
			$('#ajc-stats-total').text(stats.total || 0);
		}
		if ($('#ajc-stats-pending').length) {
			$('#ajc-stats-pending').text(stats.pending || 0);
		}
		if ($('#ajc-stats-processing').length) {
			$('#ajc-stats-processing').text(stats.processing || 0);
		}
		if ($('#ajc-stats-success').length) {
			$('#ajc-stats-success').text(stats.success || 0);
		}
		if ($('#ajc-stats-error').length) {
			$('#ajc-stats-error').text(stats.error || 0);
		}
	}
	
	/**
	 * Start polling for bulk sync progress
	 */
	function startPolling() {
		var pollInterval = setInterval(function() {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ajc_bridge_get_stats',
					nonce: ajcBridgeSettings.statsNonce
				},
				success: function(response) {
					if (response.success) {
						var stats = response.data;
						var total = stats.total || 0;
						var completed = (stats.success || 0) + (stats.error || 0);
						var percentage = total > 0 ? Math.round((completed / total) * 100) : 0;
						
						$('#atomic-jamstack-progress-text').text(completed + ' / ' + total + ' posts processed');
						$('#atomic-jamstack-progress-fill').css('width', percentage + '%');
						
						// Stop polling if all done
						if (stats.pending === 0 && stats.processing === 0) {
							clearInterval(pollInterval);
							updateStatsDisplay(stats);
						}
					}
				}
			});
		}, 3000); // Poll every 3 seconds
	}
	
	/**
	 * Initialize when document is ready
	 */
	$(document).ready(function() {
		// Initialize SSG type field visibility
		initSSGTypeToggle();
		
		// Load initial stats
		loadStats();
		
		// Bulk sync button
		$('#atomic-jamstack-bulk-sync-button').on('click', function() {
			if (!confirm(ajcBridgeSettings.textBulkConfirm)) {
				return;
			}
			
			var $button = $(this);
			var $status = $('#atomic-jamstack-bulk-status');
			var $message = $('#atomic-jamstack-bulk-message');
			
			$button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + ajcBridgeSettings.textStarting);
			$status.show();
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ajc_bridge_bulk_sync',
					nonce: ajcBridgeSettings.bulkSyncNonce
				},
				success: function(response) {
					if (response.success) {
						$message.html('✓ ' + response.data.message);
						$('#atomic-jamstack-progress-text').text(response.data.enqueued + ' / ' + response.data.total + ' posts enqueued');
						$('#atomic-jamstack-progress-fill').css('width', '100%');
						
						// Start polling
						startPolling();
					} else {
						$message.html('✗ ' + response.data.message);
					}
					$button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> ' + ajcBridgeSettings.textSynchronize);
				},
				error: function() {
					$message.html('✗ ' + ajcBridgeSettings.textRequestFailed);
					$button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> ' + ajcBridgeSettings.textSynchronize);
				}
			});
		});
		
		// Refresh stats button
		$('#atomic-jamstack-refresh-stats').on('click', function() {
			loadStats();
		});
		
		// Sync Now buttons in history table
		$('.atomic-jamstack-sync-now').on('click', function() {
			var $button = $(this);
			var postId = $button.data('post-id');
			
			$button.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> ' + ajcBridgeSettings.textSyncing);
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'ajc_bridge_sync_single',
					nonce: ajcBridgeSettings.syncSingleNonce,
					post_id: postId
				},
				success: function(response) {
					if (response.success) {
						$button.html('<span class="dashicons dashicons-yes" style="color: #46b450;"></span> ' + ajcBridgeSettings.textSynced);
						// Reload page after 2 seconds
						setTimeout(function() {
							location.reload();
						}, 2000);
					} else {
						$button.html('<span class="dashicons dashicons-no" style="color: #dc3232;"></span> ' + response.data.message);
						$button.prop('disabled', false);
						setTimeout(function() {
							$button.html('<span class="dashicons dashicons-update"></span> ' + ajcBridgeSettings.textSyncNow);
						}, 3000);
					}
				},
				error: function() {
					$button.html('<span class="dashicons dashicons-no"></span> ' + ajcBridgeSettings.textError);
					$button.prop('disabled', false);
					setTimeout(function() {
						$button.html('<span class="dashicons dashicons-update"></span> ' + ajcBridgeSettings.textSyncNow);
					}, 3000);
				}
			});
		});
	});
	
	/**
	 * Test GitHub connection
	 */
	$('#ajc-bridge-test-github').on('click', function() {
		var $button = $(this);
		var $result = $('#ajc-bridge-test-github-result');
		
		$button.prop('disabled', true).text(ajcBridgeSettings.textTesting);
		$result.html('<span class="spinner is-active" style="float: none; margin: 0;"></span>');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ajc_bridge_test_connection',
				nonce: ajcBridgeSettings.testConnectionNonce
			},
			success: function(response) {
				$button.prop('disabled', false).text(ajcBridgeSettings.textConnected);
				if (response.success) {
					$result.html('<span style="color: #46b450;">✓ ' + response.data.message + '</span>');
				} else {
					$result.html('<span style="color: #dc3232;">✗ ' + response.data.message + '</span>');
				}
			},
			error: function(xhr, status, error) {
				$button.prop('disabled', false).text('Test Connection');
				$result.html('<span style="color: #dc3232;">✗ ' + ajcBridgeSettings.textRequestFailed + ': ' + error + '</span>');
			}
		});
	});
	
	/**
	 * Test Dev.to connection
	 */
	$('#ajc-bridge-test-devto').on('click', function() {
		var $button = $(this);
		var $result = $('#ajc-bridge-test-devto-result');
		var apiKey = $('input[name="ajc_bridge_settings[devto_api_key]"]').val();
		
		if (!apiKey) {
			$result.html('<span style="color: #dc3232;">✗ Please enter an API key first</span>');
			return;
		}
		
		$button.prop('disabled', true).text(ajcBridgeSettings.textTesting);
		$result.html('<span class="spinner is-active" style="float: none; margin: 0;"></span>');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'ajc_bridge_test_devto',
				nonce: ajcBridgeSettings.testConnectionNonce,
				api_key: apiKey
			},
			success: function(response) {
				$button.prop('disabled', false).text(ajcBridgeSettings.textConnected);
				if (response.success) {
					$result.html('<span style="color: #46b450;">✓ ' + response.data.message + '</span>');
				} else {
					$result.html('<span style="color: #dc3232;">✗ ' + response.data.message + '</span>');
				}
			},
			error: function(xhr, status, error) {
				$button.prop('disabled', false).text('Test Connection');
				$result.html('<span style="color: #dc3232;">✗ ' + ajcBridgeSettings.textRequestFailed + ': ' + error + '</span>');
			}
		});
	});
	
})(jQuery);
