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

    public $min_time = 5;
    public $max_time = 300;

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

    public function spam_filter( $spam ){
        if( $this->spam ) return true;
        return $spam;
    }

    public function log( $infos, $log_name ) {

        if ( ! empty( $infos ) ) {

            $ip_address = new RemoteAddress();

            file_put_contents( EXOPITE_ANTI_SPAM_PATH . '/logs/anti-spam' . $log_name . date( '_Y-m-d' ) . '.txt', PHP_EOL . date( 'Y-m-d H:i:s' ) . ' - ' . $ip_address->getIpAddress() .  PHP_EOL . var_export( $infos, true ) . PHP_EOL, FILE_APPEND );
        }

    }

    public function mark_as_spam( $result, $reason = '' ) {

        $this->spam = true;
        add_filter( 'wpcf7_spam', array( $this, 'spam_filter' ) );

        $ip_address = new RemoteAddress();

        if ( ! empty( $reason ) ) {
            file_put_contents( EXOPITE_ANTI_SPAM_PATH . '/logs/wpcf7_validate_as_spam.txt', PHP_EOL . date( 'Y-m-d H:i:s' ) . ' - ' . $ip_address->getIpAddress() . ' - ' . $reason . PHP_EOL . var_export( $_POST, true ) . PHP_EOL, FILE_APPEND );
        }

        return $result;

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
                file_put_contents( EXOPITE_ANTI_SPAM_PATH . '/logs/wpcf7_errors.txt', PHP_EOL . date( 'Y-m-d H:i:s' ) . ' - not exists: ' . $fn . PHP_EOL, FILE_APPEND );
                return array();
            }

        }

        return $this->words;
    }

    public function generate_image_captcha_title( $seleted_titles ) {

        return implode( ' ' . esc_attr__( 'and', 'exopite-anti-spam' ) . ' ', $seleted_titles );
        // return strtolower( implode( ' ' . esc_attr__( 'and', 'exopite-anti-spam' ) . ' ', $seleted_titles ) );

    }

    public function get_timestamp_value() {

        $time = time();

        $token = bin2hex( random_bytes( 32 ) );

        $timestamp_encrypted = bin2hex( $this->crypter->encrypt( $token . $time, $this->get_token() ) );

        return $timestamp_encrypted;
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
     * AJAX function
     */

    public function reload_cf7_fields_ajax() {

        $ret = array();

        if ( isset( $_POST['timestamp'] ) && ! empty( $_POST['timestamp'] ) ) {
            $ret['timestamp'] = $this->get_timestamp_value();
        }

        if ( isset( $_POST['exanspselAuth'] ) && ! empty( $_POST['exanspselAuth'] ) ) {
            $ret['exanspsel'] = $this->get_image_captcha_html_ajax( true );
        }

        echo json_encode( $ret );

        die();
    }

    public function get_image_captcha_html_ajax( $return = false ) {

        $auth = $_POST['exanspselAuth'];

        $icons_amount = 5;
        $selected_amount = 2;

        try {

            $image_captcha_data_decrypted = $this->crypter->decrypt( hex2bin( $auth ), $this->get_token() );
            $image_captcha_data = explode( '|', $image_captcha_data_decrypted );

            $icons_amount = json_decode( $image_captcha_data[3] );
            $selected_amount = json_decode( $image_captcha_data[4] );

        } catch (Exception $e) {

        }

        if ( $return ) {
            return $this->get_image_captcha_html( $icons_amount, $selected_amount, false );
        }

        echo $this->get_image_captcha_html( $icons_amount, $selected_amount, false );

        die();
    }

    public function get_image_captcha_html( $icons_amount, $selected_amount, $wrapper = true ) {

        /**
         * In AJAX call this will be false.
         */
        $options = $this->get_cf7_meta();
        $ajaxload = false;
        if ( $options && $options['ajaxload'] === 'yes' ) {
            $ajaxload = true;
        }

        $instance = WPCF7_ContactForm::get_current();
        $ajaxload = apply_filters( 'exopite_anti_spam_ajaxload', $ajaxload, $instance );

        $icons = new Exopite_Anti_Spam_Icons();
        $choices = $icons->get_icons( $icons_amount );

        // $human = rand( 0, ( count( $choices ) - 1 ) );

        $keys = array_keys( $choices );

        $range = range( 0, ( count( $choices ) - 1 ) );

        $selcted_keys = array_rand( $range, $selected_amount );

        $token_once = bin2hex( random_bytes( 32 ) );

        $to_encrypt = $token_once . '|' . time() . '|' . json_encode( $selcted_keys ) . '|' . $icons_amount . '|' . $selected_amount;

        $selected_keys_encrypted = bin2hex( $this->crypter->encrypt( $to_encrypt, $this->get_token() ) );

        $seleted_titles = array();

        /**
         * array_rand does not return array, if selected_amount is 1.
         */
        if ( is_array( $selcted_keys ) ) {

            foreach ( $selcted_keys as $item ) {
                $seleted_titles[] = $keys[$item];
            }

        } else {
            $seleted_titles[] = $keys[$selcted_keys];
        }

        $output = '<span class="eas-image-selector"><span class="eas-image-selector-title">';

        if ( $options && $options['ajaxload'] === 'yes' && ! isset( $_POST['action'] ) ) {
            $inner = '<span class="exanspsel-ajax-loading">' . esc_attr__( 'Loading...', 'exopite-anti-spam' ) . '</span>';
        } else {
            $inner = esc_attr__( 'Please select ', 'exopite-anti-spam' );

            $inner .= $this->generate_image_captcha_title( $seleted_titles );
            $inner .= '</span>';
            $inner .= '<span class="eas-image-selector-images">';

            $i = 0;
            foreach ( $choices as $title => $image ) {
                $inner .= '<label><input type="checkbox" name="exanspsel[]" value="'. $i .'" />'. $image .'</label>';
                $i++;
            }
        }

        $output .= $inner;

        $output .= '</span></span>';
        // $output .= '<input type="text" name="exanspsel-auth" class="exanspsel-auth" value="' . $selected_keys_encrypted . '" autocomplete="off" tabindex="-1">';
        $output .= '<input type="hidden" name="exanspsel-auth" class="exanspsel-auth" value="' . $selected_keys_encrypted . '" autocomplete="off" tabindex="-1">';

        $error = '';

        $submission = WPCF7_Submission::get_instance();
        if ( $submission ) {
            $invalid_fields = $submission->get_invalid_fields();
            if ( isset( $invalid_fields['exanspsel']['reason'] ) ) {
                $error = '<span role="alert" class="wpcf7-not-valid-tip">' . $invalid_fields['exanspsel']['reason'] . '</span>';
            }
        }

        if ( $wrapper ) {
            return '<span class="wpcf7-form-control-wrap exanspsel"><span class="wpcf7-form-control wpcf7-checkbox">' . $output . $error . '</span></span>';
        } else {
            return $output;
        }

    }


    /**
     * Create fields.
     */

    public function wpcf7_init() {

        wpcf7_add_form_tag( array( $this->main->honeypot_name ), array( $this, 'wpcf7_honeypot_form_tag_handler' ), array( 'name-attr' => true ) );
        wpcf7_add_form_tag( array( 'eastimestamp' ), array( $this, 'wpcf7_timestamp_form_tag_handler' ), array( 'name-attr' => true ) );
        wpcf7_add_form_tag( array( 'easimagecaptcha' ), array( $this, 'wpcf7_image_captcha_form_tag_handler' ), array( 'name-attr' => true ) );
        wpcf7_add_form_tag( array( 'easacceptance' ), array( $this, 'wpcf7_easacceptance_form_tag_handler' ), array( 'name-attr' => true ) );

    }

    public function wpcf7_easacceptance_form_tag_handler( $tag ) {

        $acceptance_ajaxcheck = false;
        $options = $this->get_cf7_meta();
        if ( $options && $options['acceptance_ajaxcheck'] === 'yes' ) {
            $acceptance_ajaxcheck = true;
        }

        $instance = WPCF7_ContactForm::get_current();
        $acceptance_ajaxcheck = apply_filters( 'exopite_anti_spam_easacceptance', $acceptance_ajaxcheck, $tag, $instance );

        if ( ! $acceptance_ajaxcheck ) {
            return '';
        }

        $atts          = array();
        $atts['name']  = 'easacceptance';
        $atts['id'] = 'easacceptance';
        // $atts['type']  = 'text';
        $atts['type']  = 'hidden';
        $atts['value'] = '';
        $atts['autocomplete'] = 'off';
        $atts['tabindex'] = '-1';
        $atts = wpcf7_format_atts( $atts );

        $html = sprintf( '<input %1$s  /><noscript style="color:red;text-align:center;display:block;font-weight:bold;line-height:1.2;padding:15px 0;">This contact form will not function without javascript enabled. Please enable javascript on your browser.</noscript>', $atts );

        return $html;

    }

    public function wpcf7_timestamp_form_tag_handler( $tag ) {

        // $ip_address = new RemoteAddress();
        // $ip_address->getIpAddress();

        if ( $this->is_user_logged_in() ) {
            return '';
        }

        $timestamp = false;
        $options = $this->get_cf7_meta();
        if ( $options && $options['timestamp'] === 'yes' ) {
            $timestamp = true;
        }

        $instance = WPCF7_ContactForm::get_current();
        $timestamp = apply_filters( 'exopite_anti_spam_timestamp', $timestamp, $tag, $instance );

        if ( ! $timestamp ) {
            return '';
        }

        $atts          = array();
        $atts['name']  = 'eastimestamp';
        $atts['class'] = 'eastimestamp';
        // $atts['type']  = 'text';
        $atts['type']  = 'hidden';
        $atts['value'] = $this->get_timestamp_value();
        $atts['autocomplete'] = 'off';
        $atts['tabindex'] = '-1';
        $atts = wpcf7_format_atts( $atts );

        $html = sprintf( '<input %1$s  />', $atts );

        return $html;
    }

    public function wpcf7_honeypot_form_tag_handler( $tag ) {

        if ( $this->is_user_logged_in() ) {
            return '';
        }

        $options = $this->get_cf7_meta();
        $honeypot = false;
        if ( $options && $options['honeypot'] === 'yes' ) {
            $honeypot = true;
        }

        $instance = WPCF7_ContactForm::get_current();
        $honeypot = apply_filters( 'exopite_anti_spam_honeypot', $honeypot, $tag, $instance );

        if ( ! $honeypot ) {
            return '';
        }

        $atts          = array();
        $atts['name']  = $this->main->honeypot_name;
        $atts['class'] = 'wpcf7-form-control wpcf7-text ' . $this->main->honeypot_name;
        $atts['type']  = 'url';
        $atts['value'] = '';
        $atts['autocomplete'] = 'off';
        $atts['tabindex'] = '-1';
        $atts['aria-invalid'] = 'false';
        $atts['size'] = '40';
        $atts = wpcf7_format_atts( $atts );

        $html = sprintf( '<input %1$s  />', $atts );

        return $html;
    }

    /**
     * [image_captcha human-test icon:5 choose:3]
     *
     * @link https://github.com/encharm/Font-Awesome-SVG-PNG
     *
     * Combinations Calculator
     * @link https://www.calculatorsoup.com/calculators/discretemathematics/combinations.php
     */
    public function wpcf7_image_captcha_form_tag_handler( $tag ) {

        if ( $this->is_user_logged_in() ) {
            return '';
        }

        $html = '';

        $tag = new WPCF7_FormTag( $tag );
        $instance = WPCF7_ContactForm::get_current();

        $icons_amount = $tag->get_option( 'icon', 'int', true );
        if ( ! $icons_amount ) $icons_amount = 5;
        if ( $icons_amount < 2 ) $icons_amount = 2;
        if ( $icons_amount > 10 ) $icons_amount = 10;

        $icons_amount = apply_filters( 'exopite_anti_spam_icons_amount', $icons_amount, $tag, $instance );

        $selected_amount = $tag->get_option( 'choose', 'int', true );
        if ( ! $selected_amount ) $selected_amount = 2;
        if ( $selected_amount >= $icons_amount ) $selected_amount = ( $icons_amount - 1 );
        if ( $selected_amount < 1 ) $selected_amount = 1;

        $selected_amount = apply_filters( 'exopite_anti_spam_selected_amount', $selected_amount, $tag, $instance );

        $captcha_html = $this->get_image_captcha_html( $icons_amount, $selected_amount );

        return $captcha_html;
    }

    /**
     * Validate fields
     */

    public function wpcf7_validate( $result, $tags ) {

        if ( $this->is_user_logged_in() ) {
            return $result;
        }

        if ( $this->timeout ) {

            $result->invalidate( $tags[0], esc_attr__( 'Your session has timed out. Please refresh and try again.', 'exopite-anti-spam' ) );

        }

        return $result;

    }

    public function validate_easacceptance( $result, $tag ) {

        if ( $this->is_user_logged_in() ) {
            return $result;
        }

        $tag = new WPCF7_FormTag( $tag );

        if ( empty( $_POST['easacceptance'] ) ) {
            return $this->mark_as_spam( $result, 'acceptance ajax auth empty' );
        }

        $token_acceptance = $this->crypter->decrypt( hex2bin( $_POST['easacceptance'] ), $this->get_token() );

        if ( ! $token_acceptance ) {
            return $this->mark_as_spam( $result, 'acceptance data can not decrypt' );
        }

        $token_acceptance = json_decode( $token_acceptance );
        $submit_ip = (isset($_SERVER['X_FORWARDED_FOR'])) ? $_SERVER['X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];

        if ( ! $this->check_elapsed( $token_acceptance[2] ) ) {

            $elapsed_seconds = ( time() - $token_acceptance[2] );

            if ( $elapsed_seconds > ( $this->max_time ) ) {

                $tag->name = $name;
                $result->invalidate( $tag, esc_attr__( 'Timeout.', 'exopite-anti-spam' ) );
                $this->timeout = true;

            }

            if ( $elapsed_seconds < $this->min_time ) {

                $tag->name = $name;
                $result->invalidate( $tag, esc_attr__( 'Timestamp error!', 'exopite-anti-spam' ) );

                return $this->mark_as_spam( $result, 'acceptance token timestamp is smaller then ' . $this->min_time . ' sec' );
            }

        }

        if ( $token_acceptance[1] != $submit_ip ) {
            return $this->mark_as_spam( $result, 'acceptance IP mismatch ' . $submit_ip . ' != ' . $token_acceptance[1] );
        }

        return $result;

    }

    public function validate_easimagecaptcha( $result, $tag ) {

        if ( $this->is_user_logged_in() ) {
            return $result;
        }

        $tag = new WPCF7_FormTag( $tag );

        if ( empty( $_POST['exanspsel'] ) ) {

            $tag->name = "exanspsel";
            $result->invalidate( $tag, esc_attr__( 'Please make your selection.', 'exopite-anti-spam' ) );

        }

        if ( empty( $_POST['exanspsel-auth'] ) ) {
            return $this->mark_as_spam( $result, 'captcha auth empty' );
        }

        if ( ! isset( $_POST['exanspsel'] ) ) {
            $tag->name = "exanspsel";
            $result->invalidate( $tag, esc_attr__( 'Please select the correct icon(s).', 'exopite-anti-spam' ) );
            return $result;
        }

        $selected = $_POST['exanspsel'];
        $auth = $_POST['exanspsel-auth'];

        $image_captcha_data_decrypted = $this->crypter->decrypt( hex2bin( $auth ), $this->get_token() );

        if ( ! $image_captcha_data_decrypted ) {
            return $this->mark_as_spam( $result, 'captcha data can not decrypt' );
        }

        $image_captcha_data = explode( '|', $image_captcha_data_decrypted );

        if ( ! is_array( $image_captcha_data ) ) {
            return $this->mark_as_spam( $result, 'captcha data is not an array' );
        }

        $image_captcha_token_once = $image_captcha_data[0];
        $image_captcha_time = $image_captcha_data[1];
        $image_captcha_selected = json_decode( $image_captcha_data[2] );

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

                return $this->mark_as_spam( $result, 'captcha timestamp is smaller then ' . $this->min_time . ' sec' );
            }

        }

        if ( $image_captcha_selected && ! $this->array_equal( $image_captcha_selected, $selected ) ) {

            $tag->name = "exanspsel";
            $result->invalidate( $tag, esc_attr__( 'Please select the correct icon(s).', 'exopite-anti-spam' ) );

        }

        if ( ! $image_captcha_selected ) {
            return $this->mark_as_spam( $result, 'captcha selected is invalid' . PHP_EOL . $image_captcha_data );
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

            return $this->mark_as_spam( $result, 'timestamp empty' );

        }

        $decrypted_data = $this->crypter->decrypt( hex2bin( $value ), $this->get_token() );
        $this->token = substr( $decrypted_data, 0, 64 );
        $timestamp_decrypted = substr( $decrypted_data, 64);

         if ( empty( $timestamp_decrypted ) ) {

            return $this->mark_as_spam( $result, 'timestamp data is invalid' );

        }

        if ( $this->check_token( $this->token, 'sent' ) ) {

            return $this->mark_as_spam( $result, 'token already exist' );

        }

        if ( ! $this->check_elapsed( $timestamp_decrypted ) ) {

            $elapsed_seconds = ( time() - $timestamp_decrypted );

            if ( $elapsed_seconds > ( $this->max_time ) ) {

                $tag->name = $name;
                $result->invalidate( $tag, esc_attr__( 'Timeout.', 'exopite-anti-spam' ) );
                $this->timeout = true;

            }

            if ( $elapsed_seconds < $this->min_time ) {

                $tag->name = $name;
                $result->invalidate( $tag, esc_attr__( 'Timestamp error!', 'exopite-anti-spam' ) );

                return $this->mark_as_spam( $result, 'timestamp is smaller then ' . $this->min_time . ' sec' );
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

            return $this->mark_as_spam( $result, 'honeypot is not empty' );

        }

        return $result;
    }

    /**
     * Add honeypot and timestamp programatically to Contact Form 7 form.
     *
     * @link https://stackoverflow.com/questions/24987518/php-preg-match-all-search-and-replace/24987702#24987702
     */
    public function wpcf7_contact_form( $contact_form ){

        if ( ( is_admin() && ! isset( $_POST['action'] ) ) || ( is_admin() && isset( $_POST['action'] ) && $_POST['action'] != 'eas_get_contact_form_7_ajax' ) ) {
            return;
        }

        $properties = $contact_form->get_properties();
        $form       = $properties['form'];
        $cf7_meta   = get_post_meta( $contact_form->id() );
        $options    = false;
        $ajaxload   = false;
        if ( isset( $cf7_meta['exopite-anti-spam'][0] ) ) {
            $options = maybe_unserialize( $cf7_meta['exopite-anti-spam'][0] );
        }

        if ( $options && isset( $options['honeypot'] ) && $options['honeypot'] === 'yes' ) {

            $pattern = '/\[(.*?)?\](?:([^\[]+)?\[\/\])?/';

            $amount = preg_match_all( $pattern, $form, $matches );

            $random_pos = mt_rand( 0, ( $amount - 1 ) );

            $honeypot = '<span class="wpcf7-form-control-wrap ' . $this->main->honeypot_name . '" data-js="false">[' . $this->main->honeypot_name . ' ' . $this->main->honeypot_name . ']</span>';

            $i = 0;
            foreach( $matches[1] as $match) {
                if ( $i == $random_pos ) {
                    $form = str_replace( '[' . $match . ']' ,  '[' . $match . ']' . $honeypot, $form );
                }
                $i++;
            }

        }

        if ( $options && isset( $options['ajaxload'] ) && $options['ajaxload'] === 'yes' ) {
            $ajaxload = true;
        }

        if ( $options && isset( $options['timestamp'] ) && $options['timestamp'] === 'yes' ) {

            $form .= '[eastimestamp eastimestamp]';
        }

        if ( $options && isset( $options['acceptance_ajaxcheck'] ) && $options['acceptance_ajaxcheck'] === 'yes' ) {

            $form .= '[easacceptance easacceptance]';
        }

        $form .= '<div class="eas-ajax-url" data-ajax-load="' . $ajaxload . '" data-ajax-url="' . admin_url( 'admin-ajax.php' ) . '"></div>';

        $properties['form']  = $form;
        $contact_form->set_properties( $properties );

    }

    public function get_contact_form_7_content( $cf7_id, $cf7_title ) {

        return apply_filters( 'the_content', '[contact-form-7 id="' . $cf7_id . '" title="' . $cf7_title . '"]' );

    }

    public function get_contact_form_7_ajax() {

        $cf7_id = intval( $_POST['cf7_id'] );
        $cf7_title = esc_attr( $_POST['cf7_title'] );
        echo $this->get_contact_form_7_content( $cf7_id, $cf7_title );
        die();

    }

    public function contact_form_7_ajax( $atts ) {

        $args = shortcode_atts(
            array(
                'id'   => '',
                'title'   => '',
                'method' => 'click' //click, lazyload
            ),
            $atts
        );

        if ( empty( $args['id'] ) ) {
            die( 'ID can not be empty!' );
        }

        $ret = '<div class="eas-cf7-shortcode" ';
        $ret .= 'data-id="' . intval( $args['id'] ) . '" ';
        $ret .= 'data-title="' . esc_attr( $args['title'] ) . '" ';
        $ret .= 'data-method="' . esc_attr( $args['method'] ) . '" ';
        $ret .= 'data-ajax-url="' . admin_url('admin-ajax.php') . '"';
        $ret .= '>';

        if ( isset( $_POST ) && isset( $_POST['_wpcf7'] ) && $_POST['_wpcf7'] ==  intval( $args['id'] ) ) {
            $ret .= $this->get_contact_form_7_content( intval( $args['id'] ), esc_attr( $args['title'] ) );
                    } else {
            $ret .= '<a href="#" class="eas-cf7-shortcode-load">Load Contact Form 7</a>';
        }

        $ret .= '</div>';

        return $ret;

    }

}
