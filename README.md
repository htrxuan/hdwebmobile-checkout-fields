# HDWebmobile Checkout Fields

Add your own text, dropdown, and checkbox fields to WooCommerce checkout — working identically on classic and block-based Checkout, with no file-upload field type and no unauthenticated write path.

- **WordPress.org:** https://wordpress.org/plugins/hdwebmobile-checkout-fields/
- **Requires:** WordPress 6.9+, WooCommerce, PHP 7.4+
- **License:** GPLv2 or later

## Description

Add extra fields to checkout: a gift message, a delivery note, a "leave at the door" checkbox, a dropdown of preferences. Each field can go in the contact section, the address section, or the order-notes area, be required or optional, and is validated on the server.

Fields are registered through WooCommerce's own native Checkout Fields API for the block Checkout, and a traditional hook-based implementation for classic checkout. Both save to the same order data, so values appear correctly in order emails and on the admin Edit Order screen regardless of which checkout the store uses.

## Why this plugin exists

Checkout-field plugins keep shipping the same two bugs. This one closes both by construction:

* **No unauthenticated file upload.** "Checkout Field Manager (Checkout Manager) for WooCommerce" (≤ 7.8.1) shipped CVE-2025-12500 (CWE-434, CVSS 5.3): its `ajax_checkout_attachment_upload` AJAX handler had no authorization check, letting unauthenticated visitors upload files. This plugin has no file field type, no attachment handling, and no AJAX handler at all — a shopper field is only ever text, a dropdown choice, or a checkbox.
* **No stored XSS.** Field definitions change only through one form that checks `manage_woocommerce` + a nonce; every submitted value is normalised to a plain scalar of a known type on save; every value is rendered only through WooCommerce's own escaping display layer or `woocommerce_form_field()`. There is no HTML / rich-text field type.
* **One write path**, reached only after that capability + nonce check — no admin-init / bare-`$_GET` settings write.

## Features

* Text, dropdown, and checkbox types — exactly what WooCommerce's block Checkout supports, so classic and block render an identical field set
* Place each field in the contact / address / order-notes area
* Required or optional, with server-side validation on both checkout types
* Values shown automatically in order emails and on the admin Edit Order screen
* No custom JavaScript on the block Checkout
* Works for guests and logged-in shoppers

## Limitations

* No file-upload field type — by design
* No conditional logic in this version
* A checkout field never changes the order total (use HDWebmobile Product Options & Add-ons for paid options)
* Text fields capped at 255 characters

## Installation

1. Upload to `/wp-content/plugins/hdwebmobile-checkout-fields`, or install through the WordPress plugins screen.
2. Activate. WooCommerce must already be installed and active.
3. Go to **WooCommerce > HDWebmobile > Checkout Fields** and add your fields.

## License

GPLv2 or later — https://www.gnu.org/licenses/gpl-2.0.html
