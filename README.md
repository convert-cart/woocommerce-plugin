# Convert Cart WordPress Plugin

![Version](https://img.shields.io/badge/version-1.3.3-blue.svg)
![License](https://img.shields.io/badge/license-Proprietary-red.svg)

## Table of Contents
  - [Table of Contents](#table-of-contents)
  - [Introduction](#introduction)
  - [Features](#features)
  - [Installation Instructions](#installation-instructions)
  - [Configure Domain Id](#configure-domain-id)
  - [Contact](#contact)
  - [For Development](#for-development)
    - [Using Code Sniffer](#using-code-sniffer)

## Introduction

Welcome to the WooCommerce Plugin by Convert Cart. Our plugin extends WooCommerce functionality by integrating additional webhooks and filters into the official REST API, enhancing capabilities beyond the default setup. Additionally, it seamlessly injects scripts into the frontend to track user behavior, empowering our recommendation engine. This engine utilizes data-driven insights to deliver personalized enhancements to your ecommerce operations.

## Features

This plugin provides a range of features to integrate your WooCommerce store with Convert Cart's analytics and optimization services:

*   **Frontend User Behavior Tracking:**
    *   Injects tracking scripts (`window.ccLayer`) automatically into your store's frontend.
    *   Tracks key WooCommerce events and page views:
        *   `productViewed`: Captures views of individual product pages, including product ID, name, price, URL, and image.
        *   `categoryViewed`: Tracks views of product category pages, including category ID, name, and URL.
        *   `cartViewed`: Records when a user views their shopping cart, including details of the items in the cart.
        *   `checkoutStarted`: Fires when a user begins the checkout process, capturing cart contents.
        *   `orderCompleted`: Tracks successful order placements on the thank you page, sending comprehensive order details (ID, total, currency, status, coupon codes used, and line item details like name, price, quantity, URL, image).
        *   `contentViewed`: Tracks views of general WordPress pages and the homepage, capturing page title and URL.
*   **Consent Management (Email & SMS):**
    *   Provides distinct settings to enable and manage customer consent collection for both Email and SMS marketing.
    *   Consent modes: `Live` (collects consent from all users), `Draft` (shows checkboxes only for admins), `Disabled`.
    *   Automatically adds consent checkboxes to relevant WooCommerce locations:
        *   Checkout page (before the place order button).
        *   User Registration form (on the My Account page).
        *   My Account details page (allowing users to manage their consent).
    *   Offers dedicated admin pages (`WooCommerce > CC SMS Consent`, `WooCommerce > CC Email Consent`) to customize the HTML content of the consent checkboxes using a CodeMirror editor.
    *   Persists consent choices:
        *   Saves consent status ('yes'/'no') to WooCommerce order meta data for every order.
        *   Saves consent status to WordPress user meta data for registered users.
    *   Handles consent capture correctly even when a user creates an account during the checkout process.
    *   **SMS Specific:** Includes logic to check previous guest orders (by email) when a user registers, potentially updating their SMS consent status based on prior interactions.
*   **Backend Integration & Configuration:**
    *   Adds an integration settings page under `WooCommerce > Settings > Integration > CC Analytics Settings`.
    *   Requires a unique `Client ID / Domain Id` provided by Convert Cart to activate tracking.
    *   Includes an optional `Debug Mode` setting to send additional technical metadata (WP version, WC version, PHP version etc.) for troubleshooting.
    *   Provides internal functions to retrieve customer data and consent status based on user ID or email address.
    *   Supports data synchronization mechanisms (e.g., via webhooks, API - inferred) for products, orders, customers, etc., to power Convert Cart services.
*   **Developer & Maintenance:**
    *   Uses standard WordPress and WooCommerce hooks and filters for compatibility.
    *   Employs WooCommerce CRUD methods and functions for HPOS (High-Performance Order Storage) compatibility.
    *   Utilizes the WooCommerce Logger for standardized error logging.
    *   Includes development tooling setup (`composer.json`) for code linting using PHP Code Sniffer and WordPress Coding Standards.

## Installation Instructions

### For Production Domains
If your domain is rocking the production URL (`app.convertcart.com`):
1. Download the latest release from the [Tags](https://github.com/convert-cart/woocommerce-plugin/tags) page on GitHub.

### For Beta Domains
If your domain is set up with the beta URL (`app-beta.convertcart.com`):
1. Download the latest tag that includes the `-beta` suffix from the [Tags](https://github.com/convert-cart/woocommerce-plugin/tags) page.

### Installation Process
1. **If the client is installing**:
   - Hand over the downloaded plugin file and let them work their magic 🎩.

2. **If you've got the admin credentials**:
   - Install the plugin directly through the WordPress Plugin Manager, usually located at `https://www.example.com/wp-admin/plugins.php`.
   - Alternatively, you can also upload the unzipped plugin files to the `/wp-content/plugins/` directory using FTP.

3. Once that's done, head to the 'Plugins' menu in WordPress and activate the plugin.

## Configure Domain Id

- After Installation, navigate to `WooCommerce` > `Settings` > `Integration` > `CC Analytics Settings`.
- Enter Your Client Id / Domain Id (Your Customer Support Manager will provide this).
- Select "Enable Debug Mode" if you want to include WooCommerce & WordPress plugin information in the tracking script metadata.
- Configure `Enable SMS Consent` and `Enable Email Consent` settings (Disabled/Draft/Live) as required.
- Save Changes.
- If you have enabled SMS or Email consent in `Live` mode, navigate to the respective submenus (`WooCommerce > CC SMS Consent`, `WooCommerce > CC Email Consent`) to review or customize the consent checkbox HTML.
- If you have any caching plugin installed, please clear the cache.

## Contact

Please contact [sales@convertcart.com](mailto:sales@convertcart.com) if any issues occur during the integration process.

## For Development

### Using Code Sniffer

- After making any changes to the repository, run code sniffer to validate the code agaist WordPress standards using the following command,
  `composer lint`
- Fix the code either manually or by installing and using PHP CS Fixer available [here](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer).

### Tag Creation

After making any changes to the master branch, you can create new version tags (for beta and production) by running the following command:
  `bash tagger.sh VERSION_NUMBER`
Make sure to replace VERSION_NUMBER with the actual version number you want to create.
