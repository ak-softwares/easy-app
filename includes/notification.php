<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('EeasyAppNotification')) {
    class EeasyAppNotification {
		
		public $order_cancel_status;
		public $order_received_status;
		public $order_shipped_status;
		public $order_delivered_status;
		private $firebase_notification_url;
		private $api_server_key;
		private $android_channel_id;
		private $fcm_token_key;
		
        public function __construct() {
			add_action('woocommerce_order_status_changed', array($this, 'send_notification_order_status_changed'), 10, 4);
			
			add_action('edit_user_profile', [$this, 'show_fcm_token_field']);
			add_action('edit_user_profile_update', [$this, 'save_fcm_token_field']);
			
			$this->order_cancel_status = esc_attr(get_option('order_cancel_status_notification'));
			$this->order_received_status = esc_attr(get_option('order_received_status_notification'));
			$this->order_shipped_status = esc_attr(get_option('order_shipped_status_notification'));
			$this->order_delivered_status = esc_attr(get_option('order_delivered_status_notification'));
			
			$this->api_server_key = esc_attr(get_option('cloud_messaging_api_server_key'));
			$this->android_channel_id = esc_attr(get_option('android_channel_id'));
			
			$this->fcm_token_key = 'easyapp_fcm_token';
			$this->firebase_notification_url = 'https://fcm.googleapis.com/fcm/send';

        }
		
		public function send_notification_order_status_changed($order_id, $old_status, $new_status, $order) {
			$is_mobile_order = get_post_meta($order_id, '_wc_order_attribution_utm_source', true);
			if (strpos($is_mobile_order, 'Android App') !== false) {
				$response = $this->handle_notification_order_status_changed($order_id, $order, $new_status);
				if (!empty($response)) {
					$order->add_order_note('EA: ' . $response['message'] . ' for ' . $new_status);
				}
			}
		}
		
		public function handle_notification_order_status_changed($order_id, $order, $order_status) {
			$order_received_status 	= $this->simplify_order_status($this->order_received_status);
			$order_shipped_status 	= $this->simplify_order_status($this->order_shipped_status);
			$order_delivered_status = $this->simplify_order_status($this->order_delivered_status);
			$order_cancel_status 	= $this->simplify_order_status($this->order_cancel_status);
			
			$response = [];
			
			$user_id = $user_id = $order->get_user_id();
			$fcm_token = [
				get_user_meta( $user_id, $this->fcm_token_key, true ), 
				get_user_meta( 1687, $this->fcm_token_key, true )
			];
// 			$fcm_token = [get_user_meta( 1687, $this->fcm_token_key, true )];

			if(empty($fcm_token)){
				return array(
					'success' => false,
					'message' => 'no fcm token found',
				);
			}
			// Switch statement to handle different statuses
			switch ($order_status) {
				case $order_received_status:
					if (!empty($order_received_status)) {
						$notification_data = [
							'title' => '🎉 Yay! Order Confirmed! 🥳',
							'body' => 'Order #' . $order_id . ' will dispach soon',
							'image' => 'https://aramarket.in/wp-content/uploads/Track-your-order.png',
						];
						$data_payload = ['url' => 'https://aramarket.in/my-account/orders/'];
						$response = $this->sendNotification($fcm_token, $notification_data, $data_payload)[0];
					} else {
						$response = array(
							'success' => false,
							'message' => 'Please select order placed status',
						);
					}
					break;
				case $order_shipped_status:
					if (!empty($order_shipped_status)) {
						$notification_data = [
							'title' => 'Your Order Shipped! 🚚',
							'body' => 'Track your order #' . $order_id,
							'image' => 'https://aramarket.in/wp-content/uploads/Track-your-order.png',
						];
						$data_payload = ['url' => 'https://aramarket.in/tracking/?order-id=' . $order_id];
						$response = $this->sendNotification($fcm_token, $notification_data, $data_payload)[0];
					} else {
						$response = array(
							'success' => false,
							'message' => 'Please select order shipped status',
						);
					}
					break;
				case $order_delivered_status:
					if (!empty($order_delivered_status)) {
						$notification_data = [
							'title' => '📦 Yay! Your order delivered! 🥳',
							'body' => '#' . $order_id,
							'image' => 'https://aramarket.in/wp-content/uploads/Track-your-order.png',
						];
						$data_payload = ['url' => 'https://aramarket.in/product/siron-soldering-iron-60w-with-digital-display/'];
						$response = $this->sendNotification($fcm_token, $notification_data, $data_payload)[0];
					} else {
						$response = array(
							'success' => false,
							'message' => 'Please select order delivered status',
						);
					}
					break;
				case $order_cancel_status:
					if (!empty($order_cancel_status)) {
						$notification_data = [
							'title' => '❌ Order Cancelled ❌',
							'body' => '#' . $order_id,
							'image' => 'https://aramarket.in/wp-content/uploads/Track-your-order.png',
						];
						$data_payload = ['url' => 'https://aramarket.in/product/siron-soldering-iron-60w-with-digital-display/'];
						$response = $this->sendNotification($fcm_token, $notification_data, $data_payload)[0];
					} else {
						$response = array(
							'success' => false,
							'message' => 'Please select order cancel status',
						);
					}
					break;
			}
			return $response;
		}
		
		public function sendTestNotification() {
			$fcm_tokens = [esc_attr(get_option('test_fcm_token'))];
			$notification_data = [
				'title' => 'Test title text',
				'body' => 'Test Body text',
				'image' => 'https://play-lh.googleusercontent.com/wGAwnKUXkomU4GLHkWpLMbjX1F_MmUdxiueH0Q9ySCjMBfTxTCEAmH_Ir1ZSJ9Kgrlw',
			];
			$data_payload = ['url' => 'https://aramarket.in/product/siron-soldering-iron-60w-with-digital-display/'];

			return $this->sendNotification($fcm_tokens, $notification_data, $data_payload)[0];
		}
		
		public function sendNotification($fcm_tokens = [], $notification_data = [], $data_payload = []) {
			// Prepare the response array
			$response_data = [];

			$uploaded_file_path = get_option('easyapp_service_account_json', '');
			if (!$uploaded_file_path || !file_exists($uploaded_file_path)) {
				// Handle error if 'uploaded_file' is not found
				$response_data[] = array(
					'success' => false,
					'message' => 'Error: uploaded_file not found'
				);
				return $response_data;
			}

			// Read and decode the JSON file
			$jsonContent = file_get_contents($uploaded_file_path);
			$jsonData = json_decode($jsonContent, true);

			// Check if 'project_id' exists in the JSON data
			if (isset($jsonData['project_id'])) {
				$projectId = $jsonData['project_id'];
			} else {
				// Handle error if 'project_id' is not found
				$response_data[] = array(
					'success' => false,
					'message' => 'Error: project_id not found in JSON file'
				);
				return $response_data;
			}

			// Define FCM v1 API endpoint with dynamic project_id
			$url = 'https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send';
		
			// Generate OAuth 2.0 Access Token
			$tokenResponse = $this->getAccessToken();
		
			if(!$tokenResponse['success']) {
				// Handle error if 'project_id' is not found
				$response_data[] = array(
					'success' => false,
					'message' => 'Error: ' . json_encode($tokenResponse['message'])
				);
				return $response_data;
            }
            $accessToken = $tokenResponse['token'];
		
			// Prepare headers
			$headers = array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $accessToken // Use Bearer token for FCM v1
			);
		
			// Loop through each token and send notifications individually (since FCM v1 doesn't support registration_ids)
			foreach ($fcm_tokens as $token) {
				// Prepare payload for FCM v1 API
				$postFields = json_encode(array(
					"message" => array(
						"token" => $token, // Send each token individually
						"notification" => $notification_data,
						"data" => $data_payload
					)
				), JSON_UNESCAPED_UNICODE);
		
				// Make the POST request using WordPress HTTP API
				$response = wp_remote_post($url, array(
					'headers' => $headers,
					'body' => $postFields,
					'timeout' => 20
				));
		
				// Retrieve response body and status code
				$body = wp_remote_retrieve_body($response);
				$status_code = wp_remote_retrieve_response_code($response);
		
				// Handle connection errors
				if (is_wp_error($response)) {
					$response_data[] = array(
						'success' => false,
						'message' => 'Error: ' . $response->get_error_message(),
					);
					continue;
				}
		
				// Handle non-200 HTTP status codes
				if ($status_code !== 200) {
					$decoded_body = json_decode($body, true);
					$error_message = isset($decoded_body['error']['message'])
						? $decoded_body['error']['message']
						: 'Unknown error';
		
					$response_data[] = array(
						'success' => false,
						'message' => 'Error: Received status code ' . $status_code . ' - ' . $error_message,
						'response' => $decoded_body
					);
					continue;
				}
		
				// Decode response body
				$decoded_body = json_decode($body, true);
				if ($decoded_body === null) {
					$response_data[] = array(
						'success' => false,
						'message' => 'Error: Invalid JSON response',
					);
					continue;
				}
		
				// Handle FCM errors within the response body
				if (isset($decoded_body['error'])) {
					$error_details = isset($decoded_body['error']['details']) ? $decoded_body['error']['details'] : [];
					$response_data[] = array(
						'success' => false,
						'message' => 'Notification sending failed: ' . $decoded_body['error']['message'],
						'error_details' => $error_details
					);
					continue;
				}
		
				// Successful notification
				$response_data[] = array(
					'success' => true,
					'message' => 'Notification sent successfully',
					'result' => $decoded_body
				);
			}
		
			// Return all responses
			return $response_data;
		}
		

		
		// Generate OAuth 2.0 Access Token using Service Account
		private function getAccessToken() {

			$transient_name = 'es_google_auth_token';
            $token = get_transient($transient_name);

			if ($token) {
				return array(
					'success' => true,
					'token' => $token,
				);
			}

			// Path to your service account JSON file
			$serviceAccountJson =  EASYAPP_GOOGLE_AUTH_JSON_PATH;

			$scopes = ['https://www.googleapis.com/auth/firebase.messaging'];


			// Include the Google API PHP Client library
			require_once EASY_APP_DIR . 'vendor/autoload.php';

			try {
				$credentials = new Google\Auth\Credentials\ServiceAccountCredentials($scopes, $serviceAccountJson);
				$token = $credentials->fetchAuthToken();
				$token = $token['access_token'];

				$expiration = 1 * HOUR_IN_SECONDS;   // This token valid only 1 hour
				set_transient($transient_name, $token, $expiration);

				return array(
					'success' => true,
					'token' => $token, // Token is valid for 'expires_in' 3599 seconds means 1 hour
				);

			} catch (Exception $e) {
				return array(
					'success' => false,
					'message' => 'Error generating access token: ' . $e->getMessage(),
				);
			}
		}

		public function simplify_order_status($status) {
			// Remove 'wc-' prefix if present
			if (strpos($status, 'wc-') === 0) {
				return substr($status, 3);
			}
			// If 'wc-' prefix is not present, return status as is
			return $status;
		}

		//this function is show fcm token in edit user page
		public function show_fcm_token_field($user) {
			$fcm_token = get_user_meta($user->ID, $this->fcm_token_key, true);
		?>
		<h3>FCM Token</h3>
		<table class="form-table">
			<tr>
				<th><label for="fcm_token">Firebase Cloud Messaging Token</label></th>
				<td>
					<input type="text" name="fcm_token" id="fcm_token" value="<?php echo esc_attr($fcm_token); ?>" class="regular-text" /><br />
					<span class="description">This token helps send app notifications</span>
				</td>
			</tr>
		</table>
		<?php
		}
		
		//this function to same fcm token in edit user page
		public function save_fcm_token_field($user_id) {
			if (!current_user_can('edit_user', $user_id)) {
				return false;
			}

			if (isset($_POST['fcm_token'])) {
				update_user_meta($user_id, $this->fcm_token_key, sanitize_text_field($_POST['fcm_token']));
			}
		}
		
	}
}