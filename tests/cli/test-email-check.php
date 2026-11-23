<?php
/**
 * Task 9: e-mail check (disposable domains, domains without MX/A record).
 */

require __DIR__ . '/bootstrap.php';

$main = exopite_anti_spam();

// Independent of the shipped list: mailinator.com is a disposable domain in this test.
add_filter(
	'exopite_anti_spam_disposable_domains',
	function ( $list ) {
		$list[] = 'mailinator.com';
		return $list;
	}
);

$form_id = eas_create_form( '', array( 'email_disposable' => 'yes' ) );
$post    = function ( $email ) {
	return array(
		'your-name'    => 'Peter Muster',
		'your-email'   => $email,
		'your-message' => 'Hello, I have a question.',
	);
};
$code    = function () use ( $main ) {
	$codes = $main->validate->get_codes();
	return implode( ',', $codes['invalid'] );
};

$result = eas_submit( $form_id, $post( 'x@mailinator.com' ) );
eas_assert( 'validation_failed' === $result['status'] && 'email_disposable' === $code(), 'disposable address rejected' );

$result = eas_submit( $form_id, $post( 'x@sub.mailinator.com' ) );
eas_assert( 'validation_failed' === $result['status'] && 'email_disposable' === $code(), 'subdomain of a disposable domain rejected' );

eas_set_meta( $form_id, array( 'email_disposable' => 'no' ) );
$result = eas_submit( $form_id, $post( 'x@mailinator.com' ) );
eas_assert( 'mail_sent' === $result['status'], 'check off: sent' );

eas_set_meta( $form_id, array( 'email_mx' => 'yes' ) );
add_filter( 'pre_exopite_anti_spam_domain_exists', '__return_false' );
$result = eas_submit( $form_id, $post( 'x@gmial.cmo' ) );
remove_filter( 'pre_exopite_anti_spam_domain_exists', '__return_false' );
eas_assert( 'validation_failed' === $result['status'] && 'email_domain' === $code(), 'domain without MX rejected' );

/**
 * DNS stubs.
 */
class EAS_Test_Email_Check_DNS_Error extends Exopite_Anti_Spam_Public_Email_Check {
	protected function lookup( $domain, $type ) {
		return false;
	}
}

class EAS_Test_Email_Check_No_Records extends Exopite_Anti_Spam_Public_Email_Check {
	protected function lookup( $domain, $type ) {
		return array();
	}
}

$domain = 'eas-dns-test-' . wp_generate_password( 8, false ) . '.example';
$error  = new EAS_Test_Email_Check_DNS_Error( $main );
eas_assert( true === $error->domain_exists( $domain ) && false === get_transient( 'eas_mx_' . md5( $domain ) ), 'DNS error: accepted, not cached (fail open)' );

$none = new EAS_Test_Email_Check_No_Records( $main );
eas_assert( false === $none->domain_exists( $domain ), 'no MX, A, AAAA: does not exist' );
delete_transient( 'eas_mx_' . md5( $domain ) );

$result = eas_submit( $form_id, $post( 'test@gmail.com' ) );
eas_assert( 'mail_sent' === $result['status'], 'gmail.com with real DNS: sent' );

eas_done();
