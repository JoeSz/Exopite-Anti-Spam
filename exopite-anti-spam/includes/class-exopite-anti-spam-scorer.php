<?php
/**
 * Spam score of a submission (content rules).
 *
 * @link       https://www.joeszalai.org
 * @since      20261124
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/includes
 */

/**
 * Generic content rules, each gives points; the submission is spam if the total reaches the threshold of the form.
 * No single weak signal (a link, an unusual name) is enough. The rules do not depend on the language of the site:
 * the script of the form language (e.g. Cyrillic on a Russian site) is normal. No WordPress functions are used here,
 * the WordPress side is Exopite_Anti_Spam_Public_Scoring.
 *
 * Pattern: Strategy (one method per rule, selected by name). Why: rules can be switched off (0 points) or changed
 * independently.
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/includes
 */
class Exopite_Anti_Spam_Scorer {

	/**
	 * Points per rule (0: rule off).
	 */
	const DEFAULT_POINTS = array(
		'homoglyph'      => 10,
		'bbcode_link'    => 5,
		'name_link'      => 5,
		'foreign_script' => 5,
		'link_only'      => 4,
		'same_value'     => 3,
		'gibberish_name' => 2,
		'links'          => 2,
	);

	/**
	 * Cyrillic letters, which look like Latin letters.
	 */
	const HOMOGLYPHS = 'аеорсухіјѕАВЕКМНОРСТХІЈЅ';

	/**
	 * A link.
	 */
	const URL_PATTERN = '#(?:https?://|www\.)\S+#iu';

	/**
	 * Minimum number of letters for the "foreign script" rule (one or two letters should not decide).
	 */
	const MIN_LETTERS_FOREIGN = 10;

	/**
	 * Points per rule.
	 *
	 * @var int[]
	 */
	private $points;

	/**
	 * Constructor.
	 *
	 * @param int[] $points Points per rule (merged with DEFAULT_POINTS, unknown rules are ignored).
	 */
	public function __construct( $points = array() ) {

		$this->points = self::DEFAULT_POINTS;

		foreach ( (array) $points as $rule => $value ) {
			if ( isset( $this->points[ $rule ] ) ) {
				$this->points[ $rule ] = max( 0, (int) $value );
			}
		}
	}

	/**
	 * Scripts, which can be allowed in addition to Latin and the script of the form language (PCRE properties).
	 *
	 * @return string[]
	 */
	public function get_scripts() {

		return array(
			'cyrillic' => '\p{Cyrillic}',
			'han'      => '\p{Han}',
			'japanese' => '\p{Han}\p{Hiragana}\p{Katakana}',
			'hangul'   => '\p{Hangul}',
			'arabic'   => '\p{Arabic}',
			'hebrew'   => '\p{Hebrew}',
			'greek'    => '\p{Greek}',
			'thai'     => '\p{Thai}',
		);
	}

	/**
	 * Script of a language (two letter code), 'latin' if the language uses the Latin script.
	 *
	 * @param string $language Language code, e.g. 'de', 'ru'.
	 * @return string Key of get_scripts() or 'latin'.
	 */
	public function script_for_language( $language ) {

		$map = array(
			'cyrillic' => array( 'ru', 'uk', 'bg', 'sr', 'mk', 'be', 'kk' ),
			'han'      => array( 'zh' ),
			'japanese' => array( 'ja' ),
			'hangul'   => array( 'ko' ),
			'arabic'   => array( 'ar', 'fa', 'ur' ),
			'hebrew'   => array( 'he' ),
			'greek'    => array( 'el' ),
			'thai'     => array( 'th' ),
		);

		$language = strtolower( (string) $language );

		foreach ( $map as $script => $languages ) {
			if ( in_array( $language, $languages, true ) ) {
				return $script;
			}
		}

		return 'latin';
	}

