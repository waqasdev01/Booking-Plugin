<?php
/**
 * Central settings accessors + sanitizers + defaults for the editable form.
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wise_Mirror_Settings {

	const BRAND_COLORS = array(
		'ink'        => '#0F0F0F',
		'gold'       => '#C47A2C',
		'grey'       => '#4C4C4C',
		'teal'       => '#5FA9B5',
		'brown'      => '#3A2A1E',
		'gold_light' => '#E2A95B',
		'cream'      => '#E8D8C3',
		'teal_dark'  => '#2F6F78',
		'ivory'      => '#F5EBDD',
	);

	/**
	 * Generic option getter with array-merge defaults.
	 *
	 * @param string $key     Option key.
	 * @param array  $default Fallback default.
	 * @return array
	 */
	public static function get( $key, $default = array() ) {
		$value = get_option( $key, $default );
		if ( is_array( $default ) && is_array( $value ) ) {
			return array_merge( $default, $value );
		}
		return $value;
	}

	public static function general() {
		return self::get(
			'wise_mirror_general_settings',
			array(
				'booking_page_url' => '',
				'currency'         => 'usd',
				'support_email'    => '',
				'includes_items'   => "Personalized one-on-one reading\nWritten summary sent after your session\nConfirmation email within 24 hours\nSecure, private booking details",
			)
		);
	}

	public static function pricing_map() {
		return self::get( 'wise_mirror_pricing_map', array() );
	}

	public static function stripe() {
		return self::get(
			'wise_mirror_stripe_settings',
			array(
				'mode'                 => 'test',
				'test_publishable_key' => '',
				'test_secret_key'      => '',
				'live_publishable_key' => '',
				'live_secret_key'      => '',
				'webhook_secret_test'  => '',
				'webhook_secret_live'  => '',
			)
		);
	}

	public static function email_settings() {
		return self::get(
			'wise_mirror_email_settings',
			array(
				'method'      => 'wp_mail',
				'smtp_host'   => '',
				'smtp_port'   => '587',
				'smtp_user'   => '',
				'smtp_pass'   => '',
				'smtp_secure' => 'tls',
				'from_name'   => 'The Wise Mirror',
				'from_email'  => get_bloginfo( 'admin_email' ),
			)
		);
	}

	public static function email_template() {
		return self::get( 'wise_mirror_email_template', array() );
	}

	public static function upload_settings() {
		return self::get(
			'wise_mirror_upload_settings',
			array(
				'max_size_mb'          => 20,
				'allowed_types'        => array( 'jpg', 'jpeg', 'png', 'webp', 'heic' ),
				'max_images_per_field' => 5,
				'example_smiling'      => '',
				'example_unsmiling'    => '',
				'example_profile'      => '',
			)
		);
	}

	public static function system_settings() {
		return self::get(
			'wise_mirror_system_settings',
			array(
				'debug_mode'              => false,
				'notify_admin_on_booking' => true,
				'notify_admin_email'      => get_bloginfo( 'admin_email' ),
				'cache_ttl_minutes'       => 5,
				'license_key'             => '',
			)
		);
	}

	public static function sanitize_system( $raw ) {
		return array(
			'debug_mode'              => ! empty( $raw['debug_mode'] ),
			'notify_admin_on_booking' => ! empty( $raw['notify_admin_on_booking'] ),
			'notify_admin_email'      => sanitize_email( $raw['notify_admin_email'] ?? '' ),
			'cache_ttl_minutes'       => max( 1, absint( $raw['cache_ttl_minutes'] ?? 5 ) ),
			'license_key'             => sanitize_text_field( $raw['license_key'] ?? '' ),
		);
	}

	public static function schedule_settings() {
		return self::get( 'wise_mirror_schedule_settings', self::default_schedule_settings() );
	}

	public static function ai_settings() {
		return self::get(
			'wise_mirror_ai_settings',
			array(
				'provider'          => 'none',
				'openai_api_key'    => '',
				'openai_model'      => 'gpt-4o-mini',
				'claude_api_key'    => '',
				'claude_model'      => 'claude-3-5-sonnet-latest',
				'gemini_api_key'    => '',
				'gemini_model'      => 'gemini-1.5-flash',
				'custom_endpoint'   => '',
				'custom_api_key'    => '',
				'system_prompt'     => 'You are a helpful assistant for a booking business.',
				'image_analysis_enabled' => false,
				'image_analysis_prompt'  => 'Describe what you notice in this photo, relevant to a personal reading session.',
				'max_tokens'        => 500,
				'temperature'       => 0.7,
				'logging_enabled'   => true,
			)
		);
	}

	public static function default_schedule_settings() {
		$open_days = array( 'mon', 'tue', 'wed', 'thu', 'fri' );
		$days = array();
		foreach ( array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ) as $day ) {
			$days[ $day ] = array(
				'enabled' => in_array( $day, $open_days, true ),
				'open'    => '09:00',
				'close'   => '17:00',
			);
		}
		return array(
			'days'                  => $days,
			'slot_duration_minutes' => 45,
			'buffer_hours'          => 4,
			'advance_days'          => 30,
			'blocked_dates'         => array(),
		);
	}

	/**
	 * Is the plugin currently in Stripe live mode?
	 *
	 * @return bool
	 */
	public static function is_live_mode() {
		$stripe = self::stripe();
		return 'live' === $stripe['mode'];
	}

	/**
	 * Get the active secret key for the current mode.
	 *
	 * @return string
	 */
	public static function active_secret_key() {
		$stripe = self::stripe();
		return self::is_live_mode() ? $stripe['live_secret_key'] : $stripe['test_secret_key'];
	}

	/**
	 * Get the active publishable key for the current mode.
	 *
	 * @return string
	 */
	public static function active_publishable_key() {
		$stripe = self::stripe();
		return self::is_live_mode() ? $stripe['live_publishable_key'] : $stripe['test_publishable_key'];
	}

	/**
	 * Get the active webhook secret for the current mode.
	 *
	 * @return string
	 */
	public static function active_webhook_secret() {
		$stripe = self::stripe();
		return self::is_live_mode() ? $stripe['webhook_secret_live'] : $stripe['webhook_secret_test'];
	}

	/* ---------------------------------------------------------------- *
	 * Sanitizers
	 * ---------------------------------------------------------------- */

	public static function sanitize_pricing_map( $raw ) {
		$clean = array();
		if ( ! is_array( $raw ) ) {
			return $clean;
		}
		foreach ( $raw as $key => $row ) {
			$slug = sanitize_key( $key );
			if ( '' === $slug ) {
				continue;
			}
			$clean[ $slug ] = array(
				'label'    => sanitize_text_field( $row['label'] ?? '' ),
				'amount'   => max( 0, absint( $row['amount'] ?? 0 ) ),
				'currency' => sanitize_text_field( $row['currency'] ?? 'usd' ),
			);
		}
		return $clean;
	}

	public static function sanitize_stripe( $raw ) {
		return array(
			'mode'                 => ( isset( $raw['mode'] ) && 'live' === $raw['mode'] ) ? 'live' : 'test',
			'test_publishable_key' => sanitize_text_field( $raw['test_publishable_key'] ?? '' ),
			'test_secret_key'      => sanitize_text_field( $raw['test_secret_key'] ?? '' ),
			'live_publishable_key' => sanitize_text_field( $raw['live_publishable_key'] ?? '' ),
			'live_secret_key'      => sanitize_text_field( $raw['live_secret_key'] ?? '' ),
			'webhook_secret_test'  => sanitize_text_field( $raw['webhook_secret_test'] ?? '' ),
			'webhook_secret_live'  => sanitize_text_field( $raw['webhook_secret_live'] ?? '' ),
		);
	}

	public static function sanitize_email_settings( $raw ) {
		return array(
			'method'      => ( isset( $raw['method'] ) && 'smtp' === $raw['method'] ) ? 'smtp' : 'wp_mail',
			'smtp_host'   => sanitize_text_field( $raw['smtp_host'] ?? '' ),
			'smtp_port'   => sanitize_text_field( $raw['smtp_port'] ?? '587' ),
			'smtp_user'   => sanitize_text_field( $raw['smtp_user'] ?? '' ),
			'smtp_pass'   => $raw['smtp_pass'] ?? '',
			'smtp_secure' => in_array( $raw['smtp_secure'] ?? '', array( 'tls', 'ssl', 'none' ), true ) ? $raw['smtp_secure'] : 'tls',
			'from_name'   => sanitize_text_field( $raw['from_name'] ?? '' ),
			'from_email'  => sanitize_email( $raw['from_email'] ?? '' ),
		);
	}

	public static function sanitize_email_template( $raw ) {
		return array(
			'subject'          => sanitize_text_field( $raw['subject'] ?? '' ),
			'heading'          => sanitize_text_field( $raw['heading'] ?? '' ),
			'body'             => sanitize_textarea_field( $raw['body'] ?? '' ),
			'footer'           => sanitize_text_field( $raw['footer'] ?? '' ),
			'button_text'      => sanitize_text_field( $raw['button_text'] ?? '' ),
			'button_url'       => esc_url_raw( $raw['button_url'] ?? '' ),
			'primary_color'    => sanitize_hex_color( $raw['primary_color'] ?? '' ) ?: '#5FA9B5',
			'background_color' => sanitize_hex_color( $raw['background_color'] ?? '' ) ?: '#F5EBDD',
			'text_color'       => sanitize_hex_color( $raw['text_color'] ?? '' ) ?: '#0F0F0F',
		);
	}

	public static function sanitize_general( $raw ) {
		return array(
			'booking_page_url' => esc_url_raw( $raw['booking_page_url'] ?? '' ),
			'currency'         => sanitize_text_field( $raw['currency'] ?? 'usd' ),
			'support_email'    => sanitize_email( $raw['support_email'] ?? '' ),
			'includes_items'   => sanitize_textarea_field( $raw['includes_items'] ?? '' ),
		);
	}

	public static function sanitize_upload_settings( $raw ) {
		$types = isset( $raw['allowed_types'] ) ? (string) $raw['allowed_types'] : '';
		$types = array_filter( array_map( 'trim', explode( ',', $types ) ) );
		$types = array_map( 'sanitize_key', $types );
		if ( empty( $types ) ) {
			$types = array( 'jpg', 'jpeg', 'png', 'webp' );
		}
		return array(
			'max_size_mb'          => max( 1, absint( $raw['max_size_mb'] ?? 20 ) ),
			'allowed_types'        => $types,
			'max_images_per_field' => max( 1, min( 20, absint( $raw['max_images_per_field'] ?? 5 ) ) ),
			'example_smiling'      => esc_url_raw( $raw['example_smiling'] ?? '' ),
			'example_unsmiling'    => esc_url_raw( $raw['example_unsmiling'] ?? '' ),
			'example_profile'      => esc_url_raw( $raw['example_profile'] ?? '' ),
		);
	}

	public static function sanitize_schedule( $raw ) {
		$days = array();
		foreach ( array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ) as $day ) {
			$row = $raw['days'][ $day ] ?? array();
			$days[ $day ] = array(
				'enabled' => ! empty( $row['enabled'] ),
				'open'    => preg_match( '/^\d{2}:\d{2}$/', $row['open'] ?? '' ) ? $row['open'] : '09:00',
				'close'   => preg_match( '/^\d{2}:\d{2}$/', $row['close'] ?? '' ) ? $row['close'] : '17:00',
			);
		}

		$blocked_raw = isset( $raw['blocked_dates'] ) ? (string) $raw['blocked_dates'] : '';
		$blocked     = array_filter( array_map( 'trim', explode( "\n", $blocked_raw ) ) );
		$blocked     = array_values( array_filter( $blocked, function ( $d ) {
			return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d );
		} ) );

		return array(
			'days'                  => $days,
			'slot_duration_minutes' => max( 5, absint( $raw['slot_duration_minutes'] ?? 45 ) ),
			'buffer_hours'          => max( 0, absint( $raw['buffer_hours'] ?? 4 ) ),
			'advance_days'          => max( 1, absint( $raw['advance_days'] ?? 30 ) ),
			'blocked_dates'         => $blocked,
		);
	}

	public static function sanitize_ai( $raw ) {
		$provider = sanitize_key( $raw['provider'] ?? 'none' );
		if ( ! in_array( $provider, array( 'none', 'openai', 'claude', 'gemini', 'custom' ), true ) ) {
			$provider = 'none';
		}
		return array(
			'provider'               => $provider,
			'openai_api_key'         => sanitize_text_field( $raw['openai_api_key'] ?? '' ),
			'openai_model'           => sanitize_text_field( $raw['openai_model'] ?? 'gpt-4o-mini' ),
			'claude_api_key'         => sanitize_text_field( $raw['claude_api_key'] ?? '' ),
			'claude_model'           => sanitize_text_field( $raw['claude_model'] ?? 'claude-3-5-sonnet-latest' ),
			'gemini_api_key'         => sanitize_text_field( $raw['gemini_api_key'] ?? '' ),
			'gemini_model'           => sanitize_text_field( $raw['gemini_model'] ?? 'gemini-1.5-flash' ),
			'custom_endpoint'        => esc_url_raw( $raw['custom_endpoint'] ?? '' ),
			'custom_api_key'         => sanitize_text_field( $raw['custom_api_key'] ?? '' ),
			'system_prompt'          => sanitize_textarea_field( $raw['system_prompt'] ?? '' ),
			'image_analysis_enabled' => ! empty( $raw['image_analysis_enabled'] ),
			'image_analysis_prompt'  => sanitize_textarea_field( $raw['image_analysis_prompt'] ?? '' ),
			'max_tokens'             => max( 50, absint( $raw['max_tokens'] ?? 500 ) ),
			'temperature'            => max( 0, min( 2, (float) ( $raw['temperature'] ?? 0.7 ) ) ),
			'logging_enabled'        => ! empty( $raw['logging_enabled'] ),
		);
	}

	/**
	 * Raw HTML/CSS/JS are stored unslashed but otherwise untouched — the
	 * admin explicitly wants full control (they're admin-only, capability
	 * checked, nonce protected fields), matching the "Form Builder / HTML,
	 * CSS, JS editor" requirement. We still strip disallowed script tags
	 * from an unfiltered_html-lacking user via current_user_can check
	 * upstream in the admin controller, not here.
	 */
	public static function sanitize_code_field( $raw ) {
		return wp_unslash( (string) $raw );
	}

	/* ---------------------------------------------------------------- *
	 * Default editable form markup
	 * ---------------------------------------------------------------- */

	/* ---------------------------------------------------------------- *
	 * Default editable form markup
	 * ---------------------------------------------------------------- */

	public static function default_form_html() {
		return <<<HTML
<div class="wise-booking-form-wrap wise-wizard">
  <div class="wise-booking-grid">

    <div class="wise-card wise-form-card">
      <div class="wise-form-header">
        <h2>Book Your Session</h2>
        <p class="wise-form-subtitle">Complete the steps below — you'll be redirected to Stripe to pay securely at the end.</p>
        <div class="wise-step-pills">
          <span class="wise-step-pill wise-active" data-step="1"><span>1</span><b>Details</b></span>
          <span class="wise-step-pill" data-step="2"><span>2</span><b>Photos</b></span>
          <span class="wise-step-pill" data-step="3"><span>3</span><b>Your Message</b></span>
        </div>
      </div>

      <form id="wise-booking-form" novalidate>

        <div class="wise-wizard-step" data-wizard-step="1">

          <div class="wise-section wise-package-section" id="wise-package-section">
            <h3><span class="wise-section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12v9H4v-9"/><path d="M2 7h20v5H2z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg></span>Choose Your Package</h3>
            <div class="wise-package-summary" id="wise-package-summary" hidden>
              <div>
                <span class="wise-package-summary-label">Selected Package</span>
                <strong id="wise-package-summary-name"></strong>
              </div>
            </div>
            <div class="wise-package-grid" id="wise-package-grid"></div>
            <input type="hidden" name="package_key" id="wise-package-key" required>
          </div>

          <div class="wise-section">
            <h3><span class="wise-section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg></span>Customer Details</h3>
            <div class="wise-field-row">
              <label class="wise-field">
                <span>Full Name *</span>
                <input type="text" name="full_name" required maxlength="191">
              </label>
              <label class="wise-field">
                <span>Email Address *</span>
                <input type="email" name="email" required maxlength="191">
              </label>
            </div>
            <div class="wise-field-row">
              <label class="wise-field">
                <span>Phone Number *</span>
                <input type="tel" name="phone" required maxlength="64">
              </label>
              <label class="wise-field">
                <span>Country</span>
                <select name="country" id="wise-country"><option value="">Select country</option></select>
              </label>
            </div>
            <div class="wise-field-row">
              <div class="wise-field">
                <span>Preferred Contact Method</span>
                <div class="wise-checkbox-group">
                  <label><input type="radio" name="contact_method" value="Email" checked> Email</label>
                  <label><input type="radio" name="contact_method" value="Phone"> Phone</label>
                  <label><input type="radio" name="contact_method" value="Text"> Text</label>
                </div>
              </div>
              <div class="wise-field wise-dob">
                <span>Birth Date *</span>
                <div class="wise-dob-row">
                  <select name="birth_month" required><option value="">Month</option></select>
                  <select name="birth_day" required><option value="">Day</option></select>
                  <select name="birth_year" required><option value="">Year</option></select>
                </div>
              </div>
            </div>
          </div>

          <div class="wise-section">
            <h3><span class="wise-section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></span>Choose Your Date &amp; Time</h3>
            <div class="wise-field-row">
              <label class="wise-field">
                <span>Preferred Date *</span>
                <input type="date" name="booking_date" id="wise-booking-date" required>
              </label>
            </div>
            <div class="wise-field">
              <span>Available Times *</span>
              <div class="wise-slot-grid" id="wise-slot-grid">
                <p class="wise-slot-placeholder">Pick a date to see available times.</p>
              </div>
              <input type="hidden" name="booking_time" id="wise-booking-time" required>
            </div>
          </div>

          <div class="wise-wizard-nav">
            <span></span>
            <button type="button" class="wise-btn wise-wizard-next" data-goto="2">Continue to Photos</button>
          </div>
        </div>

        <div class="wise-wizard-step" data-wizard-step="2" hidden>
          <div class="wise-section" style="margin-top:0;padding-top:0;border-top:none;">
            <h3><span class="wise-section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></span>Your Photos</h3>
            <p class="wise-hint">Drag and drop photos, or click to browse. You can upload multiple photos per category (up to the limit shown below).</p>

            <div class="wise-uploader" data-field="photo_smiling">
              <div class="wise-dropzone" tabindex="0">
                <img class="wise-example-photo" data-example="photo_smiling" alt="Example" hidden>
                <strong>Full Face (Smiling) *</strong>
                <span>Drag &amp; drop or click to upload</span>
                <input type="file" accept="image/*" multiple hidden>
              </div>
              <div class="wise-preview-grid" data-field="photo_smiling"></div>
              <p class="wise-upload-count" data-field="photo_smiling">0 Personal Images Uploaded</p>
              <p class="wise-field-error" data-field="photo_smiling" hidden></p>
            </div>

            <div class="wise-uploader" data-field="photo_unsmiling">
              <div class="wise-dropzone" tabindex="0">
                <img class="wise-example-photo" data-example="photo_unsmiling" alt="Example" hidden>
                <strong>Full Face (Unsmiling) *</strong>
                <span>Drag &amp; drop or click to upload</span>
                <input type="file" accept="image/*" multiple hidden>
              </div>
              <div class="wise-preview-grid" data-field="photo_unsmiling"></div>
              <p class="wise-upload-count" data-field="photo_unsmiling">0 Personal Images Uploaded</p>
              <p class="wise-field-error" data-field="photo_unsmiling" hidden></p>
            </div>

            <div class="wise-uploader" data-field="photo_profile">
              <div class="wise-dropzone" tabindex="0">
                <img class="wise-example-photo" data-example="photo_profile" alt="Example" hidden>
                <strong>Side Profile *</strong>
                <span>Drag &amp; drop or click to upload</span>
                <input type="file" accept="image/*" multiple hidden>
              </div>
              <div class="wise-preview-grid" data-field="photo_profile"></div>
              <p class="wise-upload-count" data-field="photo_profile">0 Personal Images Uploaded</p>
              <p class="wise-field-error" data-field="photo_profile" hidden></p>
            </div>
          </div>

          <div class="wise-wizard-nav">
            <button type="button" class="wise-btn wise-btn-secondary wise-wizard-back" data-goto="1">Back</button>
            <button type="button" class="wise-btn wise-wizard-next" data-goto="3">Continue</button>
          </div>
        </div>

        <div class="wise-wizard-step" data-wizard-step="3" hidden>
          <div class="wise-section" style="margin-top:0;padding-top:0;border-top:none;">
            <h3><span class="wise-section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>What's on Your Mind?</h3>
            <div class="wise-field">
              <span>What would you like guidance on? (select all that apply)</span>
              <div class="wise-checkbox-group">
                <label><input type="checkbox" name="concern_categories[]" value="Career"> Career</label>
                <label><input type="checkbox" name="concern_categories[]" value="Relationships"> Relationships</label>
                <label><input type="checkbox" name="concern_categories[]" value="Important Decisions"> Important Decisions</label>
                <label><input type="checkbox" name="concern_categories[]" value="Other"> Other</label>
              </div>
            </div>
            <label class="wise-field">
              <span>Your Question / Message *</span>
              <textarea name="concerns" rows="4" maxlength="4000" required></textarea>
            </label>
            <label class="wise-field">
              <span>Additional Notes (optional)</span>
              <textarea name="notes" rows="3" maxlength="2000"></textarea>
            </label>
          </div>

          <div class="wise-form-message" id="wise-form-message" role="alert" hidden></div>

          <div class="wise-wizard-nav">
            <button type="button" class="wise-btn wise-btn-secondary wise-wizard-back" data-goto="2">Back</button>
            <button type="submit" class="wise-btn wise-pay">Continue to Payment</button>
          </div>
        </div>

      </form>
    </div>

    <div class="wise-sidebar">
      <div class="wise-card wise-includes-card">
        <h3>What's Included</h3>
        <ul class="wise-includes-list" id="wise-includes-list"></ul>
      </div>

      <div class="wise-payment-card">
        <span class="wise-secure-badge"><span class="wise-secure-dot"></span> Secure Stripe Checkout</span>
        <h3>Payment</h3>
        <div class="wise-payment-package" id="wise-payment-package">Select a package</div>
        <div class="wise-payment-datetime" id="wise-payment-datetime"></div>
        <p class="wise-payment-note">After you submit, you'll be redirected to Stripe to pay securely. Your card details never touch this page.</p>
        <button type="button" class="wise-btn wise-pay wise-pay-sidebar" id="wise-sidebar-cta">Continue to Photos</button>
        <div class="wise-payment-questions" id="wise-payment-questions" hidden>
          <strong>Questions?</strong>
          <p>Email us at <a href="#" id="wise-support-email-link"></a></p>
        </div>
      </div>
    </div>

  </div>

  <div class="wise-payment-result" id="wise-payment-result" hidden>
    <div class="wise-result-icon" id="wise-result-icon"></div>
    <h2 id="wise-result-heading"></h2>
    <p id="wise-result-message"></p>
    <a href="#" class="wise-btn wise-pay" id="wise-result-continue">Continue</a>
  </div>
</div>
HTML;
	}

	public static function default_form_css() {
		return <<<CSS
.wise-booking-form-wrap{max-width:1080px;margin:0 auto;font-family:inherit;color:#0F0F0F;box-sizing:border-box;}
.wise-booking-form-wrap *{box-sizing:border-box;}
.wise-booking-grid{display:grid;grid-template-columns:1fr 340px;gap:28px;align-items:start;}
.wise-card{background:#fff;border:1px solid #E8D8C3;border-radius:16px;padding:32px;box-shadow:0 4px 18px rgba(15,15,15,.05);position:relative;overflow:hidden;}
.wise-form-card::before{content:"";position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#5FA9B5,#C47A2C);}
.wise-form-header h2{margin:0 0 6px;font-size:26px;color:#0F0F0F;}
.wise-form-subtitle{margin:0 0 20px;color:#4C4C4C;font-size:14px;}

/* Step pills */
.wise-step-pills{display:flex;gap:10px;margin-bottom:8px;flex-wrap:wrap;}
.wise-step-pill{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#a89f92;padding:7px 14px 7px 7px;border-radius:999px;background:#F5EBDD;}
.wise-step-pill span{width:22px;height:22px;border-radius:50%;background:#E8D8C3;color:#4C4C4C;display:flex;align-items:center;justify-content:center;font-size:12px;}
.wise-step-pill.wise-active{color:#3A2A1E;background:#F0E2CC;}
.wise-step-pill.wise-active span{background:#C47A2C;color:#fff;}
.wise-step-pill.wise-done span{background:#5FA9B5;color:#fff;}
.wise-step-pill.wise-done span::before{content:"✓";}

.wise-section{margin-top:28px;padding-top:24px;border-top:1px solid #E8D8C3;}
.wise-section:first-of-type{margin-top:8px;padding-top:0;border-top:none;}
.wise-section h3{margin:0 0 18px;font-size:15px;letter-spacing:.03em;color:#C47A2C;text-transform:uppercase;font-weight:700;display:flex;align-items:center;gap:9px;}
.wise-section-icon{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:9px;background:#F5EBDD;color:#C47A2C;flex:0 0 auto;}
.wise-section-icon svg{width:16px;height:16px;}
.wise-field-row{display:flex;gap:18px;flex-wrap:wrap;}
.wise-field-row>*{flex:1;min-width:200px;}
.wise-field{display:block;margin-bottom:18px;}
.wise-field>span{display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:#4C4C4C;}
.wise-field input[type=text],.wise-field input[type=email],.wise-field input[type=tel],.wise-field input[type=date],
.wise-field select,.wise-field textarea{
  width:100%;padding:11px 14px;border:1px solid #E2A95B;border-radius:10px;font-size:15px;background:#fff;
  transition:border-color .15s ease;
}
.wise-field input:focus,.wise-field select:focus,.wise-field textarea:focus{outline:none;border-color:#5FA9B5;}
.wise-dob-row{display:flex;gap:8px;}
.wise-dob-row select{flex:1;}
.wise-checkbox-group label{display:inline-flex;align-items:center;gap:6px;margin:0 18px 10px 0;font-weight:400;color:#0F0F0F;font-size:14px;}
.wise-hint{font-size:13px;color:#4C4C4C;margin-top:-8px;margin-bottom:18px;}
.wise-btn{background:#5FA9B5;color:#fff;border:none;padding:13px 22px;border-radius:999px;font-size:15px;font-weight:600;cursor:pointer;display:inline-block;text-align:center;text-decoration:none;transition:background .15s ease, transform .15s ease;}
.wise-btn:hover{background:#2F6F78;color:#fff;transform:translateY(-1px);}
.wise-btn:active{transform:translateY(0);}
.wise-btn:disabled{opacity:.6;cursor:default;transform:none;}
.wise-btn-secondary{background:#F5EBDD;color:#3A2A1E;}
.wise-btn-secondary:hover{background:#E8D8C3;color:#3A2A1E;}
.wise-wizard-nav{display:flex;justify-content:space-between;align-items:center;margin-top:28px;}
.wise-form-message{margin-top:20px;padding:12px 14px;border-radius:10px;font-size:14px;}
.wise-form-message.wise-error{background:#fbe4e4;color:#8a1f1f;}
.wise-form-message.wise-info{background:#F5EBDD;color:#3A2A1E;}

/* Package cards */
.wise-package-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;}
.wise-package-card{position:relative;border:2px solid #E8D8C3;border-radius:14px;padding:20px 18px;cursor:pointer;background:#fff;transition:border-color .15s ease, box-shadow .15s ease, transform .15s ease;}
.wise-package-card:hover{border-color:#E2A95B;transform:translateY(-2px);box-shadow:0 6px 16px rgba(196,122,44,.12);}
.wise-package-card.wise-selected{border-color:#5FA9B5;box-shadow:0 6px 18px rgba(95,169,181,.2);background:#fbfdfd;}
.wise-package-card-badge{position:absolute;top:-10px;left:14px;background:#C47A2C;color:#fff;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;padding:3px 10px;border-radius:999px;}
.wise-package-card-name{display:block;font-weight:700;font-size:15px;color:#0F0F0F;margin-bottom:6px;line-height:1.3;}
.wise-package-card-duration{display:block;font-size:12px;color:#4C4C4C;margin-bottom:8px;}
.wise-package-card-price{display:block;font-size:19px;font-weight:800;color:#C47A2C;}
.wise-package-card-check{position:absolute;top:14px;right:14px;width:18px;height:18px;border-radius:50%;border:2px solid #E2A95B;background:#fff;transition:all .15s ease;}
.wise-package-card.wise-selected .wise-package-card-check{background:#5FA9B5;border-color:#5FA9B5;box-shadow:0 0 0 3px rgba(95,169,181,.18);}
.wise-package-summary{display:flex;align-items:center;justify-content:space-between;gap:12px;background:#F5EBDD;border-radius:12px;padding:14px 18px;margin-bottom:14px;}
.wise-package-summary-label{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#4C4C4C;margin-bottom:2px;}
.wise-package-summary strong{font-size:16px;color:#3A2A1E;}
.wise-link-btn{background:none;border:none;color:#2F6F78;font-weight:600;font-size:13px;cursor:pointer;text-decoration:underline;padding:0;}

/* Date & time */
.wise-slot-grid{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;}
.wise-slot-btn{border:1px solid #E2A95B;background:#fff;color:#3A2A1E;padding:9px 16px;border-radius:999px;font-size:13.5px;cursor:pointer;transition:all .15s ease;}
.wise-slot-btn:hover{border-color:#5FA9B5;background:#F5EBDD;}
.wise-slot-btn.wise-selected{background:#5FA9B5;border-color:#5FA9B5;color:#fff;box-shadow:0 3px 10px rgba(95,169,181,.3);}
.wise-slot-btn:disabled{background:#E8D8C3;border-color:#5FA9B5;color:#a89f92;cursor:not-allowed;}
.wise-slot-placeholder,.wise-slot-empty{font-size:13.5px;color:#4C4C4C;margin:4px 0 0;}

/* Drag & drop uploader */
.wise-uploader{margin-bottom:22px;}
.wise-dropzone{border:2px dashed #E2A95B;border-radius:14px;padding:26px 20px;text-align:center;cursor:pointer;transition:all .15s ease;background:#FBF7F0;}
.wise-dropzone:hover,.wise-dropzone:focus{border-color:#5FA9B5;background:#F5EBDD;outline:none;}
.wise-dropzone.wise-dragover{border-color:#5FA9B5;background:#eef7f8;}
.wise-dropzone strong{display:block;font-size:14px;color:#0F0F0F;margin-bottom:4px;}
.wise-dropzone span{display:block;font-size:12.5px;color:#4C4C4C;}
.wise-preview-grid{display:flex;flex-wrap:wrap;gap:10px;margin-top:12px;}
.wise-upload-count{font-size:12.5px;font-weight:600;color:#2F6F78;margin:8px 0 0;}
.wise-example-photo{display:block;width:64px;height:64px;object-fit:cover;border-radius:8px;margin:0 auto 10px;border:2px solid #E2A95B;}
.wise-field-error{font-size:12.5px;font-weight:600;color:#8a1f1f;margin:8px 0 0;}
.wise-preview-item{position:relative;width:84px;height:84px;border-radius:10px;overflow:hidden;background:#F5EBDD;border:1px solid #E8D8C3;}
.wise-preview-item img{width:100%;height:100%;object-fit:cover;display:block;}
.wise-preview-remove{position:absolute;top:3px;right:3px;width:20px;height:20px;border-radius:50%;background:rgba(15,15,15,.65);color:#fff;border:none;font-size:12px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;}
.wise-preview-progress{position:absolute;left:0;right:0;bottom:0;height:4px;background:rgba(255,255,255,.5);}
.wise-preview-progress-bar{height:100%;background:#5FA9B5;width:0;transition:width .2s ease;}
.wise-preview-progress-label{position:absolute;bottom:4px;right:4px;background:rgba(15,15,15,.7);color:#fff;font-size:10px;font-weight:700;padding:1px 5px;border-radius:4px;line-height:1.4;}
.wise-preview-item.wise-upload-error{border-color:#c0392b;}
.wise-preview-error-msg{font-size:11px;color:#8a1f1f;margin-top:2px;}

/* Sidebar */
.wise-sidebar{display:flex;flex-direction:column;gap:20px;position:sticky;top:24px;}
.wise-includes-card h3{margin:0 0 16px;font-size:16px;color:#0F0F0F;}
.wise-includes-list{list-style:none;margin:0;padding:0;}
.wise-includes-list li{display:flex;gap:10px;align-items:flex-start;padding:10px 0;border-top:1px solid #F5EBDD;font-size:13.5px;color:#4C4C4C;line-height:1.4;}
.wise-includes-list li:first-child{border-top:none;padding-top:0;}
.wise-includes-list li::before{content:"✓";flex:0 0 20px;height:20px;width:20px;border-radius:50%;background:#e5f4ed;color:#2F6F78;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;margin-top:1px;}

.wise-payment-card{background:linear-gradient(160deg,#0F0F0F 0%,#3A2A1E 100%);border-radius:16px;padding:28px;color:#fff;box-shadow:0 10px 28px rgba(15,15,15,.18);}
.wise-secure-badge{display:inline-flex;align-items:center;gap:7px;background:rgba(95,169,181,.18);border:1px solid #5FA9B5;color:#8fd0da;font-size:12px;font-weight:600;padding:5px 12px;border-radius:999px;margin-bottom:18px;}
.wise-secure-dot{width:7px;height:7px;border-radius:50%;background:#5FA9B5;display:inline-block;}
.wise-payment-card h3{margin:0 0 10px;font-size:15px;color:#E8D8C3;font-weight:600;text-transform:uppercase;letter-spacing:.03em;}
.wise-payment-package{font-size:20px;font-weight:700;color:#E2A95B;margin-bottom:6px;}
.wise-payment-datetime{font-size:13px;color:#c9c2b8;margin-bottom:14px;min-height:1em;}
.wise-payment-note{font-size:12.5px;color:#c9c2b8;line-height:1.5;margin:0 0 20px;}
.wise-payment-card .wise-btn{background:#C47A2C;width:100%;}
.wise-payment-card .wise-btn:hover{background:#E2A95B;}
.wise-payment-questions{margin-top:20px;padding-top:18px;border-top:1px solid rgba(232,216,195,.2);font-size:13px;color:#c9c2b8;}
.wise-payment-questions strong{color:#fff;}
.wise-payment-questions a{color:#8fd0da;}

/* Result view */
.wise-payment-result{max-width:520px;margin:60px auto;text-align:center;padding:48px 32px;border-radius:16px;background:#fff;border:1px solid #E8D8C3;box-shadow:0 8px 30px rgba(15,15,15,.08);}
.wise-result-icon{width:64px;height:64px;border-radius:50%;margin:0 auto 20px;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;}
.wise-payment-result.wise-success .wise-result-icon{background:#e5f4ed;color:#2F6F78;}
.wise-payment-result.wise-failed .wise-result-icon{background:#fbe4e4;color:#8a1f1f;}
.wise-payment-result h2{margin:0 0 10px;font-size:22px;color:#0F0F0F;}
.wise-payment-result p{color:#4C4C4C;font-size:14.5px;line-height:1.6;margin:0 0 26px;}
.wise-payment-result .wise-btn{width:auto;padding:12px 28px;}

@media (max-width: 900px){
  .wise-booking-grid{grid-template-columns:1fr;}
  .wise-sidebar{position:static;}
}
@media (max-width: 480px){
  .wise-card{padding:22px 18px;}
  .wise-form-header h2{font-size:22px;}
  .wise-field-row{gap:12px;}
  .wise-package-grid{grid-template-columns:1fr 1fr;}
  .wise-step-pill b{display:none;}
}
CSS;
	}

	public static function default_form_js() {
		return <<<JS
(function(){
  var root = document.getElementById('wise-booking-form');
  if (!root) return;

  var data = window.WiseMirrorBooking || {};
  var wrap = document.querySelector('.wise-wizard');

  /* ---------- Wizard step navigation ---------- */
  var steps = Array.prototype.slice.call(root.querySelectorAll('.wise-wizard-step'));
  var pills = Array.prototype.slice.call(document.querySelectorAll('.wise-step-pill'));
  var currentStep = 1;

  function showStep(n){
    steps.forEach(function(s){ s.hidden = (parseInt(s.dataset.wizardStep, 10) !== n); });
    pills.forEach(function(p){
      var s = parseInt(p.dataset.step, 10);
      p.classList.toggle('wise-active', s === n);
      p.classList.toggle('wise-done', s < n);
    });
    currentStep = n;
    updateSidebarCta();
    window.scrollTo({ top: wrap.getBoundingClientRect().top + window.scrollY - 20, behavior: 'smooth' });
  }

  function validateStep(n){
    var stepEl = steps[n - 1];
    var fields = stepEl.querySelectorAll('[required]');
    for (var i = 0; i < fields.length; i++){
      var f = fields[i];
      if (f.type === 'radio'){
        var group = stepEl.querySelectorAll('[name="' + f.name + '"]');
        var checked = Array.prototype.some.call(group, function(g){ return g.checked; });
        if (!checked){ f.reportValidity(); return false; }
        continue;
      }
      if (!f.value){ f.reportValidity(); return false; }
    }
    if (n === 2){
      var fieldLabels = {
        photo_smiling: 'Full Face (Smiling)',
        photo_unsmiling: 'Full Face (Unsmiling)',
        photo_profile: 'Side Profile'
      };
      var missing = Object.keys(fieldLabels).filter(function(field){
        return !uploaded[field] || !uploaded[field].length;
      });

      Object.keys(fieldLabels).forEach(function(field){
        var errEl = root.querySelector('.wise-field-error[data-field="' + field + '"]');
        if (!errEl) return;
        if (missing.indexOf(field) > -1){
          errEl.textContent = 'Please upload your ' + fieldLabels[field] + ' photo.';
          errEl.hidden = false;
        } else {
          errEl.hidden = true;
        }
      });

      if (missing.length){
        showMessage('Please upload the required photo(s) highlighted below before continuing.', 'error');
        return false;
      }
    }
    return true;
  }

  root.querySelectorAll('.wise-wizard-next').forEach(function(btn){
    btn.addEventListener('click', function(){
      if (!validateStep(currentStep)) return;
      showStep(parseInt(btn.dataset.goto, 10));
    });
  });
  root.querySelectorAll('.wise-wizard-back').forEach(function(btn){
    btn.addEventListener('click', function(){ showStep(parseInt(btn.dataset.goto, 10)); });
  });

  var sidebarCta = document.getElementById('wise-sidebar-cta');
  function updateSidebarCta(){
    if (!sidebarCta) return;
    if (currentStep === 1){ sidebarCta.textContent = 'Continue to Photos'; }
    else if (currentStep === 2){ sidebarCta.textContent = 'Continue to Your Message'; }
    else { sidebarCta.textContent = 'Continue to Payment'; }
  }
  if (sidebarCta){
    sidebarCta.addEventListener('click', function(){
      if (!validateStep(currentStep)) return;
      if (currentStep < 3){ showStep(currentStep + 1); }
      else { root.requestSubmit ? root.requestSubmit() : root.dispatchEvent(new Event('submit', { cancelable: true })); }
    });
  }

  /* ---------- Birth date selects ---------- */
  var daySelect = root.querySelector('[name=birth_day]');
  for (var d = 1; d <= 31; d++){
    var o = document.createElement('option'); o.value = d; o.textContent = d; daySelect.appendChild(o);
  }
  var yearSelect = root.querySelector('[name=birth_year]');
  var thisYear = new Date().getFullYear();
  for (var y = thisYear; y >= thisYear - 100; y--){
    var oy = document.createElement('option'); oy.value = y; oy.textContent = y; yearSelect.appendChild(oy);
  }
  var monthSelect = root.querySelector('[name=birth_month]');
  ['January','February','March','April','May','June','July','August','September','October','November','December'].forEach(function(m, idx){
    var om = document.createElement('option'); om.value = idx + 1; om.textContent = m; monthSelect.appendChild(om);
  });

  /* ---------- Country list ---------- */
  var countrySelect = document.getElementById('wise-country');
  if (countrySelect){
    ['United States','United Kingdom','Canada','Australia','Ireland','Pakistan','India','United Arab Emirates','Germany','France','New Zealand','Other'].forEach(function(c){
      var oc = document.createElement('option'); oc.value = c; oc.textContent = c; countrySelect.appendChild(oc);
    });
  }

  /* ---------- Package cards ---------- */
  var pkgGrid = document.getElementById('wise-package-grid');
  var pkgKeyInput = document.getElementById('wise-package-key');
  var pkgSummary = document.getElementById('wise-package-summary');
  var pkgSummaryName = document.getElementById('wise-package-summary-name');
  var pkgDisplay = document.getElementById('wise-payment-package');

  function formatPrice(session){
    return session.price > 0 ? (session.price / 100).toFixed(2) + ' ' + session.currency.toUpperCase() : 'Free';
  }
  function formatPackage(session){
    return session ? (session.name + ' — ' + formatPrice(session)) : 'Select a package';
  }

  function selectPackage(key){
    pkgKeyInput.value = key;
    var session = data.sessions ? data.sessions[key] : null;
    pkgGrid.querySelectorAll('.wise-package-card').forEach(function(card){
      card.classList.toggle('wise-selected', card.dataset.key === key);
    });
    pkgDisplay.textContent = formatPackage(session);
    if (session) pkgSummaryName.textContent = formatPackage(session);
  }

  function showGrid(show){
    pkgGrid.hidden = !show;
    pkgSummary.hidden = show;
  }

  if (pkgGrid && data.sessions){
    (data.sessionsOrder || Object.keys(data.sessions)).forEach(function(key){
      var session = data.sessions[key];
      if (!session) return;
      var card = document.createElement('div');
      card.className = 'wise-package-card';
      card.dataset.key = key;
      var badge = session.badge ? '<span class="wise-package-card-badge"></span>' : '';
      card.innerHTML = '<span class="wise-package-card-check"></span>' + badge +
        '<span class="wise-package-card-name"></span>' +
        '<span class="wise-package-card-duration"></span>' +
        '<span class="wise-package-card-price"></span>';
      if (session.badge) card.querySelector('.wise-package-card-badge').textContent = session.badge;
      card.querySelector('.wise-package-card-name').textContent = session.name;
      card.querySelector('.wise-package-card-duration').textContent = session.duration || '';
      card.querySelector('.wise-package-card-price').textContent = formatPrice(session);
      card.addEventListener('click', function(){ selectPackage(key); });
      pkgGrid.appendChild(card);
    });

    var params = new URLSearchParams(window.location.search);
    var pre = params.get('package');
    if (pre && data.sessions[pre]){
      selectPackage(pre);
      showGrid(false);
    } else {
      showGrid(true);
    }
  }

  /* ---------- Date & time ---------- */
  var dateInput = document.getElementById('wise-booking-date');
  var slotGrid = document.getElementById('wise-slot-grid');
  var timeInput = document.getElementById('wise-booking-time');
  var paymentDatetime = document.getElementById('wise-payment-datetime');

  function formatTime12h(time24){
    var parts = time24.split(':');
    var h = parseInt(parts[0], 10);
    var m = parts[1];
    var suffix = h >= 12 ? 'PM' : 'AM';
    var h12 = h % 12;
    if (h12 === 0) h12 = 12;
    return h12 + ':' + m + ' ' + suffix;
  }

  if (dateInput){
    var today = new Date();
    dateInput.min = today.toISOString().slice(0, 10);
    if (data.scheduleAdvanceDays){
      var maxDate = new Date();
      maxDate.setDate(maxDate.getDate() + parseInt(data.scheduleAdvanceDays, 10));
      dateInput.max = maxDate.toISOString().slice(0, 10);
    }

    dateInput.addEventListener('change', function(){
      timeInput.value = '';
      updatePaymentDatetime();
      if (!dateInput.value){ return; }

      slotGrid.innerHTML = '<p class="wise-slot-placeholder">Loading available times…</p>';

      fetch(data.restUrl + 'available-slots?date=' + encodeURIComponent(dateInput.value))
        .then(function(r){ return r.json(); })
        .then(function(res){
          slotGrid.innerHTML = '';
          if (!res.slots || !res.slots.length){
            var msg = document.createElement('p');
            msg.className = 'wise-slot-empty';
            msg.textContent = res.message || 'No times available on that date. Please try another day.';
            slotGrid.appendChild(msg);
            return;
          }
          res.slots.forEach(function(slot){
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'wise-slot-btn';
            btn.textContent = formatTime12h(slot);
            btn.addEventListener('click', function(){
              slotGrid.querySelectorAll('.wise-slot-btn').forEach(function(b){ b.classList.remove('wise-selected'); });
              btn.classList.add('wise-selected');
              timeInput.value = slot;
              updatePaymentDatetime();
            });
            slotGrid.appendChild(btn);
          });
        })
        .catch(function(){
          slotGrid.innerHTML = '<p class="wise-slot-empty">Could not load available times. Please try again.</p>';
        });
    });
  }

  function updatePaymentDatetime(){
    if (!paymentDatetime) return;
    paymentDatetime.textContent = (dateInput.value && timeInput.value) ? (dateInput.value + ' at ' + formatTime12h(timeInput.value)) : '';
  }

  /* ---------- Example photos ---------- */
  if (data.examplePhotos){
    root.querySelectorAll('.wise-example-photo').forEach(function(img){
      var url = data.examplePhotos[img.dataset.example];
      if (url){
        img.src = url;
        img.hidden = false;
      }
    });
  }

  /* ---------- Drag & drop multi-image uploader ---------- */
  var uploaded = { photo_smiling: [], photo_unsmiling: [], photo_profile: [] };
  var maxImages = parseInt(data.uploadMaxImages, 10) || 5;
  var maxSizeBytes = (parseInt(data.uploadMaxSizeMb, 10) || 20) * 1024 * 1024;

  root.querySelectorAll('.wise-uploader').forEach(function(uploaderEl){
    var field = uploaderEl.dataset.field;
    var dropzone = uploaderEl.querySelector('.wise-dropzone');
    var input = uploaderEl.querySelector('input[type=file]');
    var previewGrid = uploaderEl.querySelector('.wise-preview-grid');

    function openPicker(){ input.click(); }
    dropzone.addEventListener('click', openPicker);
    dropzone.addEventListener('keydown', function(e){ if (e.key === 'Enter' || e.key === ' '){ e.preventDefault(); openPicker(); } });

    ['dragenter','dragover'].forEach(function(evt){
      dropzone.addEventListener(evt, function(e){ e.preventDefault(); dropzone.classList.add('wise-dragover'); });
    });
    ['dragleave','drop'].forEach(function(evt){
      dropzone.addEventListener(evt, function(e){ e.preventDefault(); dropzone.classList.remove('wise-dragover'); });
    });
    dropzone.addEventListener('drop', function(e){
      handleFiles(field, previewGrid, e.dataTransfer.files);
    });
    input.addEventListener('change', function(){
      handleFiles(field, previewGrid, input.files);
      input.value = '';
    });
  });

  function updateUploadCount(field){
    var countEl = root.querySelector('.wise-upload-count[data-field="' + field + '"]');
    if (countEl){
      var n = uploaded[field].length;
      countEl.textContent = n + ' Personal Image' + (n === 1 ? '' : 's') + ' Uploaded';
    }
    var errEl = root.querySelector('.wise-field-error[data-field="' + field + '"]');
    if (errEl && uploaded[field].length){
      errEl.hidden = true;
    }
  }

  function handleFiles(field, previewGrid, fileList){
    var files = Array.prototype.slice.call(fileList);
    files.forEach(function(file){
      if (uploaded[field].length >= maxImages){
        showMessage('You can upload up to ' + maxImages + ' photos per category.', 'error');
        return;
      }
      if (!file.type.match('image.*')){ return; }
      if (file.size > maxSizeBytes){
        showMessage(file.name + ' is too large (max ' + data.uploadMaxSizeMb + 'MB).', 'error');
        return;
      }
      uploadFile(field, previewGrid, file);
    });
  }

  function uploadFile(field, previewGrid, file){
    var item = document.createElement('div');
    item.className = 'wise-preview-item';
    var img = document.createElement('img');
    var reader = new FileReader();
    reader.onload = function(e){ img.src = e.target.result; };
    reader.readAsDataURL(file);
    item.appendChild(img);

    var progressWrap = document.createElement('div');
    progressWrap.className = 'wise-preview-progress';
    var progressBar = document.createElement('div');
    progressBar.className = 'wise-preview-progress-bar';
    progressWrap.appendChild(progressBar);
    item.appendChild(progressWrap);

    var progressLabel = document.createElement('span');
    progressLabel.className = 'wise-preview-progress-label';
    progressLabel.textContent = '0%';
    item.appendChild(progressLabel);

    var removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'wise-preview-remove';
    removeBtn.innerHTML = '&times;';
    removeBtn.style.display = 'none';
    item.appendChild(removeBtn);

    previewGrid.appendChild(item);

    var fd = new FormData();
    fd.append('action', 'wise_upload_photo');
    fd.append('nonce', data.nonce);
    fd.append('field', field);
    fd.append('file', file);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', data.ajaxUrl, true);
    xhr.upload.onprogress = function(e){
      if (e.lengthComputable){
        var pct = Math.round((e.loaded / e.total) * 100);
        progressBar.style.width = pct + '%';
        progressLabel.textContent = pct + '%';
      }
    };
    xhr.onload = function(){
      var res;
      try { res = JSON.parse(xhr.responseText); } catch (err) { res = null; }
      if (res && res.success){
        uploaded[field].push(res.data.url);
        updateUploadCount(field);
        progressWrap.remove();
        progressLabel.remove();
        removeBtn.style.display = 'flex';
        removeBtn.addEventListener('click', function(){
          var idx = uploaded[field].indexOf(res.data.url);
          if (idx > -1) uploaded[field].splice(idx, 1);
          updateUploadCount(field);
          item.remove();
        });
      } else {
        item.classList.add('wise-upload-error');
        progressWrap.remove();
        progressLabel.remove();
        var errMsg = document.createElement('div');
        errMsg.className = 'wise-preview-error-msg';
        errMsg.textContent = (res && res.data && res.data.message) || 'Upload failed';
        item.after(errMsg);
        removeBtn.style.display = 'flex';
        removeBtn.addEventListener('click', function(){ item.remove(); errMsg.remove(); });
      }
    };
    xhr.onerror = function(){
      item.classList.add('wise-upload-error');
      progressWrap.remove();
      progressLabel.remove();
    };
    xhr.send(fd);
  }

  /* ---------- What's Included / support email ---------- */
  var includesList = document.getElementById('wise-includes-list');
  if (includesList && Array.isArray(data.includesItems)){
    data.includesItems.forEach(function(item){
      if (!item) return;
      var li = document.createElement('li');
      li.textContent = item;
      includesList.appendChild(li);
    });
  }

  if (data.supportEmail){
    var qBlock = document.getElementById('wise-payment-questions');
    var qLink = document.getElementById('wise-support-email-link');
    if (qBlock && qLink){
      qLink.href = 'mailto:' + data.supportEmail;
      qLink.textContent = data.supportEmail;
      qBlock.hidden = false;
    }
  }

  function showMessage(text, type){
    var el = document.getElementById('wise-form-message');
    if (!el) return;
    el.hidden = false;
    el.textContent = text;
    el.className = 'wise-form-message wise-' + type;
  }

  /* ---------- Submit ---------- */
  root.addEventListener('submit', function(e){
    e.preventDefault();

    if (!pkgKeyInput.value){ showStep(1); showMessage('Please choose a package.', 'error'); return; }
    if (!timeInput.value){ showStep(1); showMessage('Please choose a booking date and time.', 'error'); return; }
    if (!validateStep(2)){ showStep(2); return; }
    if (!validateStep(3)) return;

    var payButtons = document.querySelectorAll('.wise-pay');
    payButtons.forEach(function(btn){ btn.disabled = true; });
    var submitBtn = root.querySelector('button[type=submit]');
    if (submitBtn) submitBtn.textContent = 'Processing…';

    var fd = new FormData(root);
    fd.append('action', 'wise_submit_booking');
    fd.append('nonce', data.nonce);
    fd.append('photo_smiling_urls', JSON.stringify(uploaded.photo_smiling));
    fd.append('photo_unsmiling_urls', JSON.stringify(uploaded.photo_unsmiling));
    fd.append('photo_profile_urls', JSON.stringify(uploaded.photo_profile));

    fetch(data.ajaxUrl, { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (res.success && res.data.checkout_url){
          window.location.href = res.data.checkout_url;
        } else {
          showMessage((res.data && res.data.message) || 'Something went wrong. Please try again.', 'error');
          payButtons.forEach(function(btn){ btn.disabled = false; });
          if (submitBtn) submitBtn.textContent = 'Continue to Payment';
        }
      })
      .catch(function(){
        showMessage('Network error. Please try again.', 'error');
        payButtons.forEach(function(btn){ btn.disabled = false; });
        if (submitBtn) submitBtn.textContent = 'Continue to Payment';
      });
  });

  /* ---------- Payment result (after Stripe redirect) ---------- */
  var urlParams = new URLSearchParams(window.location.search);
  var sessionId = urlParams.get('wise_session_id');
  if (sessionId){
    var grid = document.querySelector('.wise-booking-grid');
    if (grid) grid.style.display = 'none';

    var resultEl = document.getElementById('wise-payment-result');
    var iconEl = document.getElementById('wise-result-icon');
    var headingEl = document.getElementById('wise-result-heading');
    var messageEl = document.getElementById('wise-result-message');
    var continueEl = document.getElementById('wise-result-continue');
    var baseUrl = window.location.href.split('?')[0];
    continueEl.href = baseUrl;

    resultEl.hidden = false;
    resultEl.className = 'wise-payment-result';
    iconEl.textContent = '…';
    headingEl.textContent = 'Verifying your payment…';
    messageEl.textContent = 'Please wait a moment.';

    fetch(data.restUrl + 'verify-payment?session_id=' + encodeURIComponent(sessionId), {
      headers: { 'X-WP-Nonce': data.restNonce }
    })
      .then(function(r){ return r.json(); })
      .then(function(res){
        resultEl.className = 'wise-payment-result ' + (res.paid ? 'wise-success' : 'wise-failed');
        iconEl.textContent = res.paid ? '✓' : '✕';
        headingEl.textContent = res.paid ? 'Payment Successful' : 'Payment Failed';
        messageEl.textContent = res.paid
          ? 'Thank you — your booking is confirmed. A confirmation email is on its way.'
          : 'Your card was not charged. Please try again or contact us if this continues.';
      })
      .catch(function(){
        resultEl.className = 'wise-payment-result wise-failed';
        iconEl.textContent = '✕';
        headingEl.textContent = 'Payment Failed';
        messageEl.textContent = 'We could not verify your payment status. Please contact us before retrying.';
      });
  }

  showStep(1);
})();
JS;
	}
}
