<?php
namespace ConvertCart\Analytics;

use WP_REST_Request;

/**
 * Convert Cart WooCommerce Integration.
 *
 * @package  WC_CC_Analytics
 * @category Integration
 */
class WC_CC_Analytics extends \WC_Integration {

    /**
     * Init and hook in the integration.
     */
    public function __construct() {
        global $woocommerce;
        $this->id                 = 'cc_analytics';
        $this->method_title       = __( 'CC Analytics Settings', 'woocommerce_cc_analytics' );
        $this->method_description = __( 'Contact Convert Cart To Get Client ID / Domain Id', 'woocommerce_cc_analytics' );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.
        $this->cc_client_id = $this->get_option( 'cc_client_id' );
        add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );

        if ( ! isset( $this->cc_client_id ) || '' === $this->cc_client_id ) {
            return;
        }

        add_action( 'wp_head', array( $this, 'cc_init' ) );
        add_action( 'wp_footer', array( $this, 'addEvents' ) );
        add_action( 'woocommerce_thankyou', array( $this, 'ordered' ) );
        add_action( 'create_product_cat', array( $this, 'categoryCreated' ), 10, 3 );
        add_action( 'edit_product_cat', array( $this, 'categoryUpdated' ), 10, 3 );
        add_action( 'delete_product_cat', array( $this, 'categoryDeleted' ), 10, 3 );

        add_action( 'woocommerce_rest_insert_product_object', array( $this, 'createProduct' ), 10, 3 );
        add_action( 'woocommerce_rest_update_product_object', array( $this, 'updateProduct' ), 10, 3 );
        add_action( 'woocommerce_rest_delete_product_object', array( $this, 'deleteProduct' ), 10, 3 );

        add_action('rest_api_init', function() {
            register_rest_route('convertcart/v1', '/info', array(
                'methods' => 'GET',
                'callback' => array( $this, 'getVersionList' ),
                'permission_callback' => '__return_true',
            ));
            register_rest_route('convertcart/v1', '/filter', array(
                'methods' => 'GET',
                'callback' => array( $this, 'addUpdatedSinceFilterToRESTApi' ),
                'permission_callback' => '__return_true',
            ));
        });
    }

    /**
     * Initialize integration settings form fields.
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'cc_client_id' => array(
                'title'             => __( 'Client ID', 'woocommerce_cc_analytics' ),
                'type'              => 'text',
                'description'       => __( 'Enter your Client ID provided by Convert Cart.', 'woocommerce_cc_analytics' ),
                'desc_tip'          => true,
                'default'           => ''
            )
        );
    }

    /**
     * Initialize integration settings.
     */
    public function init_settings() {
        $this->settings = get_option( $this->plugin_id . $this->id . '_settings', null );
        if ( is_array( $this->settings ) ) {
            foreach ( $this->settings as $key => $value ) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Output the settings fields.
     */
    public function admin_options() {
        echo '<h2>' . esc_html( $this->method_title ) . '</h2>';
        echo wp_kses_post( wpautop( $this->method_description ) );
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

    /**
     * Initialize the CC Analytics script.
     */
    public function cc_init() {
        if ( ! $this->cc_client_id ) {
            return;
        }

        echo "<script>
            (function(i,s,o,g,r,a,m){i['ConvertCartObject']=r;i[r]=i[r]||function(){
            (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
            m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
            })(window,document,'script','https://d10lpsik1i8c69.cloudfront.net/w.js','cc');
            cc('init', '{$this->cc_client_id}');
        </script>";
    }

    /**
     * Add events to the footer.
     */
    public function addEvents() {
        if ( ! $this->cc_client_id ) {
            return;
        }

        echo "<script>
            cc('track', 'PageView');
        </script>";
    }

    /**
     * Track order details.
     *
     * @param int $order_id Order ID.
     */
    public function ordered( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $order_data = $order->get_data();
        $items = $order->get_items();

        $order_items = array();

        foreach ( $items as $item_id => $item ) {
            $product = $item->get_product();
            $order_items[] = array(
                'product_id' => $product->get_id(),
                'quantity'   => $item->get_quantity(),
                'total'      => $item->get_total()
            );
        }

        echo "<script>
            cc('track', 'Order', " . json_encode( array(
                'order_id'   => $order->get_id(),
                'total'      => $order_data['total'],
                'items'      => $order_items
            ) ) . ");
        </script>";
    }

    /**
     * Handle category creation.
     *
     * @param int $term_id Term ID.
     * @param array $args Category arguments.
     */
    public function categoryCreated( $term_id, $args ) {
        do_action('woocommerce_admin_product_category_webhook_handler', array('term' => $term_id, 'topic' => 'category.created', 'category' => $args));
    }

    /**
     * Handle category update.
     *
     * @param int $term_id Term ID.
     * @param array $args Category arguments.
     */
    public function categoryUpdated( $term_id, $args ) {
        do_action('woocommerce_admin_product_category_webhook_handler', array('term' => $term_id, 'topic' => 'category.updated', 'category' => $args));
    }

    /**
     * Handle category deletion.
     *
     * @param int $term_id Term ID.
     * @param string $tt_id Term taxonomy ID.
     */
    public function categoryDeleted( $term_id, $tt_id = '' ) {
        do_action('woocommerce_admin_product_category_webhook_handler', array('term' => $term_id, 'topic' => 'category.deleted', 'category' => $tt_id));
    }

    /**
     * Get version list.
     *
     * @param WP_REST_Request $request Request object.
     * @return array
     */
    public function getVersionList( WP_REST_Request $request ) {
        global $wp_version;
        global $woocommerce;
        $info = array();
        $info['wp_version'] = $wp_version;
        $info['wc_version'] = is_object( $woocommerce ) ? $woocommerce->version : null;
        return $info;
    }

    /**
     * Add Updated Since filter to REST API.
     *
     * @param array $prepared_args Prepared arguments.
     * @param WP_REST_Request $request Request object.
     * @return array
     */
    public function addUpdatedSinceFilterToRESTApi( array $prepared_args, WP_REST_Request $request ) {
        if ( $request->get_param( 'modified_after' ) ) {
            $prepared_args['meta_query'] = array(
                array(
                    'key'     => 'last_update',
                    'value'   => (int) strtotime( $request->get_param( 'modified_after' ) ),
                    'compare' => '>='
                ),
            );
        }
        return $prepared_args;
    }

    /**
     * Permission callback for custom endpoints.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool
     */
    public function permissionCallback( WP_REST_Request $request ) {
        global $wpdb;
        $queryparams = $request->get_params();
        $key = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT consumer_secret
                FROM {$wpdb->prefix}woocommerce_api_keys
                WHERE consumer_secret = %s",
                $queryparams['consumer_secret']
            ),
            ARRAY_A
        );

        return isset( $key['consumer_secret'] );
    }
}
