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

    public $min_time_recommended = 2;
    public $max_time_recommended = 10;

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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/exopite-anti-spam-admin.css', array(), $this->version, 'all' );

	}

	public function enqueue_scripts() {

        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/exopite-anti-spam-admin.js', array( 'jquery' ), $this->version, true );

	}

    public function check_dependencies() {
        if ( current_user_can( 'activate_plugins' ) && ! class_exists( 'WPCF7' ) ) {
            add_action( 'admin_notices', array( $this, 'admin_notices_cf7_required' ) );
        }
    }

    public function wpcf7_admin_init() {

        if ( ! class_exists( 'WPCF7_TagGenerator' ) ) {
            return;
        }

        $tag_generator = WPCF7_TagGenerator::get_instance();

        /**
         * The 3rd parameter must be a callable, otherwise CF7 does not register the button.
         * CF7 6+: tag generator version 2 (dialog), older versions: version 1 (thickbox).
         */
        if ( class_exists( 'WPCF7_TagGeneratorGenerator' ) ) {
            $tag_generator->add( 'easimagecaptcha', esc_attr__( 'image captcha', 'exopite-anti-spam' ), array( $this, 'cf7_tag_generator_v2' ), array( 'version' => '2' ) );
        } else {
            $tag_generator->add( 'easimagecaptcha', esc_attr__( 'image captcha', 'exopite-anti-spam' ), array( $this, 'cf7_tag_generator' ), array( 'nameless' => 1 ) );
        }

    }

    /**
     * Tag generator dialog for CF7 6+, creates e.g. [easimagecaptcha icon:5 choose:2]
     * (the tag needs no name, the field name is always "exanspsel").
     */
    public function cf7_tag_generator_v2( $contact_form, $options ) {

        $tgg = new WPCF7_TagGeneratorGenerator( $options['content'] );

        ?>
        <header class="description-box">
            <h3><?php esc_html_e( 'Image captcha form-tag generator', 'exopite-anti-spam' ); ?></h3>
            <p><?php esc_html_e( 'Generates a form-tag for an image captcha: the visitor has to select the named icons. Further anti spam settings of the form are in the "Anti Spam" tab.', 'exopite-anti-spam' ); ?></p>
        </header>

        <div class="control-box">
            <?php
            $tgg->print( 'field_type', array(
                'select_options' => array(
                    'easimagecaptcha' => __( 'Image Captcha', 'exopite-anti-spam' ),
                ),
            ) );
            ?>

            <fieldset>
                <legend id="<?php echo esc_attr( $tgg->ref( 'icon-legend' ) ); ?>"><?php esc_html_e( 'Number of icons (2-10)', 'exopite-anti-spam' ); ?></legend>
                <input type="number" data-tag-part="option" data-tag-option="icon:" value="5" min="2" max="10" aria-labelledby="<?php echo esc_attr( $tgg->ref( 'icon-legend' ) ); ?>" />
            </fieldset>

            <fieldset>
                <legend id="<?php echo esc_attr( $tgg->ref( 'choose-legend' ) ); ?>"><?php esc_html_e( 'Icons to select (less than the number of icons)', 'exopite-anti-spam' ); ?></legend>
                <input type="number" data-tag-part="option" data-tag-option="choose:" value="2" min="1" max="9" aria-labelledby="<?php echo esc_attr( $tgg->ref( 'choose-legend' ) ); ?>" />
            </fieldset>
        </div>

        <footer class="insert-box">
            <?php $tgg->print( 'insert_box_content' ); ?>
        </footer>
        <?php

    }

    /**
     * Tag generator for CF7 older than 6 (version 1).
     */
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
                    /* translators: 1: plugin name, 2: install link */
                    esc_html__( 'In order to %1$s work, Contact Form 7 needs to be installed and activated. %2$s', 'exopite-anti-spam' ),
                    '<strong>' . esc_html( EXOPITE_ANTI_SPAM_PLUGIN_NICE_NAME ) . '</strong>',
                    '<a href="' . esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=contact-form-7&from=plugins&TB_iframe=true&width=600&height=550' ) ) . '" class="thickbox" title="Contact Form 7">' . esc_html__( 'Install Now.', 'exopite-anti-spam' ) . '</a>'
                );

                ?>
            </p>
        </div>
        <?php

    }

    /**
     * Output one switch row of the "Anti Spam" panel.
     */
    public function panel_switch_row( $id, $title, $description, $label, $checked, $extra = '' ) {

        echo '<div class="eas-row">';
        echo '<div class="eas-row-title">' . esc_html( $title ) . '</div>';
        echo '<div class="eas-row-desc">' . $description . '</div>';
        echo '<label for="' . esc_attr( $id ) . '">';
        echo '<input id="' . esc_attr( $id ) . '" class="eas-switch" type="checkbox" name="' . esc_attr( $id ) . '" value="yes"' . checked( $checked, true, false ) . '>';
        echo ' ' . esc_html( $label ) . '</label>';
        echo $extra;
        echo '</div>';

    }

    public function wpcf7_editor_panel_preview() {

        // New (not yet saved) form: no post ID, defaults are used.
        $form_id = isset( $_GET['post'] ) ? intval( $_GET['post'] ) : 0;
        $options = $form_id ? get_post_meta( $form_id, 'exopite-anti-spam' ) : array();

        $timestamp_min = $this->min_time_recommended;
        if ( isset( $options[0]['timestamp_min'] ) ) {
            $timestamp_min = intval( $options[0]['timestamp_min'] );
        }

        $timestamp_max = $this->max_time_recommended;
        if ( isset( $options[0]['timestamp_max'] ) ) {
            $timestamp_max = intval( $options[0]['timestamp_max'] );
        }

        // Timestamp
        $checked = isset( $options[0]['timestamp'] ) ? ( $options[0]['timestamp'] == 'yes' ) : (bool) apply_filters( 'exopite_enable_timestamp', false );

        $extra  = '<div class="eas-row eas-timestamp-values" style="display:none;">';
        $extra .= '<div class="eas-col-6">';
        $extra .= '<label for="eas-activate-timestamp-min">' . esc_html__( 'Min (seconds):', 'exopite-anti-spam' ) . '</label> <input id="eas-activate-timestamp-min" type="number" name="eas-activate-timestamp-min" value="' . esc_attr( $timestamp_min ) . '" min="1" max="60">';
        $extra .= '</div>';
        $extra .= '<div class="eas-col-6">';
        $extra .= '<label for="eas-activate-timestamp-max">' . esc_html__( 'Max (minutes):', 'exopite-anti-spam' ) . '</label> <input id="eas-activate-timestamp-max" type="number" name="eas-activate-timestamp-max" value="' . esc_attr( $timestamp_max ) . '" min="1" max="1440">';
        $extra .= '</div>';
        $extra .= '</div>';

        $this->panel_switch_row(
            'eas-activate-timestamp',
            __( 'Timestamp', 'exopite-anti-spam' ),
            esc_html__( 'Adds a hidden, encrypted timestamp to the form. On submission the plugin checks the time elapsed since the form was displayed: if it is less than the minimum (seconds) or more than the maximum (minutes) set below, the submission is rejected, because a bot "types" much faster than a human. If the time has expired, a new timestamp is loaded automatically and the visitor can submit the form again without losing the entered data.', 'exopite-anti-spam' ),
            __( 'Activate timestamp', 'exopite-anti-spam' ),
            $checked,
            $extra
        );

        // Honeypot
        $checked = isset( $options[0]['honeypot'] ) ? ( $options[0]['honeypot'] == 'yes' ) : (bool) apply_filters( 'exopite_enable_honeypot', false );

        $this->panel_switch_row(
            'eas-activate-honeypot',
            __( 'Honeypot', 'exopite-anti-spam' ),
            esc_html__( 'Adds an extra field to the form, which is invisible for humans, but bots usually fill it out. If the field is not empty, the submission is rejected. The field is placed at a random location in the form, so it is harder for spam bots to detect it.', 'exopite-anti-spam' ),
            __( 'Activate honeypot', 'exopite-anti-spam' ),
            $checked
        );

        // Bad words
        $checked = isset( $options[0]['badwords'] ) ? ( $options[0]['badwords'] == 'yes' ) : (bool) apply_filters( 'exopite_enable_badwords', false );

        $this->panel_switch_row(
            'eas-activate-badwords',
            __( 'Bad/spam words filtering', 'exopite-anti-spam' ),
            esc_html__( 'Spam messages often contain words like "viagra" or "vicodin". The plugin searches these words in text and textarea fields, if any is found, the submission is rejected. Own words can be added on the "Blacklist" settings page (Contact menu).', 'exopite-anti-spam' ),
            __( 'Activate bad/spam words filtering', 'exopite-anti-spam' ),
            $checked
        );

        // AJAX loading
        $checked = ( isset( $options[0]['ajaxload'] ) && $options[0]['ajaxload'] == 'yes' );

        $this->panel_switch_row(
            'eas-activate-ajaxload',
            __( 'AJAX loading', 'exopite-anti-spam' ),
            esc_html__( 'Loads the image captcha and the timestamp via AJAX, so they are not cached by caching plugins. Recommended if the page is cached. Visitors with JavaScript disabled can not send the form.', 'exopite-anti-spam' ),
            __( 'Load via AJAX', 'exopite-anti-spam' ),
            $checked
        );

        // Acceptance
        $checked = ( isset( $options[0]['acceptance_ajaxcheck'] ) && $options[0]['acceptance_ajaxcheck'] == 'yes' );

        $this->panel_switch_row(
            'eas-acceptance-ajaxcheck',
            __( 'Acceptance JavaScript bot detection', 'exopite-anti-spam' ),
            sprintf(
                /* translators: %s: [acceptance] form tag */
                esc_html__( 'When the acceptance checkbox is clicked, the plugin requests a single use token via AJAX to check if the visitor is a human. Visitors with JavaScript disabled can not send the form. The %s field is required for this function!', 'exopite-anti-spam' ),
                '<code>[acceptance]</code>'
            ),
            __( 'Check acceptance via AJAX', 'exopite-anti-spam' ),
            $checked
        );

        // Rate limit
        $checked = $this->main->public->is_ratelimit_active( isset( $options[0] ) ? $options[0] : false );
        $ratelimit = $this->main->public->get_ratelimit_settings();

        $this->panel_switch_row(
            'eas-activate-ratelimit',
            __( 'Limit failed attempts', 'exopite-anti-spam' ),
            esc_html( sprintf(
                /* translators: 1: max failed attempts, 2: time window in minutes, 3: block time in minutes */
                __( 'After %1$d failed attempts (e.g. wrong image captcha, honeypot, token error) within %2$d minutes, the IP address of the visitor is blocked for %3$d minutes. This prevents bots from guessing the image captcha. The IP address is only stored as a hash in a temporary entry. Deactivate this, if the website is behind a proxy or CDN, which does not pass the IP address of the visitors (all visitors would have the same IP address).', 'exopite-anti-spam' ),
                $ratelimit['max_failures'],
                $ratelimit['window'] / MINUTE_IN_SECONDS,
                $ratelimit['block'] / MINUTE_IN_SECONDS
            ) ),
            __( 'Activate limit of failed attempts', 'exopite-anti-spam' ),
            $checked
        );

    }

    public function wpcf7_editor_panels( $panels ) {

        $panels['exopite-anti-spam-panel'] = array(
                'title' => __( 'Anti Spam', 'exopite-anti-spam' ),
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

        $timestamp_min = $this->min_time_recommended;
        if ( isset( $_POST['eas-activate-timestamp-min'] ) ) {

            $timestamp_min_int = intval( $_POST['eas-activate-timestamp-min'] );

            if ( $timestamp_min_int > 0 && $timestamp_min_int < 61 ) {
                $timestamp_min = $timestamp_min_int;
            }

        }

        $timestamp_max = $this->max_time_recommended;
        if ( isset( $_POST['eas-activate-timestamp-max'] ) ) {

            $timestamp_max_int = intval( $_POST['eas-activate-timestamp-max'] );

            if ( $timestamp_max_int > 0 && $timestamp_max_int < 1441 ) {
                $timestamp_max = $timestamp_max_int;
            }

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

        $ratelimit = isset( $_POST['eas-activate-ratelimit'] ) ? 'yes' : 'no';

        $anti_spam_options = array(
            'timestamp'             => $timestamp,
            'timestamp_min'         => $timestamp_min,
            'timestamp_max'         => $timestamp_max,
            'honeypot'              => $honeypot,
            'badwords'              => $badwords,
            'ajaxload'              => $ajaxload,
            'acceptance_ajaxcheck'  => $acceptance_ajaxcheck,
            'ratelimit'             => $ratelimit,
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
        if ( current_user_can( 'manage_options' ) ) {
            add_submenu_page('wpcf7', 'Exopite Anti Spam - Blacklist Unwanted Emails - Options', 'Blacklist', 'manage_options', $this->plugin_name, array($this, 'display_plugin_setup_page'));

        }


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

        if ( ! is_array( $options ) ) {
            $options = array();
        }

        $textarea_fields = array( 'list_of_block_domains', 'list_of_block_emails', 'custom_spam_words' );
        $text_fields = array( 'display_error_message_email', 'display_error_message_domain' );

        foreach ( $textarea_fields as $field ) {
            $options[ $field ] = isset( $input[ $field ] ) && is_string( $input[ $field ] ) ? sanitize_textarea_field( $input[ $field ] ) : '';
        }

        foreach ( $text_fields as $field ) {
            $options[ $field ] = isset( $input[ $field ] ) && is_string( $input[ $field ] ) ? sanitize_text_field( $input[ $field ] ) : '';
        }

        return $options;

    }

    public function options_update() {

        if ( current_user_can( 'manage_options' ) ) {
            register_setting( $this->plugin_name, $this->plugin_name, array(
               'sanitize_callback' => array( $this, 'validate' ),
            ) );
        }


    }

}
