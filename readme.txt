=== Salah SEO ===
Contributors: salah
Tags: seo, woocommerce, rank-math, automation, nicotine, kuwait
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automated SEO optimization for WooCommerce products. Populates Rank Math fields, optimizes images, adds internal links, and fixes canonical URLs.

== Description ==

Salah SEO is a specialized WordPress plugin designed for nicotinekw.com to automate SEO optimization tasks for WooCommerce products. The plugin intelligently populates empty SEO fields while preserving any manually entered data.

= Key Features =

* **Smart Automation**: Only operates on empty fields, never overwrites manual entries
* **Performance First**: All operations run in admin panel only, zero frontend impact
* **Rank Math Integration**: Automatically sets focus keywords and meta descriptions
* **Image Optimization**: Updates featured image metadata (title, alt text, description, caption)
* **Product Tags**: Adds product name and category as tags automatically
* **Internal Linking**: Automatically links specific keywords to relevant pages
* **Canonical Fix**: Ensures proper canonical URLs to prevent duplicate content
* **User-Friendly Settings**: Simple control panel with toggles and customizable texts

= Automated Features =

When you publish or update a WooCommerce product, the plugin automatically:

1. Sets the Rank Math focus keyword to the product name (if empty)
2. Adds a default meta description (if empty)
3. Fills the short description with default text (if empty)
4. Adds product tags: product name + last sub-category (if empty)
5. Updates featured image metadata to match product name (if empty)
6. Adds internal links for specific keywords in product descriptions
7. Fixes canonical URLs to prevent duplicate content issues

= Requirements =

* WordPress 6.0 or higher
* WooCommerce 7.0 or higher
* Rank Math SEO 1.0 or higher
* PHP 7.4 or higher

= Constitutional Principles =

This plugin follows strict development principles:
* **Performance First**: No frontend slowdown
* **Smart Automation**: Respects manual entries
* **User-Friendly Control**: Simple settings interface
* **Full Compatibility**: Works with latest WordPress, WooCommerce, and Rank Math
* **Intelligent Triggers**: Activates only when needed

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/salah-seo` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to Settings > NicotineKW SEO to configure the plugin.
4. Customize default texts and enable/disable features as needed.
5. The plugin will automatically optimize your WooCommerce products when you save them.

== Frequently Asked Questions ==

= Will this plugin overwrite my existing SEO data? =

No, absolutely not. The plugin only operates on empty fields. If you have manually entered SEO data, the plugin will preserve it completely.

= Does this plugin slow down my website? =

No. All operations are performed in the WordPress admin panel when you save products. There is zero impact on your website's frontend performance.

= Can I customize the default texts? =

Yes. Go to Settings > NicotineKW SEO to customize all default texts including meta descriptions, short descriptions, and full product descriptions.

= Can I disable specific features? =

Yes. The settings page provides toggle switches for each major feature, allowing you to enable or disable them individually.

= What happens if I deactivate WooCommerce or Rank Math? =

The plugin will detect missing dependencies and display an admin notice. It will not cause any errors but will not function until the required plugins are reactivated.

= Can I add my own internal linking keywords? =

Yes. The settings page includes a dynamic interface where you can add, edit, or remove keyword-URL pairs for internal linking.

== Screenshots ==

1. Settings page with feature toggles and default text customization
2. Internal linking management interface
3. Success notification after SEO optimization
4. Plugin activation and dependency check

== Changelog ==

= 1.0.0 =
* Initial release
* Automated Rank Math SEO field population
* Image metadata optimization
* Internal linking system
* Canonical URL fix
* User-friendly settings interface
* Full WooCommerce and Rank Math integration

== Upgrade Notice ==

= 1.0.0 =
Initial release of Salah SEO plugin for automated WooCommerce SEO optimization.

== Developer Notes ==

This plugin is specifically designed for nicotinekw.com but can be adapted for other WooCommerce stores. The code follows WordPress coding standards and includes proper sanitization and validation.

For support or customization requests, please contact the developer.

== License ==

This plugin is licensed under the GPL v2 or later.

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
