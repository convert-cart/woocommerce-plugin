=== Convert Cart Analytics ===
Contributors: Aamir
Tags: convertcart, analytics, woocommerce

Official Convert Cart Plugin For Woo Commerce.

== Changelog ==

= 1.1.2
 * Tweak - Added image, url to cartViewed & checkoutViewed events.
 * Tweak - Removed sale_price and final_price from productViewed event.
 * Fix   - Fixed categoryViewed event.

= 1.1.3
  * Tweak - Added date in meta_data.
  * Tweak - Pass orderId as string in orderCompleted event.

= 1.1.4
  * Tweak - Added manage_stock, stock_quantity, is_in_stock in productViewed event.

= 1.1.5
  * Fix   - Fixed image url in cartViewed, checkoutViewed and orderCompleted event for variable product.
  * Tweak - Removed manage_stock and stock_quantity from productViewed event for security reasons.
  * Tweak - Added plugin_version in metaData of all events.

= 1.1.6
  * Tweak - Added "data-cfasync=false" attribute to script tag to disable cloudflare rocket loader.

= 1.1.7
  * Fix   - Minor bug fixes.
  * Fix   - Replaced deprecated wordpress/woocommerce functions.
  * Tweak - Removed woocommerce/wordpress words in meta_data.
  * Tweak - Removed categories from productViewed event.

= 1.1.8
  * Tweak - Added currency in cart items.

= 1.1.9
  * Tweak - Updated fieldname to clientId/domainId in configuration.

= 1.2.0
  * Tweak - Updated convertcart init script.
  * Tweak - log events using ccLayer.

= 1.2.1
  * Tweak - Load initial convertcart script from cdn.convertcart.com.
  * Fix   - Added shopPageViewed event.

= 1.2.2
  * Fix   - Added original image in productViewed and cartViewed items.