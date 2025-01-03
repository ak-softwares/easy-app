jQuery(function ($) {
	// Function to check COD availability via AJAX
	function checkCodAvailability() {
		// Update COD notice dynamically
		$('#cod-disabled-notice').remove();
		// Recheck conditions on checkout update
		$.ajax({
			url: eaCodAjax.ajax_url,
			type: 'POST',
			data: {
				action: 'check_cod_availability'
			},
			success: function (response) {
				if (response.success && response.data) {
					$('#payment').before(response.data.message);
				}
			}
		});
	}

	// Function to handle COD payment method state
	function handleCodState() {
		const codRadio = $('input[name="payment_method"][value="cod"]');
		const codReason = $('.cod-disabled-reason').data('reason');

		if (codReason) {
			// Add disabled styles to the COD payment method
			$('.payment_method_cod').addClass('cod-disabled');

			// Deselect COD if it's selected
			if (codRadio.is(':checked')) {
				codRadio.prop('checked', false); // Deselect COD
				$('input[name="payment_method"]:not([value="cod"]):first').prop('checked', true); // Select another method
// 				alert('Cash on Delivery (COD) is not available. Please choose another payment method.');
			}
		} else {
			// Remove disabled styles if COD becomes available
			$('.payment_method_cod').removeClass('cod-disabled');
		}
	}

	// Bind actions to the updated_checkout event
	$(document.body).on('updated_checkout', function () {
		checkCodAvailability();
		handleCodState();
	});

	// Initial check on page load
	$(document).ready(function () {
		checkCodAvailability();
		handleCodState();
	});
});