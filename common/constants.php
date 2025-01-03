<?php

if (!defined('EASYAPP_URL')) {
	define('EASYAPP_URL', plugin_dir_url(dirname(__FILE__))); // This will return the URL up to the /plugins/ directory
}

//define variable for awb and courier name in order meta
if (!defined('EA_COD_BLOCK_META')) {
    define('EA_COD_BLOCK_META', 'easyapp_cod_blocked');
}

if (!defined('EASYAPP_UPLOAD_DIR')) {
    define('EASYAPP_UPLOAD_DIR', EASY_APP_DIR . 'uploads/');
}

if (!defined('EASYAPP_GOOGLE_AUTH_JSON_PATH')) {
	define('EASYAPP_GOOGLE_AUTH_JSON_PATH', EASYAPP_UPLOAD_DIR . 'service-account.json'); 
}
