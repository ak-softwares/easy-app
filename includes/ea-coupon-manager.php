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
			
			// Hook to enqueue the coupon card display script on the checkout page
            add_action('wp_enqueue_scripts', [$this, 'enqueue_coupon_styles']);
			// Hook to display the coupon card on the checkout page
			add_action('woocommerce_before_checkout_form', [$this, 'display_coupon_card']);

			// Add the action to recalculate cart totals after coupon application
			add_filter('woocommerce_coupon_get_discount_amount', array($this, 'modify_existing_coupon_discount'), 10, 5);

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
		
		// Enqueue the necessary styles for the coupon card
        public function enqueue_coupon_styles() {
            if (is_checkout()) { // Only load on the checkout page
				if (!wp_style_is('easyapp-coupon-styles', 'enqueued')) {
					wp_enqueue_style('easyapp-coupon-styles', EASYAPP_URL . 'assets/css/coupon-styles.css', [], '1.8', 'all');
				}
            }
        }

		
        // Display the coupon card dynamically
        public function display_coupon_card() {
            if (is_checkout()) {
                $args = [
                    'post_type'   => 'shop_coupon',
                    'post_status' => 'publish',
                    'meta_query'  => [
                        [
                            'key'   => 'easyapp_show_on_checkout',
                            'value' => true,
                            'compare' => '='
                        ]
                    ]
                ];
                $coupons = get_posts($args);

                if (!empty($coupons)) {
					 ?>
					<div class="coupon-container">
					<?php
					foreach ($coupons as $coupon) {
						$coupon_code = $coupon->post_title; // Get coupon code
                		$description = $coupon->post_excerpt; // Get the description from the post_excerpt (description field)
						?>
						<div class="coupon-card" data-coupon="<?php echo esc_attr($coupon_code); ?>">
							<div class="coupon-content">
								<h3><?php echo esc_html($description ?: 'Discount Offer'); ?></h3>
								<div class="code"><?php echo esc_html($coupon_code); ?></div>
							</div>
							<div class="coupon-strip">
								USE CODE
							</div>
						</div>
						<?php
					}
					?>
					</div>
					<script type="text/javascript">
						jQuery(function($) {
							// Listen for a click on the coupon card
							$('.coupon-card').on('click', function() {
								var couponCode = $(this).data('coupon'); // Get coupon code

								// Apply the coupon code dynamically
								$('input[name="coupon_code"]').val(couponCode); // Set coupon code in the input field
								$('button[name="apply_coupon"]').trigger('click'); // Trigger the apply coupon button click
							});
						});
					</script>
					<?php
                }
            }
        }
		
		public function modify_existing_coupon_discount($discount, $discounting_amount, $cart_item, $single, $coupon) { 
			// Ensure the coupon is a valid WC_Coupon object
			if (is_a($coupon, 'WC_Coupon')) {
				// Get the max discount from the coupon's metadata
				$max_discount = $coupon->get_meta('easyapp_max_discount');
		
				// If the max discount is set and the current discount exceeds it, cap the discount
				if (!empty($max_discount) && $discount > $max_discount) {
					// Return the capped discount
					return $max_discount; 
				}
		
				// Return the original discount if it's within the allowed range or if no max discount is set
				return $discount;
			}
		
			// If the coupon is invalid, return the original discount
			return $discount;
		}
		
	}
}