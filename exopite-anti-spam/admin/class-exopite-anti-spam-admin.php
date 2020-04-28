<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.joeszalai.org
 * @since      1.0.0
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/admin
 * @author     Joe Szalai <contact@joeszalai.org>
 */
class Exopite_Anti_Spam_Admin {

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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version, $plugin_main ) {

        $this->main = $plugin_main;
		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Exopite_Anti_Spam_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Exopite_Anti_Spam_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/exopite-anti-spam-admin.css', array(), $this->version, 'all' );

	}

    public function check_dependencies() {
        if ( current_user_can( 'activate_plugins' ) && ! class_exists( 'WPCF7' ) ) {
            add_action( 'admin_notices', array( $this, 'admin_notices_cf7_required' ) );
        }
    }

    public function wpcf7_admin_init() {

        $tag_generator = WPCF7_TagGenerator::get_instance();
        $tag_generator->add( 'easimagecaptcha', esc_attr__( 'image captcha', 'exopite-anti-spam' ), array( $this, 'cf7_tag_generator' ), array( 'nameless' => 1 ) );

    }

    public function cf7_tag_generator( $contact_form, $args = '' ) {
        $args = wp_parse_args( $args, array() ); ?>
        <div class="control-box">
            <fieldset>
                <legend><?php esc_attr_e( 'Add image captcha to your form', 'exopite-anti-spam' ); ?></legend>
            </fieldset>
        </div>
        <div class="insert-box">
            <input type="text" name="easimagecaptcha" class="tag code" readonly="readonly" onfocus="this.select()" />
            <div class="submitbox">
                <input type="button" class="button button-primary insert-tag" value="<?php esc_attr_e( 'Insert Tag', 'contact-form-7' ); ?>" />
            </div>
        </div>
    <?php
    }

    public function admin_notices_cf7_required() {

        ?>
        <div class="notice notice-error is-dismissible">
            <p>

                <?php
                printf(
                    esc_html__( 'In order to %1$s work, Contact From 7 needs to be installed and activated. %2$s', 'cf7-repeatable-fields' ),
                    '<strong>' . EXOPITE_ANTI_SPAM_PLUGIN_NICE_NAME . '</strong>',
                    '<a href="' . admin_url( 'plugin-install.php?tab=plugin-information&plugin=contact-form-7&from=plugins&TB_iframe=true&width=600&height=550' ) . '" class="thickbox" title="Contact Form 7">Install Now.</a>'
                );

                ?>
            </p>
        </div>
        <?php

    }

    public function wpcf7_editor_panel_preview() {

        $form_id = $_GET['post'];
        $options = get_post_meta( $form_id, 'exopite-anti-spam' );

        $checked = '';
        if ( isset( $options[0]['timestamp'] ) && $options[0]['timestamp'] == 'yes' ) {
            $checked = ' checked="checked"';
        }

        echo '<div class="eas-row">';

        echo '<div class="eas-row-title">' . esc_attr( 'Timestamp', 'exopite-anti-spam' ) . '</div>';

        echo '<div class="eas-row-desc">' . esc_attr(
            'Add hidden timestamp to ensure the minimum and maximum age of the "session". On submission, the plugin will compare the submitted timestamp with the timestamp when the form was displayed. If it is more than 5 minutes or less than 5 seconds, then it is very likely an automated bot/script, because a bot ‘types’ much faster than a human.'
            , 'exopite-anti-spam' ) . '</div>';

        echo '<label for="eas-activate-timestamp">';
        echo '<input id="eas-activate-timestamp" class="eas-switch" type="checkbox" name="eas-activate-timestamp" value="yes"' . $checked . '>';
        echo ' ' . esc_html__( 'Activate timestamp', 'exopite-anti-spam' ) . '</label>';

        echo '</div>';

        $checked = '';
        if ( isset( $options[0]['honeypot'] ) && $options[0]['honeypot'] == 'yes' ) {
            $checked = ' checked="checked"';
        }

        echo '<div class="eas-row">';

        echo '<div class="eas-row-title">' . esc_attr( 'Honeypot', 'exopite-anti-spam' ) . '</div>';

        echo '<div class="eas-row-desc">' . esc_attr(
            'Honeypot is a computer security mechanism. It is a decoy that looks and operates like a normal form field, to protect by attract and detect potential attackers. With honeypot the plugin can detect if they are being targeted by cyber threats.
            Basically, it’s a extra form field to detect whether the form filled by a genuine person or a spam-bot. The field is an invisible fields on the form. Invisible is different than hidden! Bots understand hidden fields and they will ignore it. The label is set to instruct the end user to absolutely nothing with the field and just leave it empty. The technik rely on the assumption, that an automated bot/script will complete every field in the form. However, some will get through, but not many.
            The plugin also display the honeypot field in the form in a random location. Keep moving it around between the valid fields to prevent the spambot writer to detect the field easily.'
            , 'exopite-anti-spam' ) . '</div>';

        echo '<label for="eas-activate-honeypot">';
        echo '<input id="eas-activate-honeypot" class="eas-switch" type="checkbox" name="eas-activate-honeypot" value="yes"' . $checked . '>';
        echo ' ' . esc_html__( 'Activate honeypot', 'exopite-anti-spam' ) . '</label>';

        echo '</div>';

        $checked = '';
        if ( isset( $options[0]['badwords'] ) && $options[0]['badwords'] == 'yes' ) {
            $checked = ' checked="checked"';
        }

        echo '<div class="eas-row">';

        echo '<div class="eas-row-title">' . esc_attr( 'Bad/spam words filtering', 'exopite-anti-spam' ) . '</div>';

        echo '<div class="eas-row-desc">' . esc_attr(
            'Spam emails are different from email written by humans. Most of the time significantly different. Especially using words like “vicodin” or “viagra”. Those words are useful indicators for spam. The plugin will search this words in text and textarea fiels. If any found, then it is very likely written by an automated bot/script.'
            , 'exopite-anti-spam' ) . '</div>';

        echo '<label for="eas-activate-badwords">';
        echo '<input id="eas-activate-badwords" class="eas-switch" type="checkbox" name="eas-activate-badwords" value="yes"' . $checked . '>';
        echo ' ' . esc_html__( 'Activate bad/spam words filtering', 'exopite-anti-spam' ) . '</label>';

        echo '</div>';

        $checked = '';
        if ( isset( $options[0]['ajaxload'] ) && $options[0]['ajaxload'] == 'yes' ) {
            $checked = ' checked="checked"';
        }

        echo '<div class="eas-row">';

        echo '<div class="eas-row-title">' . esc_attr( 'AJAX loading', 'exopite-anti-spam' ) . '</div>';

        echo '<div class="eas-row-desc">' . esc_attr(
            'Load image captcha and timestamp field with ajax, prevent to be cached by caching plugins. If visitor has javascript diasbled, she or he will not able to send any emails form the form.'
            , 'exopite-anti-spam' ) . '</div>';

        echo '<label for="eas-activate-ajaxload">';
        echo '<input id="eas-activate-ajaxload" class="eas-switch" type="checkbox" name="eas-activate-ajaxload" value="yes"' . $checked . '>';
        echo ' ' . esc_html__( 'Load via AJAX', 'exopite-anti-spam' ) . '</label>';

        echo '</div>';

        $checked = '';
        if ( isset( $options[0]['acceptance_ajaxcheck'] ) && $options[0]['acceptance_ajaxcheck'] == 'yes' ) {
            $checked = ' checked="checked"';
        }

        echo '<div class="eas-row">';

        echo '<div class="eas-row-title">' . esc_attr( 'Acceptance Javascript bot detection', 'exopite-anti-spam' ) . '</div>';

        echo '<div class="eas-row-desc">' . esc_attr(
            sprintf( 'On Acceptance click, the plugin will request a token via AJAX to check if the user is a bot or a human.  If visitor has javascript diasbled, she or he will not able to send any emails form the form. The %s field is required for this function!', '<code>[acceptance]</code>' )
            , 'exopite-anti-spam' ) . '</div>';

        echo '<label for="eas-acceptance-ajaxcheck">';
        echo '<input id="eas-acceptance-ajaxcheck" class="eas-switch" type="checkbox" name="eas-acceptance-ajaxcheck" value="yes"' . $checked . '>';
        echo ' ' . esc_html__( 'Check acceptance via AJAX', 'exopite-anti-spam' ) . '</label>';

        echo '</div>';

    }

    public function wpcf7_editor_panels( $panels ) {

        $panels['exopite-anti-spam-panel'] = array(
                'title' => __( 'Anti Spam', 'contact-form-7' ),
                'callback' => array( $this, 'wpcf7_editor_panel_preview' ),
        );

        return $panels;

    }

    public function wpcf7_save_contact_form( $form ) {

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! isset( $_POST ) || empty( $_POST ) ) {
            return;
        }

        $post_id = $form->id();

        if ( ! $post_id ) return;

        if ( isset( $_POST['eas-activate-honeypot'] ) ) {
            $honeypot = 'yes';
        } else {
            $honeypot = 'no';
        }

        if ( isset( $_POST['eas-activate-timestamp'] ) ) {
            $timestamp = 'yes';
        } else {
            $timestamp = 'no';
        }

        if ( isset( $_POST['eas-activate-badwords'] ) ) {
            $badwords = 'yes';
        } else {
            $badwords = 'no';
        }

        if ( isset( $_POST['eas-activate-ajaxload'] ) ) {
            $ajaxload = 'yes';
        } else {
            $ajaxload = 'no';
        }

        if ( isset( $_POST['eas-acceptance-ajaxcheck'] ) ) {
            $acceptance_ajaxcheck = 'yes';
        } else {
            $acceptance_ajaxcheck = 'no';
        }

        $anti_spam_options = array(
            'timestamp' => $timestamp,
            'honeypot' => $honeypot,
            'badwords' => $badwords,
            'ajaxload' => $ajaxload,
            'acceptance_ajaxcheck' => $acceptance_ajaxcheck,
        );

        update_post_meta( $post_id, 'exopite-anti-spam', $anti_spam_options );

        return;

    }

    /**
     * Register the administration menu for this plugin into the WordPress Dashboard menu.
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu() {

        /**
         * Add a settings page for this plugin to the Settings menu.
         *
         * NOTE:  Alternative menu locations are available via WordPress administration menu functions.
         *
         *        Administration Menus: http://codex.wordpress.org/Administration_Menus
         *
         * add_options_page( $page_title, $menu_title, $capability, $menu_slug, $function);
         *
         * @link https://codex.wordpress.org/Function_Reference/add_options_page
         */

		add_submenu_page( 'wpcf7', 'Exopite Anti Spam - Blacklist Unwanted Emails - Options', 'Blacklist', 'manage_options', $this->plugin_name, array($this, 'display_plugin_setup_page') );

    }

    /**
     * Add settings action link to the plugins page.
     *
     * @since    1.0.0
     */
    public function add_action_links( $links ) {

        /*
        *  Documentation : https://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
        */
        $settings_link = array(
            '<a href="' . admin_url( 'options-general.php?page=' . $this->plugin_name ) . '">' . __( 'Settings', $this->plugin_name ) . '</a>',
        );
        return array_merge(  $settings_link, $links );

    }

    /**
     * Render the settings page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_plugin_setup_page() {

        include_once( 'partials/' . $this->plugin_name . '-admin-display.php' );

    }

    /**
     * Validate fields from admin area plugin settings form ('exopite-lazy-load-xt-admin-display.php')
     * @param  mixed $input as field form settings form
     * @return mixed as validated fields
     */
    public function validate( $input ) {

        $options = get_option( $this->plugin_name );

        // $options['form_email_fields'] = sanitize_text_field( $input['form_email_fields'] );
        $options['list_of_block_domains'] = sanitize_textarea_field( $input['list_of_block_domains'] );
        $options['list_of_block_emails'] = sanitize_textarea_field( $input['list_of_block_emails'] );
        $options['display_error_message_email'] = sanitize_text_field( $input['display_error_message_email'] );
        $options['display_error_message_domain'] = sanitize_text_field( $input['display_error_message_domain'] );

        return $options;

    }

    public function options_update() {

        register_setting( $this->plugin_name, $this->plugin_name, array(
           'sanitize_callback' => array( $this, 'validate' ),
        ) );

    }

}
