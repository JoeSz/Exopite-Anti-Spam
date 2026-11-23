<?php
/**
 * Task 10: proof-of-work on the server.
 */

require __DIR__ . '/bootstrap.php';

$main    = exopite_anti_spam();
$pow     = $main->pow;
$form_id = eas_create_form( '', array( 'pow' => 'yes', 'pow_difficulty' => 'low' ) );
$base    = array(
	'your-name'    => 'Peter Muster',
	'your-email'   => 'peter@example.com',
	'your-message' => 'Hello, I have a question.',
);
$solve   = function ( $challenge ) use ( $pow ) {
	for ( $nonce = 0; ; $nonce++ ) {
		if ( $pow->check_solution( $challenge['s'], (string) $nonce, $challenge['b'] ) ) {
			return (string) $nonce;
		}
	}
};
$codes   = function () use ( $main ) {
	$codes = $main->validate->get_codes();
	return implode( ',', array_merge( $codes['spam'], $codes['invalid'] ) );
};

$challenge = $pow->create_challenge( $form_id );
$solution  = $solve( $challenge );
$valid     = array_merge( $base, array( 'easpowc' => $challenge['c'], 'easpows' => $solution ) );

$result = eas_submit( $form_id, $valid );
eas_assert( 'mail_sent' === $result['status'], 'valid solution: sent (' . $result['status'] . ' ' . $codes() . ')' );

$result = eas_submit( $form_id, $valid );
eas_assert( 'validation_failed' === $result['status'] && 'token_used' === $codes(), 'same solution again: token_used' );

$challenge = $pow->create_challenge( $form_id );
$result    = eas_submit( $form_id, array_merge( $base, array( 'easpowc' => $challenge['c'], 'easpows' => '1' . $solve( $challenge ) . '7' ) ) );
eas_assert( 'spam' === $result['status'] && 'pow' === $codes(), 'wrong solution: spam' );

$result = eas_submit( $form_id, $base );
eas_assert( 'spam' === $result['status'] && 'pow' === $codes(), 'no fields: spam' );

$form_2    = eas_create_form( '', array( 'pow' => 'yes', 'pow_difficulty' => 'low' ) );
$challenge = $pow->create_challenge( $form_2 );
$result    = eas_submit( $form_id, array_merge( $base, array( 'easpowc' => $challenge['c'], 'easpows' => $solve( $challenge ) ) ) );
eas_assert( 'spam' === $result['status'] && 'pow' === $codes(), 'challenge of another form: spam' );

$challenge = $pow->create_challenge( $form_id );
eas_set_meta( $form_id, array( 'pow_difficulty' => 'high' ) );
$result = eas_submit( $form_id, array_merge( $base, array( 'easpowc' => $challenge['c'], 'easpows' => $solve( $challenge ) ) ) );
eas_assert( 'spam' === $result['status'] && 'pow' === $codes(), 'easier challenge than the form setting: spam' );
eas_set_meta( $form_id, array( 'pow_difficulty' => 'low' ) );

// Expired: own challenge created 120 seconds ago, max age 60 seconds.
$salt    = bin2hex( random_bytes( 16 ) );
$expired = array(
	'c' => $main->crypter->encrypt_hex( wp_json_encode( array( 'pow', $salt, 16, $form_id, time() - 120 ) ) ),
	's' => $salt,
	'b' => 16,
);
add_filter(
	'exopite_anti_spam_pow_max_age',
	function () {
		return 60;
	}
);
$result = eas_submit( $form_id, array_merge( $base, array( 'easpowc' => $expired['c'], 'easpows' => $solve( $expired ) ) ) );
eas_assert( 'validation_failed' === $result['status'] && 'expired' === $codes(), 'expired challenge: validation error' );

eas_set_meta( $form_id, array( 'pow' => 'no' ) );
$result = eas_submit( $form_id, $base );
eas_assert( 'mail_sent' === $result['status'], 'proof-of-work off: sent without the fields' );

eas_assert( '5' === get_option( 'exopite_anti_spam_db_version' ), 'db version 5' );

eas_done();