	/**
	 * Score of a submission.
	 *
	 * @param array[]  $fields        Text fields: array( 'name' => string, 'type' => 'text'|'textarea'|'email'|'tel'|'url',
	 *                                'value' => string, 'is_name' => bool ).
	 * @param string   $language      Two letter language code of the form.
	 * @param string[] $extra_scripts Additionally allowed scripts (keys of get_scripts()).
	 * @return array array( 'total' => int, 'hits' => array( rule => points ) ), only rules with points.
	 */
	public function score( $fields, $language, $extra_scripts = array() ) {

		$fields = $this->normalize_fields( $fields );
		$hits   = array();

		foreach ( $this->points as $rule => $points ) {

			if ( $points <= 0 ) {
				continue;
			}

			$count = (int) call_user_func( array( $this, 'rule_' . $rule ), $fields, $language, (array) $extra_scripts );

			if ( $count > 0 ) {
				$hits[ $rule ] = $points * $count;
			}
		}

		return array(
			'total' => (int) array_sum( $hits ),
			'hits'  => $hits,
		);
	}

	/**
	 * Fields with all keys, values as strings.
	 *
	 * @param array $fields Fields.
	 * @return array[]
	 */
	private function normalize_fields( $fields ) {

		$normalized = array();

		foreach ( (array) $fields as $field ) {
			$normalized[] = array(
				'name'    => isset( $field['name'] ) ? (string) $field['name'] : '',
				'type'    => isset( $field['type'] ) ? (string) $field['type'] : 'text',
				'value'   => isset( $field['value'] ) && is_scalar( $field['value'] ) ? (string) $field['value'] : '',
				'is_name' => ! empty( $field['is_name'] ),
			);
		}

		return $normalized;
	}

	/**
	 * All values as one text.
	 *
	 * @param array[] $fields Fields.
	 * @return string
	 */
	private function all_text( $fields ) {

		$values = array();

		foreach ( $fields as $field ) {
			$values[] = $field['value'];
		}

		return implode( "\n", $values );
	}

	/**
	 * Number of letters (any script).
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private function count_letters( $text ) {

		return (int) preg_match_all( '/\p{L}/u', $text );
	}

	/**
	 * A Latin word with Cyrillic look-alike letters (e.g. "vіagra" with a Cyrillic "і"). Not a Cyrillic word with a
	 * Latin letter, not CJK or Greek text.
	 *
	 * @param array[] $fields Fields.
	 * @return int 1 or 0.
	 */
	private function rule_homoglyph( $fields ) {

		if ( ! preg_match_all( '/\p{L}+/u', $this->all_text( $fields ), $words ) ) {
			return 0;
		}

		foreach ( $words[0] as $word ) {

			$latin    = (int) preg_match_all( '/\p{Latin}/u', $word );
			$cyrillic = (int) preg_match_all( '/\p{Cyrillic}/u', $word );

			if ( ! $latin || ! $cyrillic || $latin <= $cyrillic ) {
				continue;
			}

			// Only look-alike letters: a word with other Cyrillic letters is something else.
			$rest = preg_replace( '/[' . self::HOMOGLYPHS . ']/u', '', $word );

			if ( ! preg_match( '/\p{Cyrillic}/u', (string) $rest ) ) {
				return 1;
			}
		}

		return 0;
	}

	/**
	 * BBCode or HTML link in any field.
	 *
	 * @param array[] $fields Fields.
	 * @return int 1 or 0.
	 */
	private function rule_bbcode_link( $fields ) {

		return preg_match( '/\[(?:url|link)=|<a\s[^>]*href\s*=/iu', $this->all_text( $fields ) ) ? 1 : 0;
	}

	/**
	 * URL or e-mail address in a name field.
	 *
	 * @param array[] $fields Fields.
	 * @return int 1 or 0.
	 */
	private function rule_name_link( $fields ) {

		foreach ( $fields as $field ) {
			if ( $field['is_name'] && preg_match( '#https?://|www\.|@#iu', $field['value'] ) ) {
				return 1;
			}
		}

		return 0;
	}

