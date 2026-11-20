<?php

/**
 * Page cache detection.
 *
 * @link       https://www.joeszalai.org
 * @since      20261006
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/includes
 */

/**
 * Cached pages contain the same single use tokens (image captcha, timestamp) for every visitor,
 * only the first submission would work, "AJAX loading" of the form is needed then.
 * - Detection of page cache plugins (filter 'exopite_anti_spam_page_cache_plugins' for server caches),
 * - first run (installation or update): "AJAX loading" is enabled for all forms if a page cache is active,
 * - a page cache activated later: admin notice with a button to enable "AJAX loading" for all forms.
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/includes
 */
class Exopite_Anti_Spam_Page_Cache {

    /**
     * The ID of this plugin (also the meta key of the form settings).
     */
    private $plugin_name;

    /**
     * Plugin main class.
     */
    public $main;

    /**
     * Detected page cache plugins, null: not detected yet.
     */
    public $plugins = null;

    public function __construct( $plugin_name, $plugin_main ) {

        $this->plugin_name = $plugin_name;
        $this->main = $plugin_main;

    }

    /**
     * Active page cache plugins. Cached pages contain the same single use tokens (image captcha, timestamp)
     * for every visitor, "AJAX loading" should be enabled then (see check()).
     * Server caches (Varnish, Cloudflare, hosting) can not be detected, they can be added with the filter:
     * add_filter( 'exopite_anti_spam_page_cache_plugins', function( $caches ) { $caches[] = 'Varnish'; return $caches; } );
     *
     * @return string[]  Names, empty if no page cache was found.
     */
    public function get_plugins() {

        if ( null === $this->plugins ) {

            $caches = array();

            if ( defined( 'WP_ROCKET_VERSION' ) ) {
                $caches[] = 'WP Rocket';
            }

            // WP Fastest Cache works without WP_CACHE (rewrite rules), it caches only if it is enabled in its settings.
            if ( class_exists( 'WpFastestCache' ) ) {
                $wpfc = json_decode( (string) get_option( 'WpFastestCache' ) );
                if ( is_object( $wpfc ) && ! empty( $wpfc->wpFastestCacheStatus ) ) {
                    $caches[] = 'WP Fastest Cache';
                }
            }

            if ( defined( 'LSCWP_V' ) ) {
                $caches[] = 'LiteSpeed Cache';
            }

            // Page cache plugins with advanced-cache.php set WP_CACHE (only then they cache).
            if ( defined( 'WP_CACHE' ) && WP_CACHE && ! $caches ) {

                $known = array(
                    'W3 Total Cache' => defined( 'W3TC' ),
                    'WP Super Cache' => defined( 'WPCACHEHOME' ),
                    'Cache Enabler'  => class_exists( 'Cache_Enabler' ),
                    'WP-Optimize'    => defined( 'WPO_VERSION' ),
                    'Breeze'         => defined( 'BREEZE_VERSION' ),
                    'Hummingbird'    => defined( 'WPHB_VERSION' ),
                );

                $caches = array_keys( array_filter( $known ) );

                if ( ! $caches ) {
                    $caches[] = 'WP_CACHE (advanced-cache.php)';
                }
            }

            $this->plugins = array_values( array_unique( array_filter( (array) apply_filters( 'exopite_anti_spam_page_cache_plugins', $caches ), 'is_string' ) ) );
        }

        return $this->plugins;
    }

    public function is_active() {

        return (bool) $this->get_plugins();
    }

