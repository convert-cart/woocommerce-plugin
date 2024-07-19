# Convert Cart WordPress Plugin

![Version](https://img.shields.io/badge/version-1.2.3-blue.svg)
![License](https://img.shields.io/badge/license-Proprietary-red.svg)

## Table of Contents
  - [Table of Contents](#table-of-contents)
  - [Introduction](#introduction)
  - [Features](#features)
  - [Installation](#installation)
  - [Configure Domain Id](#configure-domain-id)
  - [Contact](#contact)
  - [For Development](#for-development)
    - [Using Code Sniffer](#using-code-sniffer)

## Introduction

Welcome to the WooCommerce Plugin by Convert Cart. Our plugin extends WooCommerce functionality by integrating additional webhooks and filters into the official REST API, enhancing capabilities beyond the default setup. Additionally, it seamlessly injects scripts into the frontend to track user behavior, empowering our recommendation engine. This engine utilizes data-driven insights to deliver personalized enhancements to your ecommerce operations.

## Features

- Script injection on the frontend for user behavior tracking.
- Token generation for synchronizing product/order/customer/category data to Convert Cart servers for recommendations.
- Adding webhooks through the official REST API and plugin code.

## Installation

- *For Production Domains*: If your domain is set up with the production URL (app.convertcart.com), download the latest tag from the [tags](https://github.com/convert-cart/woocommerce-plugin/tags) page of the repository on GitHub.
- *For Beta Domains*: If your domain is set up with the beta URL (app-beta.convertcart.com), download the latest tag that has the suffix `-beta` from the tags page of the repository on GitHub.
- Upload the plugin files to the `/wp-content/plugins/` directory, or install it directly from the WordPress Plugin Manager.
- Activate the plugin through the 'Plugins' menu in WordPress.

## Configure Domain Id

- After Installation, navigate to `Settings` > `Integration`.
- Enter Your Client Id / Domain Id (Your Customer Support Manager will provide this).
- Save Changes.
- If you have any caching plugin installed, then please clear the cache.

## Contact

Please contact [sales@convertcart.com](mailto:sales@convertcart.com) if any issues occur during the integration process.

## For Development

### Using Code Sniffer

- After making any changes to the repository, run code sniffer to validate the code agaist WordPress standards using the following command,
  `composer lint`
- Fix the code either manually or by installing and using PHP CS Fixer available [here](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer).