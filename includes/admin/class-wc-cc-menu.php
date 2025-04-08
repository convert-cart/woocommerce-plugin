<?php
declare(strict_types=1);

namespace ConvertCart\Analytics\Admin;

use ConvertCart\Analytics\Abstract\WC_CC_Base;
use WC_Integration;

/**
 * Handles admin menu functionality.
 */
class WC_CC_Menu extends WC_CC_Base {
    /**
     * Initialize hooks.
     */
    public function init(): void {
        add_action('admin_menu', [$this, 'add_menu_items']);
        add_action('admin_head', [$this, 'add_menu_icon_styles']);
        add_filter('parent_file', [$this, 'highlight_menu_item']);
        add_filter('submenu_file', [$this, 'highlight_submenu_item']);
    }

    /**
     * Add menu items.
     */
    public function add_menu_items(): void {
        $parent_slug = 'convert-cart';
        $consent_mode = [
            'sms' => $this->integration->get_option('enable_sms_consent', 'disabled'),
            'email' => $this->integration->get_option('enable_email_consent', 'disabled'),
        ];

        // Only show menu if either consent type is enabled
        if ($consent_mode['sms'] === 'disabled' && $consent_mode['email'] === 'disabled') {
            return;
        }

        // Add main menu
        add_menu_page(
            __('Convert Cart', 'woocommerce_cc_analytics'),
            __('Convert Cart', 'woocommerce_cc_analytics'),
            'manage_woocommerce',
            $parent_slug,
            null,
            '',
            59
        );

        // Add SMS Consent submenu if enabled
        if ($consent_mode['sms'] !== 'disabled') {
            add_submenu_page(
                $parent_slug,
                __('SMS Consent', 'woocommerce_cc_analytics'),
                __('SMS Consent', 'woocommerce_cc_analytics'),
                'manage_woocommerce',
                'convert-cart-sms-consent',
                [$this, 'render_sms_consent_page']
            );
        }

        // Add Email Consent submenu if enabled
        if ($consent_mode['email'] !== 'disabled') {
            add_submenu_page(
                $parent_slug,
                __('Email Consent', 'woocommerce_cc_analytics'),
                __('Email Consent', 'woocommerce_cc_analytics'),
                'manage_woocommerce',
                'convert-cart-email-consent',
                [$this, 'render_email_consent_page']
            );
        }

        // Add Settings submenu
        add_submenu_page(
            $parent_slug,
            __('Convert Cart Settings', 'woocommerce_cc_analytics'),
            __('Domain Settings', 'woocommerce_cc_analytics'),
            'manage_woocommerce',
            'admin.php?page=wc-settings&tab=integration&section=' . $this->integration->id,
            null
        );

        // Remove default submenu
        remove_submenu_page($parent_slug, $parent_slug);
    }

    /**
     * Add menu icon styles.
     */
    public function add_menu_icon_styles(): void {
        $icon_url = esc_url(plugin_dir_url(dirname(__DIR__)) . 'assets/images/icon.svg');
        ?>
        <style>
            #adminmenu .toplevel_page_convert-cart .wp-menu-image {
                background-image: url('<?php echo $icon_url; ?>') !important;
                background-repeat: no-repeat;
                background-position: center center;
                background-size: 20px auto;
            }
            #adminmenu .toplevel_page_convert-cart .wp-menu-image:before {
                content: none !important;
            }
        </style>
        <?php
    }

    /**
     * Highlight parent menu item.
     *
     * @param string $parent_file
     * @return string
     */
    public function highlight_menu_item(string $parent_file): string {
        global $plugin_page;

        if (strpos($plugin_page ?? '', 'convert-cart') === 0) {
            $parent_file = 'convert-cart';
        }

        return $parent_file;
    }

    /**
     * Highlight submenu item.
     *
     * @param string|null $submenu_file
     * @return string|null
     */
    public function highlight_submenu_item(?string $submenu_file): ?string {
        global $plugin_page;

        if ($plugin_page === 'wc-settings' && isset($_GET['section']) && $_GET['section'] === $this->integration->id) {
            $submenu_file = 'admin.php?page=wc-settings&tab=integration&section=' . $this->integration->id;
        }

        return $submenu_file;
    }

    /**
     * Render SMS consent page.
     */
    public function render_sms_consent_page(): void {
        $this->render_consent_page('sms');
    }

    /**
     * Render Email consent page.
     */
    public function render_email_consent_page(): void {
        $this->render_consent_page('email');
    }

    /**
     * Render consent page.
     *
     * @param string $type Consent type (sms|email)
     */
    private function render_consent_page(string $type): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'woocommerce_cc_analytics'));
        }

        $consent_mode = $this->integration->get_option("enable_{$type}_consent", 'disabled');
        $contexts = ['checkout', 'registration', 'account'];
        $html_values = [];

        foreach ($contexts as $context) {
            $option_name = "cc_{$type}_consent_{$context}_html";
            $html_values[$context] = get_option($option_name);
        }

        require_once dirname(__DIR__) . '/templates/admin-consent-page.php';
    }
} 