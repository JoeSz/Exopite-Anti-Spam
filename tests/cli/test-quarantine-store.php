<?php
/**
 * Task 4: quarantine storage, retention and maximum.
 */

require __DIR__ . '/bootstrap.php';

global $wpdb;

$main       = exopite_anti_spam();
$quarantine = $main->quarantine;
$table      = $quarantine->table();
$honeypot   = $main->honeypot_name;
$form_id    = eas_create_form( '', array( 'honeypot' => 'yes', 'timestamp' => 'yes', 'quarantine' => 'yes' ) );
$valid      = array(
	'your-name'    => 'Peter Muster',
	'your-email'   => 'peter@example.com',
	'your-message' => 'Hello, I have a question.',
);
$rows       = function () use ( $wpdb, $table, $form_id ) {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `$table` WHERE form_id = %d", $form_id ) );
};

eas_submit( $form_id, array_merge( $valid, array( 'eastimestamp' => eas_timestamp(), $honeypot => 'https://spam.test' ) ) );
$row     = $wpdb->get_row( $wpdb->prepare( "SELECT reasons, posted_data FROM `$table` WHERE form_id = %d ORDER BY id DESC LIMIT 1", $form_id ), ARRAY_A );
$reasons = $row ? json_decode( $row['reasons'], true ) : array();
$posted  = $row ? json_decode( $row['posted_data'], true ) : array();
eas_assert(
	1 === $rows() && in_array( 'honeypot', $reasons['codes'], true ) && 'Peter Muster' === $posted['your-name'] && ! isset( $posted[ $honeypot ] ) && ! isset( $posted['eastimestamp'] ) && ! isset( $posted['_wpcf7'] ),
	'honeypot spam stored without internal fields'
);

eas_set_meta( $form_id, array( 'quarantine' => 'no' ) );
eas_submit( $form_id, array_merge( $valid, array( 'eastimestamp' => eas_timestamp(), $honeypot => 'https://spam.test' ) ) );
eas_assert( 1 === $rows(), 'quarantine off: not stored' );

eas_set_meta( $form_id, array( 'quarantine' => 'yes' ) );
eas_submit( $form_id, $valid );
eas_assert( 1 === $rows(), 'sure bot signal (timestamp missing): not stored' );

// Maximum: only rows of this form exist on the test site, keep the others anyway (count all rows).
$options = get_option( 'exopite-anti-spam' );
update_option( 'exopite-anti-spam', array_merge( (array) $options, array( 'quarantine_max' => 100 ) ) );
$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" );
for ( $i = 0; $i < 105 - $before; $i++ ) {
	$wpdb->insert( $table, array( 'created' => current_time( 'mysql' ), 'form_id' => $form_id, 'reasons' => '{}', 'posted_data' => '{}', 'meta' => '{}' ) );
}
$min_before = (int) $wpdb->get_var( "SELECT MIN(id) FROM `$table`" );
$quarantine->maybe_clean_up( true );
$min_after = (int) $wpdb->get_var( "SELECT MIN(id) FROM `$table`" );
eas_assert( 100 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" ) && $min_after === $min_before + 5, 'maximum 100: oldest 5 deleted' );
update_option( 'exopite-anti-spam', $options );

$old = wp_date( 'Y-m-d H:i:s', time() - 31 * DAY_IN_SECONDS );
$wpdb->insert( $table, array( 'created' => $old, 'form_id' => $form_id, 'reasons' => '{}', 'posted_data' => '{}', 'meta' => '{}' ) );
$old_id = (int) $wpdb->insert_id;
$quarantine->maybe_clean_up( true );
eas_assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `$table` WHERE id = %d", $old_id ) ), 'entry older than 30 days deleted' );

eas_done();
