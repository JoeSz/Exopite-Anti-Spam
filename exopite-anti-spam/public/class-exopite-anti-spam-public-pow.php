<?php
/**
 * Invisible proof-of-work.
 *
 * @link       https://www.joeszalai.org
 * @since      20261124
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/public
 */

/**
 * Proof-of-work per form (principle of ALTCHA, MIT): the server gives a signed and encrypted challenge (random salt,
 * difficulty, form, time) via the existing AJAX endpoint, the browser searches a number, for which
 * SHA-256( salt . number ) starts with the given number of zero bits (Web Worker, while the visitor fills the form),
 * the server checks it with one hash. Every challenge can be used once (token table, type 'pow').
 * Missing or wrong solution: spam (a human can not cause it, only a disabled JavaScript). Expired: validation error.
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/public
 */
class Exopite_Anti_Spam_Public_Pow {

	/**
	 * Leading zero bits per difficulty (measured: the worker does about 1.6 million hashes per second on a desktop, a phone about 4-6 times less: "medium" = 262144 hashes on average, about 0.15 seconds on a desktop and 1 second on a phone).
	 */
	const DIFFICULTIES = array(
		'low'    => 16,
		'medium' => 18,
		'high'   => 20,
	);

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
	 * Is the proof-of-work activated in the form settings? Default: off.
	 *
	 * @param array|false $options Anti spam settings of the form.
	 * @return bool
	 */
	public function is_active( $options ) {

		return is_array( $options ) && isset( $options['pow'] ) && 'yes' === $options['pow'];
	}

	/**
	 * Difficulty of the form: 'low', 'medium' (default) or 'high'.
	 *
	 * @param array|false $options Anti spam settings of the form.
	 * @return string
	 */
	public function get_difficulty( $options ) {

		$difficulty = is_array( $options ) && isset( $options['pow_difficulty'] ) ? (string) $options['pow_difficulty'] : 'medium';

		return isset( self::DIFFICULTIES[ $difficulty ] ) ? $difficulty : 'medium';
	}

	/**
	 * Number of leading zero bits for the form (filter 'exopite_anti_spam_pow_bits', 1-30).
	 *
	 * @param array|false $options Anti spam settings of the form.
	 * @return int
	 */
	public function get_bits( $options ) {

		$bits = self::DIFFICULTIES[ $this->get_difficulty( $options ) ];

		return max( 1, min( 30, (int) apply_filters( 'exopite_anti_spam_pow_bits', $bits, $options ) ) );
	}

	/**
	 * New challenge for a form with proof-of-work.
	 *
	 * @param int $form_id Form ID.
	 * @return array|null array( 'c' => encrypted challenge, 's' => salt, 'b' => bits ), null if not active.
	 */
	public function create_challenge( $form_id ) {

		$form_id = (int) $form_id;

		if ( $form_id < 1 || 'wpcf7_contact_form' !== get_post_type( $form_id ) ) {
			return null;
		}

		$options = get_post_meta( $form_id, 'exopite-anti-spam', true );

		if ( ! $this->is_active( $options ) ) {
			return null;
		}

		$salt = bin2hex( random_bytes( 16 ) );
		$bits = $this->get_bits( $options );

		return array(
			'c' => $this->main->crypter->encrypt_hex( wp_json_encode( array( 'pow', $salt, $bits, $form_id, time() ) ) ),
			's' => $salt,
			'b' => $bits,
		);
	}

	/**
	 * Does SHA-256( salt . nonce ) start with $bits zero bits?
	 *
	 * @param string $salt  Salt of the challenge.
	 * @param string $nonce Solution (decimal number, max. 12 digits).
	 * @param int    $bits  Number of leading zero bits.
	 * @return bool
	 */
	public function check_solution( $salt, $nonce, $bits ) {

		if ( ! is_string( $nonce ) || ! preg_match( '/^\d{1,12}$/', $nonce ) ) {
			return false;
		}

		$hash  = hash( 'sha256', $salt . $nonce, true );
		$bytes = intdiv( (int) $bits, 8 );

		for ( $i = 0; $i < $bytes; $i++ ) {
			if ( 0 !== ord( $hash[ $i ] ) ) {
				return false;
			}
		}

		$rest = (int) $bits % 8;

		return ! $rest || 0 === ( ord( $hash[ $bytes ] ) >> ( 8 - $rest ) );
	}

	/**
	 * Check the solution of the submission (called by Exopite_Anti_Spam_Public_Validate_Fields::wpcf7_validate()).
	 *
	 * @param WPCF7_Validation $result Validation result.
	 * @param WPCF7_FormTag[]  $tags   Form tags.
	 * @return WPCF7_Validation
	 */
	public function validate( $result, $tags ) {

		$options = $this->main->public->get_cf7_meta();

		if ( ! $this->is_active( $options ) ) {
			return $result;
		}

		$validate = $this->main->validate;

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Contact Form 7 submission.
		$challenge = isset( $_POST['easpowc'] ) && is_string( $_POST['easpowc'] ) ? sanitize_text_field( wp_unslash( $_POST['easpowc'] ) ) : '';
		$solution  = isset( $_POST['easpows'] ) && is_string( $_POST['easpows'] ) ? sanitize_text_field( wp_unslash( $_POST['easpows'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( '' === $challenge || '' === $solution ) {
			$validate->add_spam( 'proof-of-work missing', 'pow' );
			return $result;
		}

		$data = json_decode( (string) $this->main->crypter->decrypt_hex( $challenge ), true );

		if ( ! is_array( $data ) || 5 !== count( $data ) || 'pow' !== $data[0] ) {
			$validate->add_spam( 'proof-of-work challenge can not be decrypted or is invalid', 'pow' );
			return $result;
		}

		list( , $salt, $bits, $form_id, $created ) = $data;

		$contact_form = WPCF7_ContactForm::get_current();

		if ( ! $contact_form || (int) $form_id !== (int) $contact_form->id() ) {
			$validate->add_spam( 'proof-of-work challenge of another form', 'pow' );
			return $result;
		}

		if ( (int) $bits < $this->get_bits( $options ) ) {
			$validate->add_spam( 'proof-of-work challenge easier than the form setting', 'pow' );
			return $result;
		}

		// Expired: the visitor can submit again, the JS requests a new challenge after every submission.
		if ( ( time() - (int) $created ) > $this->main->tokens->get_max_age( 'pow' ) ) {
			$validate->invalidate_log( 'proof-of-work expired, created: ' . wp_date( 'Y-m-d H:i:s', (int) $created ), false, 'expired' );
			$validate->invalidate_visible( $result, $tags, esc_attr__( 'Your session has timed out. Please submit the form again.', 'exopite-anti-spam' ) );
			return $result;
		}

		if ( ! $this->check_solution( (string) $salt, $solution, (int) $bits ) ) {
			$validate->add_spam( 'proof-of-work solution is wrong', 'pow' );
			return $result;
		}

		if ( ! $this->main->tokens->consume( (string) $salt, 'pow' ) ) {
			$validate->invalidate_log( 'proof-of-work already used', true, 'token_used' );
			$validate->invalidate_visible( $result, $tags, esc_attr__( 'Token error.', 'exopite-anti-spam' ) . ' (14)' );
		}

		return $result;
	}
}
