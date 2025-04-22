<?php
declare(strict_types=1);

namespace ConvertCart\Analytics\Tracking;

use ConvertCart\Analytics\Abstract\WC_CC_Base;
use WC_Order;
use Exception;

/**
 * Handles analytics tracking functionality.
 */
class WC_CC_Analytics_Tracking extends WC_CC_Base {
    /**
     * Initialize hooks.
     */
    public function init(): void {
        add_action('wp_head', [$this, 'add_tracking_script']);
        add_action('woocommerce_thankyou', [$this, 'track_order']);
        // Note: Other page-specific events (product, cart, etc.) are handled by Event_Manager via wp_footer

        // Get plugin version from main plugin file or set a default
        $version = $this->plugin->get_plugin_version();

        // Enqueue WooCommerce dependencies first
        wp_enqueue_script('wc-blocks-checkout');
        wp_enqueue_script('wc-settings');
        wp_enqueue_script('wc-blocks-data');

        // Register our script with dependencies
        wp_register_script(
            'convertcart-blocks-integration',
            plugins_url('assets/build/js/block-checkout-integration.js', dirname(__FILE__)),
            array(
                'wp-blocks',
                'wp-element',
                'wp-components',
                'wp-i18n',
                'wp-data',
                'wc-blocks-checkout',
                'wc-settings',
                'wc-blocks-data'
            ),
            $version ?? '1.0.0', // Fallback version if not set
            true
        );

        // Enqueue our script
        wp_enqueue_script('convertcart-blocks-integration');

        // Add script data
        wp_localize_script(
            'convertcart-blocks-integration',
            'convertCartBlocksData',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('convertcart-blocks'),
                // Add any other data your script needs
            )
        );
    }

    /**
     * Add ConvertCart base tracking script to head.
     */
    public function add_tracking_script(): void {
        $client_id = $this->get_option('cc_client_id');
        if (empty($client_id)) {
            return;
        }
        ?>
        <!-- ConvertCart Analytics -->
        <script>
            (function(c,o,n,v,e,r,t){
                c[e]=c[e]||function(){(c[e].q=c[e].q||[]).push(arguments)};
                r=o.createElement(n);r.async=1;r.src=v;
                t=o.getElementsByTagName(n)[0];t.parentNode.insertBefore(r,t);
            })(window,document,'script','https://cdn.convertcart.com/<?php echo esc_js($client_id); ?>.js','_cc');
            _cc('init', '<?php echo esc_js($client_id); ?>');
        </script>
        <!-- /ConvertCart Analytics -->
        <?php
    }

    /**
     * Placeholder for potential future event additions via this class.
     * Currently, page view events are handled by Event_Manager.
     */
    public function add_events(): void {
        // Currently unused as Event_Manager handles page view events.
    }

    /**
     * Track order completion.
     *
     * @param int $order_id Order ID
     */
    public function track_order(int $order_id): void {
        if (!is_wc_endpoint_url('order-received')) {
            return;
        }

        try {
            $order = wc_get_order($order_id);
            if (!$order instanceof WC_Order) {
                return;
            }

            $event_data = $this->get_order_event_data($order);
            $this->output_tracking_script('orderCompleted', $event_data);
        } catch (Exception $e) {
            // Log critical errors during order tracking
             $this->log_error("Failed to track order {$order_id}: " . $e->getMessage());
        }
    }

    /**
     * Track product view.
     */
    private function track_product_view(): void {
        global $product;
        if (!$product) {
            return;
        }

        $event_data = [
            'name' => $product->get_name(),
            'price' => $product->get_price(),
            'currency' => get_woocommerce_currency(),
            'url' => get_permalink($product->get_id()),
        ];

        if ($product->get_image_id()) {
            $image_url = wp_get_attachment_image_url($product->get_image_id(), 'full');
            if ($image_url) {
                $event_data['image'] = $image_url;
            }
        }

        $this->output_tracking_script('productView', $event_data);
    }

    /**
     * Track cart view.
     */
    private function track_cart_view(): void {
        $cart = WC()->cart;
        if (!$cart) {
            return;
        }

        $event_data = [
            'total' => $cart->get_total(),
            'currency' => get_woocommerce_currency(),
            'items' => [],
        ];

        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if (!$product) {
                continue;
            }

            $event_data['items'][] = [
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'quantity' => $cart_item['quantity'],
                'url' => get_permalink($product->get_id()),
            ];
        }

        $this->output_tracking_script('cartView', $event_data);
    }

    /**
     * Track checkout view.
     */
    private function track_checkout_view(): void {
        $cart = WC()->cart;
        if (!$cart) {
            return;
        }

        $event_data = [
            'total' => $cart->get_total(),
            'currency' => get_woocommerce_currency(),
        ];

        $this->output_tracking_script('checkoutView', $event_data);
    }

    /**
     * Get order event data.
     *
     * @param WC_Order $order Order object
     * @return array
     */
    private function get_order_event_data(WC_Order $order): array {
        $event_data = [
            'orderId' => (string)$order->get_id(),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'status' => $order->get_status(),
            'items' => [],
        ];

        $promos = $order->get_coupon_codes();
        if (!empty($promos)) {
            $event_data['coupon_code'] = $promos[0];
        }

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            $item_data = [
                'productId' => (string)$product->get_id(),
                'name' => $product->get_name(),
                'price' => $item->get_total() / $item->get_quantity(), // Price per unit
                'quantity' => $item->get_quantity(),
                'url' => get_permalink($product->get_id()),
            ];

            // Add image
            $image_id = $product->get_image_id();
            if ($image_id) {
                $image_url = wp_get_attachment_image_url($image_id, 'full');
                if ($image_url) {
                    $item_data['image'] = $image_url;
                }
            }
            // END Add image

            $event_data['items'][] = $item_data;
        }

        return $event_data;
    }

    /**
     * Output tracking script.
     *
     * @param string $event Event name
     * @param array $data Event data
     */
    private function output_tracking_script(string $event, array $data): void {
        ?>
        <script>
            window._cc = window._cc || [];
            window._cc.push(['track', '<?php echo esc_js($event); ?>', <?php echo wp_json_encode($data); ?>]);
        </script>
        <?php
    }
} 