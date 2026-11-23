<?php
/**
 * CLI test helpers for Exopite Anti Spam, run against a local WordPress (default: test1).
 *
 * Usage: php tests/cli/test-<name>.php [path to wp-load.php]
 * The tests create temporary Contact Form 7 forms (skip_mail, no lead API) and delete them at the end.
 * The plugin under test is the copy installed on the site (sync the repository to the site first).
 */

$eas_wp_load = isset( $argv[1] ) ? $argv[1] : 'C:/Data/laragon/www/test1/wp-load.php';

$_SERVER['HTTP_HOST']       = 'www.test1.test';
$_SERVER['REQUEST_URI']     = '/eas-cli-test/';
$_SERVER['REMOTE_ADDR']     = '203.0.113.5';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (EAS CLI test)';
$_SERVER['REQUEST_METHOD']  = 'POST';

require $eas_wp_load;

$GLOBALS['eas_test_passed'] = 0;
$GLOBALS['eas_test_failed'] = 0;
$GLOBALS['eas_test_forms']  = array();

/**
 * One assertion, prints PASS/FAIL.
 */
function eas_assert( $condition, $label ) {

	if ( $condition ) {
		$GLOBALS['eas_test_passed']++;
		echo 'PASS ' . $label . PHP_EOL;
	} else {
		$GLOBALS['eas_test_failed']++;
		echo 'FAIL ' . $label . PHP_EOL;
	}
}

/**
 * Summary line, exit code 1 if an assertion failed.
 */
function eas_done() {

	eas_cleanup();

	echo $GLOBALS['eas_test_passed'] . ' passed, ' . $GLOBALS['eas_test_failed'] . ' failed' . PHP_EOL;

	exit( $GLOBALS['eas_test_failed'] ? 1 : 0 );
}

/**
 * Temporary form: skip_mail, recipient test@example.test, all anti spam features off unless set in $anti_spam_meta.
 *
 * @return int Form ID.
 */
function eas_create_form( $template = '', $anti_spam_meta = array() ) {

	if ( '' === $template ) {
		$template = '[text* your-name] [email* your-email] [textarea your-message] [submit "Send"]';
	}

	$contact_form = WPCF7_ContactForm::get_template( array( 'title' => 'EAS CLI test ' . uniqid() ) );

	$properties                        = $contact_form->get_properties();
	$properties['form']                = $template;
	$properties['mail']['recipient']   = 'test@example.test';
	$properties['mail_2']['active']    = false;
	$properties['additional_settings'] = 'skip_mail: on';
	$contact_form->set_properties( $properties );

	$form_id = (int) $contact_form->save();

	$defaults = array(
		'timestamp'            => 'no',
		'timestamp_min'        => 2,
		'timestamp_max'        => 10,
		'honeypot'             => 'no',
		'badwords'             => 'no',
		'ajaxload'             => 'no',
		'acceptance_ajaxcheck' => 'no',
		'ratelimit'            => 'no',
	);

	update_post_meta( $form_id, 'exopite-anti-spam', array_merge( $defaults, $anti_spam_meta ) );

	$GLOBALS['eas_test_forms'][] = $form_id;

	return $form_id;
}

/**
 * Change anti spam settings of a form.
 */
function eas_set_meta( $form_id, $anti_spam_meta ) {

	$meta = get_post_meta( $form_id, 'exopite-anti-spam', true );

	update_post_meta( $form_id, 'exopite-anti-spam', array_merge( is_array( $meta ) ? $meta : array(), $anti_spam_meta ) );
}

/**
 * Reset the per request state of Contact Form 7 and of the plugin, so a process can submit more than once.
 */
function eas_reset_request_state() {

	$main = exopite_anti_spam();

	$instance = new ReflectionProperty( 'WPCF7_Submission', 'instance' );
	$instance->setAccessible( true );
	$instance->setValue( null, null );

	$main->validate->spam_reasons         = array();
	$main->validate->spam_codes           = array();
	$main->validate->invalid_codes        = array();
	$main->validate->invalidated_fields   = array();
	$main->validate->invalid_before_cf7cf = array();
	$main->validate->timestamp_token      = false;
	$main->validate->refresh_timestamp    = false;
	$main->tokens->used                   = array();
	$main->tokens->release_locks();
	$main->ratelimiter->failed  = false;
	$main->ratelimiter->blocked = false;
	$main->public->cf7_meta     = array();

	foreach ( array( 'scoring', 'pow', 'email_check' ) as $part ) {
		if ( isset( $main->$part ) && method_exists( $main->$part, 'reset' ) ) {
			$main->$part->reset();
		}
	}
}

/**
 * Submit a form like the browser does.
 *
 * @return array Contact Form 7 result (status, message, invalid_fields ...).
 */
function eas_submit( $form_id, $post ) {

	eas_reset_request_state();

	$contact_form = wpcf7_contact_form( $form_id );

	$_POST = wp_slash(
		array_merge(
			array(
				'_wpcf7'                => $form_id,
				'_wpcf7_version'        => WPCF7_VERSION,
				'_wpcf7_locale'         => $contact_form->locale(),
				'_wpcf7_unit_tag'       => 'wpcf7-f' . $form_id . '-o1',
				'_wpcf7_container_post' => 0,
			),
			$post
		)
	);

	$_REQUEST = $_POST;

	return $contact_form->submit();
}

/**
 * Valid encrypted timestamp, created $age_seconds ago.
 */
function eas_timestamp( $age_seconds = 10 ) {

	return exopite_anti_spam()->crypter->encrypt_hex( bin2hex( random_bytes( 32 ) ) . ( time() - $age_seconds ) );
}

/**
 * Delete the temporary forms (and their statistics and quarantine rows).
 */
function eas_cleanup() {
	global $wpdb;

	foreach ( $GLOBALS['eas_test_forms'] as $form_id ) {

		wp_delete_post( $form_id, true );

		foreach ( array( 'eas_cf7_stats', 'eas_cf7_quarantine' ) as $table ) {
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $table ) ) ) {
				$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . $wpdb->prefix . $table . '` WHERE form_id = %d', $form_id ) );
			}
		}
	}

	$GLOBALS['eas_test_forms'] = array();
}

register_shutdown_function( 'eas_cleanup' );
