<?php
/**
 * Quarantine of spam submissions.
 *
 * @link       https://www.joeszalai.org
 * @since      20261124
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/includes
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- own table (name from $wpdb->prefix), no object cache needed.

/**
 * Spam submissions of forms with "Quarantine" enabled are stored in {prefix}eas_cf7_quarantine, if a human could
 * have caused the spam reason (spam score, honeypot, proof-of-work, see STORED_CODES). Sure bot signals (manipulated
 * tokens, submission without the anti spam fields) are only counted in the statistics, so bots do not fill the table.
 * An admin can view, delete or release an entry: the release runs the whole Contact Form 7 submission again
 * (mail, Flamingo, other plugins), without the anti spam checks.
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/includes
 */
class Exopite_Anti_Spam_Quarantine {

	/**
	 * Default retention in days.
	 */
	const DEFAULT_DAYS = 30;

	/**
	 * Default maximum number of entries.
	 */
	const DEFAULT_MAX = 1000;

	/**
	 * Spam reasons stored in the quarantine (a human may cause them).
	 */
	const STORED_CODES = array( 'score', 'honeypot', 'pow' );

	/**
	 * Values longer than this are shortened before they are stored.
	 */
	const MAX_VALUE_LENGTH = 20000;

	/**
	 * Plugin main class.
	 *
	 * @var Exopite_Anti_Spam
	 */
	public $main;

	/**
	 * Spam codes of the entry, which is released right now (counted by the statistics).
	 *
	 * @var string[]
	 */
	public $released_codes = array();

