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
 * The anti spam fields: form tags, injection into the forms, AJAX reload of the image captcha,
 * the timestamp and the acceptance token. Validation: Exopite_Anti_Spam_Public_Validate_Fields.
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/public
 * @author     Joe Szalai <contact@joeszalai.org>
 */
class Exopite_Anti_Spam_Public_Fields {

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

	}

    /**
     * Create fields.
     */

    public function wpcf7_init() {

        /**
         * @link https://wordpress.org/support/topic/wpcf7_add_form_tag-function-not-working/
         */

        /**
         * If there is a "true" or "array( 'name-attr' => true )" added to the last attr, then the name attribute is required for the tag:
         * [tag name], the tag without the name then not enough [tag]!
         */
        wpcf7_add_form_tag( array( $this->main->honeypot_name ), array( $this, 'wpcf7_honeypot_form_tag_handler' ) );
        wpcf7_add_form_tag( array( 'eastimestamp' ), array( $this, 'wpcf7_timestamp_form_tag_handler' ) );
        wpcf7_add_form_tag( array( 'easimagecaptcha' ), array( $this, 'wpcf7_image_captcha_form_tag_handler' ) );
        wpcf7_add_form_tag( array( 'easacceptance' ), array( $this, 'wpcf7_easacceptance_form_tag_handler' ) );

    }

    /**
     * Timestamp enabled for the current form? Default: enabled (filter 'exopite_enable_timestamp'),
     * filter 'exopite_anti_spam_timestamp' (the 2nd parameter, the form tag, is null since the timestamp is a hidden field).
     * Used by the hidden field and the validation.
     */
    public function is_timestamp_active() {

        if ( $this->main->public->skip_checks() ) {
            return false;
        }

        $options = $this->main->public->get_cf7_meta();
        $timestamp = isset( $options['timestamp'] ) ? ( $options['timestamp'] === 'yes' ) : apply_filters( 'exopite_enable_timestamp', true );

        return (bool) apply_filters( 'exopite_anti_spam_timestamp', $timestamp, null, WPCF7_ContactForm::get_current() );
    }

    /**
     * Is the acceptance AJAX check active for the current form?
     * Used by the hidden field and the validation (filter 'exopite_anti_spam_easacceptance', the form tag is null).
     */
    public function is_acceptance_ajaxcheck_active( $tag = null ) {

        $acceptance_ajaxcheck = false;
        $options = $this->main->public->get_cf7_meta();
        if ( $options && isset( $options['acceptance_ajaxcheck'] ) && $options['acceptance_ajaxcheck'] === 'yes' ) {
            $acceptance_ajaxcheck = true;
        }

        $instance = WPCF7_ContactForm::get_current();

        return apply_filters( 'exopite_anti_spam_easacceptance', $acceptance_ajaxcheck, $tag, $instance );
    }

    /**
     * [easacceptance] and [eastimestamp] were added to the form template in older versions, now they are hidden fields
     * (wpcf7_form_hidden_fields()). The tags are still registered, so a template which contains them does not show them as text.
     */
    public function wpcf7_easacceptance_form_tag_handler( $tag ) {

        return '';
    }

    public function wpcf7_timestamp_form_tag_handler( $tag ) {

        return '';
    }

    /**
     * Hidden fields of Contact Form 7: timestamp and acceptance token (filled by the JS).
     * The JS finds them by the name (Contact Form 7 adds no class).
     */
    public function wpcf7_form_hidden_fields( $fields ) {

        $fields = (array) $fields;

        if ( ! $this->should_inject_fields() || ! WPCF7_ContactForm::get_current() || $this->main->public->is_user_logged_in() ) {
            return $fields;
        }

        if ( $this->is_timestamp_active() ) {
            $fields['eastimestamp'] = $this->get_timestamp_value();
        }

        if ( $this->is_acceptance_ajaxcheck_active() ) {
            $fields['easacceptance'] = '';
        }

        // Proof-of-work: challenge and solution, filled by the JS.
        if ( $this->main->pow->is_active( $this->main->public->get_cf7_meta() ) ) {
            $fields['easpowc'] = '';
            $fields['easpows'] = '';
        }

        return $fields;
    }

    /**
     * End of the form elements: settings for the JS (AJAX URL, AJAX loading) and the note for visitors without JavaScript.
     */
    public function wpcf7_form_elements( $html ) {

        if ( ! $this->should_inject_fields() || ! WPCF7_ContactForm::get_current() ) {
            return $html;
        }

        $pow = ! $this->main->public->is_user_logged_in() && $this->main->pow->is_active( $this->main->public->get_cf7_meta() );

        if ( ! $this->main->public->is_user_logged_in() && ( $this->is_acceptance_ajaxcheck_active() || $pow ) ) {
            $html .= '<noscript style="color:red;text-align:center;display:block;font-weight:bold;line-height:1.2;padding:15px 0;">This contact form will not function without javascript enabled. Please enable javascript on your browser.</noscript>';
        }

        $ajaxload = $this->is_ajaxload_active( $this->main->public->get_cf7_meta() );

        // Proof-of-work: the JS requests a challenge for this form and solves it in a Web Worker.
        $pow_attributes = '';

        if ( $pow ) {
            $worker_url = add_query_arg( 'ver', $this->version, EXOPITE_ANTI_SPAM_URL . 'public/js/exopite-anti-spam-pow-worker.js' );
            $pow_attributes = ' data-pow="1" data-pow-worker="' . esc_url( $worker_url ) . '" data-form-id="' . esc_attr( WPCF7_ContactForm::get_current()->id() ) . '"';
        }

        $html .= '<div class="eas-ajax-url" data-ajax-load="' . ( $ajaxload ? '1' : '' ) . '" data-ajax-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '"' . $pow_attributes . '></div>';

        return $html;
    }

    public function wpcf7_honeypot_form_tag_handler( $tag ) {

        if ( $this->main->public->is_user_logged_in() ) {
            return '';
        }

        $options = $this->main->public->get_cf7_meta();
        $honeypot = false;
        $honeypot_active = isset( $options['honeypot'] ) ? ( $options['honeypot'] === 'yes' ) : apply_filters( 'exopite_enable_honeypot', true );
        if ( $honeypot_active ) {
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

        if ( $this->main->public->is_user_logged_in() ) {
            return '';
        }

        $html = '';

        $tag = new WPCF7_FormTag( $tag );

        $tag->name = 'exanspsel';

        list( $icons_amount, $selected_amount ) = $this->get_image_captcha_amounts( $tag );

        /**
         * This is required for display error messages:
         * <span class="wpcf7-form-control-wrap" data-name="THE-TAG-NAME">
         */
        $captcha_html = '<span class="wpcf7-form-control-wrap" data-name="exanspsel">' . $this->get_image_captcha_html( $icons_amount, $selected_amount ) . '</span>';

        return $captcha_html;
    }

    /**
     * Number of icons and icons to select of the [easimagecaptcha] tag (options icon:N choose:N, filters).
     * Also used by the validation: the captcha of the submission must have the same amounts.
     *
     * @return int[]  array( icons, selected )
     */
    public function get_image_captcha_amounts( $tag ) {

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

        return array( intval( $icons_amount ), intval( $selected_amount ) );
    }

    /**
     * Add the honeypot programatically to the Contact Form 7 form, at a random position (a text field, hidden with CSS:
     * bots ignore hidden fields). The timestamp and the acceptance token are hidden fields, see wpcf7_form_hidden_fields().
     *
     * @link https://stackoverflow.com/questions/24987518/php-preg-match-all-search-and-replace/24987702#24987702
     */
    /**
     * The fields are added to the form only in memory, for showing and submitting the form.
     * Not in the form editor and other admin pages, not in REST requests which may save the form
     * (otherwise the added tags could be saved into the form template).
     * AJAX requests are allowed: forms loaded by popup/AJAX plugins need the fields too.
     * Not in WP-CLI and cron (a form saved there would contain the fields).
     * Submissions in requests without the fields are rejected (Exopite_Anti_Spam_Public_Validate_Fields::wpcf7_validate()).
     */
    public function should_inject_fields() {

        if ( is_admin() && ! wp_doing_ajax() ) {
            return false;
        }

        if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
            return false;
        }

        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {

            $route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';

            // Submission, validation schema and refill of Contact Form 7. Case-insensitive like the route matching of WordPress,
            // otherwise e.g. /Contact-Form-7/v1/... would submit the form without the anti spam fields.
            return (bool) preg_match( '#^/contact-form-7/v1/contact-forms/\d+/(feedback|refill)#i', $route );
        }

        return true;
    }

    public function wpcf7_contact_form( $contact_form ){

        if ( ! $this->should_inject_fields() ) {
            return;
        }

        $properties = $contact_form->get_properties();
        $form       = $properties['form'];
        $cf7_meta   = get_post_meta( $contact_form->id() );
        $options    = false;
        if ( isset( $cf7_meta['exopite-anti-spam'][0] ) ) {
            $options = maybe_unserialize( $cf7_meta['exopite-anti-spam'][0] );
        }

        // Honeypot inject
        $honeypot_active = isset( $options['honeypot'] ) ? ( $options['honeypot'] === 'yes' ) : apply_filters( 'exopite_enable_honeypot', true );

        if ( $honeypot_active ) {

            $pattern = '/\[(.*?)?\](?:([^\[]+)?\[\/\])?/';

            $amount = preg_match_all( $pattern, $form, $matches );

            // Empty template (e.g. a new form): mt_rand( 0, -1 ) would throw a ValueError on PHP 8.
            $random_pos = $amount > 0 ? mt_rand( 0, ( $amount - 1 ) ) : -1;

            // aria-hidden: screen readers skip the field, so blind visitors do not fill it out by mistake (bots ignore it).
            $honeypot = '<span class="wpcf7-form-control-wrap" data-name="' . $this->main->honeypot_name . '" data-js="false" aria-hidden="true">[' . $this->main->honeypot_name . ' ' . $this->main->honeypot_name . ']</span>';

            $i = 0;
            foreach( $matches[1] as $match) {
                if ( $i == $random_pos ) {
                    $form = str_replace( '[' . $match . ']' ,  '[' . $match . ']' . $honeypot, $form );
                }
                $i++;
            }

        }

        $properties['form']  = $form;
        $contact_form->set_properties( $properties );

    }

    /**
     * [contact-form-7-ajax] loaded the whole form with AJAX after a click on a link (removed in 20261006,
     * "AJAX loading" in the form settings keeps the image captcha and the timestamp working on cached pages).
     * Pages which still contain the shortcode show the form directly, like [contact-form-7].
     */
    public function contact_form_7_ajax( $atts ) {

        if ( ! function_exists( 'wpcf7_contact_form_tag_func' ) ) {
            return '';
        }

        return wpcf7_contact_form_tag_func( $atts, null, 'contact-form-7' );

    }

    public function get_timestamp_value() {

        $time = time();

        $token = bin2hex( random_bytes( 32 ) );

        $timestamp_encrypted = $this->main->crypter->encrypt_hex( $token . $time );

        return $timestamp_encrypted;
    }

    /**
     * Token request limit (see Exopite_Anti_Spam_Public_Rate_Limiter::token_request_wait()): HTTP 429 with
     * Retry-After and an empty body. The JS ends the loading state in its error handler and asks again
     * on the next interaction or submission.
     *
     * @return bool  true if the request was rejected (the response headers are sent, the caller ends the request).
     */
    private function reject_if_token_limit_reached() {

        $wait = $this->main->ratelimiter->token_request_wait();

        if ( ! $wait ) {
            return false;
        }

        nocache_headers();
        status_header( 429 );
        header( 'Retry-After: ' . intval( $wait ) );

        return true;
    }

    /**
     * AJAX function
     */

     public function reload_cf7_fields_ajax() {

        $ret = array();

        if ( $this->reject_if_token_limit_reached() ) {
            die();
        }

        // CF7 removed without the plugins page (e.g. via FTP): WordPress does not deactivate this plugin then.
        if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
            echo json_encode( $ret );
            die();
        }

        if ( isset( $_POST['timestamp'] ) && ! empty( $_POST['timestamp'] ) ) {
            $ret['timestamp'] = $this->get_timestamp_value();
        }

        if ( isset( $_POST['exanspselAuth'] ) && ! empty( $_POST['exanspselAuth'] ) ) {
            $ret['exanspsel'] = $this->get_image_captcha_html_ajax( true );
        }

        // Proof-of-work challenge for the form ID (only if it is activated for that form).
        if ( isset( $_POST['pow'] ) && is_scalar( $_POST['pow'] ) && absint( $_POST['pow'] ) ) {
            $challenge = $this->main->pow->create_challenge( absint( $_POST['pow'] ) );
            if ( $challenge ) {
                $ret['pow'] = $challenge;
            }
        }

        echo json_encode( $ret );

        die();
    }

    /**
     * AJAX: acceptance token, requested when an acceptance checkbox is checked.
     */
    public function get_acceptance_token() {

        if ( $this->reject_if_token_limit_reached() ) {
            die();
        }

        $token_random = bin2hex( random_bytes( 32 ) );
        $submit_ip = $this->main->public->get_visitor_ip();
        $submit_time = time();
        $token_acceptance = json_encode( array( $token_random, $submit_ip, $submit_time ) );

        /**
         * Nothing is saved here (no DB request on every checkbox click),
         * the random part is stored as used on submission, see Exopite_Anti_Spam_Public_Validate_Fields::validate_acceptance_token().
         */
        $token_acceptance = $this->main->crypter->encrypt_hex( $token_acceptance );

        echo $token_acceptance;

        die();
    }

    public function get_image_captcha_html_ajax( $return = false ) {

        $auth = isset( $_POST['exanspselAuth'] ) ? $_POST['exanspselAuth'] : '';

        $icons_amount = 5;
        $selected_amount = 2;

        $image_captcha_data_decrypted = $this->main->crypter->decrypt_hex( $auth );

        if ( $image_captcha_data_decrypted ) {

            $image_captcha_data = explode( '|', $image_captcha_data_decrypted );

            $icons_amount = isset( $image_captcha_data[3] ) ? intval( $image_captcha_data[3] ) : 0;
            $selected_amount = isset( $image_captcha_data[4] ) ? intval( $image_captcha_data[4] ) : 0;

        }

        // Fix, if the "exanspsel-auth" value was attempted to be tampered with.
        if ( empty( $icons_amount ) ) {
            $icons_amount = 5;
        }

        if ( empty( $selected_amount ) ) {
            $selected_amount = 2;
        }

        if ( $return ) {
            return $this->get_image_captcha_html( $icons_amount, $selected_amount, false );
        }

        echo $this->get_image_captcha_html( $icons_amount, $selected_amount, false );

        die();
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

    public function generate_image_captcha_title( $seleted_titles ) {

        return implode( ' ' . esc_attr__( 'and', 'exopite-anti-spam' ) . ' ', $seleted_titles );

    }

    /**
     * Load the image captcha and the timestamp with AJAX ("AJAX loading" of the form).
     * With a page cache it is enabled on installation and recommended by an admin notice, see Exopite_Anti_Spam_Page_Cache::check().
     * Filter: 'exopite_anti_spam_ajaxload'.
     */
    public function is_ajaxload_active( $options ) {

        $ajaxload = is_array( $options ) && isset( $options['ajaxload'] ) && 'yes' === $options['ajaxload'];

        return (bool) apply_filters( 'exopite_anti_spam_ajaxload', $ajaxload, WPCF7_ContactForm::get_current() );
    }

    public function get_image_captcha_html( $icons_amount, $selected_amount, $wrapper = true ) {

        /**
         * In AJAX call this will be false.
         */
        $options = $this->main->public->get_cf7_meta();
        $ajaxload = $this->is_ajaxload_active( $options );

        $icons = new Exopite_Anti_Spam_Icons();
        $choices = $icons->get_icons( $icons_amount );
        $choices = apply_filters( 'exopite_anti_spam_exanspsel_icons', $choices, $icons_amount );

        $keys = array_keys( $choices );

        $range = range( 0, ( count( $choices ) - 1 ) );

        $selcted_keys = array_rand( $range, $selected_amount );

        $token_once = bin2hex( random_bytes( 32 ) );

        $to_encrypt = $token_once . '|' . time() . '|' . json_encode( $selcted_keys ) . '|' . $icons_amount . '|' . $selected_amount;

        $selected_keys_encrypted = $this->main->crypter->encrypt_hex( $to_encrypt );

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


        // In the AJAX reload ($_POST['action']) the captcha itself is rendered.
        if ( $ajaxload && ! isset( $_POST['action'] ) ) {
            $inner = '<span class="exanspsel-ajax-loading">' . esc_attr__( 'Loading...', 'exopite-anti-spam' ) . '</span>';
        } else {
            $selected_amount_as_text = $this->get_icons_amount_translation( $selected_amount );
            $invalidate_text = sprintf( esc_attr__( 'Please select %s', 'exopite-anti-spam' ), $selected_amount_as_text ) . '&#44; ';

            $inner = $invalidate_text;

            $inner .= $this->generate_image_captcha_title( $seleted_titles );
            $inner .= '<span class="eas-image-selector--required">*</span></span>';
            $inner .= '<span class="eas-image-selector-images">';

            $i = 0;
            // Slightly different SVG markup on every render (invisible), can be disabled with the filter.
            $randomize_icons = apply_filters( 'exopite_anti_spam_randomize_icons', true );

            foreach ( $choices as $title => $image ) {
                if ( $randomize_icons ) {
                    $image = $icons->randomize_svg( $image );
                }
                $inner .= '<label><input type="checkbox" name="exanspsel[]" value="'. $i .'"  />'. $image .'</label>';
                $i++;
            }
        }

        $output .= apply_filters( 'exopite_anti_spam_exanspsel_icons_html', $inner, $choices );

        $output .= '</span></span>';
        $output .= '<input type="hidden" name="exanspsel-auth" class="exanspsel-auth" value="' . $selected_keys_encrypted . '" autocomplete="off" tabindex="-1">';

        $error = '';

        $submission = WPCF7_Submission::get_instance();
        if ( $submission ) {
            $invalid_fields = $submission->get_invalid_fields();
            if ( isset( $invalid_fields['exanspsel']['reason'] ) ) {
                $error = '<span role="alert" class="wpcf7-not-valid-tip">*' . esc_html( $invalid_fields['exanspsel']['reason'] ) . '</span>';
            }
        }

        if ( $wrapper ) {
            return '<span class="wpcf7-form-control-wrap exanspsel"><span class="wpcf7-form-control wpcf7-checkbox wpcf7-validates-as-required">' . $output . $error . '</span></span>';
        } else {
            return $output;
        }

    }

}
