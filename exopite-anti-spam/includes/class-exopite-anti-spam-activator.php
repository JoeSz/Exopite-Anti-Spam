<?php

/**
 * Fired during plugin activation
 *
 * @link       https://www.joeszalai.org
 * @since      1.0.0
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/includes
 * @author     Joe Szalai <contact@joeszalai.org>
 */
class Exopite_Anti_Spam_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {

        global $wpdb;
        $table_name = $wpdb->prefix . "eas_cf7_email_tokens";

        $charset_collate = $wpdb->get_charset_collate();

        $sql[] = "CREATE TABLE " . $table_name . " (
            id INT NOT NULL AUTO_INCREMENT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            submit_ip VARCHAR(46),
            submit_user_id SMALLINT,
            cf7_id SMALLINT,
            token VARCHAR(64),
            type enum('acceptance', 'sent') DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        /*
         * It seems IF NOT EXISTS isn't needed if you're using dbDelta - if the table already exists it'll
         * compare the schema and update it instead of overwriting the whole table.
         *
         * @link https://code.tutsplus.com/tutorials/custom-database-tables-maintaining-the-database--wp-28455
         */
        dbDelta( $sql );

    }

}
