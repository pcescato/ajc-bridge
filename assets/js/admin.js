/**
 * Atomic Jamstack Connector - Admin JavaScript
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// Test connection button
		$('#atomic-jamstack-test-connection').on('click', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var $result = $('#atomic-jamstack-test-result');
			
			// Disable button
			$button.prop('disabled', true);
			$result.html('<span class="atomic-jamstack-test-result testing">' + atomicJamstackAdmin.strings.testing + '</span>');
			
			// Make AJAX request
			$.ajax({
				url: atomicJamstackAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'atomic_jamstack_test_connection',
					nonce: atomicJamstackAdmin.testConnectionNonce
				},
				success: function(response) {
					if (response.success) {
						$result.html('<span class="atomic-jamstack-test-result success">✓ ' + response.data.message + '</span>');
					} else {
						$result.html('<span class="atomic-jamstack-test-result error">✗ ' + atomicJamstackAdmin.strings.error + ' ' + response.data.message + '</span>');
					}
				},
				error: function() {
					$result.html('<span class="atomic-jamstack-test-result error">✗ ' + atomicJamstackAdmin.strings.error + ' Network error</span>');
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		});

		// Dev.to show/hide canonical URL field based on mode
		$('input[name="atomic_jamstack_settings[devto_mode]"]').on('change', function() {
			var isSecondary = $(this).val() === 'secondary';
			$('#devto_canonical_url_field').toggle(isSecondary);
			$('#devto_canonical_url_description').toggle(isSecondary);
		});

		// Dev.to test connection button
		$('#devto_test_connection').on('click', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var $result = $('#devto_test_result');
			var apiKey = $('input[name="atomic_jamstack_settings[devto_api_key]"]').val();
			
			if (!apiKey) {
				$result.html('<span class="atomic-jamstack-test-result error">✗ Please enter an API key first</span>');
				return;
			}
			
			// Disable button
			$button.prop('disabled', true).text(atomicJamstackAdmin.strings.testing || 'Testing...');
			$result.html('');
			
			// Make AJAX request
			$.ajax({
				url: atomicJamstackAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'atomic_jamstack_test_devto',
					nonce: atomicJamstackAdmin.testConnectionNonce,
					api_key: apiKey
				},
				success: function(response) {
					if (response.success) {
						$result.html('<span class="atomic-jamstack-test-result success">✓ ' + response.data.message + '</span>');
					} else {
						$result.html('<span class="atomic-jamstack-test-result error">✗ ' + (response.data.message || 'Connection failed') + '</span>');
					}
				},
				error: function() {
					$result.html('<span class="atomic-jamstack-test-result error">✗ Network error</span>');
				},
				complete: function() {
					$button.prop('disabled', false).text('Test Connection');
				}
			});
		});
	});

})(jQuery);
