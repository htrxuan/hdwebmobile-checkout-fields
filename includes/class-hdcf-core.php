<?php

namespace htrxuan\hdcf;

if (!defined('ABSPATH')) {
    exit;
}

final class HDCF_Core
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->includes();
        $this->init_hooks();
    }

    private function __clone()
    {
    }

    private function includes()
    {
        require_once HDCF_PLUGIN_DIR . 'includes/class-hdcf-repository.php';
        require_once HDCF_PLUGIN_DIR . 'includes/class-hdcf-admin.php';
        require_once HDCF_PLUGIN_DIR . 'includes/class-hdcf-checkout-blocks.php';
        require_once HDCF_PLUGIN_DIR . 'includes/class-hdcf-checkout-classic.php';
    }

    private function init_hooks()
    {
        add_action('admin_notices', array($this, 'render_missing_woocommerce_notice'));

        if (!class_exists('WooCommerce')) {
            return;
        }

        // HDCF_Admin owns the hdwebmobile_hub_tabs registration used by the shared hub page,
        // so it must load unconditionally (not only when is_admin()).
        HDCF_Admin::get_instance();

        // Registering the fields on the block Checkout also feeds WooCommerce's own order /
        // email display layer, so it runs regardless of which checkout the store uses.
        HDCF_Checkout_Blocks::get_instance();
        HDCF_Checkout_Classic::get_instance();
    }

    public function render_missing_woocommerce_notice()
    {
        $screen = get_current_screen();
        if (!$screen || 'plugins' !== $screen->id) {
            return;
        }

        if (!get_transient('hdcf_wc_missing_notice')) {
            return;
        }
        delete_transient('hdcf_wc_missing_notice');
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php esc_html_e('HDWebmobile Checkout Fields requires WooCommerce to be installed and active. The plugin has been deactivated.', 'hdwebmobile-checkout-fields'); ?>
            </p>
        </div>
        <?php
    }
}
