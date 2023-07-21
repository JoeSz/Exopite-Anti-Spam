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

    public $cf7_meta = false;

    public $min_time = 2;
    public $max_time = 600;

    public $logging = true;
    public $honeypot = true;

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
        return ( array_diff( $a, $b ) == array_diff( $b, $a ) );
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

        if ( $this->cf7_meta ) {
            return $this->cf7_meta;
        }

        $wpcf7 = WPCF7_ContactForm::get_current();

        if ( ! $wpcf7 ) {
            return false;
        }

        $cf7_meta = get_post_meta( $wpcf7->id() );

        if ( isset( $cf7_meta['exopite-anti-spam'][0] ) ) {

            $this->cf7_meta = maybe_unserialize( $cf7_meta['exopite-anti-spam'][0] );

        }

        return $this->cf7_meta;

    }

    public function invalidate_log( $reason ) {

        if ( ! empty( $reason ) && $this->logging ) {

            if ( $this->logging ) {

                $ip_address = new RemoteAddress();

                file_put_contents( EXOPITE_ANTI_SPAM_PATH . '/logs/wpcf7_invalidate' . date( '_Y-m-d' ) . '.log', PHP_EOL . date( 'Y-m-d H:i:s' ) . ' - ' . $ip_address->getIpAddress() . ' - ' . $reason . PHP_EOL . var_export( $_POST, true ) . PHP_EOL . '---' . PHP_EOL , FILE_APPEND );

            }

            /**
             * Leaving a spam log.
             * @link https://contactform7.com/2020/07/18/custom-spam-filtering/
             */
            $submission = WPCF7_Submission::get_instance();

            $submission->add_spam_log( array(
                'agent' => $this->plugin_name,
                'reason' => $reason,
            ) );

        }

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

        // DEBUG
        file_put_contents( EXOPITE_ANTI_SPAM_PATH . '/logs/options-test-' . date( '_Y-m-d' ) . '.txt', PHP_EOL . date( 'Y-m-d H:i:s' ) . ' - ' . var_export( $options, true ) . PHP_EOL, FILE_APPEND );
        $elapsed_seconds = ( time() - $time );

        if ( $elapsed_seconds < $this->min_time || $elapsed_seconds > ( $this->max_time ) ) {
            return false;
        }

        return true;
    }

    public function get_words() {

        if ( ! $this->words ) {

            $fn = EXOPITE_ANTI_SPAM_PATH . '/lists/spamwords.txt';

            if ( file_exists( $fn ) ) {
                $lines = file_get_contents( EXOPITE_ANTI_SPAM_PATH . '/lists/spamwords.txt' );
                // $list = explode( PHP_EOL, $lines );
                $list = preg_split( '/\r\n|\r|\n/', $lines );
                $this->words = array_filter( $list );
            } else {
                file_put_contents( EXOPITE_ANTI_SPAM_PATH . '/logs/wpcf7_errors.log', PHP_EOL . date( 'Y-m-d H:i:s' ) . ' - not exists: ' . $fn . PHP_EOL, FILE_APPEND );
                return array();
            }

        }

        return $this->words;
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

        $elapsed = '';
        switch ( $type ) {
            case 'acceptance':
                $elapsed = '30 MINUTE';
                break;
            case 'sent':
                $elapsed = '31 DAY';
                break;

        }

        $sql = 'DELETE FROM ' . $wpdb->prefix . "eas_cf7_email_tokens WHERE `timestamp` < DATE_SUB(NOW(), INTERVAL " . $elapsed . ") AND `type` = %s;";
        $sql = $wpdb->prepare( $sql, $type );
        return $wpdb->query( $sql );
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
        // $submit_time = date_i18n('Y-m-d H:i:s');
        $token_acceptance = json_encode( array( $token_random, $submit_ip, $submit_time ) );

        $token_acceptance = bin2hex( $this->crypter->encrypt( $token_acceptance, $this->get_token() ) );

        /**
         * Could save random token to db.
         * But why make a db requests.
         *
         * - generate token
         * - add to datebase
         * - clean up old entries in db (older then 30 min)
         */
        // //time
        // $posted_data['submit_time'] = date_i18n('Y-m-d H:i:s');
        // // $posted_data['submit_time'] = date_i18n('Y-m-d H:i:s', $submission->get_meta('eastimestamp'));
        // //ip
        // $posted_data['submit_ip'] = (isset($_SERVER['X_FORWARDED_FOR'])) ? $_SERVER['X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
        // //user id
        // $posted_data['submit_user_id'] = 0;
        // if (function_exists('is_user_logged_in') && is_user_logged_in()) {
        //     $current_user = wp_get_current_user(); // WP_User
        //     $posted_data['submit_user_id'] = $current_user->ID;
        // }
        // $posted_data['cf7_id'] = 0;
        // $posted_data['token'] = $token_acceptance;
        // $this->save_token_db( $posted_data, 'acceptance' );
        // $this->clean_up_tokens( 'acceptance' );

        /**
         * Could use transient, but why make a db requests.
         */
        // set_transient( 'esc_token_acceptance_set_transient', $token_acceptance, DAY_IN_SECONDS );

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

        global $wpdb;

        $submission = WPCF7_Submission::get_instance();

        $posted_data = array();

        //time
        $posted_data['submit_time'] = date_i18n('Y-m-d H:i:s');
        //ip
        $posted_data['submit_ip'] = (isset($_SERVER['X_FORWARDED_FOR'])) ? $_SERVER['X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
        //user id
        $posted_data['submit_user_id'] = 0;
        if (function_exists('is_user_logged_in') && is_user_logged_in()) {
            $current_user = wp_get_current_user(); // WP_User
            $posted_data['submit_user_id'] = $current_user->ID;
        }

        $posted_data['cf7_id'] = $contact_form->id();
        $posted_data['token'] = $this->token;

        $this->save_token_db( $posted_data, 'sent' );

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

            $this->invalidate_log( 'Your session has timed out. Please refresh and try again.' );
            $result->invalidate( $tags[0], esc_attr__( 'Your session has timed out. Please refresh and try again.', 'exopite-anti-spam' ) );

        }

        return $result;

    }

    public function validate_easacceptance( $result, $tag ) {

        if ( $this->is_user_logged_in() ) {
            return $result;
        }

        $tag = new WPCF7_FormTag( $tag );
        $name = "easacceptance";

        if ( empty( $_POST['easacceptance'] ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'acceptance ajax auth empty' );
            $result->invalidate( $tag, esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (01)' );

            return $result;
        }

        $token_acceptance = $this->crypter->decrypt( hex2bin( $_POST['easacceptance'] ), $this->get_token() );

        if ( ! $token_acceptance ) {

            $tag->name = $name;
            $this->invalidate_log( 'acceptance data can not decrypt' );
            $result->invalidate( $tag, esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (02)' );

            return $result;
        }

        $token_acceptance = json_decode( $token_acceptance );
        $submit_ip = (isset($_SERVER['X_FORWARDED_FOR'])) ? $_SERVER['X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];

        /**
         * Do not check timestamp for image captcha,
         * use "Timestamp" to check elapsed time.
         */
        // if ( ! $this->check_elapsed( $token_acceptance[2] ) ) {

        //     $elapsed_seconds = ( time() - $token_acceptance[2] );

        //     if ( $elapsed_seconds > ( $this->max_time ) ) {

        //         $tag->name = $name;
        //         $this->invalidate_log( 'Timeout. elapsed_seconds: ' . $elapsed_seconds . ', max time: ' . $this->max_time );
        //         $result->invalidate( $tag, esc_attr__( 'Your session has timed out. Please refresh and try again.', 'exopite-anti-spam' ) );
        //         $this->timeout = true;

        //     }

        //     if ( $elapsed_seconds < $this->min_time ) {
        //         $tag->name = $name;
        //         $this->invalidate_log( 'acceptance token timestamp ' . $elapsed_seconds . ' sec is smaller then ' . $this->min_time . ' sec' );
        //         $result->invalidate( $tag, esc_attr__( 'Timestamp error!', 'exopite-anti-spam' ) );

        //         return $result;
        //     }

        // }

        if ( $token_acceptance[1] != $submit_ip ) {

            $tag->name = $name;
            $this->invalidate_log( 'acceptance IP mismatch ' . $submit_ip . ' != ' . $token_acceptance[1] );
            $result->invalidate( $tag, esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (03)' );

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
            $this->invalidate_log( 'Please make your selection.' );
            $result->invalidate( $tag, esc_attr__( 'Please make your selection.', 'exopite-anti-spam' ) );

            // $result->invalidate( $tag, wpcf7_get_message( 'quiz_answer_not_correct' ) );
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

        $selected = $_POST['exanspsel'];
        $auth = $_POST['exanspsel-auth'];

        $image_captcha_data_decrypted = $this->crypter->decrypt( hex2bin( $auth ), $this->get_token() );

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
        $image_captcha_time = $image_captcha_data[1];
        $image_captcha_selected = json_decode( $image_captcha_data[2] );

        /**
         * Do not check timestamp for image captcha,
         * use "Timestamp" to check elapsed time.
         */
        /*
        if ( ! $this->check_elapsed( $image_captcha_time ) ) {

            $elapsed_seconds = ( time() - $image_captcha_time );

            if ( $elapsed_seconds > ( $this->max_time ) ) {

                if ( isset( $tag->name ) ) {
                    $tag->name = $name;
                }
                $result->invalidate( $tag, esc_attr__( 'Timeout.', 'exopite-anti-spam' ) );
                $this->timeout = true;

            }

            if ( $elapsed_seconds < $this->min_time ) {

                if ( isset( $tag->name ) ) {
                    $tag->name = $name;
                }
                $result->invalidate( $tag, esc_attr__( 'Timestamp error!', 'exopite-anti-spam' ) );

            }

        }
        */

        if ( $image_captcha_selected && ! $this->array_equal( $image_captcha_selected, $selected ) ) {

            $tag->name = $name;
            $invalidate_text = sprintf( esc_attr__( 'Please select the correct %s', 'exopite-anti-spam' ), $this->get_icons_amount_translation( count( $image_captcha_selected ) ) ) . '.';
            $this->invalidate_log( 'Please select the correct (' . count( $image_captcha_selected ) . ') icon(s).' );
            $result->invalidate( $tag, $invalidate_text ) ;
            // $result->invalidate( $tag, wpcf7_get_message( 'quiz_answer_not_correct' ) );

            return $result;
        }

        if ( ! $image_captcha_selected ) {

            $tag->name = $name;
            $this->invalidate_log( 'captcha selected is invalid' );
            $result->invalidate( $tag, esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (07)' );

            // no need return $result, this is the last if
        }

        return $result;

    }

    public function wpcf7_validate_eastimestamp( $result, $tag ) {

        if ( $this->is_user_logged_in() ) {
            return $result;
        }

        $name  = 'eastimestamp';
        $value = isset( $_POST[$name] ) ? esc_attr( $_POST[$name] )  : '';

        if ( empty( $value ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'timestamp empty' );
            $result->invalidate( $tag, esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (08)' );
            return $result;


        }

        $decrypted_data = $this->crypter->decrypt( hex2bin( $value ), $this->get_token() );
        $this->token = substr( $decrypted_data, 0, 64 );
        $timestamp_decrypted = substr( $decrypted_data, 64);

         if ( empty( $timestamp_decrypted ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'timestamp data is invalid' );
            $result->invalidate( $tag, esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (09)' );

            return $result;

        }

        if ( $this->check_token( $this->token, 'sent' ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'token already exist' );
            $result->invalidate( $tag, esc_attr__( "Token error.", 'exopite-anti-spam' ) . ' (10)' );

            return $result;
        }

        if ( ! $this->check_elapsed( $timestamp_decrypted ) ) {

            $elapsed_seconds = ( time() - $timestamp_decrypted );

            if ( $elapsed_seconds > ( $this->max_time ) ) {

                $tag->name = $name;
                $this->invalidate_log( 'Timeout, timestamp from user is bigger (' . $elapsed_seconds . ' sec), then the max time (' . $this->max_time . ' sec) to send.' );
                $result->invalidate( $tag, esc_attr__( 'Your session has timed out. Please refresh and try again.', 'exopite-anti-spam' ) );
                $this->timeout = true;

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

        $options = $this->get_cf7_meta();
        if ( ! isset( $options ) || $options && $options['badwords'] === 'no' ) {
            return $result;
        }

        $words = $this->get_words();

        $instance = WPCF7_ContactForm::get_current();
        $words = apply_filters( 'exopite_anti_spam_bad_words', $words, $tag, $instance );

        if ( ! $words || empty( $words ) ) {
            return $result;
        }

        $content = '';

        if ( $tag->type == 'textarea' || $tag->type == 'textarea*' ) {
            $content = sanitize_textarea_field( $_POST[ $tag->name ] );
        } else {
            $content = sanitize_text_field( $_POST[ $tag->name ] );
        }

        /**
         * Prepare field value.
         * Convert to lowercase and convert all whitespane (new line, multiple spaces and tabs) to single space.
         *
         * @link https://stackoverflow.com/questions/2109325/how-do-i-strip-all-spaces-out-of-a-string-in-php/2109339#2109339
         */
        $content = strtolower( $content );
        $content = preg_replace( '/\s+/', ' ', $content );

        foreach ( $words as $word ) {

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
        $email = sanitize_text_field( trim( $_POST[ $tag->name ] ) );

        $instance = WPCF7_ContactForm::get_current();

        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            $this->invalidate_log( 'invalid_email' );
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

        $domain = explode( '@', $email );
        $domain = $domain[1];

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
        if ( ! isset( $options ) || $options && $options['honeypot'] === 'no' ) {
            return $result;
        }

        $name  = $this->main->honeypot_name;
        $value = isset( $_POST[$name] ) ? esc_attr( $_POST[$name] )  : '';

        if ( ! empty( $value ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'honeypot is not empty' );

            /**
             * This field/tag is not visible, so the error message not visible too,
             * so we need to add the error message to the first tag, in the wpcf7_validate hook.
             */
            // $result->invalidate( $tag, esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (11)' );
            $this->honeypot = false;

        }

        return $result;
    }

    /**
     * To cache spam logs
     */
    public function wpcf7_submit( $contact_form, $result ) {

        // only fr HR4YOU

        $submission = WPCF7_Submission::get_instance();
        $spam_log = $submission->get_spam_log();

        $ip_address = new RemoteAddress();

        file_put_contents( EXOPITE_ANTI_SPAM_PATH . '/logs/wpcf7_submit_spam' . date( '_Y-m-d' ) . '.log', PHP_EOL . date( 'Y-m-d H:i:s' ) . ' - ' . $ip_address->getIpAddress() . PHP_EOL .
        var_export( $contact_form->id, true ) . PHP_EOL .
        var_export( $spam_log, true ) . PHP_EOL .
        var_export( $_POST, true ) . PHP_EOL .
        var_export( $result, true ) . PHP_EOL .
        '---' . PHP_EOL . PHP_EOL , FILE_APPEND );

        /*
        if ( $this->logging ) {

            $submission = WPCF7_Submission::get_instance();
            $spam_log = $submission->get_spam_log();

            if ( ! empty( $spam_log ) ) {
                $ip_address = new RemoteAddress();

                file_put_contents( EXOPITE_ANTI_SPAM_PATH . '/logs/wpcf7_submit_spam' . date( '_Y-m-d' ) . '.log', PHP_EOL . date( 'Y-m-d H:i:s' ) . ' - ' . $ip_address->getIpAddress() . PHP_EOL .
                var_export( $contact_form->id, true ) . PHP_EOL .
                var_export( $spam_log, true ) . PHP_EOL .
                var_export( $_POST, true ) . PHP_EOL .
                var_export( $result, true ) . PHP_EOL .
                '---' . PHP_EOL . PHP_EOL , FILE_APPEND );
            }

        }
        */

    }



}
