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

    public $cf7_meta = false;

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

        // wpcf7_add_form_tag( array( $this->main->honeypot_name ), array( $this, 'wpcf7_honeypot_form_tag_handler' ), array( 'name-attr' => true ) );
        // wpcf7_add_form_tag( array( 'eastimestamp' ), array( $this, 'wpcf7_timestamp_form_tag_handler' ), array( 'name-attr' => true ) );
        // wpcf7_add_form_tag( array( 'easimagecaptcha' ), array( $this, 'wpcf7_image_captcha_form_tag_handler' ), array( 'name-attr' => true ) );
        // wpcf7_add_form_tag( array( 'easacceptance' ), array( $this, 'wpcf7_easacceptance_form_tag_handler' ), array( 'name-attr' => true ) );

    }

    public function wpcf7_easacceptance_form_tag_handler( $tag ) {

        $acceptance_ajaxcheck = false;
        $options = $this->main->public->get_cf7_meta();
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

        if ( $this->main->public->is_user_logged_in() ) {
            return '';
        }

        $timestamp = false;
        $options = $this->main->public->get_cf7_meta();
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

        $html = sprintf( '<span class="wpcf7-form-control-wrap" data-name="eastimestamp"><input %1$s  /></span>', $atts );

        return $html;
    }

    public function wpcf7_honeypot_form_tag_handler( $tag ) {

        if ( $this->main->public->is_user_logged_in() ) {
            return '';
        }

        $options = $this->main->public->get_cf7_meta();
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

        if ( $this->main->public->is_user_logged_in() ) {
            return '';
        }

        $html = '';

        $tag = new WPCF7_FormTag( $tag );

        $tag->name = 'exanspsel';
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

        /**
         * This is required for display error messages:
         * <span class="wpcf7-form-control-wrap" data-name="THE-TAG-NAME">
         */
        $captcha_html = '<span class="wpcf7-form-control-wrap" data-name="exanspsel">' . $this->get_image_captcha_html( $icons_amount, $selected_amount ) . '</span>';

        return $captcha_html;
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

            $honeypot = '<span class="wpcf7-form-control-wrap" data-name="' . $this->main->honeypot_name . '" data-js="false">[' . $this->main->honeypot_name . ' ' . $this->main->honeypot_name . ']</span>';

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

    public function get_timestamp_value() {

        $time = time();

        $token = bin2hex( random_bytes( 32 ) );

        $timestamp_encrypted = bin2hex( $this->main->public->crypter->encrypt( $token . $time, $this->main->public->get_token() ) );

        return $timestamp_encrypted;
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

            $image_captcha_data_decrypted = $this->main->public->crypter->decrypt( hex2bin( $auth ), $this->main->public->get_token() );
            $image_captcha_data = explode( '|', $image_captcha_data_decrypted );

            $icons_amount = json_decode( $image_captcha_data[3] );
            $selected_amount = json_decode( $image_captcha_data[4] );

        } catch (Exception $e) {

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

    public function generate_image_captcha_title( $seleted_titles ) {

        return implode( ' ' . esc_attr__( 'and', 'exopite-anti-spam' ) . ' ', $seleted_titles );

    }

    public function get_image_captcha_html( $icons_amount, $selected_amount, $wrapper = true ) {

        /**
         * In AJAX call this will be false.
         */
        $options = $this->main->public->get_cf7_meta();
        $ajaxload = false;
        if ( $options && $options['ajaxload'] === 'yes' ) {
            $ajaxload = true;
        }

        $instance = WPCF7_ContactForm::get_current();
        $ajaxload = apply_filters( 'exopite_anti_spam_ajaxload', $ajaxload, $instance );

        $icons = new Exopite_Anti_Spam_Icons();
        $choices = $icons->get_icons( $icons_amount );
        $choices = apply_filters( 'exopite_anti_spam_exanspsel_icons', $choices, $icons_amount );

        // $human = rand( 0, ( count( $choices ) - 1 ) );

        $keys = array_keys( $choices );

        $range = range( 0, ( count( $choices ) - 1 ) );

        $selcted_keys = array_rand( $range, $selected_amount );

        $token_once = bin2hex( random_bytes( 32 ) );

        $to_encrypt = $token_once . '|' . time() . '|' . json_encode( $selcted_keys ) . '|' . $icons_amount . '|' . $selected_amount;

        $selected_keys_encrypted = bin2hex( $this->main->public->crypter->encrypt( $to_encrypt, $this->main->public->get_token() ) );

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
            $selected_amount_as_text = $this->main->public->get_icons_amount_translation( $selected_amount );
            $invalidate_text = sprintf( esc_attr__( 'Please select %s', 'exopite-anti-spam' ), $selected_amount_as_text ) . '&#44; ';

            $inner = $invalidate_text;

            $inner .= $this->generate_image_captcha_title( $seleted_titles );
            $inner .= '<span class="eas-image-selector--required">*</span></span>';
            $inner .= '<span class="eas-image-selector-images">';

            $i = 0;
            foreach ( $choices as $title => $image ) {
                $inner .= '<label><input type="checkbox" name="exanspsel[]" value="'. $i .'"  />'. $image .'</label>';
                $i++;
            }
        }

        $output .= apply_filters( 'exopite_anti_spam_exanspsel_icons_html', $inner, $choices );
        // $output .= '<pre>' .  var_export( $choices, true ) . '</pre>';

        $output .= '</span></span>';
        // Debug
        // $output .= '<input type="text" name="exanspsel-auth" class="exanspsel-auth" value="' . $selected_keys_encrypted . '" autocomplete="off" tabindex="-1">';
        $output .= '<input type="hidden" name="exanspsel-auth" class="exanspsel-auth" value="' . $selected_keys_encrypted . '" autocomplete="off" tabindex="-1">';

        $error = '';

        $submission = WPCF7_Submission::get_instance();
        if ( $submission ) {
            $invalid_fields = $submission->get_invalid_fields();
            if ( isset( $invalid_fields['exanspsel']['reason'] ) ) {
                $error = '<span role="alert" class="wpcf7-not-valid-tip">*' . $invalid_fields['exanspsel']['reason'] . '</span>';
            }
        }

        if ( $wrapper ) {
            return '<span class="wpcf7-form-control-wrap exanspsel"><span class="wpcf7-form-control wpcf7-checkbox wpcf7-validates-as-required">' . $output . $error . '</span></span>';
        } else {
            return $output;
        }

    }

}
