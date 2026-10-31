=== Mojito Sinpe ===
Contributors: quantumdev
Donate link: https://github.com/sponsors/nomanualdev
Tags: ecommerce, woocommerce, payment
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 1.3.0
Requires PHP: 8.2
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free manual SINPE Movil payments for WooCommerce, with classic checkout, Checkout Blocks and HPOS.

== Description ==

Free manual SINPE Móvil payments for Costa Rican WooCommerce stores, with classic checkout, Checkout Blocks and HPOS support. No plugin subscription or transaction fees.

Configure your store SINPE phone number. Customers can select their bank, open an SMS command, or use their banking app. An optional voucher reference can be requested.

This plugin does not verify bank transfers. Orders remain on hold until the merchant checks receipt of funds. Opening an SMS or entering a voucher is not proof of payment.

CRC totals are used directly. USD requires a valid manual or Hacienda USD/CRC selling rate. Other currencies are unavailable.

= External services =

Only when the store currency is USD and Hacienda is selected, the plugin sends a GET request to https://api.hacienda.go.cr/indicadores/tc to obtain public exchange rates. It sends no customer, order, phone or voucher data; the provider sees the server IP and ordinary HTTP request information. The timeout is five seconds. Valid rates are cached for six hours; failures are cached for one minute and never replaced with rate 1.

Service information: https://www.hacienda.go.cr/
Privacy policy: https://www.hacienda.go.cr/Documentos/Footer/Pol%C3%ADtica%20de%20Privacidad%20Directriz%20N%C2%B0%20DM-009-2021.pdf

= Requirements =

PHP 8.2 or later; PHP 8.3 or later recommended. PHP 8.2 upstream security support ends on December 31, 2026.
WordPress 6.2 or later and WooCommerce 8.2 or later. Older versions are compatibility targets, not a recommendation to run outdated store software.

== Installation ==

1. Install WooCommerce and this plugin through Plugins > Add New.
2. Open WooCommerce > Settings > Payments > SINPE Móvil > Manage.
3. Enable the method and enter the store Costa Rican SINPE mobile number (eight digits; +506 is also accepted).
4. Configure bank selection, optional voucher reference, instructions and USD conversion if needed.
5. Use either the WooCommerce Checkout block or the classic checkout shortcode. No extra paid extension is required.
6. Place a test order without sending money; confirm the amount, bank number, email and order status before enabling it for customers.

== Frequently Asked Questions ==

= Does it confirm that the customer paid? =
No. The merchant must confirm receipt manually before processing or completing the order.

= What happens when exchange rates change? =
The amount, rate, currency and recipient are saved with the order. Later changes to store settings do not change existing instructions. Changing an order total or currency invalidates its instructions.

= How can the merchant regenerate an old USD order? =
Edit an eligible unpaid SINPE order and use the order action "Regenerate SINPE instructions using the current rate". Review and send the updated instructions to the customer. Historical exchange rates are not invented.

= Why does an old email link stop working? =
Links from 1.2.0 did not include the order key. New links require the key and an eligible unpaid order. Send a new on-hold order email or use the new order instructions. Paid or cancelled orders cannot open payment instructions.

= Is Product Vendors supported? =
The experimental integration remains disabled. This update does not add marketplace payouts, automatic refunds or recurring payments.

== Upgrade Notice ==

= 1.3.0 =
Requires PHP 8.2, WordPress 6.2 and WooCommerce 8.2. Adds Checkout Blocks, fixes HPOS metadata, secures order links and unifies CRC/USD amounts. Back up and test on staging. Existing settings and orders are preserved. Resend instructions for old links without an order key.

== Changelog ==

= 1.3.0 =
* Complete Checkout Blocks payment fields, validation, editor preview and live server amounts.
* Shared classic/Store API processing and HPOS CRUD metadata with retry protection.
* Unified CRC/USD conversion, saved order instructions and authorized regeneration.
* Authorized order links, uncached instructions, encoded Android/iOS SMS commands.
* Central bank catalog; Spanish PHP and JavaScript translations.
* PHP 8.2-8.5 checks, regression tests and deterministic production packaging.


= 1.2.0 =
* PHP 8.X compatibility
* New bank: Coocique
* Update MUCAP and Mutual Alajuela account numbers
* New action mojito_sinpe_after_fields (thanks to @jothaxc)

= 1.1.0 =
* WooCommerce High-Performance Order Storage compatibility
* Exchange rate options
* New filter mojito_sinpe_exchange_rate
* New filter mojito_sinpe_amount
* Tested up to WP 6.5.3
* Tested up to WC 8.9.1
* PHP Fixes

= 1.0.8 =
* iPhone Compatibility fix
* Tested up to WP 6.2.2
* Tested up to WC 7.9.0

= 1.0.7 =
* New filter mojito_sinpe_show_text_after_banks_list
* Tested up to WP 7.3.0
* Tested up to WC 6.6.1

= 1.1.0 =
* New option to hide banks list 
* Compatibility fix for WooWallet
* Tested up to WP 5.9.3
* Tested up to WC 6.5.1

= 1.0.5 = 
* Banks numbers updated.
* Minor code fixes
* Tested up to WP 5.8.2
* Tested up to WC 6.0.0

= 1.0.4 =
* Minor code fixes
* Tested up to WP 5.8
* Tested up to WC 5.7.1

= 1.0.3 = 
* BAC Bank number updated
* Added Credecoop
* Added voucher field

= 1.0.2 =
* Rest API URL Fix

= 1.0.0 =
* Initial Release