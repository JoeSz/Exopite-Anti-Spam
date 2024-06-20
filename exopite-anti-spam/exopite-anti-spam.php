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
 * Version:           20240619
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
define( 'EXOPITE_ANTI_SPAM_VERSION', '20240619' );
define( 'EXOPITE_ANTI_SPAM_URL', plugin_dir_url( __FILE__ ) );
define( 'EXOPITE_ANTI_SPAM_PATH', plugin_dir_path( __FILE__ ) );
define( 'EXOPITE_ANTI_SPAM_FILE', __FILE__ );
define( 'EXOPITE_ANTI_SPAM_PLUGIN_NAME', 'exopite-anti-spam' );
define( 'EXOPITE_ANTI_SPAM_PLUGIN_NICE_NAME', 'Exopite Anti-Spam' );



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
