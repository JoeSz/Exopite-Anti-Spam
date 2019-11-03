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

    /**
     * Store plugin admin class to allow public access.
     *
     * @since    20180622
     * @var object      The admin class.
     */
    public $admin;

	public $honeypot_name = 'eashpc_website_url';

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

        $this->load_dependencies();
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

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-exopite-anti-spam-public.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-exopite-anti-spam-icons.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-exopite-anti-spam-crytper.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ip-address.php';

		$this->loader = new Exopite_Anti_Spam_Loader();

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

        $this->loader->add_action( 'admin_init', $this->admin, 'check_dependencies' );

        // From here added
        // Save/Update our plugin options
        $this->loader->add_action( 'admin_init', $this->admin, 'options_update' );

        // Add menu item
        $this->loader->add_action( 'admin_menu', $this->admin, 'add_plugin_admin_menu' );

        // Add Settings link to the plugin
        // $plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_name . '.php' );

        // $this->loader->add_filter( 'plugin_action_links_' . $plugin_basename, $plugin_admin, 'add_action_links' );

        $this->loader->add_filter( 'wpcf7_admin_init', $this->admin, 'wpcf7_admin_init', 55 );
        $this->loader->add_action( 'wpcf7_editor_panels', $this->admin, 'wpcf7_editor_panels', 10, 1 );
        $this->loader->add_action( 'wpcf7_save_contact_form', $this->admin, 'wpcf7_save_contact_form', 10, 1);



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

		$this->loader->add_action( 'wp_enqueue_scripts', $this->public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $this->public, 'enqueue_scripts' );

        add_filter( 'wpcf7_verify_nonce', '__return_true' );

        $this->loader->add_filter( 'wpcf7_init', $this->public, 'wpcf7_init', 10, 0 );

        $this->loader->add_filter( 'wpcf7_contact_form', $this->public, 'wpcf7_contact_form', 10, 2 );

        $this->loader->add_filter( 'wpcf7_validate', $this->public, 'wpcf7_validate', 10, 2 );
        $this->loader->add_filter( 'wpcf7_validate_' . $this->honeypot_name, $this->public, 'wpcf7_validate_honeypot', 10, 2 );
        $this->loader->add_filter( 'wpcf7_validate_eastimestamp', $this->public, 'wpcf7_validate_eastimestamp', 10, 2 );
        $this->loader->add_filter( 'wpcf7_validate_text', $this->public, 'validate_text_textarea_bad_words', 20, 2 );
        $this->loader->add_filter( 'wpcf7_validate_text*', $this->public, 'validate_text_textarea_bad_words', 20, 2 );
        $this->loader->add_filter( 'wpcf7_validate_textarea', $this->public, 'validate_text_textarea_bad_words', 20, 2 );
        $this->loader->add_filter( 'wpcf7_validate_textarea*', $this->public, 'validate_text_textarea_bad_words', 20, 2 );
        $this->loader->add_filter( 'wpcf7_validate_email', $this->public, 'validate_text_email_blacklist', 20, 2 );
        $this->loader->add_filter( 'wpcf7_validate_email*', $this->public, 'validate_text_email_blacklist', 20, 2 );
        $this->loader->add_filter( 'wpcf7_validate_easimagecaptcha', $this->public, 'validate_easimagecaptcha', 20, 2 );

        $this->loader->add_filter( 'wp_ajax_eap_reload_cf7_fields', $this->public, 'reload_cf7_fields_ajax' );
        $this->loader->add_filter( 'wp_ajax_nopriv_eap_reload_cf7_fields', $this->public, 'reload_cf7_fields_ajax' );
        $this->loader->add_filter( 'wpcf7_mail_sent', $this->public, 'wpcf7_mail_sent' );

        $this->loader->add_shortcode( "contact-form-7-ajax", $this->public, "contact_form_7_ajax", $priority = 10, $accepted_args = 2 );
        $this->loader->add_filter( "wp_ajax_eas_get_contact_form_7_ajax", $this->public, "get_contact_form_7_ajax" );
        $this->loader->add_filter( "wp_ajax_nopriv_eas_get_contact_form_7_ajax", $this->public, "get_contact_form_7_ajax" );

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
