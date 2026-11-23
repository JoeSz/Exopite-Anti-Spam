<?php
/**
 * Statistics of the submissions.
 *
 * @link       https://www.joeszalai.org
 * @since      20261124
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/includes
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- own table, written and read once per request.

/**
 * Counts every Contact Form 7 submission once, at the end of the submission (wpcf7_submit): sent, failed,
 * aborted, or the stable reason codes of the anti spam checks (see Exopite_Anti_Spam_Public_Validate_Fields::get_codes()).
 * One row per day, form and reason in {prefix}eas_cf7_stats, no personal data. Always active.
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/includes
 */
class Exopite_Anti_Spam_Stats {

	/**
	 * Rows older than this are deleted.
	 */
	const KEEP_DAYS = 90;

	/**
	 * Plugin main class.
	 *
	 * @var Exopite_Anti_Spam
	 */
	public $main;

	/**
	 * Constructor.
	 *
	 * @param Exopite_Anti_Spam $plugin_main Plugin main class.
	 */
	public function __construct( $plugin_main ) {

		$this->main = $plugin_main;
	}

	/**
	 * Count the submission. Hook: wpcf7_submit (priority 100, after the rate limit and the quarantine).
	 *
	 * @param WPCF7_ContactForm $contact_form Submitted form.
	 * @param array             $result       Result of the submission (status ...).
	 */
	public function wpcf7_submit( $contact_form, $result ) {

		if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'id' ) ) {
			return;
		}

		$released_codes = array();

		if ( isset( $this->main->quarantine ) && is_array( $this->main->quarantine->released_codes ) ) {
			$released_codes = $this->main->quarantine->released_codes;
		}

		$codes = $this->get_codes_for_result( (array) $result, $this->main->validate->get_codes(), ! empty( $this->main->releasing ), $released_codes );

		$this->record( (int) $contact_form->id(), $codes );
	}

	/**
	 * Codes to count for a submission.
	 *
	 * @param array $result         Result of the submission ('status').
	 * @param array $codes          array( 'spam' => string[], 'invalid' => string[] ).
	 * @param bool  $releasing      The submission is the release of a quarantined submission.
	 * @param array $released_codes Spam codes of the released quarantine entry.
	 * @return string[]
	 */
	public function get_codes_for_result( $result, $codes, $releasing, $released_codes ) {

		$status  = isset( $result['status'] ) ? (string) $result['status'] : '';
		$spam    = isset( $codes['spam'] ) ? (array) $codes['spam'] : array();
		$invalid = isset( $codes['invalid'] ) ? (array) $codes['invalid'] : array();

		if ( $releasing ) {

			if ( 'mail_sent' !== $status ) {
				return array();
			}

			$counted = array( 'released' );

			foreach ( $released_codes as $code ) {
				$counted[] = 'released:' . $code;
			}

			return $counted;
		}

		switch ( $status ) {
			case 'mail_sent':
				return array( 'sent' );
			case 'mail_failed':
				return array( 'failed' );
			case 'aborted':
				return array( 'aborted' );
			case 'spam':
				return $spam ? array_values( array_unique( $spam ) ) : array( 'spam_other' );
		}

		$counted = array_values( array_unique( array_merge( $invalid, $spam ) ) );

		return $counted ? $counted : array( 'invalid_other' );
	}

	/**
	 * Increase the counters of today.
	 *
	 * @param int      $form_id Form ID.
	 * @param string[] $codes   Reason codes.
	 */
	public function record( $form_id, $codes ) {
		global $wpdb;

		$date = wp_date( 'Y-m-d' );

		foreach ( array_unique( (array) $codes ) as $code ) {

			$code = substr( (string) $code, 0, 40 );

			if ( '' === $code ) {
				continue;
			}

			$wpdb->query(
				$wpdb->prepare(
					'INSERT INTO `' . $wpdb->prefix . 'eas_cf7_stats` (stat_date, form_id, reason, count) VALUES (%s, %d, %s, 1)
					ON DUPLICATE KEY UPDATE count = count + 1',
					$date,
					$form_id,
					$code
				)
			);
		}

		$this->maybe_clean_up();
	}

	/**
	 * Delete rows older than KEEP_DAYS, at most once a day.
	 */
	public function maybe_clean_up() {
		global $wpdb;

		if ( get_transient( 'eas_stats_cleanup' ) ) {
			return;
		}

		set_transient( 'eas_stats_cleanup', 1, DAY_IN_SECONDS );

		$cutoff = wp_date( 'Y-m-d', time() - self::KEEP_DAYS * DAY_IN_SECONDS );

		$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . $wpdb->prefix . 'eas_cf7_stats` WHERE stat_date < %s', $cutoff ) );
	}

	/**
	 * Totals of the last $days days (today included), per form and reason.
	 *
	 * @param int $days Number of days.
	 * @return array[] Rows with form_id, reason, total; ordered by form and total (descending).
	 */
	public function get_summary( $days ) {
		global $wpdb;

		$from = wp_date( 'Y-m-d', time() - ( max( 1, (int) $days ) - 1 ) * DAY_IN_SECONDS );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT form_id, reason, SUM(count) AS total
				FROM `' . $wpdb->prefix . 'eas_cf7_stats`
				WHERE stat_date >= %s
				GROUP BY form_id, reason
				ORDER BY form_id ASC, total DESC',
				$from
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}
}
