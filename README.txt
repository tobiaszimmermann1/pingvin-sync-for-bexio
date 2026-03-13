=== Pingvin Sync for Bexio ===
Contributors: pingvindigital
Tags: bexio, buchhaltung, schweiz, synchronisierung
Requires at least: 6.7
Tested up to: 6.9
Stable tag: 0.5.1
Requires PHP: 8.1
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0

Synchronizes products, contacts, and orders between Bexio and WooCommerce. No additional subscription is required. No third party data access.

== Description ==
Currently Pingvin Sync for Bexio synchronizes products and contacts from Bexio to WooCommerce. Furthermore it will create a Bexio order for every WooCommerce order that has been created. The plugin is intended to eliminate the most frustrating copy and paste tasks when running a WooCommerce store and using Bexio for handling invoicing, stock management and bookkeeping. Important: You do not need any third-party subscription and noone but you will be able to access your data. Synchronisation happens directly between WooCommerce and Bexio.

This plugin is not intended to sync every single detail between Bexio and WooCommerce. If you are missing a feature, need support or need custom feature, feel free to reach out to me: tobias@pingvin.digital

The use of Bexio and the Bexio API is subject to Bexios terms of service and privacy policy. More information can be found here: https://www.bexio.com/en-CH/policies

== Installation ==
Copy the unzipped folder into your plugins folder. Activate the plugin via the Plugins admin page. Requires WooCommerce

== Frequently Asked Questions ==

= Do I need a any third-party account? =
No. The plugin communicates directly with the Bexio API. No subscription or third-party service is required beyond a valid Bexio account.

= Which Bexio plan is required? =
Any Bexio plan that includes API access will work.

= Are guest orders pushed to Bexio? =
Yes. For guest orders a Bexio contact is created (or matched by e-mail) without requiring a WordPress user account.

= My prices in Bexio look wrong — what should I check? =
Make sure all three WooCommerce tax classes (standard, reduced, special/zero) are mapped to the correct Bexio tax rates on the plugin settings page.

= I need a feature that is not yet available. =
Feel free to reach out: tobias@pingvin.digital

== Screenshots ==

1. Plugin settings page.
2. WooCommerce order list showing Bexio sync status.

== Code ==
https://github.com/tobiaszimmermann1/pingvin-sync-for-bexio

== Changelog ==

= 0.5.1 =
- Bugfix

= 0.5.0 =
* Initial public release.
