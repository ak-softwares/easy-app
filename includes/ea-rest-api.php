<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('EeasyAppRestAPI')) {
    class EeasyAppRestAPI {
        
        public function __construct() {
            add_action('rest_api_init', array($this, 'register_routes'));
        }
    
        public function register_routes() {
            
            // This route for products sold together
            $this->register_route(
                'flutter-app/v1', '/products-sold-together/',
                'handle_products_sold_together',
                array(
                    'product_id' => array(
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                )
            );

            // This route for sending a reset password link to the user
            $this->register_route(
                'flutter-app/v1', '/reset-password/',
                'handle_password_reset',
                array(
                    'email' => array(
                        'required' => true,
                        'sanitize_callback' => 'sanitize_email',
                    ),
                ),
                WP_REST_Server::CREATABLE
            );

            // This route for fetching customer by phone number
            $this->register_route(
                'flutter-app/v1', '/customer-by-phone/',
                'handle_get_customer_by_phone',
                array(
                    'phone' => array(
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                )
            );

            // This route for user authentication
            $this->register_route(
                'flutter-app/v1', '/authenticate/',
                'handle_user_authentication',
                array(
                    'email' => array(
                        'required' => true,
                        'sanitize_callback' => 'sanitize_email',
                    ),
                    'password' => array(
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
                WP_REST_Server::CREATABLE
            );
        }
    
        private function register_route($namespace, $route, $callback, $args = array(), $methods = WP_REST_Server::READABLE) {
            register_rest_route($namespace, $route, array(
                'methods' => $methods,
                'callback' => array($this, $callback),
                'args' => $args,
            ));
        }
    
        public function handle_products_sold_together($request) {
            global $wpdb;
            $product_id = $request['product_id'];
    
            // Step 1: Get all order IDs containing the specified product ID
            $order_ids = $wpdb->get_col($wpdb->prepare("
                SELECT DISTINCT order_id
                FROM {$wpdb->prefix}woocommerce_order_items oi
                INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
                WHERE oim.meta_key = '_product_id' AND oim.meta_value = %d
            ", $product_id));
    
            if (!is_array($order_ids) || empty($order_ids)) {
                return rest_ensure_response(array());
            }
    
            $products_sold = array();
    
            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
    
                if (!$order) {
                    continue;
                }
    
                foreach ($order->get_items() as $item) {
                    $item_product_id = $item->get_product_id();
                    $quantity = $item->get_quantity();
    
                    if ($item_product_id != $product_id) {
                        if (isset($products_sold[$item_product_id])) {
                            $products_sold[$item_product_id] += $quantity;
                        } else {
                            $products_sold[$item_product_id] = $quantity;
                        }
                    }
                }
            }
    
            // Sort the array by quantity sold in descending order
            arsort($products_sold);
    
            // Extract only the keys (product IDs)
            $product_ids = array_keys($products_sold);
    
            // Slice the array to only include the top 9 products
            $top_9_products = array_slice($product_ids, 0, 9, true);
    
            // Return the response with top 9 products
            return rest_ensure_response($top_9_products);
        }
    
        public function handle_password_reset($request) {
            $parameters = $request->get_json_params();
            
            $data = [];
            // Check if email is provided
            if (isset($parameters['email'])) {
                $email = $parameters['email'];
    
                // Check if the user exists
                $user_data = get_user_by('email', $email);
                if (!$user_data) {
                    // Set response status code to 404
                    $data = array(
                        'success' => false,
                        'message' => 'User not found with that email address',
                    );
                    return rest_ensure_response($data);
                }
    
                // Generate password reset key
                $reset_key = get_password_reset_key($user_data);
    
                if (is_wp_error($reset_key)) {
                    $data = array(
                        'success' => false,
                        'message' => $reset_key->get_error_message(),
                    );
                    return rest_ensure_response($data);
                }
    
                // Generate password reset link
                $reset_link = network_site_url("my-account/lost-password/?action=rp&key=$reset_key&login=" . rawurlencode($user_data->user_login), 'login');
    
                // Send password reset email
                $subject = __('Password Reset');
                $message = sprintf('Hi: %s', $email) . "\r\n\r\n";
                $message .= sprintf('Someone requested that the password be reset for the following account: %s', $email) . "\r\n\r\n";
                $message .= __('If this was a mistake, just ignore this email and nothing will happen.') . "\r\n\r\n";
                $message .= __('To reset your password, visit the following link:') . "\r\n\r\n";
                $message .= $reset_link . "\r\n";
    
                if (wp_mail($email, $subject, $message) === false) {
                    $data = array(
                        'success' => false,
                        'message' => 'Failed to send password reset email',
                    );
                    return rest_ensure_response($data);
                } else {
                    $data = array(
                        'success' => true,
                        'message' => 'Password reset email sent successfully',
                    );
                    return rest_ensure_response($data);
                }
    
            } else {
                $data = array(
                    'success' => false,
                    'message' => 'Email is required',
                );
                return rest_ensure_response($data);
            }
        }
        
        public function handle_get_customer_by_phone($request) {
            global $wpdb;
            $phone = $request['phone'];
        
            // Search for customer ID using phone number in user meta
            $customer_id = $wpdb->get_var($wpdb->prepare("
                SELECT user_id
                FROM {$wpdb->usermeta}
                WHERE meta_key = 'billing_phone'
                AND meta_value = %s
            ", $phone));
        
            if ($customer_id) {
                // Get customer email using the customer ID
                $customer_email = get_userdata($customer_id)->user_email;
        
                // Customer found, return email
                return rest_ensure_response(array('id' => $customer_id, 'email' => $customer_email));
            } else {
                // Customer not found
                return new WP_Error('customer_not_found', 'Customer not found', array('status' => 404));
            }
        }
        
    
        public function handle_user_authentication($request) {
            $parameters = $request->get_json_params();
            // Check if email and password are provided
            if (isset($parameters['email']) && isset($parameters['password'])) {
                $email = $parameters['email'];
                $password = $parameters['password'];
    
                // Check if the user exists
                $user_data = get_user_by('email', $email);
                if (!$user_data) {
                    // Set response status code to 404
                    status_header(404);
                    return array(
                        'success' => false,
                        'message' => 'User not found with that email address',
                    );
                }
    
                // Your authentication logic here
                // For example, you can use wp_authenticate function to validate email and password
                $user = wp_authenticate($email, $password);
    
                if (is_wp_error($user)) {
                    // Set response status code to 401
                    status_header(401);
                    return array(
                        'success' => false,
                        'message' => 'Invalid email or password',
                    );
                } else {
                    return array(
                        'success' => true,
                        'message' => 'Authentication successful',
                        'user_id' => $user->ID,
                    );
                }
            } else {
                // Set response status code to 401
                status_header(400);
                return array(
                    'success' => false,
                    'message' => 'Email and password are required',
                );
            }
        }
    }
}
