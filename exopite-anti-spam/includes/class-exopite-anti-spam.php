<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.joeszalai.org
 * @since      1.0.0
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/includes
 * @author     Joe Szalai <contact@joeszalai.org>
 */
class Exopite_Anti_Spam {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Exopite_Anti_Spam_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

    /**
     * Store plugin main class to allow public access.
     *
     * @since    20180622
     * @var object      The main class.
     */
    public $main;

    /**
     * Store plugin public class to allow public access.
     *
     * @since    20180622
     * @var object      The public class.
     */
    public $public;
    public $fields;

    /**
     * Validation of the anti spam fields.
     *
     * @var Exopite_Anti_Spam_Public_Validate_Fields
     */
    public $validate;

    /**
     * Encryption (key, encrypt/decrypt).
     *
     * @var Exopite_Anti_Spam_Crypter
     */
    public $crypter;

    /**
     * Single use tokens.
     *
     * @var Exopite_Anti_Spam_Token
     */
    public $tokens;

    /**
     * Page cache detection.
     *
     * @var Exopite_Anti_Spam_Page_Cache
     */
    public $page_cache;

    /**
     * Rate limit (failed attempts per visitor).
     *
     * @var Exopite_Anti_Spam_Public_Rate_Limiter
     */
    public $ratelimiter;

    /**
     * Statistics of the submissions.
     *
     * @var Exopite_Anti_Spam_Stats
     */
    public $stats;

    /**
     * Quarantine of spam submissions.
     *
     * @var Exopite_Anti_Spam_Quarantine
     */
    public $quarantine;

    /**
     * Spam score (content rules).
     *
     * @var Exopite_Anti_Spam_Public_Scoring
     */
    public $scoring;

    /**
     * E-mail check (disposable addresses, domains without MX).
     *
     * @var Exopite_Anti_Spam_Public_Email_Check
     */
    public $email_check;

    /**
     * Invisible proof-of-work.
     *
     * @var Exopite_Anti_Spam_Public_Pow
     */
    public $pow;

    /**
     * Store plugin admin class to allow public access.
     *
     * @since    20180622
     * @var object      The admin class.
     */
    public $admin;

    /**
     * Admin page "Anti Spam" (quarantine, statistics).
     *
     * @var Exopite_Anti_Spam_Admin_Overview
     */
    public $overview;

	public $honeypot_name = 'eashpc_website_url';

