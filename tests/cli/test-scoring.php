<?php
/**
 * Task 8: spam score in the submission.
 */

require __DIR__ . '/bootstrap.php';

global $wpdb;

$main    = exopite_anti_spam();
$form_id = eas_create_form( '', array( 'scoring' => 'yes' ) );
$links   = 'See https://a.test https://b.test https://c.test https://d.test https://e.test for our offers and many more details here.';
$post    = function ( $message ) {
	return array(
		'your-name'    => 'Peter Muster',
		'your-email'   => 'peter@example.com',
		'your-message' => $message,
	);
};

$result = eas_submit( $form_id, $post( $links . ' [url=offer]click[/url]' ) );
$codes  = $main->validate->get_codes();
eas_assert( 'spam' === $result['status'] && in_array( 'score', $codes['spam'], true ) && 11 === $main->scoring->last_total, '5 links + BBCode: spam, score 11 (' . $result['status'] . ', ' . $main->scoring->last_total . ')' );

$result = eas_submit( $form_id, $post( $links ) );
eas_assert( 'mail_sent' === $result['status'], '5 links: sent (' . $result['status'] . ')' );

eas_set_meta( $form_id, array( 'scoring' => 'no' ) );
$result = eas_submit( $form_id, $post( $links . ' [url=offer]click[/url]' ) );
eas_assert( 'mail_sent' === $result['status'], 'scoring off: sent' );

eas_set_meta( $form_id, array( 'scoring' => 'yes', 'quarantine' => 'yes' ) );
eas_submit( $form_id, $post( $links . ' [url=offer]click[/url]' ) );
$score = $wpdb->get_var( $wpdb->prepare( 'SELECT score FROM `' . $main->quarantine->table() . '` WHERE form_id = %d ORDER BY id DESC LIMIT 1', $form_id ) );
eas_assert( '11' === (string) $score, 'quarantine stores the score (' . $score . ')' );

eas_done();
