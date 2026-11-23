<?php
/**
 * Task 5: release and delete quarantine entries.
 */

require __DIR__ . '/bootstrap.php';

global $wpdb;

$main       = exopite_anti_spam();
$quarantine = $main->quarantine;
$table      = $quarantine->table();
$honeypot   = $main->honeypot_name;
$form_id    = eas_create_form( '', array( 'honeypot' => 'yes', 'quarantine' => 'yes' ) );
$spam       = array(
	'your-name'    => 'Peter Muster',
	'your-email'   => 'peter@example.com',
	'your-message' => 'Hello, I have a question.',
	$honeypot      => 'https://autofill.test',
);
$latest_id  = function ( $form ) use ( $wpdb, $table ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(id) FROM `$table` WHERE form_id = %d", $form ) );
};
$stat       = function ( $reason ) use ( $wpdb, $form_id ) {
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT count FROM `' . $wpdb->prefix . 'eas_cf7_stats` WHERE stat_date = %s AND form_id = %d AND reason = %s', wp_date( 'Y-m-d' ), $form_id, $reason ) );
};

// The original request: referer and IP, stored with the entry.
$_SERVER['HTTP_REFERER'] = home_url( '/kontakt/?from=test' );
$_SERVER['REQUEST_URI']  = '/kontakt/?from=test';
$_SERVER['REMOTE_ADDR']  = '198.51.100.7';
eas_submit( $form_id, $spam );
$_SERVER['REMOTE_ADDR']  = '203.0.113.5';
$_SERVER['REQUEST_URI']  = '/eas-cli-test/';
unset( $_SERVER['HTTP_REFERER'] );

$id   = $latest_id( $form_id );
$seen = array();

// In the CLI the request URI of Contact Form 7 is cached on "init": store the URL of the page like a browser submission.
$entry                = $quarantine->get_entry( $id );
$entry['meta']['url'] = home_url( '/kontakt/?from=test' );
$wpdb->update( $table, array( 'meta' => wp_json_encode( $entry['meta'] ) ), array( 'id' => $id ) );
add_action(
	'wpcf7_mail_sent',
	function () use ( &$seen ) {
		$submission = WPCF7_Submission::get_instance();
		$seen       = array(
			'name' => isset( $_POST['your-name'] ) ? wp_unslash( $_POST['your-name'] ) : '',
			'ip'   => $submission->get_meta( 'remote_ip' ),
			'url'  => $submission->get_meta( 'url' ),
		);
	},
	1
);

$sent_before = $stat( 'sent' );
eas_reset_request_state();
$result = $quarantine->release( $id );
eas_assert( $id && $result['ok'] && 'mail_sent' === $result['status'] && null === $quarantine->get_entry( $id ), 'released and deleted (' . $result['status'] . ')' );
eas_assert( isset( $seen['name'] ) && 'Peter Muster' === $seen['name'] && '198.51.100.7' === $seen['ip'] && false !== strpos( $seen['url'], '/kontakt/?from=test' ), 'release sees the original data, IP and URL' );
eas_assert( 1 === $stat( 'released' ) && 1 === $stat( 'released:honeypot' ) && $sent_before === $stat( 'sent' ), 'statistics: released counted, sent not' );
eas_assert( ! $main->releasing && '/eas-cli-test/' === $_SERVER['REQUEST_URI'] && ! isset( $_SERVER['HTTP_REFERER'] ), 'request restored after the release' );

// Validation error on release: the e-mail is missing.
eas_submit( $form_id, $spam );
$id = $latest_id( $form_id );
$wpdb->update( $table, array( 'posted_data' => wp_json_encode( array( 'your-name' => 'Peter Muster' ) ) ), array( 'id' => $id ) );
eas_reset_request_state();
$result = $quarantine->release( $id );
$entry  = $quarantine->get_entry( $id );
eas_assert( ! $result['ok'] && $entry && '' !== (string) $entry['release_error'], 'validation error: kept with release_error (' . ( $entry ? $entry['release_error'] : '' ) . ')' );

// Locked by another connection (double click).
$other = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$other->get_var( $other->prepare( 'SELECT GET_LOCK( %s, 0 )', 'eas_q_' . md5( $wpdb->prefix . $id ) ) );
$result = $quarantine->release( $id );
eas_assert( 'locked' === $result['status'] && $quarantine->get_entry( $id ), 'locked entry is not released' );
$other->get_var( $other->prepare( 'SELECT RELEASE_LOCK( %s )', 'eas_q_' . md5( $wpdb->prefix . $id ) ) );

// delete_all() of one form only.
$form_2 = eas_create_form( '', array( 'honeypot' => 'yes', 'quarantine' => 'yes' ) );
eas_submit( $form_2, $spam );
$quarantine->delete_all( $form_id );
eas_assert( 0 === $latest_id( $form_id ) && 0 < $latest_id( $form_2 ), 'delete_all( form ) deletes only that form' );

// Deleted form: not releasable, entry kept.
$id_2 = $latest_id( $form_2 );
wp_delete_post( $form_2, true );
$result = $quarantine->release( $id_2 );
eas_assert( 'not_releasable' === $result['status'] && $quarantine->get_entry( $id_2 ), 'deleted form: not releasable, entry kept' );

eas_done();
