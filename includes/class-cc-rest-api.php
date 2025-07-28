<?php
/**
 * REST API Class
 *
 * @package ConvertCart\Analytics
 */

namespace ConvertCart\Analytics;

use WP_REST_Request;

class CC_Rest_API {

	public function init_endpoints() {
		add_action( 'rest_api_init', array( $this, 'register_version_endpoint' ) );
		add_action( 'rest_api_init', array( $this, 'register_plugin_info_endpoint' ) );
		add_filter( 'woocommerce_rest_product_object_query', array( $this, 'add_modified_after_filter_to_products' ), 10, 2 );
		add_filter( 'woocommerce_rest_orders_prepare_object_query', array( $this, 'add_modified_after_filter_to_orders' ), 10, 2 );
		add_filter( 'woocommerce_rest_customer_query', array( $this, 'add_updated_since_filter_to_rest_api' ), 10, 2 );
		add_filter( 'rest_product_collection_params', array( $this, 'maximum_api_filter' ) );
	}

	public function register_version_endpoint() {
		register_rest_route(
			'wc/v3',
			'cc-version',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_version_list' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	public function register_plugin_info_endpoint() {
		register_rest_route(
			'wc/v3',
			'cc-plugin-info',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_plugin_info' ),
				'permission_callback' => array( $this, 'permission_callback' ),
			)
		);
	}

	public function add_modified_after_filter_to_products( $args, $request ) {
		if ( $request->get_param( 'modified_after' ) ) {
			$args['date_query'] = array(
				array(
					'column' => 'post_modified',
					'after'  => $request->get_param( 'modified_after' ),
				),
			);
		}
		return $args;
	}

	public function add_modified_after_filter_to_orders( $args, $request ) {
		if ( $request->get_param( 'modified_after' ) ) {
			$args['date_modified'] = '>=' . $request->get_param( 'modified_after' );
		}
		return $args;
	}

	public function add_updated_since_filter_to_rest_api( $prepared_args, $request ) {
		if ( $request->get_param( 'modified_after' ) ) {
			$prepared_args['meta_query'] = array(
				array(
					'key'     => 'last_update',
					'value'   => (int) strtotime( $request->get_param( 'modified_after' ) ),
					'compare' => '>=',
				),
			);
		}
		return $prepared_args;
	}

	public function maximum_api_filter( $query_params ) {
		$query_params['per_page']['maximum'] = 5000;
		return $query_params;
	}

	public function get_version_list( $request ) {
		global $wp_version;

		$info               = array();
		$info['wp_version'] = $wp_version;
		$info['wc_version'] = ( function_exists( 'WC' ) && is_object( WC() ) ) ? WC()->version : null;
		return $info;
	}

	public function get_plugin_info() {
		global $wpdb;
		global $wp_version;

		$info                      = array();
		$info['wp_version']        = $wp_version;
		$info['wc_plugin_version'] = ( function_exists( 'WC' ) && is_object( WC() ) ) ? WC()->version : null;
		$info['cc_plugin_version'] = defined( 'CC_PLUGIN_VERSION' ) ? CC_PLUGIN_VERSION : null;

		$sql       = "SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE 'cc_%'";
		$cache_key = 'cc_plugin_info_' . md5( $sql );

		$results = function_exists( 'wp_cache_get' ) ? wp_cache_get( $cache_key ) : false;

		if ( false === $results ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE %s",
					'cc_%'
				)
			);
			if ( function_exists( 'wp_cache_set' ) ) {
				wp_cache_set( $cache_key, $results );
			}
		}

		$info['cc_options'] = $results;

		return $info;
	}

	public function permission_callback( $request ) {
		global $wpdb;
		$queryparams = $request->get_params();
		$key         = $wpdb->get_row(
			$wpdb->prepare(
				"
			SELECT consumer_secret
			FROM {$wpdb->prefix}woocommerce_api_keys
			WHERE consumer_secret = %s
		",
				$queryparams['consumer_secret']
			),
			ARRAY_A
		);

		if ( $key['consumer_secret'] ) {
			return true;
		}
		return false;
	}

	public function send_category_related_notification( $args ) {
		global $wpdb;

		$id         = isset( $args['term'] ) ? $args['term'] : 0;
		$topic      = isset( $args['topic'] ) ? $args['topic'] : null;
		$body       = $args;
		$body['id'] = $id;

		$data              = explode( '.', $topic );
		$original_resource = 'product';
		$modified_resource = $data[0];
		$event             = $data[1];
		$sql               = "SELECT webhook_id, `name`, delivery_url FROM {$wpdb->prefix}wc_webhooks WHERE topic='{$original_resource}.{$event}' AND `name` LIKE 'convertcart%' AND delivery_url LIKE '%data-warehouse%'";
		$cache_key         = 'cc_plugin_info_' . md5( $sql );

		$results = function_exists( 'wp_cache_get' ) ? wp_cache_get( $cache_key ) : false;

		if ( false === $results ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT webhook_id, `name`, delivery_url FROM {$wpdb->prefix}wc_webhooks WHERE topic='{$original_resource}.{$event}' AND `name` LIKE %s AND delivery_url LIKE %s",
					'convertcart%',
					'%data-warehouse%'
				)
			);
			if ( function_exists( 'wp_cache_set' ) ) {
				wp_cache_set( $cache_key, $results );
			}
		}

		foreach ( $results as $result ) {
			$model_delivery_url = $result->delivery_url;
			$target_url         = preg_replace( "/{$original_resource}/", "{$modified_resource}", $model_delivery_url );

			$opts = array(
				'body'        => wp_json_encode( $body ),
				'timeout'     => '120',
				'redirection' => '5',
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'cookies'     => array(),
				'url'         => $target_url,
			);

			$response = wp_remote_post( $target_url, $opts );
		}
	}
}
