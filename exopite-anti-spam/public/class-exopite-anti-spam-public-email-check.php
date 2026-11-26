<?php
/**
 * E-mail check: disposable addresses and domains which do not exist.
 *
 * @link       https://www.joeszalai.org
 * @since      20261124
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/public
 */

/**
 * Validation of the e-mail fields, per form: reject disposable e-mail addresses (list lists/disposable-domains.txt,
 * filter 'exopite_anti_spam_disposable_domains') and domains without MX or A/AAAA record. A rejection is a validation
 * error (the visitor can correct a typo), not spam. DNS errors never reject an address (fail open).
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/public
 */
class Exopite_Anti_Spam_Public_Email_Check {

	/**
	 * Source of the list update (CC0, https://github.com/disposable-email-domains/disposable-email-domains).
	 */
	const LIST_URL = 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/main/disposable_email_blocklist.conf';

	/**
	 * Option with the updated list (not autoloaded).
	 */
	const LIST_OPTION = 'exopite_anti_spam_disposable_domains';

	/**
	 * Checks of a downloaded list.
	 */
	const MIN_LIST_DOMAINS = 1000;
	const MAX_LIST_BYTES   = 2097152;

	/**
	 * Plugin main class.
	 *
	 * @var Exopite_Anti_Spam
	 */
	public $main;

	/**
	 * Disposable domains as keys, loaded on first use.
	 *
	 * @var array|null
	 */
	private $domains = null;

	/**
	 * E-mail fields for the DNS check of the current submission: array( WPCF7_FormTag, domain ).
	 *
	 * @var array[]
	 */
	private $pending_mx = array();

	/**
	 * Constructor.
	 *
	 * @param Exopite_Anti_Spam $plugin_main Plugin main class.
	 */
	public function __construct( $plugin_main ) {

		$this->main = $plugin_main;
	}

	/**
	 * Settings of the form: array( 'disposable' => bool, 'mx' => bool ). Default: both off.
	 *
	 * @param array|false $options Anti spam settings of the form.
	 * @return bool[]
	 */
	public function get_settings( $options ) {

		return array(
			'disposable' => is_array( $options ) && isset( $options['email_disposable'] ) && 'yes' === $options['email_disposable'],
			'mx'         => is_array( $options ) && isset( $options['email_mx'] ) && 'yes' === $options['email_mx'],
		);
	}

