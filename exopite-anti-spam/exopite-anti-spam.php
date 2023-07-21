<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.joeszalai.org
 * @since             1.0.0
 * @package           Exopite_Anti_Spam
 *
 * @wordpress-plugin
 * Plugin Name:       Exopite Anti Spam
 * Plugin URI:        https://www.joeszalai.org/exopite/anti-spam
 * Description:       Anti Spam plugin for Contact Form 7 with timestamp, honeypot (random location), token matching, bad/spam word filtering, email and domain blacklist and an image captcha, eg: [easimagecaptcha] or [easimagecaptcha icon:6 choose:3].
 * Version:           20230719
 * Author:            Joe Szalai
 * Author URI:        https://www.joeszalai.org
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       exopite-anti-spam
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'EXOPITE_ANTI_SPAM_VERSION', '20230719' );
define( 'EXOPITE_ANTI_SPAM_URL', plugin_dir_url( __FILE__ ) );
define( 'EXOPITE_ANTI_SPAM_PATH', plugin_dir_path( __FILE__ ) );
define( 'EXOPITE_ANTI_SPAM_FILE', __FILE__ );
define( 'EXOPITE_ANTI_SPAM_PLUGIN_NAME', 'exopite-anti-spam' );
define( 'EXOPITE_ANTI_SPAM_PLUGIN_NICE_NAME', 'Exopite Anti-Spam' );

/**
 * Update
 */
if ( is_admin() ) {

    /**
     * A custom update checker for WordPress plugins.
     *
     * Useful if you don't want to host your project
     * in the official WP repository, but would still like it to support automatic updates.
     * Despite the name, it also works with themes.
     *
     * @link http://w-shadow.com/blog/2011/06/02/automatic-updates-for-commercial-themes/
     * @link https://github.com/YahnisElsts/plugin-update-checker
     * @link https://github.com/YahnisElsts/wp-update-server
     */
    if( ! class_exists( 'Puc_v4_Factory' ) ) {

        require_once join( DIRECTORY_SEPARATOR, array( EXOPITE_ANTI_SPAM_PATH, 'vendor', 'plugin-update-checker', 'plugin-update-checker.php' ) );

    }

    $MyUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
        'https://update.joeszalai.org/?action=get_metadata&slug=' . EXOPITE_ANTI_SPAM_PLUGIN_NAME, //Metadata URL.
        __FILE__, //Full path to the main plugin file.
        EXOPITE_ANTI_SPAM_PLUGIN_NAME //Plugin slug. Usually it's the same as the name of the directory.
    );

    /**
     * Add plugin upgrade notification
     * https://andidittrich.de/2015/05/howto-upgrade-notice-for-wordpress-plugins.html
     *
     * This version add an extra <p> after the notice.
     * I want that to remove later.
     */
    add_action('in_plugin_update_message-' . EXOPITE_ANTI_SPAM_PLUGIN_NAME . '/' . EXOPITE_ANTI_SPAM_PLUGIN_NAME . '.php', 'show_upgrade_notification_exopite_anti_spam', 10, 2);
    function show_upgrade_notification_exopite_anti_spam( $current_plugin_metadata, $new_plugin_metadata ){
       // check "upgrade_notice"
       if (isset( $new_plugin_metadata->upgrade_notice ) && strlen( trim( $new_plugin_metadata->upgrade_notice ) )  > 0 ) {

            echo '<span style="background-color:#d54e21;padding:6px;color:#f9f9f9;margin-top:10px;display:block;"><strong>' . esc_html( 'Upgrade Notice', 'plugin-name' ) . ':</strong><br>';
            echo esc_html( $new_plugin_metadata->upgrade_notice );
            echo '</span>';

       }
    }

}
// End Update

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-exopite-anti-spam-activator.php
 */
function activate_exopite_anti_spam() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-exopite-anti-spam-activator.php';
	Exopite_Anti_Spam_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-exopite-anti-spam-deactivator.php
 */
function deactivate_exopite_anti_spam() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-exopite-anti-spam-deactivator.php';
	Exopite_Anti_Spam_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_exopite_anti_spam' );
register_deactivation_hook( __FILE__, 'deactivate_exopite_anti_spam' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-exopite-anti-spam.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_exopite_anti_spam() {

	$plugin = new Exopite_Anti_Spam();
	$plugin->run();

}
run_exopite_anti_spam();
