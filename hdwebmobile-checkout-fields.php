<?php

/**
 * Plugin Name: HDWebmobile Checkout Fields
 * Plugin URI: https://hdwebmobile.com/plugins/hdwebmobile-checkout-fields/
 * Description: Add your own text, dropdown, and checkbox fields to checkout. Works on both classic and block-based Checkout, with no file-upload field type and no unauthenticated write path.
 * Version: 1.0.0
 * Author: htrxuan - Han Tran
 * Author URI: https://hdwebmobile.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hdwebmobile-checkout-fields
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Requires at least: 6.9
 */

namespace htrxuan\hdcf;

if (!defined('ABSPATH')) {
    exit;
}

define('HDCF_VERSION', '1.0.0');
define('HDCF_PLUGIN_FILE', __FILE__);
define('HDCF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HDCF_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once HDCF_PLUGIN_DIR . 'includes/class-hdcf-activator.php';

register_activation_hook(__FILE__, array(HDCF_Activator::class, 'activate'));

add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', HDCF_PLUGIN_FILE, true);
    }
});

add_action('plugins_loaded', function () {
    require_once HDCF_PLUGIN_DIR . 'includes/class-hdcf-core.php';
    HDCF_Core::get_instance();
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $donate_link = '<a href="https://paypal.me/htrxuan/20" target="_blank" rel="noopener noreferrer">' . esc_html__('Donate', 'hdwebmobile-checkout-fields') . '</a>';
    array_unshift($links, $donate_link);
    return $links;
});
