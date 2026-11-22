<?php

/**
 * Rate limit: block visitors after too many failed attempts.
 *
 * @link       https://www.joeszalai.org
 * @since      20261006
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/public
 */

/**
 * Failed attempts (anti spam checks or CF7 spam status) are counted per visitor in a transient,
 * only a hash of the IP address is stored. After too many failed attempts within the time window
 * the visitor is blocked for a while.
 *
 * Disabled by default, can be enabled per form ("Anti Spam" tab) or with the 'exopite_enable_ratelimit' filter.
 * Filters: 'exopite_anti_spam_ratelimit_max_failures', 'exopite_anti_spam_ratelimit_window',
 * 'exopite_anti_spam_ratelimit_block', 'exopite_anti_spam_ratelimit_ip', 'exopite_anti_spam_ratelimit_allow_private_ip'.
 */
class Exopite_Anti_Spam_Public_Rate_Limiter {

    /**
     * Plugin main class.
     */
    public $main;

    /**
     * An anti spam check failed in this request (set by Exopite_Anti_Spam_Public_Validate_Fields::invalidate_log()).
     */
    public $failed = false;

    /**
     * The visitor is blocked, the submission was rejected because of the rate limit.
     */
    public $blocked = false;

    public function __construct( $plugin_main ) {

        $this->main = $plugin_main;

    }

    /** Defaults: failed attempts, time window (minutes), block (minutes) */
    const DEFAULT_MAX_FAILURES   = 5;
    const DEFAULT_WINDOW_MINUTES = 10;
    const DEFAULT_BLOCK_MINUTES  = 30;

    /**
     * Rate limit settings of the form ("Anti Spam" tab: attempts, window and block in minutes),
     * the defaults for forms saved before these fields existed. The filters get the value of the
     * form (window and block in seconds) and can still change it.
     *
     * @param array|false|null $options  Form settings, null: settings of the current form.
     * @return array max_failures, window (seconds), block (seconds)
     */
    public function get_settings( $options = null ) {

        if ( null === $options ) {
            $options = $this->main->public->get_cf7_meta();
        }

        $max    = isset( $options['ratelimit_max'] ) ? intval( $options['ratelimit_max'] ) : self::DEFAULT_MAX_FAILURES;
        $window = isset( $options['ratelimit_window'] ) ? intval( $options['ratelimit_window'] ) : self::DEFAULT_WINDOW_MINUTES;
        $block  = isset( $options['ratelimit_block'] ) ? intval( $options['ratelimit_block'] ) : self::DEFAULT_BLOCK_MINUTES;

        return array(
            'max_failures' => max( 1, intval( apply_filters( 'exopite_anti_spam_ratelimit_max_failures', max( 1, $max ) ) ) ),
            'window'       => max( MINUTE_IN_SECONDS, intval( apply_filters( 'exopite_anti_spam_ratelimit_window', max( 1, $window ) * MINUTE_IN_SECONDS ) ) ),
            'block'        => max( MINUTE_IN_SECONDS, intval( apply_filters( 'exopite_anti_spam_ratelimit_block', max( 1, $block ) * MINUTE_IN_SECONDS ) ) ),
        );

    }

    /**
     * Default: disabled if the form settings were never saved, can be changed with the 'exopite_enable_ratelimit' filter.
     *
     * @param array|false|null $options  Form settings, null: settings of the current form.
     */
    public function is_active( $options = null ) {

        if ( null === $options ) {
            $options = $this->main->public->get_cf7_meta();
        }

        return isset( $options['ratelimit'] ) ? ( $options['ratelimit'] === 'yes' ) : (bool) apply_filters( 'exopite_enable_ratelimit', false );
    }

