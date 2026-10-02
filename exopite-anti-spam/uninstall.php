<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://www.joeszalai.org
 * @since      1.0.0
 *
 * @package    Exopite_Anti_Spam
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-exopite-anti-spam-logger.php';

/**
 * Remove the token table and the log files (temporary data only).
 *
 * The settings (blacklist, encryption key, per form settings) are kept by default,
 * because a manual update (delete and reinstall) would remove them too.
 * To remove everything, add to wp-config.php before uninstall:
 * define( 'EXOPITE_ANTI_SPAM_UNINSTALL_DELETE_ALL', true );
 */
function exopite_anti_spam_uninstall_site() {
	global $wpdb;

	$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . 'eas_cf7_email_tokens`' );
	delete_option( 'exopite_anti_spam_db_version' );
	Exopite_Anti_Spam_Logger::delete_all();

	if ( defined( 'EXOPITE_ANTI_SPAM_UNINSTALL_DELETE_ALL' ) && EXOPITE_ANTI_SPAM_UNINSTALL_DELETE_ALL ) {
		delete_option( 'exopite-anti-spam' );
		delete_post_meta_by_key( 'exopite-anti-spam' );
	}
}

if ( is_multisite() ) {

	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $exopite_anti_spam_site_id ) {
		switch_to_blog( $exopite_anti_spam_site_id );
		exopite_anti_spam_uninstall_site();
		restore_current_blog();
	}

} else {

	exopite_anti_spam_uninstall_site();

}
