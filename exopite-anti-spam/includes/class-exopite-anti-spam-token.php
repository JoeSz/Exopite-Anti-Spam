<?php

/**
 * Single use tokens.
 *
 * @link       https://www.joeszalai.org
 * @since      20261006
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/includes
 */

/**
 * Single use tokens in the table {prefix}eas_cf7_email_tokens (created by Exopite_Anti_Spam_Activator):
 * - 'captcha', 'acceptance': stored as used on submission (right or wrong answer),
 * - 'sent': the timestamp token, stored as used after the mail was sent (Exopite_Anti_Spam_Public::wpcf7_mail_sent()).
 * MySQL named locks prevent that parallel requests with the same token are all accepted.
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/includes
 */
class Exopite_Anti_Spam_Token {

    /**
     * Plugin main class.
     */
    public $main;

    /**
     * Single use tokens (captcha, acceptance) already consumed in this request.
     */
    public $used = array();

    /**
     * MySQL named locks held by this request.
     */
    public $locks = array();

    public function __construct( $plugin_main ) {

        $this->main = $plugin_main;

    }

    /**
     * Image captcha and acceptance tokens are single use: every submitted token is stored
     * (right or wrong answer). After an unsuccessful submission the JS loads a new token,
     * which is stored on its first submission too.
     *
     * A MySQL named lock prevents that parallel requests with the same token are all accepted.
     *
     * @param string $token  Random part of the token.
     * @param string $type   'captcha' or 'acceptance'.
     * @return bool  false if the token was already used.
     */
    public function consume( $token, $type ) {
        global $wpdb;

        if ( empty( $token ) || ! is_string( $token ) ) {
            return false;
        }

        // Validation may run more than once in a request (e.g. by other plugins).
        if ( in_array( $type . ':' . $token, $this->used, true ) ) {
            return true;
        }

        $lock_name = $this->lock( $token, $type, 5 );

        // Timeout: an other request with the same token is running right now.
        if ( false === $lock_name ) {
            return false;
        }

        $valid = ! $this->is_used( $token, $type );

        if ( $valid ) {

            $contact_form = WPCF7_ContactForm::get_current();
            $cf7_id = $contact_form ? $contact_form->id() : 0;

            // Fail closed: a token which can not be stored as used could be replayed (e.g. database error).
            if ( false === $this->save( $this->get_db_data( $cf7_id, $token ), $type ) ) {
                $valid = false;
            } else {
                $this->used[] = $type . ':' . $token;
            }

        }

        $this->release_lock( $lock_name );

        if ( $valid ) {
            $this->clean_up( $type );
        }

        return $valid;
    }

    /**
     * @return bool  true if the token is stored as used.
     */
    public function is_used( $token, $type ) {
        global $wpdb;

        $sql = "SELECT COUNT(token) AS token FROM `" . $wpdb->prefix . "eas_cf7_email_tokens` WHERE token = %s AND `type` = %s LIMIT 1";
        $sql = $wpdb->prepare( $sql, $token, $type );
        $results = $wpdb->get_results( $sql, ARRAY_A );

        if ( ! isset( $results[0] ) || ! isset( $results[0]['token'] ) ) {
            return false;
        }

        $token_valid = ! ( $results[0]['token'] == 0 );

        return $token_valid;
    }

    /**
     * MySQL named lock for a token. Locks belong to the DB connection,
     * so they are released automatically at the end of the request too.
     *
     * @return string|false|null  Lock name: locked, false: timeout (an other request
     *                            with the same token is running), null: locks are not supported.
     */
    public function lock( $token, $type, $timeout ) {
        global $wpdb;

        // Lock names are limited to 64 characters.
        $lock_name = 'eas_' . md5( $wpdb->prefix . $type . $token );
        $locked = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, %d )', $lock_name, $timeout ) );

        if ( '1' === $locked ) {
            $this->locks[ $lock_name ] = true;
            return $lock_name;
        }

        return ( '0' === $locked ) ? false : null;
    }

    public function release_lock( $lock_name ) {
        global $wpdb;

        if ( empty( $lock_name ) || ! isset( $this->locks[ $lock_name ] ) ) {
            return;
        }

        $wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock_name ) );
        unset( $this->locks[ $lock_name ] );
    }

    /**
     * Release the locks still held (timestamp token) after the submission.
     */
    public function release_locks() {

        foreach ( array_keys( $this->locks ) as $lock_name ) {
            $this->release_lock( $lock_name );
        }

    }

    public function save( $data, $type ) {
        global $wpdb;
        $sql = 'INSERT INTO ' . $wpdb->prefix . 'eas_cf7_email_tokens ( `timestamp`, `submit_ip`, `submit_user_id`, `cf7_id`, `token`, `type` ) VALUES ( %s, %s, %d, %d, %s, %s)';
        $sql = $wpdb->prepare( $sql, $data['submit_time'], $data['submit_ip'], $data['submit_user_id'], $data['cf7_id'], $data['token'], $type );

        return $wpdb->query( $sql );
    }

    /**
     * Data for the token table.
     */
    public function get_db_data( $cf7_id, $token ) {

        $data = array();

        $data['submit_time'] = date_i18n( 'Y-m-d H:i:s' );
        /**
         * The IP address is not used for any check, so it is not stored (GDPR data minimization).
         * Uncomment the next line to store it again.
         */
        // $data['submit_ip'] = $this->main->public->get_visitor_ip();
        $data['submit_ip'] = '';
        $data['submit_user_id'] = 0;
        if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
            $current_user = wp_get_current_user(); // WP_User
            $data['submit_user_id'] = $current_user->ID;
        }
        $data['cf7_id'] = $cf7_id;
        $data['token'] = $token;

        return $data;
    }

    public function clean_up( $type ) {
        global $wpdb;

        switch ( $type ) {
            case 'acceptance':
            case 'captcha':
            case 'pow':
                // Used tokens must be kept at least as long as they are valid, otherwise they could be replayed.
                $elapsed = $this->get_max_age( $type ) + HOUR_IN_SECONDS;
                break;
            case 'sent':
            default:
                $elapsed = 31 * DAY_IN_SECONDS;
                break;
        }

        // Same time format/timezone as the stored timestamps (date_i18n), MySQL NOW() may use a different timezone.
        $cutoff = wp_date( 'Y-m-d H:i:s', time() - $elapsed );

        $sql = 'DELETE FROM ' . $wpdb->prefix . "eas_cf7_email_tokens WHERE `timestamp` < %s AND `type` = %s;";
        $sql = $wpdb->prepare( $sql, $cutoff, $type );
        return $wpdb->query( $sql );
    }

    /**
     * Max age of an image captcha or acceptance token in seconds, filters:
     * 'exopite_anti_spam_captcha_max_age' and 'exopite_anti_spam_acceptance_max_age'.
     * The minimum time is not checked here, use "Timestamp" for that.
     */
    public function get_max_age( $type ) {

        return max( MINUTE_IN_SECONDS, intval( apply_filters( 'exopite_anti_spam_' . $type . '_max_age', DAY_IN_SECONDS ) ) );

    }

}
