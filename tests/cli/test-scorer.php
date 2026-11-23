<?php
/**
 * Task 7: spam scorer rules, without WordPress (runs on PHP 8.2 and 8.5).
 * Usage: php tests/cli/test-scorer.php
 */

require dirname( __DIR__, 2 ) . '/exopite-anti-spam/includes/class-exopite-anti-spam-scorer.php';

$passed = 0;
$failed = 0;

function eas_scorer_assert( $condition, $label ) {
	global $passed, $failed;

	if ( $condition ) {
		$passed++;
		echo 'PASS ' . $label . PHP_EOL;
	} else {
		$failed++;
		echo 'FAIL ' . $label . PHP_EOL;
	}
}

/**
 * Fields: name (text, name field), email, message (textarea).
 */
function eas_fields( $name, $message, $email = 'peter@example.com' ) {

	return array(
		array( 'name' => 'your-name', 'type' => 'text', 'value' => $name, 'is_name' => true ),
		array( 'name' => 'your-email', 'type' => 'email', 'value' => $email, 'is_name' => false ),
		array( 'name' => 'your-message', 'type' => 'textarea', 'value' => $message, 'is_name' => false ),
	);
}

$scorer = new Exopite_Anti_Spam_Scorer();

$r = $scorer->score( eas_fields( 'Peter', 'Buy cheap vіagra here today please' ), 'de' );
eas_scorer_assert( isset( $r['hits']['homoglyph'] ) && 10 === $r['hits']['homoglyph'] && $r['total'] >= 10, 'homoglyph: 10' );

$russian = 'Здравствуйте, хочу заказать консультацию по вашему предложению.';
$r = $scorer->score( eas_fields( 'Иван Петров', $russian ), 'ru' );
eas_scorer_assert( ! isset( $r['hits']['homoglyph'] ) && ! isset( $r['hits']['foreign_script'] ), 'Russian form, Russian text: no points' );

$r = $scorer->score( eas_fields( 'Иван Петров', $russian ), 'de' );
eas_scorer_assert( isset( $r['hits']['foreign_script'] ) && 5 === $r['hits']['foreign_script'], 'German form, Russian text: foreign_script 5' );

$r = $scorer->score( eas_fields( '王小明', '您好，我想了解一下你们的产品价格和交货时间。' ), 'de', array( 'han' ) );
eas_scorer_assert( ! isset( $r['hits']['foreign_script'] ), 'Chinese allowed in addition: no foreign_script' );

$links = 'See https://a.test https://b.test https://c.test https://d.test https://e.test for our offers and many more details here.';
$r     = $scorer->score( eas_fields( 'Peter', $links ), 'de' );
eas_scorer_assert( isset( $r['hits']['links'] ) && 6 === $r['hits']['links'] && 6 === $r['total'], '5 links: 6 points' );

$r = $scorer->score( eas_fields( 'Peter', $links . ' [url=offer]click[/url]' ), 'de' );
eas_scorer_assert( 11 === $r['total'], '5 links + BBCode: 11 (' . $r['total'] . ')' );

$ok = true;
foreach ( array( 'Peter', 'McDonald', 'Müller', 'Hirschfeld', 'Schmidt', 'Schwarz', 'Strzelczyk' ) as $name ) {
	$r  = $scorer->score( eas_fields( $name, 'Hello, I have a question about your offer.' ), 'de' );
	$ok = $ok && ! isset( $r['hits']['gibberish_name'] );
}
eas_scorer_assert( $ok, 'real names: no gibberish_name' );

$r1 = $scorer->score( eas_fields( 'xKqLmPz', 'Hello, I have a question about your offer.' ), 'de' );
$r2 = $scorer->score( eas_fields( 'Bcdfgh', 'Hello, I have a question about your offer.' ), 'de' );
eas_scorer_assert( isset( $r1['hits']['gibberish_name'], $r2['hits']['gibberish_name'] ) && 2 === $r1['hits']['gibberish_name'] && 2 === $r2['hits']['gibberish_name'], 'gibberish names: 2' );

$r = $scorer->score( eas_fields( 'www.spam.test', 'Hello, I have a question about your offer.' ), 'de' );
eas_scorer_assert( isset( $r['hits']['name_link'] ) && 5 === $r['hits']['name_link'], 'URL in name: 5' );

$r = $scorer->score( eas_fields( 'Peter', 'https://x.test' ), 'de' );
eas_scorer_assert( isset( $r['hits']['link_only'] ) && 4 === $r['hits']['link_only'], 'link only message: 4' );

$r = $scorer->score( eas_fields( 'Hello world', 'Hello world' ), 'de' );
eas_scorer_assert( isset( $r['hits']['same_value'] ) && 3 === $r['hits']['same_value'], 'same value in name and message: 3' );

$r = $scorer->score(
	array(
		array( 'name' => 'your-email', 'type' => 'email', 'value' => 'peter@example.com', 'is_name' => false ),
		array( 'name' => 'your-email-confirm', 'type' => 'email', 'value' => 'peter@example.com', 'is_name' => false ),
	),
	'de'
);
eas_scorer_assert( ! isset( $r['hits']['same_value'] ), 'e-mail confirmation: no same_value' );

$no_links = new Exopite_Anti_Spam_Scorer( array( 'links' => 0 ) );
$r        = $no_links->score( eas_fields( 'Peter', $links ), 'de' );
eas_scorer_assert( ! isset( $r['hits']['links'] ), 'rule with 0 points is off' );

$r = $scorer->score( eas_fields( 'Peter Muster', 'Hallo, ich hätte gerne ein Angebot für Ihre Dienstleistung. Viele Grüße' ), 'de' );
eas_scorer_assert( 0 === $r['total'], 'normal German message: 0 points' );

echo $passed . ' passed, ' . $failed . ' failed' . PHP_EOL;
exit( $failed ? 1 : 0 );