    /**
     * Transient name for the visitor, only a hash of the IP address is stored.
     * IPv6: a visitor usually has a whole /64 network, the addresses of it are counted together.
     */
    public function get_key() {

        $ip = $this->get_ip();

        if ( false !== strpos( $ip, ':' ) ) {
            $packed = @inet_pton( $ip );
            if ( false !== $packed && 16 === strlen( $packed ) ) {
                $ip = bin2hex( substr( $packed, 0, 8 ) ) . '/64';
            }
        }

        return 'eas_rl_' . md5( wp_salt( 'nonce' ) . $ip );
    }

    /**
     * IP address for the rate limit, empty if it can not identify a visitor:
     * behind a reverse proxy on the same server or in the local network, REMOTE_ADDR is a loopback/private
     * address, the same for all visitors, the rate limit would block all of them.
     * Filters: 'exopite_anti_spam_visitor_ip' (all IP checks of the plugin) and 'exopite_anti_spam_ratelimit_ip'
     * (only the rate limit), e.g. the real IP from a trusted proxy header,
     * 'exopite_anti_spam_ratelimit_allow_private_ip' (true: use private addresses too, e.g. for local tests).
     */
    public function get_ip() {

        $ip = (string) apply_filters( 'exopite_anti_spam_ratelimit_ip', $this->main->public->get_visitor_ip() );

        if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return '';
        }

        $public = false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );

        if ( ! $public && ! apply_filters( 'exopite_anti_spam_ratelimit_allow_private_ip', false, $ip ) ) {
            return '';
        }

        return $ip;
    }

    /**
     * Not for logged in users, only if enabled for the form and the visitor can be identified.
     */
    public function applies() {

        return ! $this->main->public->is_user_logged_in() && $this->is_active() && '' !== $this->get_ip();
    }

    /**
     * Reject the submission, if the visitor is blocked (too many failed attempts).
     * Hook: wpcf7_validate (priority 5).
     */
    public function wpcf7_validate( $result, $tags ) {

        if ( ! $this->applies() ) {
            return $result;
        }

        $data = get_transient( $this->get_key() );

        if ( is_array( $data ) && ! empty( $data['blocked_until'] ) && $data['blocked_until'] > time() ) {

            $this->blocked = true;
            $this->main->validate->invalidate_log( 'rate limit: too many failed attempts, blocked until ' . wp_date( 'Y-m-d H:i:s', $data['blocked_until'] ), false );

            $result->invalidate( $this->main->public->get_first_named_tag( $tags ), esc_attr__( 'Too many failed attempts. Please try again in a few minutes.', 'exopite-anti-spam' ) );

        }

        return $result;
    }

    /**
     * Count failed attempts (anti spam checks or CF7 spam status), block the visitor after too many.
     * Rejections because of the block itself are not counted, so the block is not extended.
     * Hook: wpcf7_submit.
     */
    public function wpcf7_submit( $contact_form, $result ) {

        if ( $this->blocked || ! $this->applies() ) {
            return;
        }

        $status = isset( $result['status'] ) ? $result['status'] : '';

        if ( 'mail_sent' === $status || ( ! $this->failed && 'spam' !== $status ) ) {
            return;
        }

        $settings = $this->get_settings();
        $key = $this->get_key();
        $now = time();

        $data = get_transient( $key );

        if ( ! is_array( $data ) || empty( $data['start'] ) || $data['start'] < ( $now - $settings['window'] ) ) {
            $data = array( 'count' => 0, 'start' => $now, 'blocked_until' => 0 );
        }

        $data['count']++;

        if ( $data['count'] >= $settings['max_failures'] ) {

            $data['blocked_until'] = $now + $settings['block'];
            $this->main->validate->invalidate_log( 'rate limit: ' . $data['count'] . ' failed attempts, blocked for ' . ( $settings['block'] / MINUTE_IN_SECONDS ) . ' minutes', false );

        }

        $expiration = $data['blocked_until'] ? max( $settings['window'], $data['blocked_until'] - $now ) : $settings['window'];
        set_transient( $key, $data, $expiration );

    }

}
