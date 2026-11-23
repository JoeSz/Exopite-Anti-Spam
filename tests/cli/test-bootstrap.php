<?php
/**
 * Smoke test of the CLI helpers: a valid submission is sent, a filled honeypot is spam.
 */

require __DIR__ . '/bootstrap.php';

$form_id = eas_create_form( '', array( 'honeypot' => 'yes', 'timestamp' => 'yes' ) );
$valid   = array(
	'your-name'    => 'Peter Muster',
	'your-email'   => 'peter@example.com',
	'your-message' => 'Hello, I have a question about your offer.',
	'eastimestamp' => eas_timestamp(),
);

$result = eas_submit( $form_id, $valid );
eas_assert( 'mail_sent' === $result['status'], 'valid submission is sent (' . $result['status'] . ')' );

$result = eas_submit( $form_id, array_merge( $valid, array( 'eastimestamp' => eas_timestamp(), exopite_anti_spam()->honeypot_name => 'https://spam.test' ) ) );
eas_assert( 'spam' === $result['status'], 'filled honeypot is spam (' . $result['status'] . ')' );

eas_done();
