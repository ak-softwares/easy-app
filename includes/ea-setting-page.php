<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('EasyAppSetting')) {
    class EasyAppSetting {
		
		private $ew_testing_page;

        public function __construct() {
            add_action('init', array($this, 'register_settings'));
            add_action('init', array($this, 'register_notification_settings'));
            add_action('admin_menu', array($this, 'register_menu_page'));
            add_action('admin_menu', array($this, 'rename_default_submenu'), 999);
            // add_action('admin_post_ea_upload_google_auth_json', array($this, 'handle_file_upload'));
        }

        public function register_settings() {
            // General settings
            $settings = [
				'ea_banner1_image_url',
				'ea_banner1_target_screen',
				'ea_banner2_image_url',
				'ea_banner2_target_screen',
				'ea_banner3_image_url',
				'ea_banner3_target_screen',
				'ea_setting_blocked_pincodes',
            ];

            foreach ($settings as $setting) {
                register_setting('easy-app-settings-group', $setting, 'sanitize_textarea_field');
            }
        }
		
        public function register_notification_settings() {
            //here is simple example of register settings
            // register_setting('easy-whatsapp-plugin-status', 'plugin_status');

            // General settings
            $settings = [
				'android_channel_id',
				'order_cancel_status_notification',
                'order_received_status_notification',
                'order_shipped_status_notification',
                'order_delivered_status_notification',
				'test_fcm_token', // FCM - Firebase Cloud Messaging 
            ];

            foreach ($settings as $setting) {
                register_setting('easy-app-notification-group', $setting);
            }
        }

        public function register_menu_page() {
            // Menu name -> EasyApp, url -> easy-app-main, callback function ->  dashboard_page, icon -> 'dashicons-smartphone', priority -> 7    
			add_menu_page('EasyApp Dashboard', 'EasyApp', 'manage_options', 'easy-app-main', array($this, 'easyapp_settings'), 'dashicons-smartphone', 7 );
			add_submenu_page('easy-app-main','Notification Settings','Notification Settings','manage_options','ea-notification-settings', array($this, 'notification_settings') );
			// Hook to change the default submenu
        }
		
		public function rename_default_submenu() {
			global $submenu;

			// Check if the EasyApp Dashboard menu exists
			if (isset($submenu['easy-app-main'])) { // Replace with the slug of your menu
				foreach ($submenu['easy-app-main'] as &$item) {
					// Rename the desired submenu item
					if ($item[2] === 'easy-app-main') { // Replace with the current submenu title
						$item[0] = 'EasyApp Settings'; // Replace with your new title
						break;
					}
				}
			}
		}
		
        //Page design start from here
        public function notification_settings() {
            ?>
            <div class="wrap">
                <h1>Easy App Notification Settings</h1>

				<!-- form for upload json file for google auth -->
                <form method="post" enctype="multipart/form-data">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Upload Google Auth Json file</th>
                            <td>
                                <input type="file" name="ea_upload_service_account_file" accept=".json" />
                                <input type="submit" name="ea_upload_google_auth_json" class="button-primary" value="Upload" />
								<br />
								<?php
									$uploaded_file_path = get_option('easyapp_service_account_json', '');
									if ($uploaded_file_path && file_exists($uploaded_file_path)) {
										echo '<div style="display: flex; align-items: center; gap: 10px;">';
										echo '<a href="' . esc_url(str_replace(EASY_APP_DIR, EASYAPP_URL, EASYAPP_GOOGLE_AUTH_JSON_PATH)) . '" class="button" download>Download</a>';
										echo '<p style="margin: 0;">Download uploaded JSON to verify</p>';
										echo '</div>';									
									}
								?>
								<!-- <p>Download uploaded json to verify</p> -->
								<br />
								<a href="https://www.google.com" target="_blank">Click here to get Google Auth file</a>
							</td>
                        </tr>
                    </table>
                </form>

                <!-- form for setting -->
                <form method="post" action="options.php">
                    <?php settings_fields('easy-app-notification-group'); ?>
                    <?php do_settings_sections('easy-app-notification-group'); ?>
                    <table class="form-table">	
						
						<!-- <tr valign="top">
							<th scope="row">Android channel id</th>
							<td><input type="text" name="android_channel_id" placeholder="puxxxxxxx" value="<?php echo esc_attr(get_option('android_channel_id')); ?>" />
							</td>
						</tr> -->
						
						<?php
						// order status and templat
                        $statuses = [
                            'order_received' => 'Select order received status',
                            'order_shipped' => 'Select order shipped status',
                            'order_delivered' => 'Select order delivered status',
							'order_cancel' => 'Select order cancel status',
                        ];

                        foreach ($statuses as $status => $label) {
                            echo '<tr valign="top">
                                    <th scope="row">' . esc_html($label) . '</th>
                                    <td>
                                        <select name="' . esc_attr($status) . '_status_notification">
                                            <option value="">-- Select Status --</option>';
                                            if (function_exists('wc_get_order_statuses')) {
                                                $order_statuses = wc_get_order_statuses();
                                                $selected_status = get_option($status . '_status_notification');
                                                foreach ($order_statuses as $order_status => $status_label) {
                                                    $selected = selected($order_status, $selected_status, false);
                                                    echo '<option value="' . esc_attr($order_status) . '" ' . $selected . '>' . esc_html($status_label) . '</option>';
                                                }
                                            } else {
                                                echo '<option value="" disabled>WooCommerce not active</option>';
                                            }
                            echo ' </select>
                                    </td>
                                  </tr>';
                        }
						?>
						<tr valign="top">
							<th scope="row">Test FCM Token</th>
							<td><input type="text" name="test_fcm_token" placeholder="dbn7khYETfiHExxxx" value="<?php echo esc_attr(get_option('test_fcm_token')); ?>" />
							</td>
						</tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </div>

			<?php
				if (isset($_POST['ea_upload_google_auth_json']) && isset($_FILES['ea_upload_service_account_file'])) {
					$uploaded_file = $_FILES['ea_upload_service_account_file'];
			
					// Check for errors
					if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
						echo '<div class="notice notice-error is-dismissible"><p>Error uploading the file.</p></div>';
						return;
					}
			
					// Check file type (only allow .json)
					$file_info = pathinfo($uploaded_file['name']);
					$file_extension = strtolower($file_info['extension']); // Get the extension in lowercase
					if ($file_extension !== 'json') {
						echo '<div class="notice notice-error is-dismissible"><p>Invalid file type. Please upload a .json file.</p></div>';
						return;
					}
			
					// Ensure the directory exists
					if (!file_exists(EASYAPP_UPLOAD_DIR)) {
						mkdir(EASYAPP_UPLOAD_DIR, 0777, true);
					}
			
					// Move the uploaded file to the target directory
					if (move_uploaded_file($uploaded_file['tmp_name'], EASYAPP_GOOGLE_AUTH_JSON_PATH)) {
						update_option('easyapp_service_account_json', EASYAPP_GOOGLE_AUTH_JSON_PATH); // Store the file path in WordPress options
						echo '<div class="notice notice-success is-dismissible"><p>File uploaded successfully.</p></div>';
					} else {
						echo '<div class="notice notice-error is-dismissible"><p>Error moving the file.</p></div>';
					}
				}

				// test notification code
				if (isset($_POST['ea_test_notification']))  {
					$response = (new EeasyAppNotification())->sendTestNotification();
					// Display the success or error message
					if ($response['success']) {
						echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($response['message']) . '</p></div>';
					} else {
						echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($response['message']) . '</p></div>';
					}
				}
			?>
				<form method="post" action="">
					<table class="form-table">
						<tr valign="top">
							<th scope="row">Test Notification</th>
							<td><input type="submit" name="ea_test_notification" class="button-primary" value="Test Notification"></td>
						</tr>
					</table>
				</form>
            <?php 
        }
		
		
		public function easyapp_settings() {
			?>
            <div class="wrap">
				<h1><?php _e('EasyApp Settings', 'woocommerce'); ?></h1>

                <!-- form for setting -->
                <form method="post" action="options.php">
                    <?php settings_fields('easy-app-settings-group'); ?>
                    <?php do_settings_sections('easy-app-settings-group'); ?>
                    <table class="form-table">
						<!-- Banner 1 -->
						<tr>
							<th scope="row"><?php _e('Banner 1', 'easyapp'); ?></th>
							<td>
								<input 
									   type="url" 
									   name="ea_banner1_image_url" 
									   id="ea_banner1_image_url" 
									   value="<?php echo esc_url(get_option('ea_banner1_image_url', '')); ?>" 
									   class="regular-text" 
									   placeholder="<?php _e('Enter Banner 1 Image URL', 'easyapp'); ?>">
								<p>
									<input 
										   type="text" 
										   name="ea_banner1_target_screen" 
										   id="ea_banner1_target_screen" 
										   value="<?php echo esc_attr(get_option('ea_banner1_target_screen', '')); ?>" 
										   class="regular-text" 
										   placeholder="<?php _e('Enter Banner 1 Target Screen', 'easyapp'); ?>">
								</p>
							</td>
						</tr>
						<!-- Banner 2 -->
						<tr>
							<th scope="row"><?php _e('Banner 2', 'easyapp'); ?></th>
							<td>
								<input 
									   type="url" 
									   name="ea_banner2_image_url" 
									   id="ea_banner2_image_url" 
									   value="<?php echo esc_url(get_option('ea_banner2_image_url', '')); ?>" 
									   class="regular-text" 
									   placeholder="<?php _e('Enter Banner 2 Image URL', 'easyapp'); ?>">
								<p>
									<input 
										   type="text" 
										   name="ea_banner2_target_screen" 
										   id="ea_banner2_target_screen" 
										   value="<?php echo esc_attr(get_option('ea_banner2_target_screen', '')); ?>" 
										   class="regular-text" 
										   placeholder="<?php _e('Enter Banner 2 Target Screen', 'easyapp'); ?>">
								</p>
							</td>
						</tr>

						<!-- Banner 3 -->
						<tr>
							<th scope="row"><?php _e('Banner 3', 'easyapp'); ?></th>
							<td>
								<input 
									   type="url" 
									   name="ea_banner3_image_url" 
									   id="ea_banner3_image_url" 
									   value="<?php echo esc_url(get_option('ea_banner3_image_url', '')); ?>" 
									   class="regular-text" 
									   placeholder="<?php _e('Enter Banner 3 Image URL', 'easyapp'); ?>">
								<p>
									<input 
										   type="text" 
										   name="ea_banner3_target_screen" 
										   id="ea_banner3_target_screen" 
										   value="<?php echo esc_attr(get_option('ea_banner3_target_screen', '')); ?>" 
										   class="regular-text" 
										   placeholder="<?php _e('Enter Banner 3 Target Screen', 'easyapp'); ?>">
								</p>
							</td>
						</tr>
						
						<tr>
							<th scope="row">
								<label for="ea_setting_blocked_pincodes"><?php _e('Blocked Pincodes', 'woocommerce'); ?></label>
							</th>
							<td>
								<textarea name="ea_setting_blocked_pincodes" id="ea_setting_blocked_pincodes" rows="2" cols="25" class="large-text"><?php echo esc_textarea(get_option('ea_setting_blocked_pincodes', '')); ?></textarea>
								<p class="description"><?php _e('Enter pincodes to block COD, separated by commas (e.g., 123456,654321).', 'woocommerce'); ?></p>
							</td>
						</tr>
					</table>
                    <?php submit_button(__('Save Changes', 'woocommerce')); ?>
                </form>
            </div>
            <?php
		}


		
    }
}

