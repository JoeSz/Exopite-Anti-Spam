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

		if ( $settings['mx'] && ! $this->domain_exists( $domain ) ) {

			$message = ! empty( $options['display_error_message_mx'] ) ? (string) $options['display_error_message_mx'] : __( 'The domain of this e-mail address does not exist.', 'exopite-anti-spam' );

			$this->main->validate->invalidate_log( 'e-mail domain without MX/A record: ' . $domain, false, 'email_domain' );
			$result->invalidate( $tag, $message );
			$this->main->validate->invalidated_fields[] = $tag->name;
		}

		return $result;
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
	 * Disposable domains (lists/disposable-domains.txt, one per line, "#" comments), as array keys.
	 *
	 * @return array
	 */
	public function get_domains() {

		if ( null !== $this->domains ) {
			return $this->domains;
		}

		$list = array();
		$file = EXOPITE_ANTI_SPAM_PATH . 'lists/disposable-domains.txt';

		if ( is_readable( $file ) ) {
			foreach ( (array) file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
				$line = strtolower( trim( $line ) );
				if ( '' !== $line && '#' !== $line[0] ) {
					$list[] = $line;
				}
			}
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
	 * Does the domain have an MX record, or (RFC 5321 fallback) an A or AAAA record?
	 * DNS errors: true (fail open), not cached. Results are cached for a day.
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

		if ( '1' === $cached || '0' === $cached ) {
			return '1' === $cached;
		}

		foreach ( array( DNS_MX, DNS_A, DNS_AAAA ) as $type ) {

			$records = $this->lookup( $domain, $type );

			// DNS error (no answer, no DNS on the server): accept the address.
			if ( false === $records ) {
				return true;
			}

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