    /**
     * Page cache plugins (cached pages contain the same single use tokens for every visitor, "AJAX loading" is needed):
     * - first run (installation or update to this version): "AJAX loading" is enabled for all forms if a page cache is active,
     * - a page cache activated later: admin notice (until it is dismissed or "AJAX loading" is enabled for all forms).
     * The detected caches are stored in the option 'exopite_anti_spam_page_cache'.
     */
    public function check() {

        // Not in AJAX requests (admin_init runs there too) and not for other users (e.g. subscribers in the profile), the next admin page view of an admin does it.
        if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) || ! class_exists( 'WPCF7_ContactForm' ) ) {
            return;
        }

        $caches = $this->get_plugins();
        sort( $caches );
        $current = implode( ', ', $caches );
        $stored = get_option( 'exopite_anti_spam_page_cache', null );

        if ( null === $stored ) {

            update_option( 'exopite_anti_spam_page_cache', $current, false );

            if ( '' !== $current ) {
                $this->enable_ajaxload_for_all_forms();
            }

            return;
        }

        if ( $current === $stored ) {
            return;
        }

        update_option( 'exopite_anti_spam_page_cache', $current, false );

        if ( '' !== $current && $this->get_forms_without_ajaxload() ) {
            update_option( 'exopite_anti_spam_page_cache_notice', $current, false );
        } else {
            delete_option( 'exopite_anti_spam_page_cache_notice' );
        }
    }

    /**
     * Post IDs of the forms without "AJAX loading".
     */
    public function get_forms_without_ajaxload() {

        $ids = array();

        foreach ( WPCF7_ContactForm::find( array( 'post_status' => 'any' ) ) as $contact_form ) {
            $options = get_post_meta( $contact_form->id(), $this->plugin_name, true );
            if ( ! is_array( $options ) || ! isset( $options['ajaxload'] ) || 'yes' !== $options['ajaxload'] ) {
                $ids[] = $contact_form->id();
            }
        }

        return $ids;
    }

    /**
     * Enable "AJAX loading" for all forms. Forms without saved settings get only this setting (the other settings keep their defaults).
     *
     * @return int  Number of changed forms.
     */
    public function enable_ajaxload_for_all_forms() {

        $ids = $this->get_forms_without_ajaxload();

        foreach ( $ids as $id ) {
            $options = get_post_meta( $id, $this->plugin_name, true );
            $options = is_array( $options ) ? $options : array();
            $options['ajaxload'] = 'yes';
            update_post_meta( $id, $this->plugin_name, $options );
        }

        return count( $ids );
    }

    /**
     * Buttons of the page cache notice: enable "AJAX loading" for all forms or dismiss the notice.
     */
    public function handle_notice() {

        if ( ! isset( $_GET['eas-page-cache'] ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        check_admin_referer( 'eas-page-cache' );

        $count = 0;

        if ( 'enable' === $_GET['eas-page-cache'] && class_exists( 'WPCF7_ContactForm' ) ) {
            $count = $this->enable_ajaxload_for_all_forms();
        }

        delete_option( 'exopite_anti_spam_page_cache_notice' );

        $url = remove_query_arg( array( 'eas-page-cache', '_wpnonce' ) );
        if ( 'enable' === $_GET['eas-page-cache'] ) {
            $url = add_query_arg( 'eas-ajaxload-enabled', $count, $url );
        }

        wp_safe_redirect( $url );
        exit;
    }

    /**
     * WordPress removes the parameter from the address bar after the success notice.
     */
    public function removable_query_args( $args ) {
        $args[] = 'eas-ajaxload-enabled';
        return $args;
    }

    public function admin_notices() {

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_GET['eas-ajaxload-enabled'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf(
                /* translators: %d: number of forms */
                __( 'Exopite Anti Spam: "AJAX loading" was enabled for %d contact forms.', 'exopite-anti-spam' ),
                absint( $_GET['eas-ajaxload-enabled'] )
            ) ) . '</p></div>';
        }

        $caches = (string) get_option( 'exopite_anti_spam_page_cache_notice', '' );

        if ( '' === $caches ) {
            return;
        }

        $enable_url = wp_nonce_url( add_query_arg( 'eas-page-cache', 'enable' ), 'eas-page-cache' );
        $dismiss_url = wp_nonce_url( add_query_arg( 'eas-page-cache', 'dismiss' ), 'eas-page-cache' );

        echo '<div class="notice notice-warning"><p><strong>Exopite Anti Spam:</strong> ' . esc_html( sprintf(
            /* translators: %s: names of the cache plugins */
            __( 'A page cache was activated (%s). On cached pages all visitors get the same single use tokens of the image captcha and the timestamp, only the first submission would work. Please enable "AJAX loading" for the contact forms.', 'exopite-anti-spam' ),
            $caches
        ) ) . '</p><p>';
        echo '<a class="button button-primary" href="' . esc_url( $enable_url ) . '">' . esc_html__( 'Enable "AJAX loading" for all forms', 'exopite-anti-spam' ) . '</a> ';
        echo '<a class="button" href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Dismiss', 'exopite-anti-spam' ) . '</a>';
        echo '</p></div>';
    }

}
