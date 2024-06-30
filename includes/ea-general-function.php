<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('EasyAppFunction')) {
	class EasyAppFunction {

		public function simplify_order_status($status) {
			// Remove 'wc-' prefix if present
			if (strpos($status, 'wc-') === 0) {
				return substr($status, 3);
			}
			// If 'wc-' prefix is not present, return status as is
			return $status;
		}

		public function extract_phone_number($input) {
			// Convert input to string if it is not already
			$input = strval($input);

			// Remove any non-numeric characters
			$cleaned_number = preg_replace('/[^0-9]/', '', $input);

			// If the cleaned number is more than 10 digits, get the last 10 digits
			if (strlen($cleaned_number) > 10) {
				$cleaned_number = substr($cleaned_number, -10);
			}

			// Check if the cleaned input has fewer than 10 digits
			if (strlen($cleaned_number) < 10) {
				return array(
					'success' => false,
					'message' => 'Invalid phone number'
				);
			}

			return array(
				'success' => true,
				'message' => 'Phone number cleaned successfully',
				'result'  => $cleaned_number
			);
		}
		
		public function remove_special_characters($input) {
			// Convert input to string if it is not already
			$input = strval($input);

			// Remove any character that is not a letter, number, or space
			$cleaned_input = preg_replace('/[^a-zA-Z0-9\s]/', '', $input);

			// Check if the cleaned input is empty
			if (strlen($cleaned_input) == 0) {
				return array(
					'success' => false,
					'message' => 'Input only contains special characters'
				);
			}

			return array(
				'success' => true,
				'message' => 'Special characters removed successfully',
				'result'  => $cleaned_input
			);
		}

	}
}

?>