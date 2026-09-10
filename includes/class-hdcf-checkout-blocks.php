<?php

namespace htrxuan\hdcf;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers every admin-defined field on the block-based Checkout via
 * woocommerce_register_additional_checkout_field() -- WooCommerce's own native mechanism
 * (confirmed in src/Blocks/Domain/Services/CheckoutFields.php: supported types are exactly
 * text / select / checkbox, locations are contact / address / order). WooCommerce renders the
 * input itself (no custom React/JS), stores the value in `_wc_other/{id}` order meta, and
 * shows it on the admin order screen and in emails through its own escaping display layer.
 *
 * Registration runs regardless of which checkout the store actually uses, because that same
 * registration is what powers the order/email display for values saved by the classic-checkout
 * path too (HDCF_Checkout_Classic writes to the identical meta keys).
 */
class HDCF_Checkout_Blocks
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
        add_action('woocommerce_init', array($this, 'register_fields'));
    }

    public function register_fields()
    {
        if (!function_exists('woocommerce_register_additional_checkout_field')) {
            return;
        }

        foreach (HDCF_Repository::get_fields() as $field) {
            $args = array(
                'id'                => HDCF_Repository::field_id($field['key']),
                'label'             => $field['label'],
                'location'          => $field['location'],
                'type'              => $field['type'],
                'required'          => (bool) $field['required'],
                'sanitize_callback' => function ($value) use ($field) {
                    return HDCF_Repository::sanitize_field_value($field, $value);
                },
                'validate_callback' => function ($value) use ($field) {
                    $result = HDCF_Repository::validate_field_value($field, $value);
                    if (true !== $result) {
                        return new \WP_Error('hdcf_invalid_' . $field['key'], $result);
                    }
                    return true;
                },
            );

            if ('select' === $field['type']) {
                $args['options'] = array();
                foreach ($field['options'] as $opt) {
                    $args['options'][] = array('value' => $opt, 'label' => $opt);
                }
            }

            // WooCommerce's own duplicate guard fires a _doing_it_wrong on a second
            // registration of the same id; guard here so a plugin reload / double init stays
            // quiet.
            try {
                woocommerce_register_additional_checkout_field($args);
            } catch (\Throwable $e) {
                // A malformed definition should never take checkout down -- skip that one field.
                continue;
            }
        }
    }
}
