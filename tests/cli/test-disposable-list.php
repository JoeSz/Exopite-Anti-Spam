<?php
/**
 * Update of the disposable e-mail domain list (button on the "Blacklist" page), HTTP mocked.
 */

require __DIR__ . '/bootstrap.php';

$check  = exopite_anti_spam()->email_check;
$option = Exopite_Anti_Spam_Public_Email_Check::LIST_OPTION;
$backup = get_option( $option );
$mock   = null;

add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) use ( &$mock ) {
		return null === $mock ? $pre : $mock;
	},
	10,
	3
);

$response = function ( $body, $code = 200 ) {
	return array(
		'headers'  => array(),
		'body'     => $body,
		'response' => array( 'code' => $code, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => null,
	);
};

$domains = array();
for ( $i = 0; $i < 1200; $i++ ) {
	$domains[] = 'trash' . $i . '.example';
}
$domains[] = 'new-disposable.example';

delete_option( $option );
$bundled = $check->get_list_info();
eas_assert( 'bundled' === $bundled['source'] && $bundled['count'] > 10000, 'without update: list of the plugin (' . $bundled['count'] . ')' );

$mock   = $response( implode( "\n", $domains ) . "\n" );
$result = $check->update_list();
$info   = $check->get_list_info();
eas_assert( $result['ok'] && 'updated' === $info['source'] && 1201 === $info['count'], 'valid list saved (' . $result['message'] . ')' );
eas_assert( $check->is_disposable( 'x.new-disposable.example' ) && ! $check->is_disposable( 'mailinator.com' ), 'updated list in use' );
eas_assert( 'no' === $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s", $option ) ) || 'off' === $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s", $option ) ), 'option not autoloaded' );

$mock   = $response( implode( "\n", $domains ) . "\n<script>alert(1)</script>\n" );
$result = $check->update_list();
eas_assert( ! $result['ok'] && 1201 === $check->get_list_info()['count'], 'line which is not a domain: rejected, old list kept' );

$mock   = $response( "a.example\nb.example\n" );
$result = $check->update_list();
eas_assert( ! $result['ok'] && 1201 === $check->get_list_info()['count'], 'too few domains: rejected' );

$mock   = $response( 'Not found', 404 );
$result = $check->update_list();
eas_assert( ! $result['ok'] && 1201 === $check->get_list_info()['count'], 'HTTP 404: rejected (' . $result['message'] . ')' );

$mock = new WP_Error( 'http_request_failed', 'timeout' );
$result = $check->update_list();
eas_assert( ! $result['ok'], 'network error: rejected' );

$check->reset_list();
$info = $check->get_list_info();
eas_assert( 'bundled' === $info['source'] && $check->is_disposable( 'mailinator.com' ), 'reset: list of the plugin again' );

if ( $backup ) {
	update_option( $option, $backup, false );
}

eas_done();