	/**
	 * Constructor.
	 *
	 * @param Exopite_Anti_Spam $plugin_main Plugin main class.
	 */
	public function __construct( $plugin_main ) {

		$this->main = $plugin_main;
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public function table() {
		global $wpdb;

		return $wpdb->prefix . 'eas_cf7_quarantine';
	}

	/**
	 * Is the quarantine enabled in the form settings? Default: off.
	 *
	 * @param array|false $options Anti spam settings of the form.
	 * @return bool
	 */
	public function is_active( $options ) {

		return is_array( $options ) && isset( $options['quarantine'] ) && 'yes' === $options['quarantine'];
	}

	/**
	 * Retention (days) and maximum number of entries from the "Blacklist" settings page.
	 *
	 * @return array array( 'days' => int, 'max' => int )
	 */
	public function get_settings() {

		$options = get_option( 'exopite-anti-spam' );
		$days    = isset( $options['quarantine_days'] ) ? intval( $options['quarantine_days'] ) : self::DEFAULT_DAYS;
		$max     = isset( $options['quarantine_max'] ) ? intval( $options['quarantine_max'] ) : self::DEFAULT_MAX;

		return array(
			'days' => min( 365, max( 1, $days ) ),
			'max'  => min( 10000, max( 100, $max ) ),
		);
	}

	/**
	 * Fields of this plugin, not stored (tokens, honeypot, captcha, proof-of-work).
	 *
	 * @return string[]
	 */
	public function get_internal_fields() {

		return array( 'eastimestamp', 'easacceptance', 'exanspsel', 'exanspsel-auth', $this->main->honeypot_name, 'easpowc', 'easpows' );
	}

	/**
	 * Store a spam submission. Hook: wpcf7_submit (priority 90).
	 *
	 * @param WPCF7_ContactForm $contact_form Submitted form.
	 * @param array             $result       Result of the submission.
	 */
	public function wpcf7_submit( $contact_form, $result ) {

		if ( ! empty( $this->main->releasing ) || ! isset( $result['status'] ) || 'spam' !== $result['status'] ) {
			return;
		}

		if ( ! $this->is_active( get_post_meta( $contact_form->id(), 'exopite-anti-spam', true ) ) ) {
			return;
		}

		$codes = $this->main->validate->get_codes();

		if ( ! array_intersect( $codes['spam'], self::STORED_CODES ) ) {
			return;
		}

		$score = isset( $this->main->scoring ) ? (int) $this->main->scoring->last_total : 0;

		$this->store( $contact_form, $codes['spam'], $score );
	}

	/**
	 * Save the current submission ($_POST and the submission meta data).
	 *
	 * @param WPCF7_ContactForm $contact_form Submitted form.
	 * @param string[]          $codes        Spam codes.
	 * @param int               $score        Spam score (0 if not calculated).
	 * @return int|false ID of the entry.
	 */
	public function store( $contact_form, $codes, $score ) {
		global $wpdb;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Contact Form 7 submission, stored for the admin.
		$post       = wp_unslash( $_POST );
		$internal   = $this->get_internal_fields();
		$posted     = array();
		$submission = WPCF7_Submission::get_instance();

		foreach ( (array) $post as $key => $value ) {

			$key = (string) $key;

			if ( in_array( $key, $internal, true ) || 0 === strpos( $key, '_wpcf7' ) ) {
				continue;
			}

			$posted[ $key ] = $this->shorten( $value );
		}

		$meta = array(
			'url'            => $submission ? (string) $submission->get_meta( 'url' ) : '',
			'referer'        => isset( $_SERVER['HTTP_REFERER'] ) ? $this->shorten( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) ) : '',
			'user_agent'     => $submission ? $this->shorten( (string) $submission->get_meta( 'user_agent' ) ) : '',
			'remote_ip'      => $submission ? (string) $submission->get_meta( 'remote_ip' ) : '',
			'container_post' => isset( $post['_wpcf7_container_post'] ) ? absint( $post['_wpcf7_container_post'] ) : 0,
			'unit_tag'       => isset( $post['_wpcf7_unit_tag'] ) && is_string( $post['_wpcf7_unit_tag'] ) ? sanitize_text_field( $post['_wpcf7_unit_tag'] ) : '',
			'locale'         => isset( $post['_wpcf7_locale'] ) && is_string( $post['_wpcf7_locale'] ) ? sanitize_text_field( $post['_wpcf7_locale'] ) : '',
		);

		$reasons = array(
			'codes' => array_values( array_unique( (array) $codes ) ),
			'log'   => array_values( array_unique( $this->main->validate->spam_reasons ) ),
		);

		$inserted = $wpdb->insert(
			$this->table(),
			array(
				'created'     => current_time( 'mysql' ),
				'form_id'     => (int) $contact_form->id(),
				'reasons'     => wp_json_encode( $reasons ),
				'score'       => (int) $score,
				'posted_data' => wp_json_encode( $posted ),
				'meta'        => wp_json_encode( $meta ),
				'has_files'   => $contact_form->scan_form_tags( array( 'type' => array( 'file', 'file*' ) ) ) ? 1 : 0,
			),
			array( '%s', '%d', '%s', '%d', '%s', '%s', '%d' )
		);

		if ( ! $inserted ) {
			return false;
		}

		$id = (int) $wpdb->insert_id;

		$this->maybe_clean_up();

		return $id;
	}

	/**
	 * Shorten long strings (also inside arrays), so a bot can not fill the table with huge values.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public function shorten( $value ) {

		if ( is_array( $value ) ) {
			return array_map( array( $this, 'shorten' ), $value );
		}

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = (string) $value;

		if ( strlen( $value ) <= self::MAX_VALUE_LENGTH ) {
			return $value;
		}

		return function_exists( 'mb_strcut' ) ? mb_strcut( $value, 0, self::MAX_VALUE_LENGTH, 'UTF-8' ) : substr( $value, 0, self::MAX_VALUE_LENGTH );
	}

	/**
	 * Delete entries older than the retention (at most once an hour, unless forced) and the oldest entries above the
	 * maximum (always, so the table never grows above it).
	 *
	 * @param bool $force Delete old entries now.
	 */
	public function maybe_clean_up( $force = false ) {
		global $wpdb;

		$settings = $this->get_settings();

		if ( $force || ! get_transient( 'eas_quarantine_cleanup' ) ) {

			set_transient( 'eas_quarantine_cleanup', 1, HOUR_IN_SECONDS );

			$cutoff = wp_date( 'Y-m-d H:i:s', time() - $settings['days'] * DAY_IN_SECONDS );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . $this->table() . '` WHERE created < %s', $cutoff ) );
		}

		$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $this->table() . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.

		if ( $count > $settings['max'] ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . $this->table() . '` ORDER BY id ASC LIMIT %d', $count - $settings['max'] ) );
		}
	}
}
