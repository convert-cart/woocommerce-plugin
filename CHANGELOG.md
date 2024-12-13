# Changelog

All notable changes to this project will be documented in this file.

## [1.3.0] - 2024-12-13
### Added
- Consent tracking added. User should be able to update their consent from Checkout/My Account & Registration forms.
- Consent styling can be changed from the Admin.

## [1.2.4] - 2024-08-28
### Tweaks
- Added a 'Debug' setting to control the inclusion of version meta data in tracking scripts.

## [1.2.3] - 2023-12-18
### Fixed
- Added webhook for category changes.
- Added filters for modified from.

## [1.2.2] - 2019-11-07
### Fixed
- Added original image in productViewed and cartViewed items.
- Added productsSearched event.
- Changed title to name in categoryViewed event.

## [1.2.1] - 2019-10-31
### Added
- ShopPageViewed event.

### Tweaked
- Load initial convertcart script from cdn.convertcart.com.

## [1.2.0] - 2019-04-23
### Tweaked
- Updated convertcart init script.
- Log events using ccLayer.

## [1.1.9] - 2019-01-03
### Tweaked
- Updated fieldname to clientId/domainId in configuration.

## [1.1.8] - 2018-12-07
### Tweaked
- Added currency in cart items.

## [1.1.7] - 2018-11-17
### Fixed
- Minor bug fixes.
- Replaced deprecated WordPress/WooCommerce functions.

### Tweaked
- Removed woocommerce/wordpress words in meta_data.
- Removed categories from productViewed event.

## [1.1.6] - 2017-11-14
### Tweaked
- Added "data-cfasync=false" attribute to script tag to disable Cloudflare rocket loader.

## [1.1.5] - 2017-07-12
### Fixed
- Fixed image URL in cartViewed, checkoutViewed, and orderCompleted event for variable product.

### Tweaked
- Removed manage_stock and stock_quantity from productViewed event for security reasons.
- Added plugin_version in metaData of all events.

## [1.1.4] - 2017-05-05
### Tweaked
- Added manage_stock, stock_quantity, is_in_stock in productViewed event.

## [1.1.3] - 2017-05-05
### Tweaked
- Added date in meta_data.
- Pass orderId as string in orderCompleted event.

## [1.1.2] - 2016-11-29
### Tweaked
- Added image, URL to cartViewed & checkoutViewed events.
- Removed sale_price and final_price from productViewed event.

### Fixed
- Fixed categoryViewed event.
