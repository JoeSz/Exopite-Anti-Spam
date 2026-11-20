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
 * The public-facing functionality of the plugin: scripts and styles, settings of the current form,
 * saving the timestamp token after the mail was sent, logging and helpers used by the other classes.
 *
 * - Fields (render, AJAX):  Exopite_Anti_Spam_Public_Fields
 * - Validation:            Exopite_Anti_Spam_Public_Validate_Fields
 * - Single use tokens:     Exopite_Anti_Spam_Token
 * - Rate limit:            Exopite_Anti_Spam_Public_Rate_Limiter
 * - Encryption (key):      Exopite_Anti_Spam_Crypter
 * - Page cache detection:  Exopite_Anti_Spam_Page_Cache
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

    /**
     * Anti spam settings per form ID (a page may contain multiple forms).
     */
    public $cf7_meta = array();

    /**
     * Logging can be enabled here, in wp-config.php with
     * define( 'EXOPITE_ANTI_SPAM_LOG', true );
     * or with the 'exopite_anti_spam_logging' filter.
     */
    public $logging = false;
    public $logging_enabled = null;

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
     * $_POST for the log, personal data masked (see Exopite_Anti_Spam_Logger::mask_data()).
     */
    public function get_masked_post_data() {

        // CF7 and plugin internal fields (form ID, tokens, honeypot) contain no personal data.
        $internal_fields = array( 'eastimestamp', 'easacceptance', 'exanspsel', 'exanspsel-auth', $this->main->honeypot_name );

        return Exopite_Anti_Spam_Logger::mask_data( $_POST, $internal_fields );
    }

    /**
     * IP address of the visitor: REMOTE_ADDR, it can not be faked by the visitor.
     * Behind a reverse proxy or CDN it is the address of the proxy, the real IP (from a trusted header)
     * can be passed with the filter 'exopite_anti_spam_visitor_ip'. Used by the acceptance token (IP check),
     * the limit of failed attempts (also filter 'exopite_anti_spam_ratelimit_ip') and the logs.
     */
    public function get_visitor_ip() {

        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';

        return (string) apply_filters( 'exopite_anti_spam_visitor_ip', $ip );
    }

    /**
     * First form tag with a name: invalidate() ignores tags without a name ([submit], [response], [easimagecaptcha]),
     * a fake tag is used if the form has none.
     */
    public function get_first_named_tag( $tags ) {

        foreach ( (array) $tags as $tag ) {
            if ( ! empty( $tag->name ) ) {
                return $tag;
            }
        }

        return array( 'name' => 'exopite-anti-spam' );
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
        if ( ! empty( $this->main->validate->timestamp_token ) ) {

            $this->main->tokens->save( $this->main->tokens->get_db_data( $contact_form->id(), $this->main->validate->timestamp_token ), 'sent' );

        }

        /**
         * Clean up.
         * Delete all tokens which older then 1 month.
         */
        $this->main->tokens->clean_up( 'sent' );

    }

    /**
     * To cache spam logs
     */
    public function wpcf7_submit( $contact_form, $result ) {

        if ( $this->is_logging() ) {

            $submission = WPCF7_Submission::get_instance();
            $spam_log = $submission->get_spam_log();

            if ( ! empty( $spam_log ) ) {
                Exopite_Anti_Spam_Logger::write( 'wpcf7_submit_spam', PHP_EOL . wp_date( 'Y-m-d H:i:s' ) . ' - ' . Exopite_Anti_Spam_Logger::anonymize_ip( $this->get_visitor_ip() ) . PHP_EOL .
                var_export( $contact_form->id(), true ) . PHP_EOL .
                var_export( $spam_log, true ) . PHP_EOL .
                var_export( $this->get_masked_post_data(), true ) . PHP_EOL .
                var_export( $result, true ) . PHP_EOL .
                '---' . PHP_EOL . PHP_EOL );
            }

        }

    }



}
