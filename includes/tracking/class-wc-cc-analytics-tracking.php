<?php
declare(strict_types=1);

namespace ConvertCart\Analytics\Tracking;

use ConvertCart\Analytics\Abstract\WC_CC_Base;
use WC_Order;
use Exception;
use WC_Integration;

/**
 * Handles analytics tracking functionality.
 */
class WC_CC_Analytics_Tracking extends WC_CC_Base {
    /**
     * Constructor.
     *
     * @param WC_Integration $integration
     */
    public function __construct(WC_Integration $integration) {
        parent::__construct($integration);
    }

    /**
     * Initialize hooks.
     */
    public function init(): void {
        // Get the Client ID from the main integration settings
        $client_id = $this->integration->get_option('cc_client_id');
        $client_id_exists = !empty($client_id);

        // Only add tracking hooks if the Client ID exists
        if ($client_id_exists) {
            // Add hook for the main script in the head
            add_action('wp_head', [$this, 'add_tracking_code']);

            // Add hook for order tracking on thank you page
            add_action('woocommerce_thankyou', [$this, 'track_order']);

        }
    }

    /**
     * Add tracking code to head.
     */
    public function add_tracking_code(): void {
        $client_id = $this->integration->get_option('cc_client_id');
        if (empty($client_id)) {
            return;
        }

        // Outputs the main ConvertCart JS library loader
        ?>
        <script type="text/javascript">
            (function(c,o,v,e,r,t){
                c[r]=c[r]||function(){(c[r].q=c[r].q||[]).push(arguments)};
                t=o.createElement(v);t.async=1;t.src=e;o.head.appendChild(t);
            })(window,document,'script','https://cdn.convertcart.com/<?php echo esc_js($client_id); ?>.js','cc');
        </script>
        <?php
    }

    /**
     * Add events script to footer.
     * NOTE: This method is likely no longer called as the hook was removed in init().
     * Kept for potential future use or direct calls if needed.
     */
    public function add_events(): void {
        // --- Original Code Start (Commented out) ---
        // try { ... } catch { ... }
        // --- Original Code End ---
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
            // Log critical errors if necessary, otherwise remove for production
            // error_log("ConvertCart ERROR: Failed to track order {$order_id}: " . $e->getMessage());
            // OR keep the $this->log_error call if that method handles production logging appropriately.
            // $this->log_error("Failed to track order {$order_id}: " . $e->getMessage());
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
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'currency' => $order->get_currency(),
                'quantity' => $item->get_quantity(),
                'url' => get_permalink($product->get_id()),
            ];

            if ($product->get_image_id()) {
                $image_url = wp_get_attachment_image_url($product->get_image_id(), 'full');
                if ($image_url) {
                    $item_data['image'] = $image_url;
                }
            }

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