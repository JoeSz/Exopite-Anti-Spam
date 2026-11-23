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
 * Description:       Anti Spam plugin for Contact Form 7 with timestamp, honeypot (random location), token matching, bad/spam word filtering, email and domain blacklist and an image captcha, eg: [easimagecaptcha easimagecaptcha] or [easimagecaptcha icon:6 choose:3].
 * Version:           20261123
 * Author:            Joe Szalai
 * Author URI:        https://www.joeszalai.org
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       exopite-anti-spam
 * Domain Path:       /languages
 * Requires Plugins:  contact-form-7
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
define( 'EXOPITE_ANTI_SPAM_VERSION', '20261123' );
define( 'EXOPITE_ANTI_SPAM_URL', plugin_dir_url( __FILE__ ) );
define( 'EXOPITE_ANTI_SPAM_PATH', plugin_dir_path( __FILE__ ) );
define( 'EXOPITE_ANTI_SPAM_FILE', __FILE__ );
define( 'EXOPITE_ANTI_SPAM_PLUGIN_NAME', 'exopite-anti-spam' );
define( 'EXOPITE_ANTI_SPAM_PLUGIN_NICE_NAME', 'Exopite Anti-Spam' );

/**
 * Self-update through Plugin Update Checker 6.0 (vendor/plugin-update-checker).
 * Switched off: nothing is loaded unless wp-config.php sets
 * define( 'EXOPITE_ANTI_SPAM_UPDATE_CHECKER', true );
 * Plugin Update Checker 6.0 requires PHP 7.4, on older PHP versions it is not loaded (the plugin itself works).
 * The metadata URL is the one the plugin used up to 4.8.
 */
if ( defined( 'EXOPITE_ANTI_SPAM_UPDATE_CHECKER' ) && EXOPITE_ANTI_SPAM_UPDATE_CHECKER && version_compare( PHP_VERSION, '7.4', '>=' ) ) {
	require_once EXOPITE_ANTI_SPAM_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php';
	\YahnisElsts\PluginUpdateChecker\v6\PucFactory::buildUpdateChecker(
		'https://update.joeszalai.org/?action=get_metadata&slug=' . EXOPITE_ANTI_SPAM_PLUGIN_NAME,
		EXOPITE_ANTI_SPAM_FILE,
		EXOPITE_ANTI_SPAM_PLUGIN_NAME
	);
}


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
