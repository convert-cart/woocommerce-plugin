<?php

namespace ConvertCart\Analytics;

abstract class CC_Consent_Manager {
	protected $consent_type;
	protected $settings;

	public function __construct( $consent_type, $settings ) {
		$this->consent_type = $consent_type;
		$this->settings     = $settings;
	}

	protected function is_enabled() {
		return $this->is_consent_enabled();
	}

	protected function get_consent_html( $context, $default_html ) {
		return get_option( "cc_{$this->consent_type}_consent_{$context}_html", $default_html );
	}

	protected function is_consent_enabled() {
		$options = get_option( 'woocommerce_cc_analytics_settings' );
		return isset( $options[ "enable_{$this->consent_type}_consent" ] ) &&
				$options[ "enable_{$this->consent_type}_consent" ] === 'live';
	}

	protected function save_consent_to_user( $user_id, $consent_value ) {
		update_user_meta( $user_id, "{$this->consent_type}_consent", $consent_value );
	}

	protected function save_consent_to_order( $order, $consent_value ) {
		$order->update_meta_data( "{$this->consent_type}_consent", $consent_value );
	}

	protected function get_user_consent( $user_id ) {
		return get_user_meta( $user_id, "{$this->consent_type}_consent", true );
	}

	protected function is_consent_given() {
		return isset( $_POST[ "{$this->consent_type}_consent" ] );
	}

	public function save_consent_to_order_or_customer( $order, $data ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			if ( $this->is_consent_given() ) {
				$this->save_consent_to_user( $user_id, 'yes' );
			}
		} else {
			$consent_value = $this->is_consent_given() ? 'yes' : 'no';
			$this->save_consent_to_order( $order, $consent_value );
		}
	}

	public function save_consent_when_account_is_created( $customer_id, $new_customer_data = null, $password_generated = null ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$consent_value = $this->is_consent_given() ? 'yes' : 'no';
		$this->save_consent_to_user( $customer_id, $consent_value );
	}

	public function save_consent_from_account_page( $user_id ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( $this->is_consent_given() ) {
			$this->save_consent_to_user( $user_id, 'yes' );
		}
	}

	public function save_consent_from_registration_form( $customer_id ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$consent_value = $this->is_consent_given() ? 'yes' : 'no';
		$this->save_consent_to_user( $customer_id, $consent_value );
	}

	public function update_consent_from_previous_orders( $customer_id, $new_customer_data, $password_generated ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$user_email = $new_customer_data['user_email'];

		$orders = wc_get_orders(
			array(
				'billing_email' => $user_email,
				'limit'         => -1,
				'customer_id'   => 0,
			)
		);

		foreach ( $orders as $order ) {
			$consent = $order->get_meta( "{$this->consent_type}_consent" );

			if ( $consent === 'yes' ) {
				$this->save_consent_to_user( $customer_id, 'yes' );
				break;
			}
		}
	}

	abstract public function add_checkout_checkbox();
	abstract public function add_registration_checkbox();
	abstract public function add_account_checkbox();
}

class CC_SMS_Consent_Manager extends CC_Consent_Manager {
	public function __construct( $settings ) {
		parent::__construct( 'sms', $settings );
	}

	public function add_checkout_checkbox() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$default_html = '<div class="sms-consent-checkbox">
			<label for="sms_consent">
				<input type="checkbox" name="sms_consent" id="sms_consent" />
				I consent to receive SMS communications.
			</label>
		</div>';

		$checkout_html = $this->get_consent_html( 'checkout', $default_html );

		if ( is_user_logged_in() ) {
			$user_id       = get_current_user_id();
			$sms_consent   = $this->get_user_consent( $user_id );
			$checkout_html = str_replace( 'id="sms_consent"', 'id="sms_consent" ' . checked( $sms_consent, 'yes', false ), $checkout_html );
		}

		echo $checkout_html;
	}

	public function add_registration_checkbox() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$default_html = '<p class="form-row form-row-wide">
			<label for="sms_consent">' . esc_html__( 'I consent to receive SMS communications', 'woocommerce' ) . '</label>
			<input type="checkbox" name="sms_consent" id="sms_consent" />
		</p>';

		$registration_html = $this->get_consent_html( 'registration', $default_html );
		echo $registration_html;
	}

	public function add_account_checkbox() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$user_id     = get_current_user_id();
		$sms_consent = $this->get_user_consent( $user_id );

		$default_html = '<p class="form-row form-row-wide">
			<label for="sms_consent">' . esc_html__( 'I consent to receive SMS communications', 'woocommerce' ) . '</label>
			<input type="checkbox" name="sms_consent" id="sms_consent" ' . checked( $sms_consent, 'yes', false ) . ' />
		</p>';

		$account_html = $this->get_consent_html( 'account', $default_html );
		$account_html = str_replace( 'id="sms_consent"', 'id="sms_consent" ' . checked( $sms_consent, 'yes', false ), $account_html );

		echo $account_html;
	}
}

class CC_Email_Consent_Manager extends CC_Consent_Manager {
	public function __construct( $settings ) {
		parent::__construct( 'email', $settings );
	}

	public function add_checkout_checkbox() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$default_html = '<div class="email-consent-checkbox">
			<label for="email_consent">
				<input type="checkbox" name="email_consent" id="email_consent" />
				I consent to receive email communications.
			</label>
		</div>';

		$checkout_html = $this->get_consent_html( 'checkout', $default_html );

		if ( is_user_logged_in() ) {
			$user_id       = get_current_user_id();
			$email_consent = $this->get_user_consent( $user_id );
			$checkout_html = str_replace( 'id="email_consent"', 'id="email_consent" ' . checked( $email_consent, 'yes', false ), $checkout_html );
		}

		echo $checkout_html;
	}

	public function add_registration_checkbox() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$default_html = '<p class="form-row form-row-wide">
			<label for="email_consent">' . esc_html__( 'I consent to receive email communications', 'woocommerce' ) . '</label>
			<input type="checkbox" name="email_consent" id="email_consent" />
		</p>';

		$registration_html = $this->get_consent_html( 'registration', $default_html );
		echo $registration_html;
	}

	public function add_account_checkbox() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$user_id       = get_current_user_id();
		$email_consent = $this->get_user_consent( $user_id );

		$default_html = '<p class="form-row form-row-wide">
			<label for="email_consent">' . esc_html__( 'I consent to receive email communications', 'woocommerce' ) . '</label>
			<input type="checkbox" name="email_consent" id="email_consent" ' . checked( $email_consent, 'yes', false ) . ' />
		</p>';

		$account_html = $this->get_consent_html( 'account', $default_html );
		$account_html = str_replace( 'id="email_consent"', 'id="email_consent" ' . checked( $email_consent, 'yes', false ), $account_html );

		echo $account_html;
	}
}
