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

- Script injection on the frontend for user behavior tracking.
- Token generation for synchronizing product/order/customer/category data to Convert Cart servers for recommendations.
- Adding webhooks through the official REST API and plugin code.

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

- After Installation, navigate to `Settings` > `Integration`.
- Enter Your Client Id / Domain Id (Your Customer Support Manager will provide this).
- Select "Enable Debug Mode" if you wanted to track WooCommerce & WordPress plugin information to the tracking script.
- Save Changes.
- If you have any caching plugin installed, then please clear the cache.

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