	/**
	 * More than half of the letters are not Latin, not the script of the form language and not allowed in addition.
	 *
	 * @param array[]  $fields        Fields.
	 * @param string   $language      Language code.
	 * @param string[] $extra_scripts Allowed scripts.
	 * @return int 1 or 0.
	 */
	private function rule_foreign_script( $fields, $language, $extra_scripts ) {

		$text  = $this->all_text( $fields );
		$total = $this->count_letters( $text );

		if ( $total < self::MIN_LETTERS_FOREIGN ) {
			return 0;
		}

		$scripts = $this->get_scripts();
		$allowed = '\p{Latin}';

		foreach ( array_merge( array( $this->script_for_language( $language ) ), $extra_scripts ) as $script ) {
			if ( isset( $scripts[ $script ] ) ) {
				$allowed .= $scripts[ $script ];
			}
		}

		$allowed_letters = (int) preg_match_all( '/[' . $allowed . ']/u', $text );

		return ( ( $total - $allowed_letters ) * 2 > $total ) ? 1 : 0;
	}

	/**
	 * A message (textarea) with a link and less than 20 other letters.
	 *
	 * @param array[] $fields Fields.
	 * @return int 1 or 0.
	 */
	private function rule_link_only( $fields ) {

		foreach ( $fields as $field ) {

			if ( 'textarea' !== $field['type'] || ! preg_match( self::URL_PATTERN, $field['value'] ) ) {
				continue;
			}

			if ( $this->count_letters( (string) preg_replace( self::URL_PATTERN, '', $field['value'] ) ) < 20 ) {
				return 1;
			}
		}

		return 0;
	}

	/**
	 * The same value (at least 5 characters) in two different text fields (e.g. name and message).
	 * E-mail, phone and URL fields are not compared (an e-mail confirmation field is legit).
	 *
	 * @param array[] $fields Fields.
	 * @return int 1 or 0.
	 */
	private function rule_same_value( $fields ) {

		$seen = array();

		foreach ( $fields as $field ) {

			if ( ! in_array( $field['type'], array( 'text', 'textarea' ), true ) ) {
				continue;
			}

			$value = trim( function_exists( 'mb_strtolower' ) ? mb_strtolower( $field['value'], 'UTF-8' ) : strtolower( $field['value'] ) );

			if ( preg_match_all( '/./us', $value ) < 5 ) {
				continue;
			}

			if ( isset( $seen[ $value ] ) && $seen[ $value ] !== $field['name'] ) {
				return 1;
			}

			$seen[ $value ] = $field['name'];
		}

		return 0;
	}

	/**
	 * Name fields with a word, which does not look like a name: 5+ consonants in a row ("sch" counts as one, e.g.
	 * "Hirschfeld"), 3+ changes from a lower to an upper case letter ("McDonald" has one), or 7+ Latin letters with
	 * less than 10% vowels ("Schmidt", "Schwarz" have 14%).
	 *
	 * @param array[] $fields Fields.
	 * @return int Number of fields.
	 */
	private function rule_gibberish_name( $fields ) {

		$count = 0;

		foreach ( $fields as $field ) {

			if ( ! $field['is_name'] || 'text' !== $field['type'] || ! preg_match_all( '/\p{L}+/u', $field['value'], $words ) ) {
				continue;
			}

			foreach ( $words[0] as $word ) {
				if ( $this->is_gibberish_word( $word ) ) {
					++$count;
					break;
				}
			}
		}

		return $count;
	}

	/**
	 * Does a word of a name field look like random letters?
	 *
	 * @param string $word Word (letters only).
	 * @return bool
	 */
	private function is_gibberish_word( $word ) {

		if ( preg_match( '/[bcdfghjklmnpqrstvwxz]{5,}/i', (string) preg_replace( '/sch/i', 's', $word ) ) ) {
			return true;
		}

		if ( preg_match_all( '/\p{Ll}\p{Lu}/u', $word ) >= 3 ) {
			return true;
		}

		$latin = (int) preg_match_all( '/\p{Latin}/u', $word );

		if ( $latin >= 7 ) {
			$vowels = (int) preg_match_all( '/[aeiouyàáâãäåæèéêëìíîïòóôõöøœùúûüűőýÿ]/iu', $word );

			if ( $vowels * 10 < $latin ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Links: the first two are free, every further link counts.
	 *
	 * @param array[] $fields Fields.
	 * @return int Number of links above two.
	 */
	private function rule_links( $fields ) {

		return max( 0, (int) preg_match_all( self::URL_PATTERN, $this->all_text( $fields ) ) - 2 );
	}
}
