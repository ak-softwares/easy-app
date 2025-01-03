<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('EasyAppCouponManager')) {
    class EasyAppCouponManager {
		
		public function __construct() {
            // Add a new tab to the coupon menu
            add_filter('woocommerce_coupon_data_tabs', [$this, 'add_easyapp_settings_tab']);
            // Add content to the new tab
            add_action('woocommerce_coupon_data_panels', [$this, 'render_easyapp_settings_content']);
            // Save custom fields from the new tab
            add_action('woocommerce_coupon_options_save', [$this, 'save_easyapp_settings']);
		}
		// Add a new tab to the coupon edit screen
        public function add_easyapp_settings_tab($tabs) {
            $tabs['easyapp_settings'] = array(
                'label'    => __('EasyApp Settings', 'woocommerce'),
                'target'   => 'easyapp_settings_data',
                'class'    => '',
                'priority' => 30, // Adjust priority to control placement
            );
            return $tabs;
        }

		// Render the content for the new tab
		public function render_easyapp_settings_content($coupon_id) {
			?>
			<div id="easyapp_settings_data" class="panel woocommerce_options_panel">
				<?php
				// Maximum Discount Price Field
				woocommerce_wp_text_input(array(
					'id'          => 'easyapp_max_discount',
					'label'       => __('Maximum Discount Price', 'woocommerce'),
					'placeholder' => __('Enter max discount', 'woocommerce'),
					'desc_tip'    => true,
					'description' => __('Set the maximum discount price for this coupon.', 'woocommerce'),
					'type'        => 'number',
					'value'       => get_post_meta($coupon_id, 'easyapp_max_discount', true),
				));

				// Show Coupon on Checkout Page Field
				woocommerce_wp_checkbox(array(
					'id'          => 'easyapp_show_on_checkout',
					'label'       => __('Show on Checkout Page', 'woocommerce'),
					'description' => __('Enable this to display the coupon code on the checkout page.', 'woocommerce'),
					'value'       => get_post_meta($coupon_id, 'easyapp_show_on_checkout', true) ? 'yes' : 'no',
				));
				?>
			</div>
			<?php
		}

        // Save the custom fields in the new tab
        public function save_easyapp_settings($coupon_id) {
			// Save Maximum Discount Price
            if (isset($_POST['easyapp_max_discount'])) {
                update_post_meta($coupon_id, 'easyapp_max_discount', sanitize_text_field($_POST['easyapp_max_discount']));
            }
			
			// Save Show on Checkout Page
			$show_on_checkout = isset($_POST['easyapp_show_on_checkout']) ? true : false;
			update_post_meta($coupon_id, 'easyapp_show_on_checkout', $show_on_checkout);
        }
		
	}
}