=== HDWebmobile Checkout Fields ===
Contributors: htrxuan
Donate link: https://paypal.me/htrxuan/20
Tags: woocommerce, checkout fields, checkout field editor, custom checkout, block checkout
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add your own text, dropdown, and checkbox fields to checkout. Works on classic and block Checkout, with no file-upload field type.

== Description ==

HDWebmobile Checkout Fields lets you add extra fields to WooCommerce checkout: a gift message, a delivery note, a "leave at the door" checkbox, a dropdown of preferences. Each field can be placed in the contact section, the address section, or the order-notes area, marked required or optional, and is validated on the server.

Fields are registered through WooCommerce's own native Checkout Fields API for the block-based Checkout, and through a traditional hook-based implementation for classic checkout. Both paths save to the same order data, so every value appears correctly in order emails and on the admin Edit Order screen no matter which checkout your store uses.

= Why this plugin exists =
Checkout-field plugins are a repeated source of the same two security bugs, and this plugin is built to make both impossible by construction rather than by patching:

* **No unauthenticated file upload.** A leading competitor, "Checkout Field Manager (Checkout Manager) for WooCommerce" (versions up to and including 7.8.1), shipped CVE-2025-12500 (CWE-434, CVSS 5.3): its `ajax_checkout_attachment_upload` AJAX handler ran with no authorization check at all, so any unauthenticated visitor could upload files to the server. This plugin has **no "file" field type, no attachment handling, and no AJAX handler of any kind** — a shopper's checkout field can only ever be a line of text, a choice from a dropdown you defined, or a checkbox. There is simply nothing to upload to.
* **No stored XSS from a field label, option, or submitted value.** Competing plugins have repeatedly rendered an admin-defined label/option or a customer-submitted value without escaping (for example the 2026 "Unauthenticated Stored XSS via Block Checkout Custom Radio Field" disclosure). Here, field definitions can only be changed through one form that verifies `manage_woocommerce` **and** a nonce before reading any input; every submitted value is normalised to a plain scalar of a known type on save; and every value is displayed only through WooCommerce's own escaping order/email layer or `woocommerce_form_field()` — never echoed raw. There is no HTML or rich-text field type.
* **One write path.** The field configuration is written in exactly one place, reached only after that capability + nonce check. There is no admin-init, admin-head, or bare-`$_GET` code path that changes settings.

= Key Features =
* Text, dropdown, and checkbox field types — the exact set WooCommerce's block Checkout supports, so classic and block Checkout render an identical set of fields
* Place each field in the contact section, address section, or order-notes area
* Required or optional, with server-side validation on both checkout types
* Values appear automatically in order emails and on the admin Edit Order screen, using WooCommerce's own order-field display
* No custom JavaScript on the block-based Checkout
* Works for guests and logged-in shoppers alike

= Limitations (please read before installing) =
* No file-upload field type — by design (see above)
* No conditional logic (show field B only if field A is set) in this version
* No per-field pricing — a checkout field never changes the order total; use HDWebmobile Product Options & Add-ons for paid options
* Text fields are capped at 255 characters

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/hdwebmobile-checkout-fields` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress. WooCommerce must already be installed and active.
3. Go to **WooCommerce > HDWebmobile > Checkout Fields** and add your fields.

== How to Use ==

= 1. Add fields =
On the Checkout Fields tab, give each field a key (lowercase letters, numbers and hyphens), a label, a type (Text, Dropdown, or Checkbox), and a position. For a Dropdown, list the options separated by commas or new lines. Tick "Required" if the shopper must fill it in.

= 2. Customers fill them in at checkout =
The fields appear on both the classic and block-based Checkout in the position you chose. Every value is validated on the server; a required field left blank, or a dropdown value that wasn't one of your options, blocks checkout with a clear message.

= 3. See the values =
Saved values appear on the order confirmation page, in order emails, and on the admin Edit Order screen automatically, since they are stored as standard WooCommerce order fields.

== Screenshots ==

1. The Checkout Fields settings tab under WooCommerce > HDWebmobile.
2. Custom fields on the block-based Checkout.
3. The submitted values on the admin Edit Order screen.

== Changelog ==

= 1.0.0 =
* Initial release: text/dropdown/checkbox checkout fields for classic and block Checkout, capability- and nonce-gated configuration, no file-upload field type, server-side validation of every submission.
