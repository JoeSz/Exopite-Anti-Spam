<?php
/**
 * Task 2: statistics.
 */

require __DIR__ . '/bootstrap.php';

global $wpdb;

$main  = exopite_anti_spam();
$stats = $main->stats;
$table = $wpdb->prefix . 'eas_cf7_stats';
$empty = array( 'spam' => array(), 'invalid' => array() );

eas_assert( array( 'spam_other' ) === $stats->get_codes_for_result( array( 'status' => 'spam' ), $empty, false, array() ), 'spam without own code: spam_other' );
eas_assert( array( 'released', 'released:score' ) === $stats->get_codes_for_result( array( 'status' => 'mail_sent' ), $empty, true, array( 'score' ) ), 'released codes' );
eas_assert( array( 'invalid_other' ) === $stats->get_codes_for_result( array( 'status' => 'validation_failed' ), $empty, false, array() ), 'validation error without own code: invalid_other' );

$form_id = eas_create_form( '', array( 'honeypot' => 'yes' ) );
$today   = wp_date( 'Y-m-d' );
$count   = function ( $reason ) use ( $wpdb, $table, $form_id, $today ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT count FROM `$table` WHERE stat_date = %s AND form_id = %d AND reason = %s", $today, $form_id, $reason ) );
};

$stats->record( $form_id, array( 'sent' ) );
$stats->record( $form_id, array( 'sent' ) );
eas_assert( 2 === $count( 'sent' ), 'two records: count 2' );

eas_submit(
	$form_id,
	array(
		'your-name'           => 'Peter Muster',
		'your-email'          => 'peter@example.com',
		$main->honeypot_name  => 'https://spam.test',
	)
);
eas_assert( 1 === $count( 'honeypot' ), 'honeypot submission counted' );

$old = wp_date( 'Y-m-d', time() - 91 * DAY_IN_SECONDS );
$wpdb->query( $wpdb->prepare( "INSERT INTO `$table` (stat_date, form_id, reason, count) VALUES (%s, %d, 'sent', 5)", $old, $form_id ) );
delete_transient( 'eas_stats_cleanup' );
$stats->maybe_clean_up();
eas_assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `$table` WHERE stat_date = %s AND form_id = %d", $old, $form_id ) ), 'rows older than 90 days deleted' );

eas_assert( Exopite_Anti_Spam_Activator::DB_VERSION === get_option( 'exopite_anti_spam_db_version' ), 'db version current' );

eas_done();
