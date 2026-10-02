<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.joeszalai.org
 * @since      1.0.0
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/public
 * @author     Joe Szalai <contact@joeszalai.org>
 */
class Exopite_Anti_Spam_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
    private $version;

    /**
     * Store plugin main class to allow public access.
     *
     * @since    20180622
     * @var object      The main class.
     */
    public $main;

    public $timeout = false;

    public $words = false;

    public $token = false;

    public $crypter = false;

    /**
     * Anti spam settings per form ID (a page may contain multiple forms).
     */
    public $cf7_meta = array();

    public $min_time = 2;
    public $max_time = 600;

    /**
     * Logging can be enabled here, in wp-config.php with
     * define( 'EXOPITE_ANTI_SPAM_LOG', true );
     * or with the 'exopite_anti_spam_logging' filter.
     */
    public $logging = false;
    public $logging_enabled = null;
    public $honeypot = true;

    /**
     * Single use tokens (captcha, acceptance) already consumed in this request.
     */
    public $tokens_used = array();

    /**
     * MySQL named locks held by this request.
     */
    public $token_locks = array();

    /**
     * The timestamp is expired or already used, the JS should load a new one,
     * so the visitor can submit again without reloading the page (and losing the entered data).
     */
    public $refresh_timestamp = false;

    /**
     * Rate limit: an anti spam check failed in this request / the visitor is blocked.
     */
    public $ratelimit_failed = false;
    public $ratelimit_blocked = false;

    /**
     * Validation errors before "Conditional Fields for CF7" rebuilds the result.
     */
    public $invalid_before_cf7cf = array();

    /**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version, $plugin_main ) {

        $this->main = $plugin_main;
		$this->plugin_name = $plugin_name;
        $this->version = $version;

        $this->crypter = new Exopite_Anti_Spam_Crypter();

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/exopite-anti-spam-public.css', array(), $this->version, 'all' );

        // Spinner URL from PHP, an absolute path in the CSS file does not work if WordPress is installed in a subdirectory.
        wp_add_inline_style( $this->plugin_name, '.wpcf7-form-control.wpcf7-acceptance.loading::after,.eas-image-selector.loading::after{content:url("' . esc_url( includes_url( 'images/spinner-2x.gif' ) ) . '");}' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/exopite-anti-spam-public.js', array( 'jquery' ), $this->version, true );

    }

    /**
     * @link https://stackoverflow.com/questions/5678959/php-check-if-two-arrays-are-equal
     */
    public function array_equal( $a, $b ) {

        // Compare as sets of integers, scalars (e.g. single selection) are converted to arrays.
        $a = array_unique( array_map( 'intval', (array) $a ) );
        $b = array_unique( array_map( 'intval', (array) $b ) );
        sort( $a );
        sort( $b );

        return ( $a === $b );
    }

    public function get_icons_amount_translation( $selected_amount ) {
        $selected_amount_texts = array(
            1 => esc_attr__( 'one', 'exopite-anti-spam' ),
            2 => esc_attr__( 'two', 'exopite-anti-spam' ),
            3 => esc_attr__( 'three', 'exopite-anti-spam' ),
            4 => esc_attr__( 'four', 'exopite-anti-spam' ),
            5 => esc_attr__( 'five', 'exopite-anti-spam' ),
            6 => esc_attr__( 'six', 'exopite-anti-spam' ),
            7 => esc_attr__( 'seven', 'exopite-anti-spam' ),
            8 => esc_attr__( 'eight', 'exopite-anti-spam' ),
            9 => esc_attr__( 'nine', 'exopite-anti-spam' ),
        );

        $selected_amount_icon_texts = ( $selected_amount > 1 ) ? esc_attr__( 'icons', 'exopite-anti-spam' ) : esc_attr__( 'icon', 'exopite-anti-spam' );
        $selected_amount_as_text = $selected_amount_texts[$selected_amount] . ' ' . $selected_amount_icon_texts;

        return $selected_amount_as_text;
    }


    public function get_cf7_meta() {

        $wpcf7 = WPCF7_ContactForm::get_current();

        if ( ! $wpcf7 ) {
            return false;
        }

        $form_id = $wpcf7->id();

        if ( ! array_key_exists( $form_id, $this->cf7_meta ) ) {

            $this->cf7_meta[ $form_id ] = false;

            $cf7_meta = get_post_meta( $form_id );

            if ( isset( $cf7_meta['exopite-anti-spam'][0] ) ) {

                $this->cf7_meta[ $form_id ] = maybe_unserialize( $cf7_meta['exopite-anti-spam'][0] );

            }

        }

        return $this->cf7_meta[ $form_id ];

    }

    /**
     * Resolved on first use, so filters added by the theme are applied too.
     */
    public function is_logging() {

        if ( null === $this->logging_enabled ) {

            $logging = $this->logging || ( defined( 'EXOPITE_ANTI_SPAM_LOG' ) && EXOPITE_ANTI_SPAM_LOG );
            $this->logging_enabled = (bool) apply_filters( 'exopite_anti_spam_logging', $logging );

        }

        return $this->logging_enabled;
    }

    /**
     * Mask personal data (names, e-mail addresses, phone numbers, street addresses) before logging.
     * Field names are matched as substrings, e.g. "name" matches "your-name", "vorname", "nachname".
     * In all other fields e-mail addresses and phone numbers inside the text are masked.
     */
    public function mask_log_data( $data, $field = '' ) {

        if ( is_array( $data ) ) {

            $masked = array();

            foreach ( $data as $key => $value ) {
                // Nested values (e.g. checkbox arrays) inherit the field name of their parent.
                $masked[ $key ] = $this->mask_log_data( $value, is_string( $key ) ? $key : $field );
            }

            return $masked;
        }

        if ( ! is_scalar( $data ) || '' === (string) $data ) {
            return $data;
        }

        $value = (string) $data;
        $field = strtolower( (string) $field );

        // CF7 and plugin internal fields (form ID, tokens, honeypot) contain no personal data.
        $internal_fields = array( 'eastimestamp', 'easacceptance', 'exanspsel', 'exanspsel-auth', strtolower( $this->main->honeypot_name ) );
        if ( 0 === strpos( $field, '_wpcf7' ) || in_array( $field, $internal_fields, true ) ) {
            return $value;
        }

        $phone_fields = array( 'tel', 'phone', 'fon', 'handy', 'mobil', 'fax' );
        $mask_fields  = array_merge( array( 'name', 'mail', 'strasse', 'straße', 'street', 'adresse', 'address' ), $phone_fields );
        $mask_fields  = apply_filters( 'exopite_anti_spam_log_mask_fields', $mask_fields );

        $is_mask_field  = false;
        $is_phone_field = false;

        foreach ( $mask_fields as $mask_field ) {
            if ( '' !== $mask_field && false !== strpos( $field, strtolower( $mask_field ) ) ) {
                $is_mask_field = true;
                $is_phone_field = $is_phone_field || in_array( $mask_field, $phone_fields, true );
            }
        }

        if ( $is_mask_field ) {

            if ( false !== strpos( $value, '@' ) ) {
                return $this->mask_emails_in_text( $value );
            }

            if ( $is_phone_field ) {
                return $this->mask_phone( $value );
            }

            return $this->mask_words( $value );
        }

        return $this->mask_phones_in_text( $this->mask_emails_in_text( $value ) );
    }

    /**
     * john.doe@example.com -> j***@example.com (domain is kept, useful for the blacklist).
     */
    public function mask_emails_in_text( $text ) {

        $masked = preg_replace_callback( '/([^\s@<>"\',;:]+)@([^\s@<>"\',;:]+\.[a-z]{2,})/iu', function( $matches ) {
            return mb_substr( $matches[1], 0, 1 ) . '***@' . $matches[2];
        }, $text );

        return ( null === $masked ) ? '***' : $masked;
    }

    /**
     * Phone numbers inside free text (at least 7 digits).
     */
    public function mask_phones_in_text( $text ) {

        $masked = preg_replace_callback( '/(?<![\w.])\+?\d[\d \/()-]{5,}\d(?![\w.])/', function( $matches ) {
            if ( strlen( preg_replace( '/\D/', '', $matches[0] ) ) < 7 ) {
                return $matches[0];
            }
            return $this->mask_phone( $matches[0] );
        }, $text );

        return ( null === $masked ) ? '***' : $masked;
    }

    /**
     * 015901057332 -> 01********32
     */
    public function mask_phone( $phone ) {

        $digits = preg_replace( '/\D/', '', $phone );

        if ( strlen( $digits ) < 5 ) {
            return '***';
        }

        return substr( $digits, 0, 2 ) . str_repeat( '*', strlen( $digits ) - 4 ) . substr( $digits, -2 );
    }

    /**
     * Max Mustermann -> M*** M***
     */
    public function mask_words( $text ) {

        $masked = preg_replace_callback( '/\S+/u', function( $matches ) {
            return mb_substr( $matches[0], 0, 1 ) . '***';
        }, $text );

        return ( null === $masked ) ? '***' : $masked;
    }

    /**
     * @param string $reason
     * @param bool   $count_failure  Counts as a failed attempt for the rate limit.
     *                               False for typical human errors (timeout, forgotten selection, invalid e-mail).
     */
    public function invalidate_log( $reason, $count_failure = true ) {

        if ( $count_failure ) {
            $this->ratelimit_failed = true;
        }

        if ( ! empty( $reason ) && $this->is_logging() ) {

            $ip_address = new RemoteAddress();

            Exopite_Anti_Spam_Logger::write( 'wpcf7_invalidate', PHP_EOL . wp_date( 'Y-m-d H:i:s' ) . ' - ' . $ip_address->getIpAddress() . ' - ' . $reason . PHP_EOL . var_export( $this->mask_log_data( $_POST ), true ) . PHP_EOL . '---' . PHP_EOL );

            /**
             * Leaving a spam log.
             * @link https://contactform7.com/2020/07/18/custom-spam-filtering/
             */
            $submission = WPCF7_Submission::get_instance();

            if ( $submission ) {
                $submission->add_spam_log( array(
                    'agent' => $this->plugin_name,
                    'reason' => $reason,
                ) );
            }

        }

    }

    /**
     * Rate limit settings, can be changed with filters.
     */
    public function get_ratelimit_settings() {

        return array(
            'max_failures' => max( 1, intval( apply_filters( 'exopite_anti_spam_ratelimit_max_failures', 5 ) ) ),
            'window'       => max( MINUTE_IN_SECONDS, intval( apply_filters( 'exopite_anti_spam_ratelimit_window', 10 * MINUTE_IN_SECONDS ) ) ),
            'block'        => max( MINUTE_IN_SECONDS, intval( apply_filters( 'exopite_anti_spam_ratelimit_block', 30 * MINUTE_IN_SECONDS ) ) ),
        );

    }

    /**
     * Default: enabled if the form settings were never saved, can be changed with the 'exopite_enable_ratelimit' filter.
     *
     * @param array|false|null $options  Form settings, null: settings of the current form.
     */
    public function is_ratelimit_active( $options = null ) {

        if ( null === $options ) {
            $options = $this->get_cf7_meta();
        }

        return isset( $options['ratelimit'] ) ? ( $options['ratelimit'] === 'yes' ) : (bool) apply_filters( 'exopite_enable_ratelimit', true );
    }

    /**
     * Transient name for the visitor, only a hash of the IP address is stored.
     */
    public function get_ratelimit_key() {

        $ip = ( isset( $_SERVER['X_FORWARDED_FOR'] ) ) ? $_SERVER['X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
        $ip = apply_filters( 'exopite_anti_spam_ratelimit_ip', $ip );

        return 'eas_rl_' . md5( wp_salt( 'nonce' ) . $ip );
    }

    /**
     * Reject the submission, if the visitor is blocked (too many failed attempts).
     */
    public function wpcf7_validate_ratelimit( $result, $tags ) {

        if ( $this->is_user_logged_in() || ! $this->is_ratelimit_active() ) {
            return $result;
        }

        $data = get_transient( $this->get_ratelimit_key() );

        if ( is_array( $data ) && ! empty( $data['blocked_until'] ) && $data['blocked_until'] > time() ) {

            $this->ratelimit_blocked = true;
            $this->invalidate_log( 'rate limit: too many failed attempts, blocked until ' . wp_date( 'Y-m-d H:i:s', $data['blocked_until'] ), false );

            if ( ! empty( $tags[0] ) ) {
                $result->invalidate( $tags[0], esc_attr__( 'Too many failed attempts. Please try again in a few minutes.', 'exopite-anti-spam' ) );
            }

        }

        return $result;
    }

    /**
     * Count failed attempts (anti spam checks or CF7 spam status), block the visitor after too many.
     * Rejections because of the block itself are not counted, so the block is not extended.
     */
    public function wpcf7_submit_ratelimit( $contact_form, $result ) {

        if ( $this->is_user_logged_in() || $this->ratelimit_blocked || ! $this->is_ratelimit_active() ) {
            return;
        }

        $status = isset( $result['status'] ) ? $result['status'] : '';

        if ( 'mail_sent' === $status || ( ! $this->ratelimit_failed && 'spam' !== $status ) ) {
            return;
        }

        $settings = $this->get_ratelimit_settings();
        $key = $this->get_ratelimit_key();
        $now = time();

        $data = get_transient( $key );

        if ( ! is_array( $data ) || empty( $data['start'] ) || $data['start'] < ( $now - $settings['window'] ) ) {
            $data = array( 'count' => 0, 'start' => $now, 'blocked_until' => 0 );
        }

        $data['count']++;

        if ( $data['count'] >= $settings['max_failures'] ) {

            $data['blocked_until'] = $now + $settings['block'];
            $this->invalidate_log( 'rate limit: ' . $data['count'] . ' failed attempts, blocked for ' . ( $settings['block'] / MINUTE_IN_SECONDS ) . ' minutes', false );

        }

        $expiration = $data['blocked_until'] ? max( $settings['window'], $data['blocked_until'] - $now ) : $settings['window'];
        set_transient( $key, $data, $expiration );

    }

    public function get_token() {

        $options = get_option( $this->plugin_name );

        if( ! isset( $options['token'] ) ) {

            if ( ! is_array( $options) ) $options = array();

            $options['token'] = $this->crypter->generate_token( 40 );
            update_option( $this->plugin_name, $options );

        }

        return $options['token'];

    }

    /**
     * Convert a hex value from the request to binary.
     * Manipulated values (array, not hex, odd length) return false without PHP warnings,
     * the caller rejects the submission and writes the reason to the plugin log (with IP).
     */
    public function request_hex2bin( $value ) {

        if ( ! is_string( $value ) || '' === $value || 0 !== strlen( $value ) % 2 || ! preg_match( '/^[0-9a-f]+$/i', $value ) ) {
            return false;
        }

        return hex2bin( $value );
    }

    public function check_elapsed( $time ) {

        $options = $this->get_cf7_meta();
        if ( isset( $options ) ) {

            if ( isset( $options['timestamp_min'] ) ) {
                $this->min_time = $options['timestamp_min'];
            }

            if ( isset( $options['timestamp_max'] ) ) {
                $this->max_time = intval( $options['timestamp_max'] ) * 60;
            }

        }

        if ( $this->is_logging() ) {
            // DEBUG
            Exopite_Anti_Spam_Logger::write( 'options-test', PHP_EOL . wp_date( 'Y-m-d H:i:s' ) . ' - ' . var_export( $options, true ) . PHP_EOL );
        }

        $elapsed_seconds = ( time() - intval( $time ) );

        if ( $elapsed_seconds < $this->min_time || $elapsed_seconds > ( $this->max_time ) ) {
            return false;
        }

        return true;
    }

    /**
     * Built-in word list (lists/spamwords.txt), the entries are regex fragments (e.g. "\$\$\$").
     */
    public function get_words() {

        if ( ! $this->words ) {

            $fn = EXOPITE_ANTI_SPAM_PATH . 'lists/spamwords.txt';

            if ( file_exists( $fn ) ) {
                $lines = file_get_contents( $fn );
                $list = preg_split( '/\r\n|\r|\n/', $lines );
                $this->words = array_filter( $list );
            } else {
                Exopite_Anti_Spam_Logger::write( 'wpcf7_errors', PHP_EOL . wp_date( 'Y-m-d H:i:s' ) . ' - not exists: ' . $fn . PHP_EOL );
                return array();
            }

        }

        return $this->words;
    }

    public function to_lower( $text ) {

        return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );

    }

    /**
     * Own words from the "Blacklist" settings page, one per line, plain text (no regex).
     * Stored in the options, so plugin updates do not overwrite them.
     */
    public function get_custom_words() {

        $options = get_option( $this->plugin_name );
        $words = array();

        if ( ! empty( $options['custom_spam_words'] ) && is_string( $options['custom_spam_words'] ) ) {

            foreach ( preg_split( '/\r\n|\r|\n/', $options['custom_spam_words'] ) as $word ) {

                $word = trim( $this->to_lower( $word ) );

                if ( '' !== $word ) {
                    $words[] = $word;
                }

            }

        }

        return array_unique( $words );
    }

    /**
     * Database function
     */

    public function save_token_db( $data, $type ) {
        global $wpdb;
        $sql = 'INSERT INTO ' . $wpdb->prefix . 'eas_cf7_email_tokens ( `timestamp`, `submit_ip`, `submit_user_id`, `cf7_id`, `token`, `type` ) VALUES ( %s, %s, %d, %d, %s, %s)';
        $sql = $wpdb->prepare( $sql, $data['submit_time'], $data['submit_ip'], $data['submit_user_id'], $data['cf7_id'], $data['token'], $type );

        return $wpdb->query( $sql );
    }

    public function clean_up_tokens( $type ) {
        global $wpdb;

        switch ( $type ) {
            case 'acceptance':
            case 'captcha':
                // Used tokens must be kept at least as long as they are valid, otherwise they could be replayed.
                $elapsed = $this->get_token_max_age( $type ) + HOUR_IN_SECONDS;
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
     * Data for the token table.
     */
    public function get_token_db_data( $cf7_id, $token ) {

        $data = array();

        $data['submit_time'] = date_i18n( 'Y-m-d H:i:s' );
        /**
         * The IP address is not used for any check, so it is not stored (GDPR data minimization).
         * Uncomment the next line to store it again.
         */
        // $data['submit_ip'] = ( isset( $_SERVER['X_FORWARDED_FOR'] ) ) ? $_SERVER['X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
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

    /**
     * Max age of an image captcha or acceptance token in seconds, filters:
     * 'exopite_anti_spam_captcha_max_age' and 'exopite_anti_spam_acceptance_max_age'.
     * The minimum time is not checked here, use "Timestamp" for that.
     */
    public function get_token_max_age( $type ) {

        return max( MINUTE_IN_SECONDS, intval( apply_filters( 'exopite_anti_spam_' . $type . '_max_age', DAY_IN_SECONDS ) ) );

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
    public function consume_token( $token, $type ) {
        global $wpdb;

        if ( empty( $token ) || ! is_string( $token ) ) {
            return false;
        }

        // Validation may run more than once in a request (e.g. by other plugins).
        if ( in_array( $type . ':' . $token, $this->tokens_used, true ) ) {
            return true;
        }

        $lock_name = $this->lock_token( $token, $type, 5 );

        // Timeout: an other request with the same token is running right now.
        if ( false === $lock_name ) {
            return false;
        }

        $valid = ! $this->check_token( $token, $type );

        if ( $valid ) {

            $contact_form = WPCF7_ContactForm::get_current();
            $cf7_id = $contact_form ? $contact_form->id() : 0;

            $this->save_token_db( $this->get_token_db_data( $cf7_id, $token ), $type );
            $this->tokens_used[] = $type . ':' . $token;

        }

        $this->release_token_lock( $lock_name );

        if ( $valid ) {
            $this->clean_up_tokens( $type );
        }

        return $valid;
    }

    /**
     * MySQL named lock for a token. Locks belong to the DB connection,
     * so they are released automatically at the end of the request too.
     *
     * @return string|false|null  Lock name: locked, false: timeout (an other request
     *                            with the same token is running), null: locks are not supported.
     */
    public function lock_token( $token, $type, $timeout ) {
        global $wpdb;

        // Lock names are limited to 64 characters.
        $lock_name = 'eas_' . md5( $wpdb->prefix . $type . $token );
        $locked = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, %d )', $lock_name, $timeout ) );

        if ( '1' === $locked ) {
            $this->token_locks[ $lock_name ] = true;
            return $lock_name;
        }

        return ( '0' === $locked ) ? false : null;
    }

    public function release_token_lock( $lock_name ) {
        global $wpdb;

        if ( empty( $lock_name ) || ! isset( $this->token_locks[ $lock_name ] ) ) {
            return;
        }

        $wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock_name ) );
        unset( $this->token_locks[ $lock_name ] );
    }

    /**
     * Release the locks still held (timestamp token) after the submission.
     */
    public function release_token_locks() {

        foreach ( array_keys( $this->token_locks ) as $lock_name ) {
            $this->release_token_lock( $lock_name );
        }

    }

    public function check_token( $token, $type ) {
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


    public function get_acceptance_token() {

        $token_random = bin2hex( random_bytes( 32 ) );
        $submit_ip = (isset($_SERVER['X_FORWARDED_FOR'])) ? $_SERVER['X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
        $submit_time = time();
        $token_acceptance = json_encode( array( $token_random, $submit_ip, $submit_time ) );

        /**
         * Nothing is saved here (no DB request on every checkbox click),
         * the random part is stored as used on submission, see validate_easacceptance().
         */
        $token_acceptance = bin2hex( $this->crypter->encrypt( $token_acceptance, $this->get_token() ) );

        echo $token_acceptance;

        die();
    }

    /**
     * I'm not sure yet, logged user see/validate this fields:
     * - yes: more security? site admin can see it is working
     * - no: site admin can see it is working, need this extra security for logged in users?
     */
    public function is_user_logged_in() {

        // DEBUG
        return false;

        return is_user_logged_in();
    }

    /**
     * Save used token to database to avoid multiple usage.
     * Also clean out databse by delete all entries older then 31 days.
     */
    public function wpcf7_mail_sent( $contact_form ) {

        if ( $this->is_user_logged_in() ) {
            return;
        }

        // No timestamp token (timestamp is disabled for this form), nothing to save.
        if ( ! empty( $this->token ) ) {

            $this->save_token_db( $this->get_token_db_data( $contact_form->id(), $this->token ), 'sent' );

        }

        /**
         * Clean up.
         * Delete all tokens which older then 1 month.
         */
        $this->clean_up_tokens( 'sent' );

    }


    /**
     * Validate fields
     */

    public function wpcf7_validate( $result, $tags ) {

        if ( $this->is_user_logged_in() ) {
            return $result;
        }

        if ( ! $this->honeypot ) {

            // This has been already logged (if enabled)
            $result->invalidate( $tags[0], esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (11)' );

        }

        if ( $this->timeout ) {

            $this->invalidate_log( 'Your session has timed out. Please submit the form again.', false );
            $result->invalidate( $tags[0], esc_attr__( 'Your session has timed out. Please submit the form again.', 'exopite-anti-spam' ) );

        }

        return $result;

    }

    /**
     * Tell the JS to load a new timestamp (expired or already used),
     * so the visitor can submit again without reloading the page.
     */
    /**
     * Compatibility with "Conditional Fields for Contact Form 7" (CF7CF).
     *
     * If the form has hidden groups, CF7CF rebuilds the validation result on 'wpcf7_validate' (priority 2)
     * and keeps only the errors of named form tags. The fields of this plugin (image captcha, timestamp,
     * honeypot, acceptance token) are nameless tags, so their errors would be lost.
     * The errors are saved here (priority 1) and restored on 'wpcf7cf_validate'.
     */
    public function wpcf7_validate_snapshot( $result, $tags ) {

        $this->invalid_before_cf7cf = $result->get_invalid_fields();

        return $result;
    }

    /**
     * Restore the errors of this plugin removed by CF7CF, except for fields inside hidden groups.
     * The validation itself is not run again here (CF7CF calls this filter for every form,
     * also for forms without image captcha).
     */
    public function wpcf7cf_validate( $result, $tags ) {

        if ( empty( $this->invalid_before_cf7cf ) || ! is_array( $this->invalid_before_cf7cf ) ) {
            return $result;
        }

        $hidden_fields = array();
        $posted_hidden = isset( $_POST['_wpcf7cf_hidden_group_fields'] ) && is_string( $_POST['_wpcf7cf_hidden_group_fields'] ) ? json_decode( stripslashes( $_POST['_wpcf7cf_hidden_group_fields'] ) ) : array();
        if ( is_array( $posted_hidden ) ) {
            foreach ( $posted_hidden as $field ) {
                if ( is_string( $field ) ) {
                    $hidden_fields[] = preg_replace( '/\[\]$/', '', $field );
                }
            }
        }

        $own_fields = array( 'exanspsel', 'eastimestamp', 'easacceptance', $this->main->honeypot_name );

        foreach ( $own_fields as $field ) {

            if ( ! isset( $this->invalid_before_cf7cf[ $field ]['reason'] ) || in_array( $field, $hidden_fields, true ) || ! $result->is_valid( $field ) ) {
                continue;
            }

            $result->invalidate( array( 'name' => $field ), $this->invalid_before_cf7cf[ $field ]['reason'] );

        }

        return $result;
    }

    public function wpcf7_feedback_response( $response, $result ) {

        if ( $this->refresh_timestamp ) {
            $response['eas_refresh_timestamp'] = true;
        }

        return $response;
    }

    public function validate_easacceptance( $result, $tag ) {

        if ( $this->is_user_logged_in() ) {
            return $result;
        }

        $tag = new WPCF7_FormTag( $tag );
        $name = "easacceptance";

        // Same logic as in the form tag handler: no validation if the field is not rendered.
        if ( ! $this->main->fields->is_acceptance_ajaxcheck_active( $tag ) ) {
            return $result;
        }

        if ( empty( $_POST['easacceptance'] ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'acceptance ajax auth empty' );
            $result->invalidate( $tag, esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (01)' );

            return $result;
        }

        $token_acceptance = $this->crypter->decrypt( $this->request_hex2bin( $_POST['easacceptance'] ), $this->get_token() );

        if ( ! $token_acceptance ) {

            $tag->name = $name;
            $this->invalidate_log( 'acceptance data can not decrypt' );
            $result->invalidate( $tag, esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (02)' );

            return $result;
        }

        $token_acceptance = json_decode( $token_acceptance );
        $submit_ip = (isset($_SERVER['X_FORWARDED_FOR'])) ? $_SERVER['X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];

        if ( ! is_array( $token_acceptance ) || count( $token_acceptance ) < 3 ) {

            $tag->name = $name;
            $this->invalidate_log( 'acceptance data is invalid' );
            $result->invalidate( $tag, esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (02)' );

            return $result;
        }

        if ( $token_acceptance[1] != $submit_ip ) {

            $tag->name = $name;
            $this->invalidate_log( 'acceptance IP mismatch ' . $submit_ip . ' != ' . $token_acceptance[1], false );
            $result->invalidate( $tag, esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (03)' );

            return $result;
        }

        // The token is requested on checkbox click, the minimum time is checked by "Timestamp".
        if ( ( time() - intval( $token_acceptance[2] ) ) > $this->get_token_max_age( 'acceptance' ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'acceptance token expired, created: ' . wp_date( 'Y-m-d H:i:s', intval( $token_acceptance[2] ) ), false );
            $result->invalidate( $tag, esc_attr__( 'Your session has timed out. Please submit the form again.', 'exopite-anti-spam' ) );

            return $result;
        }

        // Single use, the JS requests a new token after an unsuccessful submission.
        if ( ! $this->consume_token( $token_acceptance[0], 'acceptance' ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'acceptance token already used' );
            $result->invalidate( $tag, esc_attr__( "Token error.", 'exopite-anti-spam' ) . ' (13)' );

            return $result;
        }

        return $result;

    }

    public function validate_easimagecaptcha( $result, $tag ) {

        if ( $this->is_user_logged_in() ) {
            return $result;
        }

        $tag = new WPCF7_FormTag( $tag );

        $name = "exanspsel";

        if ( empty( $_POST['exanspsel'] ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'Please make your selection.', false );
            $result->invalidate( $tag, esc_attr__( 'Please make your selection.', 'exopite-anti-spam' ) );

            return $result;
        }

        if ( ! isset( $_POST['exanspsel-auth'] ) || empty( $_POST['exanspsel-auth'] ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'captcha auth empty' );
            $result->invalidate( $tag, esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (04)' );

            return $result;
        }

        if ( ! isset( $_POST['exanspsel'] ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'Please select the correct icon(s).' );
            $result->invalidate( $tag, esc_attr__( 'Please select the correct icon(s).', 'exopite-anti-spam' ) );

            return $result;
        }

        // Normally an array (checkboxes), but a manipulated request could send a string.
        $selected = (array) $_POST['exanspsel'];
        $auth = $_POST['exanspsel-auth'];

        $image_captcha_data_decrypted = $this->crypter->decrypt( $this->request_hex2bin( $auth ), $this->get_token() );

        if ( ! $image_captcha_data_decrypted ) {

            $tag->name = $name;
            $this->invalidate_log( 'captcha data can not decrypt' );
            $result->invalidate( $tag, esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (05)' );

            return $result;
        }

        $image_captcha_data = explode( '|', $image_captcha_data_decrypted );

        if ( ! is_array( $image_captcha_data ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'captcha data is not an array' );
            $result->invalidate( $tag, esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (06)' );

            return $result;
        }

        $image_captcha_token_once = $image_captcha_data[0];
        $image_captcha_time = isset( $image_captcha_data[1] ) ? intval( $image_captcha_data[1] ) : 0;
        $image_captcha_selected = isset( $image_captcha_data[2] ) ? json_decode( $image_captcha_data[2] ) : null;

        if ( ( time() - $image_captcha_time ) > $this->get_token_max_age( 'captcha' ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'captcha token expired, created: ' . wp_date( 'Y-m-d H:i:s', $image_captcha_time ), false );
            $result->invalidate( $tag, esc_attr__( 'Your session has timed out. Please submit the form again.', 'exopite-anti-spam' ) );

            return $result;
        }

        /**
         * Single use token: stored on submission, right or wrong answer.
         * The captcha is reloaded via AJAX after an unsuccessful submission.
         */
        if ( ! $this->consume_token( $image_captcha_token_once, 'captcha' ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'captcha token already used' );
            $result->invalidate( $tag, esc_attr__( "Token error.", 'exopite-anti-spam' ) . ' (12)' );

            return $result;
        }

        /**
         * With "choose:1" the answer is a single integer (array_rand), it can be 0 too,
         * so check for null/false (invalid JSON) and not for empty.
         */
        if ( null === $image_captcha_selected || false === $image_captcha_selected ) {

            $tag->name = $name;
            $this->invalidate_log( 'captcha selected is invalid' );
            $result->invalidate( $tag, esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (07)' );

            return $result;
        }

        $image_captcha_selected = (array) $image_captcha_selected;

        if ( ! $this->array_equal( $image_captcha_selected, $selected ) ) {

            $tag->name = $name;
            $invalidate_text = sprintf( esc_attr__( 'Please select the correct %s', 'exopite-anti-spam' ), $this->get_icons_amount_translation( count( $image_captcha_selected ) ) ) . '.';
            $this->invalidate_log( 'Please select the correct (' . count( $image_captcha_selected ) . ') icon(s).' );
            $result->invalidate( $tag, $invalidate_text ) ;

            return $result;
        }

        return $result;

    }

    public function wpcf7_validate_eastimestamp( $result, $tag ) {

        if ( $this->is_user_logged_in() ) {
            return $result;
        }

        // Same logic as in the form tag handler: no validation if timestamp is disabled for this form.
        $options = $this->get_cf7_meta();
        $timestamp_active = isset( $options['timestamp'] ) ? ( $options['timestamp'] === 'yes' ) : apply_filters( 'exopite_enable_timestamp', false );
        $timestamp_active = apply_filters( 'exopite_anti_spam_timestamp', $timestamp_active, $tag, WPCF7_ContactForm::get_current() );

        if ( ! $timestamp_active ) {
            return $result;
        }

        $name  = 'eastimestamp';
        $value = isset( $_POST[$name] ) ? $_POST[$name] : '';

        if ( empty( $value ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'timestamp empty' );
            $result->invalidate( $tag, esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (08)' );
            return $result;


        }

        $decrypted_data = (string) $this->crypter->decrypt( $this->request_hex2bin( $value ), $this->get_token() );
        $this->token = substr( $decrypted_data, 0, 64 );
        $timestamp_decrypted = substr( $decrypted_data, 64);

         if ( empty( $timestamp_decrypted ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'timestamp data is invalid' );
            $result->invalidate( $tag, esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (09)' );

            return $result;

        }

        /**
         * The timestamp token is stored as used only after the mail was sent (wpcf7_mail_sent),
         * so the lock is held until the end of the submission (wpcf7_submit).
         * Parallel requests with the same token wait, then they find the token as used.
         */
        if ( false === $this->lock_token( $this->token, 'sent', 30 ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'token is in use by an other request' );
            $result->invalidate( $tag, esc_attr__( "Token error.", 'exopite-anti-spam' ) . ' (10)' );
            $this->refresh_timestamp = true;

            return $result;
        }

        if ( $this->check_token( $this->token, 'sent' ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'token already exist' );
            $result->invalidate( $tag, esc_attr__( "Token error.", 'exopite-anti-spam' ) . ' (10)' );
            $this->refresh_timestamp = true;

            return $result;
        }

        if ( ! $this->check_elapsed( $timestamp_decrypted ) ) {

            $elapsed_seconds = ( time() - $timestamp_decrypted );

            if ( $elapsed_seconds > ( $this->max_time ) ) {

                $tag->name = $name;
                $this->invalidate_log( 'Timeout, timestamp from user is bigger (' . $elapsed_seconds . ' sec), then the max time (' . $this->max_time . ' sec) to send.', false );
                $result->invalidate( $tag, esc_attr__( 'Your session has timed out. Please submit the form again.', 'exopite-anti-spam' ) );
                $this->timeout = true;
                $this->refresh_timestamp = true;

            }

            if ( $elapsed_seconds < $this->min_time ) {

                $tag->name = $name;
                $this->invalidate_log( 'Timestamp from user is smaller (' . $elapsed_seconds . ' sec), then the min time (' . $this->min_time . ' sec) to send.' );
                $result->invalidate( $tag, esc_attr__( 'The form was sent too quickly. Please wait a few seconds before trying again!', 'exopite-anti-spam' ) );

            }

        }

        return $result;
    }

    /**
     * List of Dirty, Naughty, Obscene, and Otherwise Bad Words
     *
     * @link https://github.com/LDNOOBW/List-of-Dirty-Naughty-Obscene-and-Otherwise-Bad-Words
     * @link https://www.textfixer.com/tools/remove-duplicate-lines.php
     * @link https://raw.githubusercontent.com/RobertJGabriel/Google-profanity-words/master/list.txt
     */
    public function validate_text_textarea_bad_words( $result, $tag ) {

        if ( $this->is_user_logged_in() ) {
            return $result;
        }

        // Default: disabled if the form settings were never saved (same as honeypot and timestamp).
        $options = $this->get_cf7_meta();
        $badwords_active = isset( $options['badwords'] ) ? ( $options['badwords'] === 'yes' ) : apply_filters( 'exopite_enable_badwords', false );

        if ( ! $badwords_active ) {
            return $result;
        }

        $words = $this->get_words();

        $instance = WPCF7_ContactForm::get_current();
        $words = apply_filters( 'exopite_anti_spam_bad_words', $words, $tag, $instance );

        $custom_words = $this->get_custom_words();

        if ( empty( $words ) && empty( $custom_words ) ) {
            return $result;
        }

        // Missing field (e.g. in a hidden conditional group) or manipulated value (array).
        $raw = isset( $_POST[ $tag->name ] ) ? $_POST[ $tag->name ] : '';
        if ( is_array( $raw ) ) {
            $raw = implode( ' ', array_filter( $raw, 'is_scalar' ) );
        }
        $raw = wp_unslash( (string) $raw );

        if ( $tag->type == 'textarea' || $tag->type == 'textarea*' ) {
            $content = sanitize_textarea_field( $raw );
        } else {
            $content = sanitize_text_field( $raw );
        }

        /**
         * Prepare field value.
         * Convert to lowercase and convert all whitespane (new line, multiple spaces and tabs) to single space.
         *
         * @link https://stackoverflow.com/questions/2109325/how-do-i-strip-all-spaces-out-of-a-string-in-php/2109339#2109339
         */
        $content = $this->to_lower( $content );
        $content = preg_replace( '/\s+/', ' ', $content );

        /**
         * Own words are plain text: quoted, and UTF-8 bytes (umlauts) count as word characters,
         * so "ärger" is found as a word, but not inside "verärgert".
         */
        foreach ( $custom_words as $word ) {

            if ( preg_match( '/(?<![\w\x80-\xff])' . preg_quote( $word, '/' ) . '(?![\w\x80-\xff])/i', $content ) ) {

                $this->invalidate_log( 'You are using some banned words. (own list)' );
                $result->invalidate( $tag, esc_attr__( "You are using some banned words.", 'exopite-anti-spam' ) );

                return $result;
            }

        }

        foreach ( (array) $words as $word ) {

            /**
             * Check if a string contains a specific word?
             * Not a substring.
             * @link https://stackoverflow.com/questions/4366730/how-do-i-check-if-a-string-contains-a-specific-word/4366744#4366744
             */
            if( preg_match( "/\b{$word}\b/i", $content ) ) {

                $this->invalidate_log( 'You are using some banned words.' );
                $result->invalidate( $tag, esc_attr__( "You are using some banned words.", 'exopite-anti-spam' ) );

                return $result;
            }

        }

        return $result;

    }

    public function validate_text_email_blacklist( $result, $tag ) {

        if ( $this->is_user_logged_in() ) {
            return $result;
        }

        $tag = new WPCF7_FormTag( $tag );
        $email = isset( $_POST[ $tag->name ] ) && is_string( $_POST[ $tag->name ] ) ? sanitize_text_field( trim( wp_unslash( $_POST[ $tag->name ] ) ) ) : '';

        // Empty field: optional fields are valid, required fields are already handled by CF7.
        if ( '' === $email ) {
            return $result;
        }

        $instance = WPCF7_ContactForm::get_current();

        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            $this->invalidate_log( 'invalid_email', false );
            $result->invalidate( $tag, wpcf7_get_message( 'invalid_email' ) );
            return $result;
        }

        $options = get_option( $this->plugin_name );

        if (
            empty( $options ) ||
            ! isset( $options['list_of_block_domains'] ) ||
            ! isset( $options['list_of_block_emails'] ) ||
            ! isset( $options['display_error_message_email'] ) ||
            ! isset( $options['display_error_message_domain'] )
        ) {
            return $result;
        }

        $list_of_block_domains = esc_attr( $options['list_of_block_domains'] );
        $list_of_block_emails = esc_attr( $options['list_of_block_emails'] );
        $display_error_message_email = esc_attr( $options['display_error_message_email'] );
        $display_error_message_domain = esc_attr( $options['display_error_message_domain'] );

        $blacklisted_domains = preg_replace( '/\s+/', '', $list_of_block_domains );
        $blacklisted_domains = explode( ",", $blacklisted_domains );

        $blacklisted_domains = apply_filters( 'exopite_anti_spam_blacklisted_domains', $blacklisted_domains, $tag, $result, $instance );
        $blacklisted_domains = array_map( 'strtolower', (array) $blacklisted_domains );

        // E-mail addresses and domains are compared case-insensitive.
        $email = strtolower( $email );
        $domain = substr( strrchr( $email, '@' ), 1 );

        if ( in_array( $domain, $blacklisted_domains ) ) {

            $domain_error_message = esc_attr__( 'Your domain is blocked.', 'exopite-anti-spam' );
            if ( ! empty( $display_error_message_domain ) ) {
                $domain_error_message = $display_error_message_domain;
            }
            $this->invalidate_log( $domain_error_message );
            $result->invalidate( $tag, $domain_error_message );
        }

        $blacklisted_emails  = preg_replace( '/\s+/', '', $list_of_block_emails );
        $blacklisted_emails  = explode( ",", $blacklisted_emails );

        $blacklisted_emails = apply_filters( 'exopite_anti_spam_blacklisted_emails', $blacklisted_emails, $tag, $result, $instance );
        $blacklisted_emails = array_map( 'strtolower', (array) $blacklisted_emails );

        if ( in_array( $email, $blacklisted_emails ) ) {

            $email_error_message = esc_attr__( 'Your email is blocked.', 'exopite-anti-spam' );
            if ( ! empty( $display_error_message_email ) ) {
                $email_error_message = $display_error_message_email;
            }
            $this->invalidate_log( $email_error_message );
            $result->invalidate( $tag, $email_error_message );

        }

        return $result;
    }

    public function wpcf7_validate_honeypot( $result, $tag ) {

        if ( $this->is_user_logged_in() ) {
            return $result;
        }

        $options = $this->get_cf7_meta();

        // Default: disabled if the form settings were never saved,
        // can be changed with the 'exopite_enable_honeypot' filter.
        $honeypot_active = isset( $options['honeypot'] ) ? ( $options['honeypot'] === 'yes' ) : apply_filters( 'exopite_enable_honeypot', false );

        if ( ! $honeypot_active ) {
            return $result;
        }

        $name  = $this->main->honeypot_name;
        // Any non-empty value (also an array) means the field was filled.
        $value = isset( $_POST[$name] ) ? $_POST[$name] : '';

        if ( ! empty( $value ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'honeypot is not empty' );

            /**
             * This field/tag is not visible, so the error message not visible too,
             * so we need to add the error message to the first tag, in the wpcf7_validate hook.
             */
            $result->invalidate( $tag, esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (11)' );
            $this->honeypot = false;

        }

        return $result;
    }

    /**
     * To cache spam logs
     */
    public function wpcf7_submit( $contact_form, $result ) {

        if ( $this->is_logging() ) {

            $submission = WPCF7_Submission::get_instance();
            $spam_log = $submission->get_spam_log();

            if ( ! empty( $spam_log ) ) {
                $ip_address = new RemoteAddress();

                Exopite_Anti_Spam_Logger::write( 'wpcf7_submit_spam', PHP_EOL . wp_date( 'Y-m-d H:i:s' ) . ' - ' . $ip_address->getIpAddress() . PHP_EOL .
                var_export( $contact_form->id(), true ) . PHP_EOL .
                var_export( $spam_log, true ) . PHP_EOL .
                var_export( $this->mask_log_data( $_POST ), true ) . PHP_EOL .
                var_export( $result, true ) . PHP_EOL .
                '---' . PHP_EOL . PHP_EOL );
            }

        }

    }



}
