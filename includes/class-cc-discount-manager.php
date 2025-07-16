<?php
/**
 * Discount Manager Class
 *
 * @package ConvertCart\Analytics
 */

namespace ConvertCart\Analytics;

class CC_Discount_Manager {
	private $user_ids;
	private $user_ids_string;

	public function __construct( $user_ids_string ) {
		$this->user_ids_string = $user_ids_string;
		$this->user_ids        = array_filter( array_map( 'intval', explode( ',', $user_ids_string ) ) );
	}

	public function init_cron() {
		register_activation_hook( $this->get_plugin_file(), array( __CLASS__, 'register_cron' ) );
		register_deactivation_hook( $this->get_plugin_file(), array( __CLASS__, 'clear_cron' ) );
	}

	public static function register_cron() {
		if ( ! wp_next_scheduled( 'flycart_user_discount_cron_hook' ) ) {
			wp_schedule_event( time(), 'one_hours', 'flycart_user_discount_cron_hook' );
			echo "Flycart cron registered.\n";
		}
	}

	public static function clear_cron() {
		wp_clear_scheduled_hook( 'flycart_user_discount_cron_hook' );
		echo "Flycart cron cleared.\n";
	}

	public function sync_discounts_for_product( $product_id ) {
		if ( ! $this->is_flycart_available() ) {
			return;
		}

		$this->process_user_discounts( array( $product_id ) );
	}

	public function sync_all_discounts() {
		if ( ! $this->is_flycart_available() ) {
			return;
		}

		if ( empty( $this->user_ids ) ) {
			return;
		}

		$product_ids = get_posts(
			array(
				'post_type'      => array( 'product', 'product_variation' ),
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			)
		);

		$this->process_user_discounts( $product_ids );
	}

	private function process_user_discounts( $product_ids ) {
		if ( empty( $this->user_ids ) ) {
			return;
		}

		$current_user_id = get_current_user_id();

		foreach ( $this->user_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}

			wp_set_current_user( $user_id );

			foreach ( $product_ids as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( ! $product ) {
					continue;
				}

				$discounted_price = apply_filters(
					'advanced_woo_discount_rules_get_product_discount_price',
					$product->get_price(),
					$product
				);

				if ( $discounted_price === false || $discounted_price === null ) {
					continue;
				}

				$new_price = wc_format_decimal( $discounted_price, wc_get_price_decimals() );
				$meta_key  = '_flycart_discounted_price_user_' . $user_id;
				$old_price = wc_format_decimal( get_post_meta( $product_id, $meta_key, true ), wc_get_price_decimals() );

				if ( ( ! $old_price && $new_price ) || ( $old_price !== $new_price ) ) {
					update_post_meta( $product_id, $meta_key, $new_price );
					if ( count( $product_ids ) > 1 ) {
						wp_update_post( array( 'ID' => $product_id ) );
					}
				}
			}
		}

		wp_set_current_user( $current_user_id );
	}

	private function is_flycart_available() {
		return has_filter( 'advanced_woo_discount_rules_get_product_discount_price' );
	}

	private function get_plugin_file() {
		if ( defined( 'WC_CC_ANALYTICS_FILE' ) ) {
			return WC_CC_ANALYTICS_FILE;
		}
		return dirname( __DIR__, 1 ) . '/cc-analytics.php';
	}

	public function handle_product_update( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active( 'woo-discount-rules/woo-discount-rules.php' ) ) {
			$this->sync_discounts_for_product( $post_id );
		}
	}
}
