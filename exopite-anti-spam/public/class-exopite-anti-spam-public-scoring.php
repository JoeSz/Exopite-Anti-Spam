<?php
/**
 * Spam score of the submissions of a form.
 *
 * @link       https://www.joeszalai.org
 * @since      20261124
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/public
 */

/**
 * Runs the content rules (Exopite_Anti_Spam_Scorer) in the Contact Form 7 spam filter, if the spam score is activated
 * for the form. Only after all other checks passed (Contact Form 7 runs the spam filter after the validation, and not
 * if the submission is already spam). Reaching the threshold of the form: status "spam", reason code "score".
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/public
 */
class Exopite_Anti_Spam_Public_Scoring {

	/**
	 * Default threshold.
	 */
	const DEFAULT_THRESHOLD = 10;

	/**
	 * Plugin main class.
	 *
	 * @var Exopite_Anti_Spam
	 */
	public $main;

	/**
	 * Score of the current submission (0 if not calculated), stored in the quarantine.
	 *
	 * @var int
	 */
	public $last_total = 0;

	/**
	 * Points per rule of the current submission.
	 *
	 * @var int[]
	 */
	public $last_hits = array();

	/**
	 * Constructor.
	 *
	 * @param Exopite_Anti_Spam $plugin_main Plugin main class.
	 */
	public function __construct( $plugin_main ) {

		$this->main = $plugin_main;
	}

	/**
	 * Forget the score of the last submission.
	 */
	public function reset() {

		$this->last_total = 0;
		$this->last_hits  = array();
	}

	/**
	 * Is the spam score activated in the form settings? Default: off.
	 *
	 * @param array|false $options Anti spam settings of the form.
	 * @return bool
	 */
	public function is_active( $options ) {

		return is_array( $options ) && isset( $options['scoring'] ) && 'yes' === $options['scoring'];
	}

	/**
	 * Threshold of the form (1-100).
	 *
	 * @param array|false $options Anti spam settings of the form.
	 * @return int
	 */
	public function get_threshold( $options ) {

		$threshold = is_array( $options ) && isset( $options['scoring_threshold'] ) ? (int) $options['scoring_threshold'] : self::DEFAULT_THRESHOLD;

		return ( $threshold >= 1 && $threshold <= 100 ) ? $threshold : self::DEFAULT_THRESHOLD;
	}

	/**
	 * Scripts allowed in addition to Latin and the script of the form language.
	 *
	 * @param array|false $options Anti spam settings of the form.
	 * @return string[]
	 */
	public function get_extra_scripts( $options ) {

		if ( ! is_array( $options ) || empty( $options['scoring_scripts'] ) || ! is_array( $options['scoring_scripts'] ) ) {
			return array();
		}

		$scorer = new Exopite_Anti_Spam_Scorer();

		return array_values( array_intersect( $options['scoring_scripts'], array_keys( $scorer->get_scripts() ) ) );
	}

	/**
	 * Contact Form 7 spam filter. Hook: wpcf7_spam (priority 20, after the bot checks of this plugin).
	 *
	 * @param bool                  $spam       Already spam.
	 * @param WPCF7_Submission|null $submission Submission.
	 * @return bool
	 */
	public function wpcf7_spam( $spam, $submission = null ) {

		$this->reset();

		if ( $spam || ! $submission instanceof WPCF7_Submission || $this->main->public->skip_checks() ) {
			return $spam;
		}

		$contact_form = $submission->get_contact_form();
		$options      = get_post_meta( $contact_form->id(), 'exopite-anti-spam', true );

		if ( ! $this->is_active( $options ) ) {
			return $spam;
		}

		/**
		 * Points per rule (0: rule off), see Exopite_Anti_Spam_Scorer::DEFAULT_POINTS.
		 *
		 * @param int[]             $points       Points per rule.
		 * @param WPCF7_ContactForm $contact_form Form.
		 */
		$points = (array) apply_filters( 'exopite_anti_spam_scoring_points', Exopite_Anti_Spam_Scorer::DEFAULT_POINTS, $contact_form );
		$scorer = new Exopite_Anti_Spam_Scorer( $points );
		$result = $scorer->score( $this->get_fields( $contact_form, $submission ), $this->get_language( $contact_form ), $this->get_extra_scripts( $options ) );

		$this->last_total = $result['total'];
		$this->last_hits  = $result['hits'];

		if ( $result['total'] < $this->get_threshold( $options ) ) {
			return $spam;
		}

		$details = array();

		foreach ( $result['hits'] as $rule => $rule_points ) {
			$details[] = $rule . '=' . $rule_points;
		}

		$reason = 'score ' . $result['total'] . ' (' . implode( ', ', $details ) . ')';

		$this->main->validate->add_spam( $reason, 'score' );
		$submission->add_spam_log(
			array(
				'agent'  => 'exopite-anti-spam',
				'reason' => $reason,
			)
		);

		return true;
	}

	/**
	 * Text fields of the submission in the format of Exopite_Anti_Spam_Scorer::score().
	 *
	 * @param WPCF7_ContactForm $contact_form Form.
	 * @param WPCF7_Submission  $submission   Submission.
	 * @return array[]
	 */
	public function get_fields( $contact_form, $submission ) {

		/**
		 * Field names containing one of these strings are name fields (e.g. "your-name", "vorname").
		 *
		 * @param string[] $patterns Parts of field names.
		 */
		$patterns = (array) apply_filters( 'exopite_anti_spam_scoring_name_patterns', array( 'name' ) );
		$fields   = array();

		foreach ( $contact_form->scan_form_tags() as $tag ) {

			if ( empty( $tag->name ) || ! in_array( $tag->basetype, array( 'text', 'textarea', 'email', 'tel', 'url' ), true ) ) {
				continue;
			}

			$value = $submission->get_posted_data( $tag->name );

			if ( is_array( $value ) ) {
				$value = implode( ' ', array_filter( $value, 'is_scalar' ) );
			}

			$is_name = false;

			foreach ( $patterns as $pattern ) {
				if ( '' !== (string) $pattern && false !== stripos( $tag->name, (string) $pattern ) ) {
					$is_name = true;
					break;
				}
			}

			$fields[] = array(
				'name'    => $tag->name,
				'type'    => $tag->basetype,
				'value'   => is_scalar( $value ) ? (string) $value : '',
				'is_name' => $is_name,
			);
		}

		return $fields;
	}

	/**
	 * Two letter language code of the form (e.g. "de" for "de_DE").
	 *
	 * @param WPCF7_ContactForm $contact_form Form.
	 * @return string
	 */
	public function get_language( $contact_form ) {

		$locale = (string) $contact_form->locale();

		if ( '' === $locale ) {
			$locale = get_locale();
		}

		$parts = explode( '_', $locale );

		return strtolower( $parts[0] );
	}
}
