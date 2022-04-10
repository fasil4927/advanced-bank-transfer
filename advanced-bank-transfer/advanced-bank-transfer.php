<?php

/**
 * Plugin Name:       Advanced Bank Transfer
 * Plugin URI:        https://example.com/plugins/the-basics/
 * Description:       Handle the basics with this plugin.
 * Version:           1.0.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Fasil PT
 * Author URI:        https://stackoverflow.com/users/5695033/fasil-palanthodi
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://example.com/my-plugin/
 * Text Domain:       advanced-bank-transfer
 * Domain Path:       /languages
 */
if (!defined('WPINC')) {
    die;
}

/**
 * Activate the plugin.
 */
function pluginprefix_activate() {    
}
register_activation_hook(__FILE__, 'abt_check');

/**
 * Deactivation hook.
 */
function pluginprefix_deactivate() {
}

register_deactivation_hook(__FILE__, 'pluginprefix_deactivate');
//

add_action('plugins_loaded', 'abt_check', 99);

function abt_check() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'abt_wc_admin_notices', 99);
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        deactivate_plugins(plugin_basename(__FILE__));
    }
    require plugin_dir_path(__FILE__) . 'includes/abt.php';
}

function abt_wc_admin_notices() {
    is_admin() && add_filter('gettext', function ($translated_text, $untranslated_text, $domain) {
                $old = array(
                    "Plugin <strong>deactivated</strong>.",
                    "Selected plugins <strong>deactivated</strong>.",
                    "Plugin deactivated.",
                    "Selected plugins deactivated.",
                    "Plugin <strong>activated</strong>.",
                    "Selected plugins <strong>activated</strong>.",
                    "Plugin activated.",
                    "Selected plugins activated."
                );
                $new = "Advanced Bank Transfer Plugin Needs WooCommerce to Work.";
                if (in_array($untranslated_text, $old, true)) {
                    $translated_text = $new;
                }
                return $translated_text;
            }, 99, 3);
}
