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
	 * Increase on every change of the token table schema.
	 * 2: type 'captcha' (single use image captcha tokens), index on token.
	 * 3: BIGINT user and form IDs (SMALLINT was max. 32767), index for the clean up.
	 * 4: tables for the statistics and the quarantine.
	 */
	const DB_VERSION = '4';

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
            submit_user_id BIGINT UNSIGNED,
            cf7_id BIGINT UNSIGNED,
            token VARCHAR(64),
            type enum('acceptance','sent','captcha') DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY token (token),
            KEY type_timestamp (type,timestamp)
        ) $charset_collate;";

        // Statistics: one row per day, form and reason (no personal data), see Exopite_Anti_Spam_Stats.
        $sql[] = "CREATE TABLE " . $wpdb->prefix . "eas_cf7_stats (
            stat_date DATE NOT NULL,
            form_id BIGINT UNSIGNED NOT NULL,
            reason VARCHAR(40) NOT NULL,
            count INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (stat_date,form_id,reason)
        ) $charset_collate;";

        // Quarantine: spam submissions of forms with quarantine enabled, see Exopite_Anti_Spam_Quarantine.
        $sql[] = "CREATE TABLE " . $wpdb->prefix . "eas_cf7_quarantine (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created DATETIME NOT NULL,
            form_id BIGINT UNSIGNED NOT NULL,
            reasons TEXT NOT NULL,
            score INT NOT NULL DEFAULT 0,
            posted_data LONGTEXT NOT NULL,
            meta TEXT NOT NULL,
            has_files TINYINT(1) NOT NULL DEFAULT 0,
            release_error TEXT NULL,
            PRIMARY KEY  (id),
            KEY created (created),
            KEY form_id (form_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        /*
         * It seems IF NOT EXISTS isn't needed if you're using dbDelta - if the table already exists it'll
         * compare the schema and update it instead of overwriting the whole table.
         *
         * @link https://code.tutsplus.com/tutorials/custom-database-tables-maintaining-the-database--wp-28455
         */
        dbDelta( $sql );

        update_option( 'exopite_anti_spam_db_version', self::DB_VERSION );

    }

    /**
     * The activation hook does not run on plugin updates (and only on the main site on multisite),
     * so the table is created/updated here if the schema version changed.
     */
    public static function maybe_update_db() {

        if ( get_option( 'exopite_anti_spam_db_version' ) !== self::DB_VERSION ) {
            self::activate();
        }

    }

}