    /**
     * True while a quarantined submission is released: all anti spam checks are skipped
     * (Exopite_Anti_Spam_Public::skip_checks()).
     *
     * @var bool
     */
    public $releasing = false;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'EXOPITE_ANTI_SPAM_VERSION' ) ) {
			$this->version = EXOPITE_ANTI_SPAM_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		if ( defined( 'EXOPITE_ANTI_SPAM_PLUGIN_NAME' ) ) {
			$this->plugin_name = EXOPITE_ANTI_SPAM_PLUGIN_NAME;
		} else {
			$this->plugin_name = 'exopite-anti-spam';
        }

        $this->main = $this;

        $this->honeypot_name = apply_filters( 'exopite_anti_spam_honeypot_name', $this->honeypot_name );

        $this->load_dependencies();

        $this->crypter = new Exopite_Anti_Spam_Crypter( $this->plugin_name );
        $this->tokens = new Exopite_Anti_Spam_Token( $this->main );
        $this->page_cache = new Exopite_Anti_Spam_Page_Cache( $this->plugin_name, $this->main );
        $this->stats = new Exopite_Anti_Spam_Stats( $this->main );
        $this->quarantine = new Exopite_Anti_Spam_Quarantine( $this->main );
        $this->scoring = new Exopite_Anti_Spam_Public_Scoring( $this->main );
        $this->email_check = new Exopite_Anti_Spam_Public_Email_Check( $this->main );
        $this->pow = new Exopite_Anti_Spam_Public_Pow( $this->main );

        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Exopite_Anti_Spam_Loader. Orchestrates the hooks of the plugin.
	 * - Exopite_Anti_Spam_i18n. Defines internationalization functionality.
	 * - Exopite_Anti_Spam_Admin. Defines all hooks for the admin area.
	 * - Exopite_Anti_Spam_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-exopite-anti-spam-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-exopite-anti-spam-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-exopite-anti-spam-admin.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-exopite-anti-spam-admin-overview.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-exopite-anti-spam-public.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-exopite-anti-spam-public-fields.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-exopite-anti-spam-public-validate-fields.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-exopite-anti-spam-public-rate-limiter.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-exopite-anti-spam-svg-path.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-exopite-anti-spam-icons.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-exopite-anti-spam-crytper.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-exopite-anti-spam-token.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-exopite-anti-spam-page-cache.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-exopite-anti-spam-stats.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-exopite-anti-spam-quarantine.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-exopite-anti-spam-scorer.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-exopite-anti-spam-public-scoring.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-exopite-anti-spam-public-email-check.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-exopite-anti-spam-public-pow.php';

        // Global class name without prefix: other plugins may contain the same class.
        if ( ! class_exists( 'RemoteAddress' ) ) {
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ip-address.php';
        }

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-exopite-anti-spam-activator.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-exopite-anti-spam-logger.php';

		$this->loader = new Exopite_Anti_Spam_Loader();

        // Create/update the token table after plugin updates.
        $this->loader->add_action( 'plugins_loaded', 'Exopite_Anti_Spam_Activator', 'maybe_update_db' );

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Exopite_Anti_Spam_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Exopite_Anti_Spam_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$this->admin = new Exopite_Anti_Spam_Admin( $this->get_plugin_name(), $this->get_version(), $this->main );

		$this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_scripts' );

        $this->loader->add_action( 'admin_init', $this->admin, 'check_dependencies' );

        // Page cache: enable "AJAX loading" on installation, notice if a cache is activated later.
        $this->loader->add_action( 'admin_init', $this->page_cache, 'check' );
        $this->loader->add_action( 'admin_init', $this->page_cache, 'handle_notice' );
        $this->loader->add_action( 'admin_notices', $this->page_cache, 'admin_notices' );
        $this->loader->add_filter( 'removable_query_args', $this->page_cache, 'removable_query_args' );

        // From here added
        // Save/Update our plugin options
        $this->loader->add_action( 'admin_init', $this->admin, 'options_update' );

        // Add menu item
        $this->loader->add_action( 'admin_menu', $this->admin, 'add_plugin_admin_menu' );

        // Page "Anti Spam": quarantine and statistics
        $this->overview = new Exopite_Anti_Spam_Admin_Overview( $this->get_plugin_name(), $this->main );
        $this->loader->add_action( 'admin_menu', $this->overview, 'add_menu' );
        $this->loader->add_action( 'admin_post_eas_quarantine', $this->overview, 'handle_action' );
        // Update of the disposable e-mail domain list (button on the "Blacklist" page)
        $this->loader->add_action( 'admin_post_eas_disposable_list', $this->admin, 'handle_disposable_list' );

        $this->loader->add_filter( 'wpcf7_admin_init', $this->admin, 'wpcf7_admin_init', 55 );
        $this->loader->add_action( 'wpcf7_editor_panels', $this->admin, 'wpcf7_editor_panels', 10, 1 );
        // After saving: a new form has an ID only then (wpcf7_save_contact_form runs before the first save).
        $this->loader->add_action( 'wpcf7_after_save', $this->admin, 'wpcf7_save_contact_form', 10, 1 );



	}

    /**
     * ToDos:
     * - timestamp
     *   - encrypt timestamp in a hidden filed (with salt and random hash)
     *   - time should be between 5 sec and 5 min
     * - min char for message
     * - check bad/spam words
     * - load CF7 via AJAX?
     *   https://wordpress.org/support/topic/ajax%E3%81%A7%E8%AA%AD%E3%81%BF%E8%BE%BC%E3%82%93%E3%81%A0cf7%E3%83%95%E3%82%A9%E3%83%BC%E3%83%A0%E3%81%8C%E6%A9%9F%E8%83%BD%E3%81%97%E3%81%AA%E3%81%84/
     *   https://stackoverflow.com/questions/28866152/enabling-ajax-on-contact-form-7-form-after-ajax-load
     *   https://wordpress.stackexchange.com/questions/265635/contact-form-7-submit-form-not-working-after-ajax-request
     * - JS filled hidden field? (check JS)
     * - honeypot
     *   - random location
     *   - hide with CSS
     *   - not obvious name
     * - image captcha
     *   - svg icons
     *   - multiple selection possible
     *   - timestamp should be between 5 sec and 5 min
     *   - encrypt in hidden field answers (with salt and random hash)
     *     https://wordpress.org/support/topic/ajax%E3%81%A7%E8%AA%AD%E3%81%BF%E8%BE%BC%E3%82%93%E3%81%A0cf7%E3%83%95%E3%82%A9%E3%83%BC%E3%83%A0%E3%81%8C%E6%A9%9F%E8%83%BD%E3%81%97%E3%81%AA%E3%81%84/
     * - conditionaly load resources
     *   https://techjourney.net/load-contact-form-7-cf7-js-css-conditionally-only-on-selected-pages/
     * - block emails and domains
     *   http://wpstudio.org/blog/list-of-around-4750-free-and-spam-domains/
     * - disable for logged in users
     * - options for different CF7 instances?
     *
     * Avoid
     * - session
     * - file creation (really simple captcha)
     */

    /**
     * Infos:
     *
     * COntact Form general infos/best practices
     * @link https://wpforms.com/research-based-tips-to-improve-contact-form-conversions/
     *
     * wpcf7_before_send_mail Change CF7 Form values dynamically
     * @link https://stackoverflow.com/questions/25817442/change-cf7-form-values-dynamically/26782359#26782359
     *
     * Contact Form 7 Hooks By Takayuki Miyoshi
     * @link http://hookr.io/plugins/contact-form-7/4.5.1/hooks/#index=a
     *
     * Using WordPress filters to modify Contact Form 7 Output
     * @link http://rollinglab.com/2012/07/using-wordpressfilters-to-modify-contact-form-7-output/
     *
     * @link https://marctroendle.de/blog/contact-form-7-tips-und-tricks/
     *
     * Enable shortcodes inside the Form Template
     * Enable shortcodes inside the Mail Template
     * @link https://www.howtosnippets.net/wordpress/make-custom-shortcodes-work-in-contact-form-7-mail-form-templates/
     *
     * Crypt
     * @link https://stackoverflow.com/questions/16600708/how-do-you-encrypt-and-decrypt-a-php-string/57249681#57249681
     *
     * Time check
     * @link https://kiwee.eu/stop-form-spam-robots-honeypot/
     *
     * Stop bot
     * @link https://www.karlrupp.net/en/computer/how_to_fight_guestbook_spam
     */

    /**
     * Documentation
     * @link https://contactform7.com/docs/
     *
     * Contact Form 7 sepcial shortcodes
     * @link https://contactform7.com/special-mail-tags/
     *
     * [type name options value]
     * [text yout-name 20/40 id:foo class:bar "Enter your name"]
     *
     * [response]
     *
     * Special Mail Tags for Submissions
     * [_remote_ip] — This tag is replaced by the submitter’s IP address.
     * [_user_agent] — This tag is replaced by the submitter’s user agent (browser) information.
     * [_url] — This tag is replaced by the URL of the page in which the contact form is placed.
     * [_date] — This tag is replaced by the date of the submission.
     * [_time] — This tag is replaced by the time of the submission.
     * [_invalid_fields] — This tag is replaced by the number of form fields with invalid input.
     *
     * Post-Related Special Mail Tags
     * [_post_id] — This tag is replaced by the ID of the post.
     * [_post_name] — This tag is replaced by the name (slug) of the post.
     * [_post_title] — This tag is replaced by the title of the post.
     * [_post_url] — This tag is replaced by the permalink URL of the post.
     * [_post_author] — This tag is replaced by the author name of the post.
     * [_post_author_email] — This tag is replaced by the author email of the post.
     *
     * Site-Related Special Mail Tags
     * [_site_title] — This tag is replaced by the title of the website.
     * [_site_description] — This tag is replaced by the description (tagline) of the website.
     * [_site_url] — This tag is replaced by the home URL of the website.
     * [_site_admin_email] — This tag is replaced by the email address of the primary admin user of the website.
     *
     * [_user_login] — This tag is replaced by the login name of the user.
     * [_user_email] — This tag is replaced by the email address of the user.
     * [_user_url] — This tag is replaced by the website URL of the user.
     * [_user_first_name] — This tag is replaced by the first name of the user.
     * [_user_last_name] — This tag is replaced by the last name of the user.
     * [_user_nickname] — This tag is replaced by the nickname of the user.
     * [_user_display_name] — This tag is replaced by the display name of the user.
     *
     */



	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$this->public = new Exopite_Anti_Spam_Public( $this->get_plugin_name(), $this->get_version(), $this->main );
		$this->fields = new Exopite_Anti_Spam_Public_Fields( $this->get_plugin_name(), $this->get_version(), $this->main );
        $this->validate = new Exopite_Anti_Spam_Public_Validate_Fields( $this->get_plugin_name(), $this->get_version(), $this->main );
        $this->ratelimiter = new Exopite_Anti_Spam_Public_Rate_Limiter( $this->main );

		$this->loader->add_action( 'wp_enqueue_scripts', $this->public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $this->public, 'enqueue_scripts' );

        // Add field hooks
        $this->loader->add_filter( 'wpcf7_init', $this->fields, 'wpcf7_init', 10, 0 );
        $this->loader->add_filter( 'wpcf7_contact_form', $this->fields, 'wpcf7_contact_form', 10, 2 );
        // Timestamp and acceptance token as hidden fields, settings for the JS at the end of the form
        $this->loader->add_filter( 'wpcf7_form_hidden_fields', $this->fields, 'wpcf7_form_hidden_fields', 10, 1 );
        $this->loader->add_filter( 'wpcf7_form_elements', $this->fields, 'wpcf7_form_elements', 10, 1 );

        // Validation hooks
        $this->loader->add_filter( 'wpcf7_validate', $this->validate, 'wpcf7_validate', 30, 2 );
        $this->loader->add_filter( 'wpcf7_validate_' . $this->honeypot_name, $this->validate, 'wpcf7_validate_honeypot', 10, 2 );
        $this->loader->add_filter( 'wpcf7_validate_text', $this->validate, 'validate_text_textarea_bad_words', 20, 2 );
        $this->loader->add_filter( 'wpcf7_validate_text*', $this->validate, 'validate_text_textarea_bad_words', 20, 2 );
        $this->loader->add_filter( 'wpcf7_validate_textarea', $this->validate, 'validate_text_textarea_bad_words', 20, 2 );
        $this->loader->add_filter( 'wpcf7_validate_textarea*', $this->validate, 'validate_text_textarea_bad_words', 20, 2 );
        $this->loader->add_filter( 'wpcf7_validate_email', $this->validate, 'validate_text_email_blacklist', 20, 2 );
        $this->loader->add_filter( 'wpcf7_validate_email*', $this->validate, 'validate_text_email_blacklist', 20, 2 );
        $this->loader->add_filter( 'wpcf7_validate_easimagecaptcha', $this->validate, 'validate_easimagecaptcha', 5, 2 );

        // E-mail check per form: disposable addresses, domains without MX (after the blacklist)
        $this->loader->add_filter( 'wpcf7_validate_email', $this->email_check, 'validate', 25, 2 );
        $this->loader->add_filter( 'wpcf7_validate_email*', $this->email_check, 'validate', 25, 2 );
        // DNS check after the bot checks (rate limit 5, timestamp/tokens/proof-of-work 30): bots cause no DNS requests
        $this->loader->add_filter( 'wpcf7_validate', $this->email_check, 'validate_mx', 40, 2 );

        /**
         * Compatibility with "Conditional Fields for Contact Form 7" plugin:
         * restore the errors of the nameless fields of this plugin, if CF7CF rebuilds the validation result.
         * (Registered always, the filters do nothing without CF7CF, the plugin may be loaded later.)
         */
        $this->loader->add_filter( 'wpcf7_validate', $this->validate, 'wpcf7_validate_snapshot', 1, 2 );
        $this->loader->add_filter( 'wpcf7cf_validate', $this->validate, 'wpcf7cf_validate', 10, 2 );

        // Bot submissions (honeypot, missing or manipulated encrypted data): status "spam" like Akismet, reCAPTCHA, Turnstile
        $this->loader->add_filter( 'wpcf7_spam', $this->validate, 'wpcf7_spam', 10, 2 );

        // Spam score: content rules, only if all other checks passed
        $this->loader->add_filter( 'wpcf7_spam', $this->scoring, 'wpcf7_spam', 20, 2 );

        // Rate limit: block visitors after too many failed attempts
        $this->loader->add_filter( 'wpcf7_validate', $this->ratelimiter, 'wpcf7_validate', 5, 2 );
        $this->loader->add_action( 'wpcf7_submit', $this->ratelimiter, 'wpcf7_submit', 10, 2 );

        // Release the timestamp token lock after the submission (mail sent and token saved)
        $this->loader->add_action( 'wpcf7_submit', $this->tokens, 'release_locks', 999, 0 );
        $this->loader->add_action( 'shutdown', $this->tokens, 'release_locks', 10, 0 );

        // Tell the JS to load a new timestamp if it is expired or already used
        $this->loader->add_filter( 'wpcf7_feedback_response', $this->validate, 'wpcf7_feedback_response', 10, 2 );

        // DEBUG
        $this->loader->add_action( 'wpcf7_submit', $this->public, 'wpcf7_submit', 20, 2 );

        // Quarantine: store spam of forms with quarantine enabled
        $this->loader->add_action( 'wpcf7_submit', $this->quarantine, 'wpcf7_submit', 90, 2 );

        // Statistics: count every submission once, after the rate limit and the quarantine
        $this->loader->add_action( 'wpcf7_submit', $this->stats, 'wpcf7_submit', 100, 2 );

        // AJAX hooks
        $this->loader->add_filter( 'wp_ajax_eap_reload_cf7_fields', $this->fields, 'reload_cf7_fields_ajax' );
        $this->loader->add_filter( 'wp_ajax_nopriv_eap_reload_cf7_fields', $this->fields, 'reload_cf7_fields_ajax' );

        $this->loader->add_filter( 'wp_ajax_eap_get_acceptance_token', $this->fields, 'get_acceptance_token' );
        $this->loader->add_filter( 'wp_ajax_nopriv_eap_get_acceptance_token', $this->fields, 'get_acceptance_token' );

        // Removed [contact-form-7-ajax]: shows the form like [contact-form-7], so existing pages keep working.
        $this->loader->add_shortcode( 'contact-form-7-ajax', $this->fields, 'contact_form_7_ajax' );

        // Mail sent hooks
        $this->loader->add_filter( 'wpcf7_mail_sent', $this->public, 'wpcf7_mail_sent' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Exopite_Anti_Spam_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
