<?php

namespace htrxuan\hdcf;

if (!defined('ABSPATH')) {
    exit;
}

class HDCF_Admin
{
    const NONCE_ACTION = 'hdcf_save_fields';

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
        require_once HDCF_PLUGIN_DIR . 'includes/class-hdcf-hub.php';
        add_filter('hdwebmobile_hub_tabs', array($this, 'register_hub_tabs'));
        add_action('admin_post_hdcf_save_fields', array($this, 'save_fields'));
    }

    public function register_hub_tabs($tabs)
    {
        $tabs['checkout-fields'] = array(
            'label'  => __('Checkout Fields', 'hdwebmobile-checkout-fields'),
            'order'  => 42,
            'render' => array($this, 'render_page'),
        );
        return $tabs;
    }

    /**
     * The ONLY caller of HDCF_Repository::save_fields(). Both the capability check AND the
     * nonce check run before a single byte of $_POST is read -- this is the exact gate the
     * vulnerable competing plugins were missing (see class-hdcf-repository.php's docblock).
     */
    public function save_fields()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to do this.', 'hdwebmobile-checkout-fields'));
        }
        if (!isset($_POST['hdcf_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hdcf_nonce'])), self::NONCE_ACTION)) {
            wp_die(esc_html__('Security check failed. Please try again.', 'hdwebmobile-checkout-fields'));
        }

        $submitted = isset($_POST['hdcf_field']) && is_array($_POST['hdcf_field'])
            ? (array) wp_unslash($_POST['hdcf_field']) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every field is individually sanitised/validated inside HDCF_Repository::save_fields(), which never trusts caller input regardless of this authorization gate.
            : array();

        $rows = array();
        foreach ($submitted as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = array(
                'key'      => $row['key'] ?? '',
                'label'    => $row['label'] ?? '',
                'type'     => $row['type'] ?? 'text',
                'options'  => $row['options'] ?? '',
                'required' => !empty($row['required']),
                'location' => $row['location'] ?? 'order',
            );
        }

        HDCF_Repository::save_fields($rows);

        wp_safe_redirect(admin_url('admin.php?page=hdwebmobile&tab=checkout-fields&updated=1'));
        exit;
    }

    public function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to do this.', 'hdwebmobile-checkout-fields'));
        }

        $fields    = HDCF_Repository::get_fields();
        $locations = array(
            'contact' => __('Contact section', 'hdwebmobile-checkout-fields'),
            'address' => __('Address section', 'hdwebmobile-checkout-fields'),
            'order'   => __('Order notes area', 'hdwebmobile-checkout-fields'),
        );
        $types = array(
            'text'     => __('Text', 'hdwebmobile-checkout-fields'),
            'select'   => __('Dropdown', 'hdwebmobile-checkout-fields'),
            'checkbox' => __('Checkbox', 'hdwebmobile-checkout-fields'),
        );
        // One empty row is always appended so there is somewhere to add a new field.
        $rows = $fields;
        $rows[] = array('key' => '', 'label' => '', 'type' => 'text', 'options' => array(), 'required' => false, 'location' => 'order');
        ?>
        <p><?php esc_html_e('Add your own fields to checkout -- a gift message, a delivery note, a "leave at door" checkbox, a dropdown of preferences. Fields work identically on the classic and block-based Checkout, and appear automatically in order emails and on the admin Edit Order screen.', 'hdwebmobile-checkout-fields'); ?></p>
        <p><?php esc_html_e('There is deliberately no file-upload field type: a shopper field can only be text, a dropdown choice, or a checkbox.', 'hdwebmobile-checkout-fields'); ?></p>

        <?php if (!empty($_GET['updated'])) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only success flag, no state change. ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Checkout fields saved.', 'hdwebmobile-checkout-fields'); ?></p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="hdcf_save_fields" />
            <?php wp_nonce_field(self::NONCE_ACTION, 'hdcf_nonce'); ?>
            <table class="widefat striped" style="max-width:960px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Field key', 'hdwebmobile-checkout-fields'); ?></th>
                        <th><?php esc_html_e('Label', 'hdwebmobile-checkout-fields'); ?></th>
                        <th><?php esc_html_e('Type', 'hdwebmobile-checkout-fields'); ?></th>
                        <th><?php esc_html_e('Dropdown options', 'hdwebmobile-checkout-fields'); ?></th>
                        <th><?php esc_html_e('Required', 'hdwebmobile-checkout-fields'); ?></th>
                        <th><?php esc_html_e('Position', 'hdwebmobile-checkout-fields'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $i => $field) : ?>
                        <tr>
                            <td><input type="text" name="hdcf_field[<?php echo (int) $i; ?>][key]" value="<?php echo esc_attr($field['key']); ?>" placeholder="gift-message" style="width:10em;" /></td>
                            <td><input type="text" name="hdcf_field[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr($field['label']); ?>" placeholder="<?php esc_attr_e('Gift message', 'hdwebmobile-checkout-fields'); ?>" style="width:12em;" /></td>
                            <td>
                                <select name="hdcf_field[<?php echo (int) $i; ?>][type]">
                                    <?php foreach ($types as $value => $text) : ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($field['type'], $value); ?>><?php echo esc_html($text); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="text" name="hdcf_field[<?php echo (int) $i; ?>][options]" value="<?php echo esc_attr(implode(', ', (array) $field['options'])); ?>" placeholder="<?php esc_attr_e('Red, Green, Blue', 'hdwebmobile-checkout-fields'); ?>" style="width:14em;" /></td>
                            <td style="text-align:center;"><input type="checkbox" name="hdcf_field[<?php echo (int) $i; ?>][required]" value="1" <?php checked($field['required']); ?> /></td>
                            <td>
                                <select name="hdcf_field[<?php echo (int) $i; ?>][location]">
                                    <?php foreach ($locations as $value => $text) : ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($field['location'], $value); ?>><?php echo esc_html($text); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description"><?php esc_html_e('Field key: lowercase letters, numbers and hyphens only (used internally and as the order-meta key). Leave a row\'s key blank to remove it. "Dropdown options" is only used when the type is Dropdown -- one option per comma or line.', 'hdwebmobile-checkout-fields'); ?></p>
            <?php submit_button(__('Save Checkout Fields', 'hdwebmobile-checkout-fields')); ?>
        </form>
        <?php
    }
}
