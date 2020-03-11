<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}
 
$options = array(
	'stgcdn_replacement_url',
	'stgcdn_settings'
);

foreach( $options as $option_name ) {
	delete_option($option_name);
}