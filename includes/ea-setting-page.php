<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('EasyAppSetting')) {
    class EasyAppSetting {
		
		private $ew_testing_page;

        public function __construct() {
            add_action('init', array($this, 'register_settings'));
            add_action('admin_menu', array($this, 'register_menu_page'));

        }

        public function register_settings() {
            //here is simple example of register settings
            // register_setting('easy-whatsapp-plugin-status', 'plugin_status');

            // General settings
            $settings = [
                'user_access_token',
            ];

            foreach ($settings as $setting) {
                register_setting('easy-app-settings-group', $setting);
            }
        }

        public function register_menu_page() {
            // Menu name -> EasyApp, url -> easy-app-main, callback function ->  dashboard_page, icon -> 'dashicons-smartphone', priority -> 7    
            add_menu_page('EasyApp Dashboard', 'EasyApp', 'manage_options', 'easy-app-main', array($this, 'dashboard_page'), 'dashicons-smartphone', 7 );
			// add_submenu_page('easy-app-main','Test massages','Test Massages','manage_options','easy-whatsapp-test-msg', array($this->$ew_testing_page, 'test_massages_page') );
        }
		
        //Page design start from here
        public function dashboard_page() {
            ?>
            <div class="wrap">
                <h1>Easy WhatsApp API Settings</h1>

                <!-- form for setting -->
                <form method="post" action="options.php">
                    <?php settings_fields('easy-app-settings-group'); ?>
                    <?php do_settings_sections('easy-app-settings-group'); ?>
                    <table class="form-table">	
						
						<!-- User access token -->
						<tr valign="top">
							<th scope="row">User access token</th>
							<td><input type="text" name="user_access_token" placeholder="EAAxxxxx" value="<?php echo esc_attr(get_option('user_access_token')); ?>" />
							</td>
						</tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </div>
            <?php
        }
    }
}

