<?php

namespace htrxuan\hdcf;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The single source of truth for the custom checkout field definitions, and the only place
 * they are ever written.
 *
 * Competing "checkout field editor" plugins have repeatedly shipped two classes of bug this
 * class is built to make impossible:
 *
 *  1. An unauthenticated file-upload endpoint. CVE-2025-12500 (CWE-434, CVSS 5.3) in
 *     "Checkout Field Manager (Checkout Manager) for WooCommerce" (<= 7.8.1): its
 *     `ajax_checkout_attachment_upload` AJAX action ran with no capability check, so any
 *     unauthenticated visitor could upload files to the server. This plugin closes that class
 *     by CONSTRUCTION: there is no "file" field type, no attachment handling, and no AJAX
 *     handler of any kind. A shopper's checkout field can only ever be a line of text, a
 *     choice from an admin-defined dropdown, or a checkbox -- there is nothing to upload to.
 *
 *  2. Stored XSS through an admin-defined label/option or a customer-submitted value that was
 *     rendered without escaping (e.g. the 2026 "Unauthenticated Stored XSS via Block Checkout
 *     Custom Radio Field" disclosure). Closed by construction here too: field definitions
 *     (label, type, options, location) can only change through HDCF_Admin's one
 *     capability- and nonce-checked form; every stored value is normalised by
 *     sanitize_field_value() to a plain scalar of a known type; and every value is only ever
 *     rendered through WooCommerce's own escaping order/email display layer or
 *     woocommerce_form_field() -- never echoed raw. There is no HTML / rich-text field type.
 *
 * save_fields() is called from exactly one place in the whole plugin (HDCF_Admin's
 * admin-post handler), which verifies current_user_can('manage_woocommerce') AND a nonce
 * before a single byte of $_POST is read. Every value is still independently validated here
 * regardless of caller.
 */
class HDCF_Repository
{
    const OPTION_KEY = 'hdcf_fields';

    /** WooCommerce's block Checkout Fields API only supports these three types. Keeping the
     *  plugin to exactly this set means the classic and block checkouts can render an
     *  identical set of fields with no divergence -- and, deliberately, none of them can
     *  carry a file. */
    const TYPES = array('text', 'select', 'checkbox');

    /** Where the field sits on checkout. Mirrors WooCommerce's own additional-field locations. */
    const LOCATIONS = array('contact', 'address', 'order');

    const MAX_TEXT_LENGTH = 255;

    /**
     * @return array<int, array{key: string, label: string, type: string, options: string[], required: bool, location: string}>
     */
    public static function get_fields()
    {
        $fields = get_option(self::OPTION_KEY, array());
        if (!is_array($fields)) {
            return array();
        }

        $clean = array();
        foreach ($fields as $field) {
            if (!is_array($field) || empty($field['key'])) {
                continue;
            }
            $clean[] = array(
                'key'      => (string) $field['key'],
                'label'    => isset($field['label']) ? (string) $field['label'] : (string) $field['key'],
                'type'     => in_array(($field['type'] ?? ''), self::TYPES, true) ? $field['type'] : 'text',
                'options'  => isset($field['options']) && is_array($field['options']) ? array_values(array_map('strval', $field['options'])) : array(),
                'required' => !empty($field['required']),
                'location' => in_array(($field['location'] ?? ''), self::LOCATIONS, true) ? $field['location'] : 'order',
            );
        }
        return $clean;
    }

    public static function get_field($key)
    {
        foreach (self::get_fields() as $field) {
            if ($field['key'] === $key) {
                return $field;
            }
        }
        return null;
    }

    /**
     * The WooCommerce additional-checkout-field id for a stored field key, e.g.
     * "gift-message" -> "hdcf/gift-message". WooCommerce requires the "namespace/name" shape
     * with lowercase letters, digits and hyphens only, which is exactly what get_fields()
     * guarantees for the key.
     */
    public static function field_id($key)
    {
        return 'hdcf/' . $key;
    }

    /**
     * The only write path. $rows is the raw grouped-array POST payload; every field is
     * validated/normalised here and anything malformed is silently dropped rather than guessed
     * at. A row's authorization was already checked by the caller -- this method still never
     * trusts the shape or content of what it is handed.
     *
     * @param array $rows Each: ['key'=>?, 'label'=>?, 'type'=>?, 'options'=>?, 'required'=>?, 'location'=>?]
     * @return array The stored, cleaned field list.
     */
    public static function save_fields(array $rows)
    {
        $fields = array();
        $seen   = array();

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            // Key: force to the lowercase-alnum-hyphen shape WooCommerce's field API accepts,
            // and de-duplicate. A blank or otherwise unusable key drops the whole row.
            $key = isset($row['key']) ? sanitize_title((string) $row['key']) : '';
            if ('' === $key || isset($seen[$key])) {
                continue;
            }

            $type = in_array(($row['type'] ?? ''), self::TYPES, true) ? $row['type'] : 'text';

            $options = array();
            if ('select' === $type) {
                $raw = isset($row['options']) ? (string) $row['options'] : '';
                foreach (preg_split('/[\r\n,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $opt) {
                    $opt = sanitize_text_field(trim($opt));
                    if ('' !== $opt && !in_array($opt, $options, true)) {
                        $options[] = $opt;
                    }
                }
                if (empty($options)) {
                    // A select with no choices is a required field the shopper can never
                    // satisfy -- skip it rather than ship a broken checkout.
                    continue;
                }
            }

            $label = isset($row['label']) ? sanitize_text_field((string) $row['label']) : '';
            if ('' === $label) {
                $label = ucwords(str_replace('-', ' ', $key));
            }

            $fields[]    = array(
                'key'      => $key,
                'label'    => $label,
                'type'     => $type,
                'options'  => $options,
                'required' => !empty($row['required']),
                'location' => in_array(($row['location'] ?? ''), self::LOCATIONS, true) ? $row['location'] : 'order',
            );
            $seen[$key] = true;
        }

        update_option(self::OPTION_KEY, $fields);
        return $fields;
    }

    /**
     * Normalise one submitted value to a safe plain scalar for its field type. This is the
     * ONLY shape a customer value is ever stored in -- there is no code path that keeps raw
     * input.
     *
     * @param array  $field A field definition from get_fields().
     * @param mixed  $raw   The raw submitted value.
     * @return string The cleaned value ('' when empty / not an allowed choice).
     */
    public static function sanitize_field_value($field, $raw)
    {
        switch ($field['type']) {
            case 'checkbox':
                return (!empty($raw) && '0' !== $raw) ? '1' : '';

            case 'select':
                $value = sanitize_text_field((string) $raw);
                return in_array($value, $field['options'], true) ? $value : '';

            case 'text':
            default:
                $value = sanitize_text_field((string) $raw);
                if (function_exists('mb_substr')) {
                    return mb_substr($value, 0, self::MAX_TEXT_LENGTH);
                }
                return substr($value, 0, self::MAX_TEXT_LENGTH);
        }
    }

    /**
     * Whether a (already sanitised) value satisfies the field's rules. Used identically by the
     * classic and block validation paths so neither can accept something the other rejects.
     *
     * @return true|string true if valid, or an error message string.
     */
    public static function validate_field_value($field, $value)
    {
        if ($field['required']) {
            if ('checkbox' === $field['type'] && '1' !== $value) {
                /* translators: %s: field label */
                return sprintf(__('Please tick "%s" to continue.', 'hdwebmobile-checkout-fields'), $field['label']);
            }
            if ('checkbox' !== $field['type'] && '' === $value) {
                /* translators: %s: field label */
                return sprintf(__('Please fill in "%s".', 'hdwebmobile-checkout-fields'), $field['label']);
            }
        }

        if ('select' === $field['type'] && '' !== $value && !in_array($value, $field['options'], true)) {
            /* translators: %s: field label */
            return sprintf(__('Please choose a valid option for "%s".', 'hdwebmobile-checkout-fields'), $field['label']);
        }

        return true;
    }
}
