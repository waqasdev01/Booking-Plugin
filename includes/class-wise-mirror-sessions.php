<?php
/**
 * Session Manager — every bookable "package" is now a fully editable
 * session record (name, price, currency, badge, duration, description,
 * features, button text, booking URL slug, status) instead of a hardcoded
 * 4-item list. Replaces the old wise_mirror_pricing_map structure.
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wise_Mirror_Sessions {

	const OPTION_KEY = 'wise_mirror_sessions';

	/**
	 * All sessions, in display order.
	 *
	 * @return array
	 */
	public static function get_all() {
		$sessions = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $sessions ) ) {
			return array();
		}
		usort( $sessions, function ( $a, $b ) {
			return ( $a['order'] ?? 0 ) <=> ( $b['order'] ?? 0 );
		} );
		return $sessions;
	}

	/**
	 * Only sessions marked active — what the booking form should offer.
	 *
	 * @return array
	 */
	public static function get_active() {
		return array_values( array_filter( self::get_all(), function ( $s ) {
			return 'active' === ( $s['status'] ?? 'active' );
		} ) );
	}

	/**
	 * Look up one session by its slug/key.
	 *
	 * @param string $key Session slug.
	 * @return array|null
	 */
	public static function get( $key ) {
		foreach ( self::get_all() as $session ) {
			if ( $session['key'] === $key ) {
				return $session;
			}
		}
		return null;
	}

	/**
	 * Save the full session list (used by the Session Manager save handler).
	 * Each row is sanitized; blank rows are dropped; keys are de-duplicated.
	 *
	 * @param array $raw Raw $_POST session rows.
	 * @return array The sanitized, saved list.
	 */
	public static function save_all( array $raw ) {
		$clean = array();
		$seen_keys = array();
		$order = 0;

		foreach ( $raw as $row ) {
			$name = sanitize_text_field( $row['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}

			$key = sanitize_title( $row['key'] ?? $name );
			if ( '' === $key ) {
				$key = 'session-' . ( $order + 1 );
			}
			$original_key = $key;
			$i = 2;
			while ( in_array( $key, $seen_keys, true ) ) {
				$key = $original_key . '-' . $i;
				$i++;
			}
			$seen_keys[] = $key;

			$features = isset( $row['features'] ) ? (string) $row['features'] : '';
			$features = array_values( array_filter( array_map( 'trim', explode( "\n", $features ) ) ) );

			$clean[] = array(
				'key'          => $key,
				'name'         => $name,
				'price'        => max( 0, absint( $row['price'] ?? 0 ) ),
				'currency'     => sanitize_text_field( $row['currency'] ?? 'usd' ),
				'badge'        => sanitize_text_field( $row['badge'] ?? '' ),
				'duration'     => sanitize_text_field( $row['duration'] ?? '' ),
				'description'  => sanitize_textarea_field( $row['description'] ?? '' ),
				'features'     => $features,
				'button_text'  => sanitize_text_field( $row['button_text'] ?? 'Book Now' ),
				'status'       => ( isset( $row['status'] ) && 'inactive' === $row['status'] ) ? 'inactive' : 'active',
				'order'        => $order,
			);
			$order++;
		}

		update_option( self::OPTION_KEY, $clean );
		Wise_Mirror_Cache::flush( 'sessions' );

		return $clean;
	}

	/**
	 * Seed sessions from the plugin's original 4 packages the first time
	 * this runs on a site — either fresh, or migrating up from the old
	 * pricing-map structure.
	 */
	public static function seed_defaults_or_migrate() {
		if ( get_option( self::OPTION_KEY, null ) !== null ) {
			return; // Already has session data — never overwrite.
		}

		$legacy = get_option( 'wise_mirror_pricing_map', array() );

		if ( is_array( $legacy ) && ! empty( $legacy ) ) {
			$sessions = array();
			$order    = 0;
			foreach ( $legacy as $key => $pkg ) {
				$sessions[] = array(
					'key'         => $key,
					'name'        => $pkg['label'] ?? $key,
					'price'       => absint( $pkg['amount'] ?? 0 ),
					'currency'    => $pkg['currency'] ?? 'usd',
					'badge'       => '',
					'duration'    => '45 Minutes',
					'description' => '',
					'features'    => array(),
					'button_text' => 'Book Now',
					'status'      => 'active',
					'order'       => $order,
				);
				$order++;
			}
			update_option( self::OPTION_KEY, $sessions );
			return;
		}

		// Brand-new install: the original 4 packages as a sensible starting point.
		update_option(
			self::OPTION_KEY,
			array(
				array(
					'key' => 'discovery-call', 'name' => 'Complimentary Discovery Call', 'price' => 0, 'currency' => 'usd',
					'badge' => '', 'duration' => '15 Minutes', 'description' => 'A short introductory call to see if we\'re a good fit.',
					'features' => array( 'Quick intro chat', 'No commitment' ), 'button_text' => 'Book Free Call', 'status' => 'active', 'order' => 0,
				),
				array(
					'key' => 'focused-reading', 'name' => 'Focused Reading', 'price' => 5000, 'currency' => 'usd',
					'badge' => '', 'duration' => '30 Minutes', 'description' => 'A focused session on one specific question or area.',
					'features' => array( 'One focus area', 'Written summary' ), 'button_text' => 'Book Now', 'status' => 'active', 'order' => 1,
				),
				array(
					'key' => 'comprehensive-reading', 'name' => 'Comprehensive Reading', 'price' => 9000, 'currency' => 'usd',
					'badge' => 'Most Popular', 'duration' => '60 Minutes', 'description' => 'A full session covering everything on your mind.',
					'features' => array( 'Full session', 'Written summary', 'Follow-up email' ), 'button_text' => 'Book Now', 'status' => 'active', 'order' => 2,
				),
				array(
					'key' => 'deep-dive-reading', 'name' => 'Deep Dive Reading', 'price' => 12500, 'currency' => 'usd',
					'badge' => '', 'duration' => '90 Minutes', 'description' => 'An extended deep-dive session for complex situations.',
					'features' => array( 'Extended session', 'Written summary', 'Follow-up email', 'Priority scheduling' ), 'button_text' => 'Book Now', 'status' => 'active', 'order' => 3,
				),
			)
		);
	}

	/**
	 * Render one editable session row for the Session Management admin
	 * screen. Pass null for a blank template row (used by "+ Add Session").
	 *
	 * @param array|null $session Session data, or null for a blank row.
	 * @param int|string $index   Row index for the name="sessions[i][...]" fields.
	 * @return string
	 */
	public static function render_admin_row( $session, $index ) {
		$s = wp_parse_args( $session ?: array(), array(
			'key' => '', 'name' => '', 'price' => 0, 'currency' => 'usd', 'badge' => '',
			'duration' => '', 'description' => '', 'features' => array(), 'button_text' => 'Book Now', 'status' => 'active',
		) );
		$n = 'sessions[' . $index . ']';

		ob_start();
		?>
		<div class="wise-session-row">
			<div class="wise-session-head">
				<span class="wise-drag-handle" title="Drag to reorder">&#9776;</span>
				<input type="text" name="<?php echo esc_attr( $n ); ?>[name]" value="<?php echo esc_attr( $s['name'] ); ?>" placeholder="Session name" class="wise-session-name-input" required>
				<span class="wise-session-price-preview"><?php echo esc_html( $s['price'] > 0 ? '$' . number_format( $s['price'] / 100, 2 ) : 'Free' ); ?></span>
				<button type="button" class="button button-small wise-session-toggle">Collapse</button>
				<button type="button" class="button button-small wise-session-duplicate">Duplicate</button>
				<button type="button" class="button button-small wise-session-delete">Delete</button>
			</div>
			<div class="wise-session-body">
				<input type="hidden" name="<?php echo esc_attr( $n ); ?>[key]" value="<?php echo esc_attr( $s['key'] ); ?>">
				<div class="wise-field-row">
					<label class="wise-field"><span>Price (in cents)</span><input type="number" min="0" name="<?php echo esc_attr( $n ); ?>[price]" value="<?php echo esc_attr( $s['price'] ); ?>"></label>
					<label class="wise-field"><span>Currency</span><input type="text" maxlength="3" name="<?php echo esc_attr( $n ); ?>[currency]" value="<?php echo esc_attr( $s['currency'] ); ?>"></label>
					<label class="wise-field"><span>Duration</span><input type="text" name="<?php echo esc_attr( $n ); ?>[duration]" value="<?php echo esc_attr( $s['duration'] ); ?>" placeholder="e.g. 45 Minutes"></label>
				</div>
				<div class="wise-field-row">
					<label class="wise-field"><span>Badge</span><input type="text" name="<?php echo esc_attr( $n ); ?>[badge]" value="<?php echo esc_attr( $s['badge'] ); ?>" placeholder="e.g. Most Popular"></label>
					<label class="wise-field"><span>Booking Button Text</span><input type="text" name="<?php echo esc_attr( $n ); ?>[button_text]" value="<?php echo esc_attr( $s['button_text'] ); ?>"></label>
					<label class="wise-field"><span>Status</span>
						<select name="<?php echo esc_attr( $n ); ?>[status]">
							<option value="active" <?php selected( $s['status'], 'active' ); ?>>Active</option>
							<option value="inactive" <?php selected( $s['status'], 'inactive' ); ?>>Inactive</option>
						</select>
					</label>
				</div>
				<label class="wise-field"><span>Description</span><textarea name="<?php echo esc_attr( $n ); ?>[description]" rows="2"><?php echo esc_textarea( $s['description'] ); ?></textarea></label>
				<label class="wise-field"><span>Feature List (one per line)</span><textarea name="<?php echo esc_attr( $n ); ?>[features]" rows="3"><?php echo esc_textarea( implode( "\n", $s['features'] ) ); ?></textarea></label>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
