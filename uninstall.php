<?php
/**
 * Uninstall script for Nekuda MCT Cookie Notice
 *
 * Removes all plugin options from the database when the plugin is deleted.
 *
 * @package Nekuda_MCT_Cookie_Notice
 */

// Exit if not called by WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove all plugin options
$options = [
    'message',
    'link_text',
    'link_url',
    'button_text',
    'color_bg',
    'color_text',
    'color_link',
    'color_btn_bg',
    'color_btn_text',
    'color_btn_border',
];

foreach ($options as $option) {
    delete_option('nekuda_mct_cookie_' . $option);
}
