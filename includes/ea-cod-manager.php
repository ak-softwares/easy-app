<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('EasyAppCODManager')) {
    class EasyAppCODManager {
		
		private static $notice_added = false;  // Flag to track if the notice has been added

        public function __construct() {
            // Hook to add the "Block COD" option to Products
            add_action('woocommerce_product_options_general_product_data', [$this, 'add_cod_blocked_option_to_product']);
            add_action('woocommerce_process_product_meta', [$this, 'save_cod_blocked_option']);
            
			// Hook to add the "Block COD" option to coupons
            add_action('woocommerce_coupon_options', [$this, 'add_cod_blocked_option_to_coupon']);
            add_action('woocommerce_coupon_options_save', [$this, 'save_cod_blocked_option_to_coupon']);
      
			//Hook to add the "Block COD" option to user
            add_action('edit_user_profile', [$this, 'add_cod_blocked_option_to_user_profile']);
            add_action('edit_user_profile_update', [$this, 'save_cod_blocked_option_to_user_profile']);
			
            add_filter('woocommerce_available_payment_gateways', [$this, 'filter_cod_payment_gateway']);
			
			add_action('wp_enqueue_scripts', [$this, 'cod_js_availability']);
			add_action('wp_ajax_check_cod_availability', [$this, 'check_cod_availability']);
			add_action('wp_ajax_nopriv_check_cod_availability', [$this, 'check_cod_availability']);

        }
		
		public function cod_js_availability() {
			if (is_checkout()) {
				if (!wp_style_is('ea-cod-block-style', 'enqueued')) {
					wp_enqueue_style('es-cod-block-style', EASYAPP_URL . 'assets/css/disable-cod.css', [], '1.7', 'all');
				}

				if (!wp_script_is('ea-cod-block-script', 'enqueued')) {
					wp_enqueue_script('ea-cod-block-script', EASYAPP_URL . 'assets/js/disable-cod.js', array('jquery'), '2.2', true);
					// Pass the AJAX URL to the script
					wp_localize_script('ea-cod-block-script', 'eaCodAjax', [
						'ajax_url' => admin_url('admin-ajax.php'),
					]);
				}
			}

		} 

		
		public function check_cod_availability() {
			$block_reason = $this->check_cod_block_reason();
			
			if ($block_reason) {
				$message =  '<div id="cod-disabled-notice" class="woocommerce-info" style="margin: 15px 0; padding: 10px; border-left: 4px solid #006699; background: #e6f5fa; color: #064663; font-size: 14px;">' . 
					esc_html('COD is unavailable due to ' . $block_reason) . 
					'</div>';
				wp_send_json_success(['message' => $message]);
			} else {
				wp_send_json_error();
			}
		}
		
        // Add a "COD Blocked" option to the product settings page.
        public function add_cod_blocked_option_to_product() {
			global $post;
			$product = wc_get_product($post->ID);
			
            woocommerce_wp_checkbox([
                'id' 			=> EA_COD_BLOCK_META,
				'label' 		=> __('Block COD', 'woocommerce'),
				'description' 	=> __('Check this if COD is blocked for this product.', 'woocommerce'),
				'value'       	=> $product->get_meta(EA_COD_BLOCK_META, true) ? 'yes' : 'no',
            ]);
        }
        // Save the "COD Blocked" option value when the product is updated.
        public function save_cod_blocked_option($product_id) {
            $cod_blocked = isset($_POST[EA_COD_BLOCK_META]) ? true : false;
            update_post_meta($product_id, EA_COD_BLOCK_META, $cod_blocked);
        }
        
        // Add "Block COD" checkbox option to the coupon settings page.
        public function add_cod_blocked_option_to_coupon($coupon_id) {
            woocommerce_wp_checkbox([
                'id'          => EA_COD_BLOCK_META,
                'label'       => __('Block COD', 'woocommerce'),
                'description' => __('Check this box to block COD when this coupon is applied.', 'woocommerce'),
                'value'       => get_post_meta($coupon_id, EA_COD_BLOCK_META, true) ? 'yes' : 'no',
            ]);
        }
        // Save the "Block COD" option for the coupon.
        public function save_cod_blocked_option_to_coupon($coupon_id) {
            $cod_blocked = isset($_POST[EA_COD_BLOCK_META]) ? true : false;
            update_post_meta($coupon_id, EA_COD_BLOCK_META, $cod_blocked);
        }
        
		// Add a "Block COD" option to the user profile page.
        public function add_cod_blocked_option_to_user_profile($user) {
            // Check if the current user can edit the profile (admin or self)
            if (current_user_can('administrator') || (get_current_user_id() == $user->ID)) {
                ?>
                <h3><?php _e('Block COD Option', 'woocommerce'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><label for="cod_blocked"><?php _e('Block Cash on Delivery (COD)', 'woocommerce'); ?></label></th>
                        <td>
                            <input type="checkbox" name="cod_blocked" id="cod_blocked" value="true" <?php checked(get_user_meta($user->ID, EA_COD_BLOCK_META, true), true); ?> />
                            <span class="description"><?php _e('Check this box to block COD for this user.', 'woocommerce'); ?></span>
                        </td>
                    </tr>
                </table>
                <?php
            }
        }
        // Save the "Block COD" option for the user.
        public function save_cod_blocked_option_to_user_profile($user_id) {
            if (isset($_POST['cod_blocked'])) {
                update_user_meta($user_id, EA_COD_BLOCK_META, true);
            } else {
                update_user_meta($user_id, EA_COD_BLOCK_META, false);
            }
        }
		
        /**
         * Filter payment gateways to disable COD if any product in the cart has COD blocked.
         *
         * @param array $gateways Available payment gateways.
         * @return array Modified payment gateways.
         */
        public function filter_cod_payment_gateway($available_gateways) {
            // Check if we're on the checkout or order-pay page
            if (!function_exists('is_checkout') || (!is_checkout() && !is_wc_endpoint_url('order-pay'))) {
                return $available_gateways;
            }
            // Ensure WooCommerce Cart is available
            if (!WC()->cart) {
                return $available_gateways;
            }

            $block_reason = $this->check_cod_block_reason();

            // If COD is blocked for any product or coupon, unset the COD payment gateway
            if ($block_reason && !self::$notice_added) {
//                 unset($available_gateways['cod']);
				
				// Add a custom class to the COD gateway to indicate it's disabled
				if (isset($available_gateways['cod'])) {
					// Update the title and description for COD
					$available_gateways['cod']->title = __('COD is unavailable due to ' . $block_reason, 'woocommerce');
					$available_gateways['cod']->description .= '<span class="cod-disabled-reason" data-reason="' . esc_attr($block_reason) . '"></span>';
        
				}
				
                // Add the notice only once for the checkout page
//                 add_action('woocommerce_review_order_before_payment', function() use ($block_reason) {
//                     $this->add_checkout_notice($block_reason);
//                 });

                // Set the flag to indicate that the notice has been added
                self::$notice_added = true;
            }
            return $available_gateways;
        }
		
		// Check if COD is blocked by any product in the cart.
		private function check_cod_block_reason() {
			// Get current user ID
            $current_user_id = get_current_user_id();
			$shipping_pincode = WC()->customer->get_shipping_postcode();
			
            // Initialize the COD block reason and message
            $block_reason = "";

            // Check if COD is blocked by products in the cart
            if ($this->is_cod_blocked_by_products()) {
                $block_reason = 'product';
            }
			
			// Check if COD is blocked by any applied coupon
			elseif ($this->is_cod_blocked_by_coupons()) {
                $block_reason = 'coupon';
            }
			
			// Check if COD is blocked by user
            elseif ($this->is_cod_blocked_by_user($current_user_id)) {
                $block_reason = 'user';
            }
            
			// Check if COD is blocked for the shipping pincode
            elseif ($this->is_cod_blocked_by_pincode($shipping_pincode)) {
                $block_reason = 'pincode';
            }
			
			return $block_reason;
		}
		
        // Check if COD is blocked by any product in the cart.
        private function is_cod_blocked_by_products() {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product = wc_get_product($cart_item['product_id']);
                if ($product->get_meta(EA_COD_BLOCK_META)) {
                    return true;
                }
            }
            return false;
        }

        // Check if COD is blocked by any applied coupon.
        private function is_cod_blocked_by_coupons() {
            $applied_coupons = WC()->cart->get_applied_coupons();
            if (!empty($applied_coupons)) {
                foreach ($applied_coupons as $coupon_code) {
                    $coupon = new WC_Coupon($coupon_code);
                    if ($coupon->get_meta(EA_COD_BLOCK_META)) {
                        return true;
                    }
                }
            }
            return false;
        }
        
		// Check if COD is blocked for the current user.
        private function is_cod_blocked_by_user($user_id) {
            return get_user_meta($user_id, EA_COD_BLOCK_META, true);
        }
		
		// Check if COD is blocked by pincode.
        private function is_cod_blocked_by_pincode($shipping_pincode) {
			$blocked_pincodes = get_option('ea_setting_blocked_pincodes', '');
			if (empty($shipping_pincode) || empty($blocked_pincodes)) {
				return false;
			}
			
			// Trim the shipping pincode as well
			$shipping_pincode = trim($shipping_pincode);
			
			$blocked_pincodes_array = array_map('trim', explode(',', $blocked_pincodes));
			return in_array($shipping_pincode, $blocked_pincodes_array);
        }
        
    }
}