	/**
	 * Validation of [email] and [email*]. Hook: wpcf7_validate_email, wpcf7_validate_email* (priority 25).
	 *
	 * @param WPCF7_Validation $result Validation result.
	 * @param WPCF7_FormTag    $tag    Form tag.
	 * @return WPCF7_Validation
	 */
	public function validate( $result, $tag ) {

		if ( $this->main->public->skip_checks() ) {
			return $result;
		}

		$tag      = new WPCF7_FormTag( $tag );
		$settings = $this->get_settings( $this->main->public->get_cf7_meta() );

		if ( ( ! $settings['disposable'] && ! $settings['mx'] ) || empty( $tag->name ) || ! $result->is_valid( $tag->name ) ) {
			return $result;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Contact Form 7 submission.
		$email = isset( $_POST[ $tag->name ] ) && is_string( $_POST[ $tag->name ] ) ? strtolower( trim( sanitize_text_field( wp_unslash( $_POST[ $tag->name ] ) ) ) ) : '';

		if ( '' === $email || false === strpos( $email, '@' ) ) {
			return $result;
		}

		$domain  = substr( strrchr( $email, '@' ), 1 );
		$options = get_option( $this->main->get_plugin_name() );

		if ( $settings['disposable'] && $this->is_disposable( $domain ) ) {

			$message = ! empty( $options['display_error_message_disposable'] ) ? (string) $options['display_error_message_disposable'] : __( 'Please use a permanent e-mail address.', 'exopite-anti-spam' );

			$this->main->validate->invalidate_log( 'disposable e-mail domain: ' . $domain, false, 'email_disposable' );
			$result->invalidate( $tag, $message );
			$this->main->validate->invalidated_fields[] = $tag->name;

			return $result;
		}

		// The DNS check runs later (validate_mx()), after the bot checks: bots must not cause DNS requests.
		if ( $settings['mx'] ) {
			$this->pending_mx[] = array( $tag, $domain );
		}

		return $result;
	}

	/**
	 * DNS check of the e-mail domains collected by validate(), after the rate limit, timestamp, tokens and
	 * proof-of-work (Hook: wpcf7_validate, priority 40). Not for bot submissions and blocked visitors.
	 *
	 * @param WPCF7_Validation $result Validation result.
	 * @param WPCF7_FormTag[]  $tags   Form tags.
	 * @return WPCF7_Validation
	 */
	public function validate_mx( $result, $tags ) {

		$pending          = $this->pending_mx;
		$this->pending_mx = array();

		if ( ! $pending || $this->main->public->skip_checks() || ! empty( $this->main->validate->spam_reasons ) || ! empty( $this->main->ratelimiter->blocked ) ) {
			return $result;
		}

		$options = get_option( $this->main->get_plugin_name() );
		$message = ! empty( $options['display_error_message_mx'] ) ? (string) $options['display_error_message_mx'] : __( 'The domain of this e-mail address does not exist.', 'exopite-anti-spam' );

		foreach ( $pending as $item ) {

			list( $tag, $domain ) = $item;

			if ( ! $result->is_valid( $tag->name ) || $this->domain_exists( $domain ) ) {
				continue;
			}

			$this->main->validate->invalidate_log( 'e-mail domain without MX/A record: ' . $domain, false, 'email_domain' );
			$result->invalidate( $tag, $message );
			$this->main->validate->invalidated_fields[] = $tag->name;
		}

		return $result;
	}

	/**
	 * Forget the e-mail domains of the last submission.
	 */
	public function reset() {

		$this->pending_mx = array();
	}

	/**
	 * Is the domain or one of its parent domains (e.g. "x.mailinator.com") a disposable e-mail domain?
	 *
	 * @param string $domain Domain.
	 * @return bool
	 */
	public function is_disposable( $domain ) {

		$domains = $this->get_domains();
		$parts   = explode( '.', strtolower( $domain ) );

		// Without the top level domain alone.
		while ( count( $parts ) > 1 ) {

			if ( isset( $domains[ implode( '.', $parts ) ] ) ) {
				return true;
			}

			array_shift( $parts );
		}

		return false;
	}

	/**
	 * Disposable domains as array keys: the list updated on the "Blacklist" page (option LIST_OPTION), otherwise the
	 * list of the plugin (lists/disposable-domains.txt, one per line, "#" comments).
	 *
	 * @return array
	 */
	public function get_domains() {

		if ( null !== $this->domains ) {
			return $this->domains;
		}

		$updated = get_option( self::LIST_OPTION );

		if ( is_array( $updated ) && ! empty( $updated['domains'] ) && is_string( $updated['domains'] ) ) {
			$list = explode( "\n", $updated['domains'] );
		} else {
			$list = $this->parse_list( is_readable( self::bundled_file() ) ? (string) file_get_contents( self::bundled_file() ) : '' );
		}

		/**
		 * Disposable e-mail domains, e.g. to add or remove domains.
		 *
		 * @param string[] $list Domains.
		 */
		$list = (array) apply_filters( 'exopite_anti_spam_disposable_domains', $list );

		$this->domains = array_flip( array_map( 'strtolower', array_filter( $list, 'is_string' ) ) );

		return $this->domains;
	}

	/**
	 * The list shipped with the plugin.
	 *
	 * @return string
	 */
	public static function bundled_file() {

		return EXOPITE_ANTI_SPAM_PATH . 'lists/disposable-domains.txt';
	}

	/**
	 * Domains of a list (one per line, "#" comments, empty lines).
	 *
	 * @param string $text List.
	 * @param bool   $strict Return false if a line is not a domain (downloaded lists).
	 * @return string[]|false
	 */
	public function parse_list( $text, $strict = false ) {

		$list = array();

		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {

			$line = strtolower( trim( $line ) );

			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}

			if ( $strict && ! preg_match( '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $line ) ) {
				return false;
			}

			$list[] = $line;
		}

		return array_values( array_unique( $list ) );
	}

	/**
	 * Source, date and size of the list in use.
	 *
	 * @return array array( 'source' => 'updated'|'bundled', 'updated' => int timestamp (0: bundled), 'count' => int )
	 */
	public function get_list_info() {

		$updated = get_option( self::LIST_OPTION );

		if ( is_array( $updated ) && ! empty( $updated['domains'] ) ) {
			return array(
				'source'  => 'updated',
				'updated' => isset( $updated['updated'] ) ? (int) $updated['updated'] : 0,
				'count'   => isset( $updated['count'] ) ? (int) $updated['count'] : 0,
			);
		}

		return array(
			'source'  => 'bundled',
			'updated' => 0,
			'count'   => count( $this->parse_list( is_readable( self::bundled_file() ) ? (string) file_get_contents( self::bundled_file() ) : '' ) ),
		);
	}

	/**
	 * Download the current list (button on the "Blacklist" page, never automatically). Saved only if every line is a
	 * domain, there are at least MIN_LIST_DOMAINS domains and the file is not larger than MAX_LIST_BYTES; otherwise the
	 * list in use is kept. Only the server connects to the list URL, no visitor data is sent.
	 *
	 * @return array array( 'ok' => bool, 'message' => string )
	 */
	public function update_list() {

		/**
		 * URL of the disposable e-mail domain list (one domain per line).
		 *
		 * @param string $url URL.
		 */
		$url      = (string) apply_filters( 'exopite_anti_spam_disposable_list_url', self::LIST_URL );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'             => 20,
				'limit_response_size' => self::MAX_LIST_BYTES + 1,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			$error = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $response );
			return array(
				'ok'      => false,
				/* translators: %s: error message */
				'message' => sprintf( __( 'The list could not be downloaded (%s). The list in use is kept.', 'exopite-anti-spam' ), $error ),
			);
		}

