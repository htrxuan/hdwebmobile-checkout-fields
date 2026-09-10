<?php

namespace htrxuan\hdcf;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classic (shortcode) Checkout support. woocommerce_register_additional_checkout_field() is a
 * block-checkout-only mechanism, so classic checkout needs this separate hook-based render /
 * validate / save. It writes to the exact same `_wc_other/{field_id}` order-meta keys the
 * Blocks API uses, so the admin Edit Order screen and order emails display the values
 * identically no matter which checkout a shopper used.
 *
 * Every value goes through the same HDCF_Repository::sanitize_field_value() /
 * validate_field_value() as the block path -- neither checkout can accept something the other
 * rejects, and a stored value is always a plain scalar of a known type.
 */
class HDCF_Checkout_Classic
{

    private static $instance = null;

    const META_PREFIX = '_wc_other/';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('woocommerce_after_checkout_billing_form', array($this, 'render_contact_address_fields'));
        add_action('woocommerce_before_order_notes', array($this, 'render_order_fields'));
        add_action('woocommerce_checkout_process', array($this, 'validate_fields'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_fields'));
    }

    public function render_contact_address_fields($checkout)
    {
        $this->render_fields_for(array('contact', 'address'), $checkout);
    }

    public function render_order_fields($checkout)
    {
        $this->render_fields_for(array('order'), $checkout);
    }

    private function render_fields_for(array $locations, $checkout)
    {
        $fields = array_filter(HDCF_Repository::get_fields(), function ($field) use ($locations) {
            return in_array($field['location'], $locations, true);
        });
        if (empty($fields)) {
            return;
        }

        echo '<div class="hdcf-checkout-fields">';
        foreach ($fields as $field) {
            $id   = HDCF_Repository::field_id($field['key']);
            $args = array(
                'label'    => $field['label'],
                'required' => (bool) $field['required'],
                'class'    => array('hdcf-field', 'hdcf-field--' . $field['type']),
            );

            switch ($field['type']) {
                case 'checkbox':
                    $args['type'] = 'checkbox';
                    break;
                case 'select':
                    $args['type']    = 'select';
                    $args['options'] = array('' => esc_html__('Choose&hellip;', 'hdwebmobile-checkout-fields'));
                    foreach ($field['options'] as $opt) {
                        $args['options'][$opt] = $opt;
                    }
                    break;
                case 'text':
                default:
                    $args['type']      = 'text';
                    $args['maxlength'] = HDCF_Repository::MAX_TEXT_LENGTH;
                    break;
            }

            woocommerce_form_field($id, $args, $checkout->get_value($id));
        }
        echo '</div>';
    }

    /**
     * @return array<string, string> field_id => sanitised value, for every defined field.
     */
    private function collect_submitted()
    {
        $values = array();
        foreach (HDCF_Repository::get_fields() as $field) {
            $id = HDCF_Repository::field_id($field['key']);
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WooCommerce's own checkout nonce is verified upstream by WC_Checkout::process_checkout() before these hooks fire; the value is sanitised on the next line by the type-aware repository sanitiser.
            $raw = isset($_POST[$id]) ? wp_unslash($_POST[$id]) : '';
            $values[$id] = HDCF_Repository::sanitize_field_value($field, $raw);
        }
        return $values;
    }

    public function validate_fields()
    {
        $values = $this->collect_submitted();
        foreach (HDCF_Repository::get_fields() as $field) {
            $id     = HDCF_Repository::field_id($field['key']);
            $result = HDCF_Repository::validate_field_value($field, isset($values[$id]) ? $values[$id] : '');
            if (true !== $result) {
                wc_add_notice($result, 'error');
            }
        }
    }

    public function save_fields($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $values = $this->collect_submitted();
        foreach (HDCF_Repository::get_fields() as $field) {
            $id  = HDCF_Repository::field_id($field['key']);
            $val = isset($values[$id]) ? $values[$id] : '';

            // A final server-side guard: validate_fields() already blocked checkout with a
            // notice if this was invalid; don't persist bad data even if that hook was bypassed.
            if (true !== HDCF_Repository::validate_field_value($field, $val)) {
                continue;
            }

            if ('' === $val) {
                $order->delete_meta_data(self::META_PREFIX . $id);
            } else {
                $order->update_meta_data(self::META_PREFIX . $id, $val);
            }
        }
        $order->save();
    }
}
