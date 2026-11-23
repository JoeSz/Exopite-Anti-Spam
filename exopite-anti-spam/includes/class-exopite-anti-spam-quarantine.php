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

	/**
	 * Decode the JSON columns of an entry.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	private function decode_row( $row ) {

		foreach ( array( 'reasons', 'posted_data', 'meta' ) as $column ) {
			if ( isset( $row[ $column ] ) ) {
				$decoded        = json_decode( (string) $row[ $column ], true );
				$row[ $column ] = is_array( $decoded ) ? $decoded : array();
			}
		}

		if ( isset( $row['reasons'] ) ) {
			$row['reasons'] = array_merge(
				array(
					'codes' => array(),
					'log'   => array(),
				),
				$row['reasons']
			);
		}

		foreach ( array( 'id', 'form_id', 'score', 'has_files' ) as $column ) {
			if ( isset( $row[ $column ] ) ) {
				$row[ $column ] = (int) $row[ $column ];
			}
		}

		return $row;
	}

	/**
	 * One entry with all data.
	 *
	 * @param int $id Entry ID.
	 * @return array|null
	 */
	public function get_entry( $id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, created, form_id, reasons, score, posted_data, meta, has_files, release_error
				FROM `' . $this->table() . '`
				WHERE id = %d',
				$id
			),
			ARRAY_A
		);

		return $row ? $this->decode_row( $row ) : null;
	}

	/**
	 * Entries for the list (without the submitted data), newest first.
	 *
	 * @param int $form_id  Only this form, 0: all forms.
	 * @param int $page     Page (1 ...).
	 * @param int $per_page Entries per page.
	 * @return array array( 'items' => array[], 'total' => int )
	 */
	public function get_entries( $form_id, $page, $per_page ) {
		global $wpdb;

		$where  = $form_id ? $wpdb->prepare( 'WHERE form_id = %d', $form_id ) : '';
		$offset = max( 0, ( max( 1, (int) $page ) - 1 ) * (int) $per_page );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared above.
		$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . $this->table() . '` ' . $where );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, created, form_id, reasons, score, has_files, release_error
				FROM `' . $this->table() . '` ' . $where . '
				ORDER BY id DESC
				LIMIT %d OFFSET %d',
				$per_page,
				$offset
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array(
			'items' => array_map( array( $this, 'decode_row' ), is_array( $rows ) ? $rows : array() ),
			'total' => $total,
		);
	}

	/**
	 * IDs of the forms with entries.
	 *
	 * @return int[]
	 */
	public function get_form_ids() {
		global $wpdb;

		return array_map( 'intval', (array) $wpdb->get_col( 'SELECT DISTINCT form_id FROM `' . $this->table() . '` ORDER BY form_id ASC' ) );
	}

	/**
	 * Can the entry be released?
	 *
	 * @param array $entry Entry (get_entry()).
	 * @return true|string True or the reason, why not.
	 */
	public function is_releasable( $entry ) {

		$contact_form = function_exists( 'wpcf7_contact_form' ) ? wpcf7_contact_form( $entry['form_id'] ) : null;

		if ( ! $contact_form ) {
			return __( 'The form does not exist anymore, the entry can not be released.', 'exopite-anti-spam' );
		}

		// Attachments are not stored: the validation of a required file field would always fail.
		if ( $contact_form->scan_form_tags( array( 'type' => 'file*' ) ) ) {
			return __( 'The form has a required file field, attachments are not stored in the quarantine. The entry can not be released.', 'exopite-anti-spam' );
		}

		return true;
	}

	/**
	 * Release an entry: run the whole Contact Form 7 submission with the stored data, without the anti spam checks.
	 * Success: the entry is deleted. Failure: the reason is stored in release_error, the entry is kept.
	 *
	 * @param int $id Entry ID.
	 * @return array array( 'ok' => bool, 'status' => string, 'message' => string )
	 */
	public function release( $id ) {
		global $wpdb;

		// A double click must not release the entry twice.
		$lock = 'eas_q_' . md5( $wpdb->prefix . $id );

		if ( '1' !== $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 0 )', $lock ) ) ) {
			return $this->release_result( false, 'locked', __( 'This entry is being released right now.', 'exopite-anti-spam' ) );
		}

		try {

			$entry = $this->get_entry( $id );

			if ( ! $entry ) {
				return $this->release_result( false, 'not_found', __( 'The entry does not exist anymore.', 'exopite-anti-spam' ) );
			}

			$releasable = $this->is_releasable( $entry );

			if ( true !== $releasable ) {
				return $this->release_result( false, 'not_releasable', $releasable );
			}

			$result = $this->submit_entry( $entry );
			$status = isset( $result['status'] ) ? (string) $result['status'] : '';

			if ( 'mail_sent' === $status ) {
				$this->delete( $id );
				return $this->release_result( true, $status, __( 'The submission was released and sent.', 'exopite-anti-spam' ) );
			}

			$error = $this->get_result_error( $result );
			$wpdb->update( $this->table(), array( 'release_error' => $error ), array( 'id' => (int) $id ), array( '%s' ), array( '%d' ) );

			/* translators: %s: error (status of Contact Form 7 and invalid fields) */
			return $this->release_result( false, $status, sprintf( __( 'The submission could not be released: %s', 'exopite-anti-spam' ), $error ) );

		} finally {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock ) );
		}
	}

	/**
	 * Result array of release().
	 *
	 * @param bool   $ok      Released.
	 * @param string $status  Status code.
	 * @param string $message Message for the admin.
	 * @return array
	 */
	private function release_result( $ok, $status, $message ) {

		return array(
			'ok'      => (bool) $ok,
			'status'  => (string) $status,
			'message' => (string) $message,
		);
	}

	/**
	 * Status and invalid fields of an unsuccessful submission as text.
	 *
	 * @param array $result Contact Form 7 result.
	 * @return string
	 */
	private function get_result_error( $result ) {

		$error  = isset( $result['status'] ) ? (string) $result['status'] : 'error';
		$fields = array();

		if ( ! empty( $result['invalid_fields'] ) && is_array( $result['invalid_fields'] ) ) {
			foreach ( $result['invalid_fields'] as $name => $field ) {
				$message  = is_array( $field ) && isset( $field['message'] ) ? $field['message'] : ( is_array( $field ) && isset( $field['reason'] ) ? $field['reason'] : '' );
				$label    = is_array( $field ) && isset( $field['field'] ) ? $field['field'] : $name;
				$fields[] = $label . ': ' . $message;
			}
		}

		return $fields ? $error . ' (' . implode( '; ', $fields ) . ')' : $error;
	}

	/**
	 * Run the Contact Form 7 submission with the stored request: $_POST, referer, user agent, request URI
	 * (the "url" meta of the submission), IP address and the global $post of the page with the form.
	 * Everything is restored afterwards.
	 *
	 * @param array $entry Entry (get_entry()).
	 * @return array Contact Form 7 result.
	 */
	private function submit_entry( $entry ) {

		$meta        = $entry['meta'];
		$server_keys = array( 'HTTP_REFERER', 'HTTP_USER_AGENT', 'REQUEST_URI' );
		$backup      = array(
			'post'        => $_POST, // phpcs:ignore WordPress.Security.NonceVerification.Missing -- restored below.
			'request'     => $_REQUEST, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- restored below.
			'global_post' => isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null,
			'server'      => array(),
		);

		foreach ( $server_keys as $key ) {
			$backup['server'][ $key ] = isset( $_SERVER[ $key ] ) ? $_SERVER[ $key ] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- restored below.
		}

		$container_post = isset( $meta['container_post'] ) ? (int) $meta['container_post'] : 0;
		$url            = isset( $meta['url'] ) ? (string) $meta['url'] : '';
		$path           = wp_parse_url( $url, PHP_URL_PATH );
		$query          = wp_parse_url( $url, PHP_URL_QUERY );
		$remote_ip      = isset( $meta['remote_ip'] ) ? (string) $meta['remote_ip'] : '';

		$_POST = wp_slash(
			array_merge(
				(array) $entry['posted_data'],
				array(
					'_wpcf7'                => $entry['form_id'],
					'_wpcf7_version'        => defined( 'WPCF7_VERSION' ) ? WPCF7_VERSION : '',
					'_wpcf7_locale'         => isset( $meta['locale'] ) ? $meta['locale'] : '',
					'_wpcf7_unit_tag'       => isset( $meta['unit_tag'] ) ? $meta['unit_tag'] : '',
					'_wpcf7_container_post' => $container_post,
				)
			)
		);

		$_REQUEST                   = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- set right above.
		$_SERVER['HTTP_REFERER']    = isset( $meta['referer'] ) ? $meta['referer'] : '';
		$_SERVER['HTTP_USER_AGENT'] = isset( $meta['user_agent'] ) ? $meta['user_agent'] : '';
		$_SERVER['REQUEST_URI']     = ( $path ? $path : '/' ) . ( $query ? '?' . $query : '' );
		$GLOBALS['post']            = $container_post ? get_post( $container_post ) : null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the page of the form, restored below.

		$ip_filter = function () use ( $remote_ip ) {
			return $remote_ip;
		};

		/**
		 * Contact Form 7 caches the request URI on "init" (wpcf7_get_request_uri()), so the "url" meta would be the
		 * URL of admin-post.php. It is set to the stored URL right after the meta data is collected
		 * (setup_meta_data() runs just before the 'wpcf7_posted_data' filter), e.g. for plugins which send the page URL.
		 */
		$url_filter = function ( $posted_data ) use ( $url ) {

			$submission = WPCF7_Submission::get_instance();

			if ( $submission && '' !== $url && property_exists( $submission, 'meta' ) ) {
				$property = new ReflectionProperty( 'WPCF7_Submission', 'meta' );
				$property->setAccessible( true );
				$meta = $property->getValue( $submission );

				if ( is_array( $meta ) ) {
					$meta['url'] = $url;
					$property->setValue( $submission, $meta );
				}
			}

			return $posted_data;
		};

		add_filter( 'wpcf7_remote_ip_addr', $ip_filter, 999 );
		add_filter( 'wpcf7_posted_data', $url_filter, 1 );
		add_filter( 'wpcf7_skip_spam_check', '__return_true', 999 );

		$this->main->releasing = true;
		$this->released_codes  = $entry['reasons']['codes'];

		try {
			$result = wpcf7_contact_form( $entry['form_id'] )->submit();
		} finally {

			$this->main->releasing = false;
			$this->released_codes  = array();

			remove_filter( 'wpcf7_remote_ip_addr', $ip_filter, 999 );
			remove_filter( 'wpcf7_posted_data', $url_filter, 1 );
			remove_filter( 'wpcf7_skip_spam_check', '__return_true', 999 );

			$_POST           = $backup['post'];
			$_REQUEST        = $backup['request'];
			$GLOBALS['post'] = $backup['global_post']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restore.

			foreach ( $backup['server'] as $key => $value ) {
				if ( null === $value ) {
					unset( $_SERVER[ $key ] );
				} else {
					$_SERVER[ $key ] = $value;
				}
			}
		}

		return is_array( $result ) ? $result : array();
	}

	/**
	 * Delete one entry.
	 *
	 * @param int $id Entry ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		return (bool) $wpdb->delete( $this->table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Delete all entries of a form or all entries.
	 *
	 * @param int $form_id Form ID, 0: all forms.
	 * @return int Number of deleted entries.
	 */
	public function delete_all( $form_id = 0 ) {
		global $wpdb;

		if ( $form_id ) {
			return (int) $wpdb->delete( $this->table(), array( 'form_id' => (int) $form_id ), array( '%d' ) );
		}

		return (int) $wpdb->query( 'DELETE FROM `' . $this->table() . '`' );
	}
}