		$body = (string) wp_remote_retrieve_body( $response );
		$list = strlen( $body ) > self::MAX_LIST_BYTES ? false : $this->parse_list( $body, true );

		if ( false === $list || count( $list ) < self::MIN_LIST_DOMAINS ) {
			return array(
				'ok'      => false,
				'message' => __( 'The downloaded file does not look like a list of domains. The list in use is kept.', 'exopite-anti-spam' ),
			);
		}

		update_option(
			self::LIST_OPTION,
			array(
				'domains' => implode( "\n", $list ),
				'updated' => time(),
				'count'   => count( $list ),
				'url'     => $url,
			),
			false
		);

		$this->domains = null;

		return array(
			'ok'      => true,
			/* translators: %s: number of domains */
			'message' => sprintf( __( 'The list was updated: %s domains.', 'exopite-anti-spam' ), number_format_i18n( count( $list ) ) ),
		);
	}

	/**
	 * Use the list of the plugin again.
	 */
	public function reset_list() {

		delete_option( self::LIST_OPTION );
		$this->domains = null;
	}

	/**
	 * Does the domain have an MX record, or (RFC 5321 fallback) an A or AAAA record?
	 * DNS errors: true (fail open), cached for 5 minutes. Results are cached for a day.
	 * An error after an answer of the resolver (e.g. an empty MX list) counts as "no record".
	 *
	 * @param string $domain Domain.
	 * @return bool
	 */
	public function domain_exists( $domain ) {

		/**
		 * Short-circuit the DNS check (e.g. tests): return true or false, null to check.
		 *
		 * @param bool|null $exists Result.
		 * @param string    $domain Domain.
		 */
		$pre = apply_filters( 'pre_exopite_anti_spam_domain_exists', null, $domain );

		if ( null !== $pre ) {
			return (bool) $pre;
		}

		if ( function_exists( 'idn_to_ascii' ) && preg_match( '/[^\x20-\x7e]/', $domain ) ) {
			$ascii  = defined( 'INTL_IDNA_VARIANT_UTS46' ) ? idn_to_ascii( $domain, 0, INTL_IDNA_VARIANT_UTS46 ) : idn_to_ascii( $domain );
			$domain = $ascii ? $ascii : $domain;
		}

		$key    = 'eas_mx_' . md5( $domain );
		$cached = get_transient( $key );

		// '2': DNS error a few minutes ago, accepted without asking again (a nameserver without answer would block every request).
		if ( '1' === $cached || '0' === $cached || '2' === $cached ) {
			return '0' !== $cached;
		}

		// Some resolvers answer an unknown domain with an empty MX list and then an error for A:
		// once the resolver has answered, a later error means "no record", not a DNS problem.
		$answered = false;

		foreach ( array( DNS_MX, DNS_A, DNS_AAAA ) as $type ) {

			$records = $this->lookup( $domain, $type );

			// DNS error (no answer, no DNS on the server): accept the address.
			if ( false === $records && ! $answered ) {
				set_transient( $key, '2', 5 * MINUTE_IN_SECONDS );
				return true;
			}

			$answered = true;

			if ( ! empty( $records ) ) {
				set_transient( $key, '1', DAY_IN_SECONDS );
				return true;
			}
		}

		set_transient( $key, '0', DAY_IN_SECONDS );

		return false;
	}

	/**
	 * DNS lookup, without PHP warnings (dns_get_record() warns on errors).
	 *
	 * @param string $domain Domain.
	 * @param int    $type   DNS_MX, DNS_A or DNS_AAAA.
	 * @return array|false Records, false on error.
	 */
	protected function lookup( $domain, $type ) {

		if ( ! function_exists( 'dns_get_record' ) ) {
			return false;
		}

		set_error_handler( '__return_true' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- dns_get_record() warns on DNS errors.
		$records = dns_get_record( $domain, $type );
		restore_error_handler();

		return is_array( $records ) ? $records : false;
	}
}
