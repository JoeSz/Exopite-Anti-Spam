<?php
/**
 * Bad / spam words: whole words only, also for list entries that start or end with a sign
 * ("earn \$", "url=", "a href="), own words with umlauts, a broken fragment never breaks the form.
 */

require __DIR__ . '/bootstrap.php';

$main     = exopite_anti_spam();
$validate = $main->validate;
$match    = function ( $fragment, $text ) use ( $validate ) {
	return 1 === preg_match( $validate->word_pattern( $fragment ), $validate->to_lower( $text ) );
};

// Entries of lists/spamwords.txt
eas_assert( $match( 'viagra', 'Cheap VIAGRA here' ), 'a word' );
eas_assert( ! $match( 'viagra', 'Viagras are not on the list' ), 'not part of a longer word' );
eas_assert( $match( 'earn \$', 'earn $ fast' ), 'entry ending with a sign: "earn \$" (never matched with \b)' );
eas_assert( ! $match( 'earn \$', 'learn $ fast' ), '... but not inside a word' );
eas_assert( $match( 'url=', 'visit [url=http://spam.test]me[/url]' ), '"url=" followed by a letter still matches' );
eas_assert( $match( 'a href=', '<a href="http://spam.test">x</a>' ), '"a href=" matches' );
eas_assert( ! $match( 'a href=', 'la href=x' ), '"a href=" not inside a word' );
eas_assert( $match( '(?<=\[)url(?=\])', 'see [url] here' ), 'entry with own lookarounds' );
eas_assert( $match( '100% guaranteed', 'it is 100% guaranteed' ), 'entry with a sign inside' );
eas_assert( ! $match( 'cash\d', 'cashd' ), 'escaped last character is not a word character of the text' );
eas_assert( $match( 'cash\d', 'cash5' ), '... but still matches what it describes' );

// Own words (plain text, quoted)
eas_assert( $match( preg_quote( 'ärger', '/' ), 'So ein Ärger!' ), 'own word with an umlaut' );
eas_assert( ! $match( preg_quote( 'ärger', '/' ), 'verärgert' ), 'own word not inside a longer word' );
eas_assert( $match( preg_quote( '$$$', '/' ), 'earn $$$now' ), 'own word of signs, also next to a letter' );

// Through the form: a list entry ending with a sign, and a broken fragment from the filter
$form_id = eas_create_form( '', array( 'badwords' => 'yes' ) );
$valid   = array(
	'your-name'    => 'Peter Muster',
	'your-email'   => 'peter@example.com',
	'your-message' => 'Hello, I have a question about your offer.',
);

$result = eas_submit( $form_id, array_merge( $valid, array( 'your-message' => 'You can earn $ 500 a day with us, just try it.' ) ) );
$codes  = $main->validate->get_codes();
eas_assert( 'validation_failed' === $result['status'] && in_array( 'badwords', $codes['invalid'], true ), 'form: "earn $" rejected (' . $result['status'] . ')' );

$broken = function ( $words ) {
	$words[] = '(broken';
	return $words;
};
add_filter( 'exopite_anti_spam_bad_words', $broken );
$warnings = array();
set_error_handler(
	function ( $no, $message ) use ( &$warnings ) {
		$warnings[] = $message;
		return true;
	}
);
$result = eas_submit( $form_id, $valid );
restore_error_handler();
remove_filter( 'exopite_anti_spam_bad_words', $broken );
eas_assert( 'mail_sent' === $result['status'] && array() === $warnings, 'a broken fragment of the filter is skipped without a warning (' . $result['status'] . ', ' . count( $warnings ) . ')' );

eas_done();
