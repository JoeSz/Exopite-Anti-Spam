<?php
/**
 * Task 1: stable reason codes of the existing checks, all checks skipped while releasing.
 */

require __DIR__ . '/bootstrap.php';

$main     = exopite_anti_spam();
$honeypot = $main->honeypot_name;
$form_id  = eas_create_form( '', array( 'honeypot' => 'yes', 'timestamp' => 'yes', 'badwords' => 'yes' ) );
$valid    = array(
	'your-name'    => 'Peter Muster',
	'your-email'   => 'peter@example.com',
	'your-message' => 'Hello, I have a question about your offer.',
);

eas_submit( $form_id, array_merge( $valid, array( 'eastimestamp' => eas_timestamp(), $honeypot => 'https://spam.test' ) ) );
$codes = $main->validate->get_codes();
eas_assert( array( 'honeypot' ) === $codes['spam'], 'honeypot code' );

eas_submit( $form_id, $valid );
$codes = $main->validate->get_codes();
eas_assert( in_array( 'timestamp', $codes['spam'], true ), 'timestamp code' );

eas_submit( $form_id, array_merge( $valid, array( 'eastimestamp' => eas_timestamp( 0 ) ) ) );
$codes = $main->validate->get_codes();
eas_assert( in_array( 'too_fast', $codes['invalid'], true ), 'too_fast code' );

eas_submit( $form_id, array_merge( $valid, array( 'eastimestamp' => eas_timestamp(), 'your-message' => 'cheap viagra here' ) ) );
$codes = $main->validate->get_codes();
eas_assert( in_array( 'badwords', $codes['invalid'], true ), 'badwords code' );

$main->releasing = true;
$result = eas_submit( $form_id, array_merge( $valid, array( $honeypot => 'https://spam.test' ) ) );
$codes  = $main->validate->get_codes();
$main->releasing = false;
eas_assert( 'mail_sent' === $result['status'] && array() === $codes['spam'] && array() === $codes['invalid'], 'releasing skips all checks (' . $result['status'] . ')' );

eas_done();
