<?php

/**
 * Validation of the anti spam fields.
 *
 * @link       https://www.joeszalai.org
 * @since      20261006
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/public
 */

/**
 * Checks the anti spam fields on submission (Contact Form 7 validation filters):
 * honeypot, timestamp, image captcha, acceptance token, bad/spam words, e-mail and domain blacklist.
 * The fields are rendered by Exopite_Anti_Spam_Public_Fields.
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/public
 * @author     Joe Szalai <contact@joeszalai.org>
 */
class Exopite_Anti_Spam_Public_Validate_Fields {

    /**
     * The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     */
    private $version;

    /**
     * Plugin main class.
     */
    public $main;

    /**
     * Built-in spam word list, loaded on first use.
     */
    public $words = false;

    /**
     * Min (seconds) and max (seconds) time between displaying and submitting the form, from the form settings.
     */
    public $min_time = 2;
    public $max_time = 600;

    /**
     * Timestamp token of the current submission, saved as used after the mail was sent.
     */
    public $timestamp_token = false;

    /**
     * Reasons, why the submission is spam (bot), see add_spam() and wpcf7_spam().
     */
    public $spam_reasons = array();

    /**
     * Stable reason codes of the current submission (statistics and quarantine), see get_codes().
     */
    public $spam_codes = array();
    public $invalid_codes = array();

    /**
     * The timestamp is expired or already used, the JS should load a new one,
     * so the visitor can submit again without reloading the page (and losing the entered data).
     */
    public $refresh_timestamp = false;

    /**
     * Validation errors before "Conditional Fields for CF7" rebuilds the result.
     */
    public $invalid_before_cf7cf = array();

    /**
     * Fields invalidated by the bad words and e-mail checks (restored if CF7CF removes them).
     */
    public $invalidated_fields = array();

    public function __construct( $plugin_name, $version, $plugin_main ) {

        $this->main = $plugin_main;
        $this->plugin_name = $plugin_name;
        $this->version = $version;

    }

    /**
     * Main validation (after the validation of the form tags and after "Conditional Fields for CF7").
     * The timestamp and the acceptance token are hidden fields (wpcf7_form_hidden_fields), not form tags, they are checked here.
     * Bot submissions (honeypot, missing or manipulated encrypted data) are marked as spam (see wpcf7_spam()),
     * errors a visitor can fix (too fast, expired, already used) are validation errors.
     */
    public function wpcf7_validate( $result, $tags ) {

        // Also while a quarantined submission is released (the anti spam fields are not part of it).
        if ( $this->main->public->skip_checks() ) {
            return $result;
        }

        // The anti spam fields were not added to the form in this request (e.g. another REST route): their checks did not run.
        if ( ! $this->main->fields->should_inject_fields() ) {

            $route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
            $this->add_spam( 'submission without the anti spam fields ' . $route, 'fields_missing' );

            return $result;
        }

        $result = $this->validate_timestamp( $result, $tags );
        $result = $this->validate_acceptance_token( $result, $tags );

        return $result;

    }

    /**
     * A bot submission: honeypot filled, encrypted data missing or manipulated.
     * The submission gets the Contact Form 7 status "spam" (see wpcf7_spam()), counts as failed attempt for the rate limit.
     *
     * @param string $reason  Text for the logs.
     * @param string $code    Stable reason code for statistics and quarantine (e.g. 'honeypot').
     */
    public function add_spam( $reason, $code = '' ) {

        $this->spam_reasons[] = $reason;

        if ( '' !== $code ) {
            $this->spam_codes[] = $code;
        }
        $this->main->ratelimiter->failed = true;

        if ( $this->main->public->is_logging() ) {
            Exopite_Anti_Spam_Logger::write( 'wpcf7_spam', PHP_EOL . wp_date( 'Y-m-d H:i:s' ) . ' - ' . Exopite_Anti_Spam_Logger::anonymize_ip( $this->main->public->get_visitor_ip() ) . ' - ' . $reason . PHP_EOL . var_export( $this->main->public->get_masked_post_data(), true ) . PHP_EOL . '---' . PHP_EOL );
        }

    }

    /**
     * Contact Form 7 spam filter (like Akismet, reCAPTCHA, Turnstile): status "spam", the reasons are added to the
     * spam log of the submission (shown e.g. in Flamingo).
     * Contact Form 7 runs the spam check only if the validation was successful.
     *
     * @link https://contactform7.com/2020/07/18/custom-spam-filtering/
     */
    public function wpcf7_spam( $spam, $submission = null ) {

        if ( empty( $this->spam_reasons ) ) {
            return $spam;
        }

        if ( $submission instanceof WPCF7_Submission ) {
            foreach ( array_unique( $this->spam_reasons ) as $reason ) {
                $submission->add_spam_log( array(
                    'agent'  => $this->plugin_name,
                    'reason' => $reason,
                ) );
            }
        }

        return true;
    }

    /**
     * Error of a hidden field (timestamp, acceptance token): shown at the given or at the first named field of the form.
     */
    public function invalidate_visible( $result, $tags, $message, $tag = null ) {

        $result->invalidate( $tag ? $tag : $this->main->public->get_first_named_tag( $tags ), $message );

        return $result;
    }

    /**
     * Compatibility with "Conditional Fields for Contact Form 7" (CF7CF).
     *
     * If the form has hidden groups, CF7CF rebuilds the validation result on 'wpcf7_validate' (priority 2)
     * and keeps only the errors of named form tags. The fields of this plugin (image captcha, timestamp,
     * honeypot, acceptance token) are nameless tags, so their errors would be lost.
     * The errors are saved here (priority 1) and restored on 'wpcf7cf_validate'.
     */
    public function wpcf7_validate_snapshot( $result, $tags ) {

        $this->invalid_before_cf7cf = $result->get_invalid_fields();

        return $result;
    }

    /**
     * Restore the errors of this plugin removed by CF7CF (anti spam fields, bad words, e-mail blacklist). They are never skipped as "hidden":
     * the list of hidden fields is sent by the browser (a bot can send any field names), and the
     * anti spam fields must not be inside a [group] (the image captcha has to be placed outside of all groups).
     * The validation itself is not run again here (CF7CF calls this filter for every form,
     * also for forms without image captcha).
     */
    public function wpcf7cf_validate( $result, $tags ) {

        if ( empty( $this->invalid_before_cf7cf ) || ! is_array( $this->invalid_before_cf7cf ) ) {
            return $result;
        }

        // Also the bad words and e-mail errors: a bot could send the names of these fields as "hidden".
        $own_fields = array_unique( array_merge( array( 'exanspsel', 'eastimestamp', 'easacceptance', $this->main->honeypot_name ), $this->invalidated_fields ) );

        foreach ( $own_fields as $field ) {

            if ( ! isset( $this->invalid_before_cf7cf[ $field ]['reason'] ) || ! $result->is_valid( $field ) ) {
                continue;
            }

            $result->invalidate( array( 'name' => $field ), $this->invalid_before_cf7cf[ $field ]['reason'] );

        }

        return $result;
    }

    /**
     * Tell the JS to load a new timestamp (expired, already used, too fast or too slow),
     * so the visitor can submit again without reloading the page.
     */
    public function wpcf7_feedback_response( $response, $result ) {

        if ( $this->refresh_timestamp ) {
            $response['eas_refresh_timestamp'] = true;
        }

        return $response;
    }

    /**
     * Acceptance token (hidden field "easacceptance", requested by the JS when an acceptance checkbox is checked).
     * Missing or manipulated token: spam. Errors a visitor can fix are shown at the checked acceptance checkbox.
     */
    public function validate_acceptance_token( $result, $tags ) {

        // Same logic as for the hidden field: no validation if the field is not rendered.
        if ( ! $this->main->fields->is_acceptance_ajaxcheck_active() ) {
            return $result;
        }

        // The token is requested when an acceptance checkbox is checked: no checked checkbox (optional acceptance), no token.
        $acceptance_tag = $this->get_checked_acceptance_tag();

        if ( ! $acceptance_tag ) {
            return $result;
        }

        if ( empty( $_POST['easacceptance'] ) ) {

            $this->add_spam( 'acceptance token empty', 'acceptance' );

            return $result;
        }

        $token_acceptance = $this->main->crypter->decrypt_hex( $_POST['easacceptance'] );

        if ( ! $token_acceptance ) {

            $this->add_spam( 'acceptance token can not be decrypted', 'acceptance' );

            return $result;
        }

        $token_acceptance = json_decode( $token_acceptance );
        $submit_ip = $this->main->public->get_visitor_ip();

        if ( ! is_array( $token_acceptance ) || count( $token_acceptance ) < 3 ) {

            $this->add_spam( 'acceptance token is invalid', 'acceptance' );

            return $result;
        }

        if ( $token_acceptance[1] != $submit_ip ) {

            $this->invalidate_log( 'acceptance IP mismatch ' . Exopite_Anti_Spam_Logger::anonymize_ip( $submit_ip ) . ' != ' . Exopite_Anti_Spam_Logger::anonymize_ip( $token_acceptance[1] ), false, 'token_used' );
            $result->invalidate( $acceptance_tag, esc_attr__( "Validation errors occurred", 'contact-form-7' ) . ' (03)' );

            return $result;
        }

        // The token is requested on checkbox click, the minimum time is checked by "Timestamp".
        if ( ( time() - intval( $token_acceptance[2] ) ) > $this->main->tokens->get_max_age( 'acceptance' ) ) {

            $this->invalidate_log( 'acceptance token expired, created: ' . wp_date( 'Y-m-d H:i:s', intval( $token_acceptance[2] ) ), false, 'expired' );
            $result->invalidate( $acceptance_tag, esc_attr__( 'Your session has timed out. Please submit the form again.', 'exopite-anti-spam' ) );

            return $result;
        }

        // Single use, the JS requests a new token after an unsuccessful submission.
        if ( ! $this->main->tokens->consume( $token_acceptance[0], 'acceptance' ) ) {

            $this->invalidate_log( 'acceptance token already used', true, 'token_used' );
            $result->invalidate( $acceptance_tag, esc_attr__( "Token error.", 'exopite-anti-spam' ) . ' (13)' );

            return $result;
        }

        return $result;

    }

    /**
     * First checked [acceptance] checkbox of the current form, null if none.
     */
    public function get_checked_acceptance_tag() {

        $contact_form = WPCF7_ContactForm::get_current();

        if ( ! $contact_form ) {
            return null;
        }

        foreach ( $contact_form->scan_form_tags( array( 'type' => array( 'acceptance' ) ) ) as $acceptance ) {
            if ( ! empty( $acceptance->name ) && ! empty( $_POST[ $acceptance->name ] ) ) {
                return $acceptance;
            }
        }

        return null;
    }

    /**
     * Is any [acceptance] checkbox of the current form checked?
     */
    public function is_any_acceptance_checked() {

        $contact_form = WPCF7_ContactForm::get_current();

        if ( ! $contact_form ) {
            return true;
        }

        foreach ( $contact_form->scan_form_tags( array( 'type' => 'acceptance' ) ) as $acceptance ) {
            if ( ! empty( $acceptance->name ) && ! empty( $_POST[ $acceptance->name ] ) ) {
                return true;
            }
        }

        return false;
    }

    public function validate_easimagecaptcha( $result, $tag ) {

        if ( $this->main->public->skip_checks() ) {
            return $result;
        }

        $tag = new WPCF7_FormTag( $tag );

        $name = "exanspsel";

        if ( empty( $_POST['exanspsel'] ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'Please make your selection.', false, 'captcha_wrong' );
            $result->invalidate( $tag, esc_attr__( 'Please make your selection.', 'exopite-anti-spam' ) );

            return $result;
        }

        if ( ! isset( $_POST['exanspsel-auth'] ) || empty( $_POST['exanspsel-auth'] ) ) {

            $this->add_spam( 'captcha auth empty', 'captcha' );

            return $result;
        }

        if ( ! isset( $_POST['exanspsel'] ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'Please select the correct icon(s).', true, 'captcha_wrong' );
            $result->invalidate( $tag, esc_attr__( 'Please select the correct icon(s).', 'exopite-anti-spam' ) );

            return $result;
        }

        // Normally an array (checkboxes), but a manipulated request could send a string.
        $selected = (array) $_POST['exanspsel'];
        $auth = $_POST['exanspsel-auth'];

        $image_captcha_data_decrypted = $this->main->crypter->decrypt_hex( $auth );

        if ( ! $image_captcha_data_decrypted ) {

            $this->add_spam( 'captcha auth can not be decrypted', 'captcha' );

            return $result;
        }

        $image_captcha_data = explode( '|', $image_captcha_data_decrypted );

        if ( ! is_array( $image_captcha_data ) ) {

            $this->add_spam( 'captcha auth is invalid', 'captcha' );

            return $result;
        }

        $image_captcha_token_once = $image_captcha_data[0];
        $image_captcha_time = isset( $image_captcha_data[1] ) ? intval( $image_captcha_data[1] ) : 0;
        $image_captcha_selected = isset( $image_captcha_data[2] ) ? json_decode( $image_captcha_data[2] ) : null;
        $image_captcha_amounts = array( isset( $image_captcha_data[3] ) ? intval( $image_captcha_data[3] ) : 0, isset( $image_captcha_data[4] ) ? intval( $image_captcha_data[4] ) : 0 );

        if ( ( time() - $image_captcha_time ) > $this->main->tokens->get_max_age( 'captcha' ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'captcha token expired, created: ' . wp_date( 'Y-m-d H:i:s', $image_captcha_time ), false, 'expired' );
            $result->invalidate( $tag, esc_attr__( 'Your session has timed out. Please submit the form again.', 'exopite-anti-spam' ) );

            return $result;
        }

        /**
         * Single use token: stored on submission, right or wrong answer.
         * The captcha is reloaded via AJAX after an unsuccessful submission.
         */
        if ( ! $this->main->tokens->consume( $image_captcha_token_once, 'captcha' ) ) {

            $tag->name = $name;
            $this->invalidate_log( 'captcha token already used', true, 'token_used' );
            $result->invalidate( $tag, esc_attr__( "Token error.", 'exopite-anti-spam' ) . ' (12)' );

            return $result;
        }

        // Same number of icons as the form tag (the AJAX reload returns a default captcha for an invalid auth).
        $tag->name = $name;

        if ( $image_captcha_amounts !== $this->main->fields->get_image_captcha_amounts( $tag ) ) {

            $this->add_spam( 'captcha amounts do not match the form: ' . implode( '/', $image_captcha_amounts ), 'captcha' );

            return $result;
        }

        /**
         * With "choose:1" the answer is a single integer (array_rand), it can be 0 too,
         * so check for null/false (invalid JSON) and not for empty.
         */
        if ( null === $image_captcha_selected || false === $image_captcha_selected ) {

            $this->add_spam( 'captcha answer is invalid', 'captcha' );

            return $result;
        }

        $image_captcha_selected = (array) $image_captcha_selected;

        if ( ! $this->array_equal( $image_captcha_selected, $selected ) ) {

            $tag->name = $name;
            $invalidate_text = sprintf( esc_attr__( 'Please select the correct %s', 'exopite-anti-spam' ), $this->main->fields->get_icons_amount_translation( count( $image_captcha_selected ) ) ) . '.';
            $this->invalidate_log( 'Please select the correct (' . count( $image_captcha_selected ) . ') icon(s).', true, 'captcha_wrong' );
            $result->invalidate( $tag, $invalidate_text ) ;

            return $result;
        }

        return $result;

    }

    /**
     * @link https://stackoverflow.com/questions/5678959/php-check-if-two-arrays-are-equal
     */
    public function array_equal( $a, $b ) {

        // Compare as sets of integers, scalars (e.g. single selection) are converted to arrays.
        $a = array_unique( array_map( 'intval', (array) $a ) );
        $b = array_unique( array_map( 'intval', (array) $b ) );
        sort( $a );
        sort( $b );

        return ( $a === $b );
    }

    /**
     * Timestamp (hidden field "eastimestamp"). Missing or manipulated: spam.
     * Too fast, expired or already used: error at the first field, the JS loads a new timestamp.
     */
    public function validate_timestamp( $result, $tags ) {

        // Same logic as for the hidden field: no validation if timestamp is disabled for this form.
        if ( ! $this->main->fields->is_timestamp_active() ) {
            return $result;
        }

        $name  = 'eastimestamp';
        $value = isset( $_POST[$name] ) ? $_POST[$name] : '';

        if ( empty( $value ) ) {

            $this->add_spam( 'timestamp empty', 'timestamp' );
            // The JS loads a new timestamp.
            $this->refresh_timestamp = true;

            return $result;
        }

        $decrypted_data = (string) $this->main->crypter->decrypt_hex( $value );
        $token = substr( $decrypted_data, 0, 64 );
        // Saved as used after the mail was sent (Exopite_Anti_Spam_Public::wpcf7_mail_sent()).
        $this->timestamp_token = $token;
        $timestamp_decrypted = substr( $decrypted_data, 64);

        // Only digits: other tokens (image captcha, acceptance) are encrypted with the same key.
         if ( empty( $timestamp_decrypted ) || ! preg_match( '/^\d{1,12}$/', $timestamp_decrypted ) ) {

            $this->add_spam( 'timestamp can not be decrypted or is invalid', 'timestamp' );
            // The JS loads a new one (e.g. a timestamp of an older plugin version: the next submission works).
            $this->refresh_timestamp = true;

            return $result;

        }

        /**
         * The timestamp token is stored as used only after the mail was sent (wpcf7_mail_sent),
         * so the lock is held until the end of the submission (wpcf7_submit).
         * Parallel requests with the same token wait, then they find the token as used.
         */
        if ( false === $this->main->tokens->lock( $token, 'sent', 30 ) ) {

            $this->invalidate_log( 'token is in use by an other request', true, 'token_used' );
            $this->invalidate_visible( $result, $tags, esc_attr__( "Token error.", 'exopite-anti-spam' ) . ' (10)' );
            $this->refresh_timestamp = true;

            return $result;
        }

        if ( $this->main->tokens->is_used( $token, 'sent' ) ) {

            $this->invalidate_log( 'token already exist', true, 'token_used' );
            $this->invalidate_visible( $result, $tags, esc_attr__( "Token error.", 'exopite-anti-spam' ) . ' (10)' );
            $this->refresh_timestamp = true;

            return $result;
        }

        if ( ! $this->check_elapsed( $timestamp_decrypted ) ) {

            $elapsed_seconds = ( time() - intval( $timestamp_decrypted ) );

            if ( $elapsed_seconds > ( $this->max_time ) ) {

                $this->invalidate_log( 'Timeout, timestamp from user is bigger (' . $elapsed_seconds . ' sec), then the max time (' . $this->max_time . ' sec) to send.', false, 'expired' );
                $this->invalidate_visible( $result, $tags, esc_attr__( 'Your session has timed out. Please submit the form again.', 'exopite-anti-spam' ) );
                $this->refresh_timestamp = true;

            }

            if ( $elapsed_seconds < $this->min_time ) {

                $this->invalidate_log( 'Timestamp from user is smaller (' . $elapsed_seconds . ' sec), then the min time (' . $this->min_time . ' sec) to send.', true, 'too_fast' );
                $this->invalidate_visible( $result, $tags, esc_attr__( 'The form was sent too quickly. Please wait a few seconds before trying again!', 'exopite-anti-spam' ) );

            }

        }

        return $result;
    }

    public function check_elapsed( $time ) {

        $options = $this->main->public->get_cf7_meta();
        if ( isset( $options ) ) {

            if ( isset( $options['timestamp_min'] ) ) {
                $this->min_time = $options['timestamp_min'];
            }

            if ( isset( $options['timestamp_max'] ) ) {
                $this->max_time = intval( $options['timestamp_max'] ) * 60;
            }

        }

        $elapsed_seconds = ( time() - intval( $time ) );

        if ( $elapsed_seconds < $this->min_time || $elapsed_seconds > ( $this->max_time ) ) {
            return false;
        }

        return true;
    }

    public function wpcf7_validate_honeypot( $result, $tag ) {

        if ( $this->main->public->skip_checks() ) {
            return $result;
        }

        $options = $this->main->public->get_cf7_meta();

        // Default: enabled if the form settings were never saved,
        // can be changed with the 'exopite_enable_honeypot' filter.
        $honeypot_active = isset( $options['honeypot'] ) ? ( $options['honeypot'] === 'yes' ) : apply_filters( 'exopite_enable_honeypot', true );

        if ( ! $honeypot_active ) {
            return $result;
        }

        $name  = $this->main->honeypot_name;
        // Any non-empty value (also an array) means the field was filled.
        $value = isset( $_POST[$name] ) ? $_POST[$name] : '';

        if ( ! empty( $value ) ) {

            $this->add_spam( 'honeypot is not empty', 'honeypot' );

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

        if ( $this->main->public->skip_checks() ) {
            return $result;
        }

        // Default: disabled if the form settings were never saved (same as honeypot and timestamp).
        $options = $this->main->public->get_cf7_meta();
        $badwords_active = isset( $options['badwords'] ) ? ( $options['badwords'] === 'yes' ) : apply_filters( 'exopite_enable_badwords', false );

        if ( ! $badwords_active ) {
            return $result;
        }

        $words = $this->get_words();

        $instance = WPCF7_ContactForm::get_current();
        $words = apply_filters( 'exopite_anti_spam_bad_words', $words, $tag, $instance );

        $custom_words = $this->get_custom_words();

        if ( empty( $words ) && empty( $custom_words ) ) {
            return $result;
        }

        // Missing field (e.g. in a hidden conditional group) or manipulated value (array).
        $raw = isset( $_POST[ $tag->name ] ) ? $_POST[ $tag->name ] : '';
        if ( is_array( $raw ) ) {
            $raw = implode( ' ', array_filter( $raw, 'is_scalar' ) );
        }
        $raw = wp_unslash( (string) $raw );

        if ( $tag->type == 'textarea' || $tag->type == 'textarea*' ) {
            $content = sanitize_textarea_field( $raw );
        } else {
            $content = sanitize_text_field( $raw );
        }

        /**
         * Prepare field value.
         * Convert to lowercase and convert all whitespane (new line, multiple spaces and tabs) to single space.
         *
         * @link https://stackoverflow.com/questions/2109325/how-do-i-strip-all-spaces-out-of-a-string-in-php/2109339#2109339
         */
        $content = $this->to_lower( $content );
        $content = preg_replace( '/\s+/', ' ', $content );

        /**
         * Own words are plain text: quoted, and UTF-8 bytes (umlauts) count as word characters,
         * so "ärger" is found as a word, but not inside "verärgert".
         */
        foreach ( $custom_words as $word ) {

            if ( preg_match( '/(?<![\w\x80-\xff])' . preg_quote( $word, '/' ) . '(?![\w\x80-\xff])/i', $content ) ) {

                $this->invalidate_log( 'You are using some banned words. (own list)', true, 'badwords' );
                $result->invalidate( $tag, esc_attr__( "You are using some banned words.", 'exopite-anti-spam' ) );
                $this->invalidated_fields[] = $tag->name;

                return $result;
            }

        }

        foreach ( (array) $words as $word ) {

            /**
             * Check if a string contains a specific word?
             * Not a substring.
             * @link https://stackoverflow.com/questions/4366730/how-do-i-check-if-a-string-contains-a-specific-word/4366744#4366744
             */
            if( preg_match( "/\b{$word}\b/i", $content ) ) {

                $this->invalidate_log( 'You are using some banned words.', true, 'badwords' );
                $result->invalidate( $tag, esc_attr__( "You are using some banned words.", 'exopite-anti-spam' ) );
                $this->invalidated_fields[] = $tag->name;

                return $result;
            }

        }

        return $result;

    }

    /**
     * Built-in word list (lists/spamwords.txt), the entries are regex fragments (e.g. "\$\$\$").
     */
    public function get_words() {

        if ( ! $this->words ) {

            $fn = EXOPITE_ANTI_SPAM_PATH . 'lists/spamwords.txt';

            if ( file_exists( $fn ) ) {
                $lines = file_get_contents( $fn );
                $list = preg_split( '/\r\n|\r|\n/', $lines );
                $this->words = array_filter( $list );
            } else {
                Exopite_Anti_Spam_Logger::write( 'wpcf7_errors', PHP_EOL . wp_date( 'Y-m-d H:i:s' ) . ' - not exists: ' . $fn . PHP_EOL );
                return array();
            }

        }

        return $this->words;
    }

    /**
     * Own words from the "Blacklist" settings page, one per line, plain text (no regex).
     * Stored in the options, so plugin updates do not overwrite them.
     */
    public function get_custom_words() {

        $options = get_option( $this->plugin_name );
        $words = array();

        if ( ! empty( $options['custom_spam_words'] ) && is_string( $options['custom_spam_words'] ) ) {

            foreach ( preg_split( '/\r\n|\r|\n/', $options['custom_spam_words'] ) as $word ) {

                $word = trim( $this->to_lower( $word ) );

                if ( '' !== $word ) {
                    $words[] = $word;
                }

            }

        }

        return array_unique( $words );
    }

    public function to_lower( $text ) {

        return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );

    }

    public function validate_text_email_blacklist( $result, $tag ) {

        if ( $this->main->public->skip_checks() ) {
            return $result;
        }

        $tag = new WPCF7_FormTag( $tag );
        $email = isset( $_POST[ $tag->name ] ) && is_string( $_POST[ $tag->name ] ) ? sanitize_text_field( trim( wp_unslash( $_POST[ $tag->name ] ) ) ) : '';

        // Empty field: optional fields are valid, required fields are already handled by CF7.
        if ( '' === $email ) {
            return $result;
        }

        $instance = WPCF7_ContactForm::get_current();

        // Same check as Contact Form 7 (FILTER_VALIDATE_EMAIL was stricter and rejected addresses CF7 accepts).
        $email_valid = function_exists( 'wpcf7_is_email' ) ? wpcf7_is_email( $email ) : is_email( $email );

        if ( ! $email_valid ) {
            $this->invalidate_log( 'invalid_email', false, 'invalid_other' );
            $result->invalidate( $tag, wpcf7_get_message( 'invalid_email' ) );
            $this->invalidated_fields[] = $tag->name;
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

        // Sanitized on save, Contact Form 7 escapes the error messages on output (esc_attr() here showed "&amp;").
        $list_of_block_domains = (string) $options['list_of_block_domains'];
        $list_of_block_emails = (string) $options['list_of_block_emails'];
        $display_error_message_email = (string) $options['display_error_message_email'];
        $display_error_message_domain = (string) $options['display_error_message_domain'];

        $blacklisted_domains = preg_replace( '/\s+/', '', $list_of_block_domains );
        $blacklisted_domains = explode( ",", $blacklisted_domains );

        $blacklisted_domains = apply_filters( 'exopite_anti_spam_blacklisted_domains', $blacklisted_domains, $tag, $result, $instance );
        $blacklisted_domains = array_map( 'strtolower', (array) $blacklisted_domains );

        // E-mail addresses and domains are compared case-insensitive.
        $email = strtolower( $email );
        $domain = substr( strrchr( $email, '@' ), 1 );

        if ( in_array( $domain, $blacklisted_domains ) ) {

            $domain_error_message = esc_attr__( 'Your domain is blocked.', 'exopite-anti-spam' );
            if ( ! empty( $display_error_message_domain ) ) {
                $domain_error_message = $display_error_message_domain;
            }
            $this->invalidate_log( $domain_error_message, true, 'blacklist' );
            $result->invalidate( $tag, $domain_error_message );
            $this->invalidated_fields[] = $tag->name;
        }

        $blacklisted_emails  = preg_replace( '/\s+/', '', $list_of_block_emails );
        $blacklisted_emails  = explode( ",", $blacklisted_emails );

        $blacklisted_emails = apply_filters( 'exopite_anti_spam_blacklisted_emails', $blacklisted_emails, $tag, $result, $instance );
        $blacklisted_emails = array_map( 'strtolower', (array) $blacklisted_emails );

        if ( in_array( $email, $blacklisted_emails ) ) {

            $email_error_message = esc_attr__( 'Your email is blocked.', 'exopite-anti-spam' );
            if ( ! empty( $display_error_message_email ) ) {
                $email_error_message = $display_error_message_email;
            }
            $this->invalidate_log( $email_error_message, true, 'blacklist' );
            $result->invalidate( $tag, $email_error_message );
            $this->invalidated_fields[] = $tag->name;

        }

        return $result;
    }

    /**
     * Reason codes of the current submission, each code once.
     *
     * @return array  array( 'spam' => string[], 'invalid' => string[] )
     */
    public function get_codes() {

        return array(
            'spam'    => array_values( array_unique( $this->spam_codes ) ),
            'invalid' => array_values( array_unique( $this->invalid_codes ) ),
        );
    }

    /**
     * @param string $reason
     * @param bool   $count_failure  Counts as a failed attempt for the rate limit.
     *                               False for typical human errors (timeout, forgotten selection, invalid e-mail).
     * @param string $code           Stable reason code for the statistics (e.g. 'too_fast'), empty: not counted.
     */
    public function invalidate_log( $reason, $count_failure = true, $code = '' ) {

        if ( $count_failure ) {
            $this->main->ratelimiter->failed = true;
        }

        if ( '' !== $code ) {
            $this->invalid_codes[] = $code;
        }

        if ( ! empty( $reason ) && $this->main->public->is_logging() ) {

            Exopite_Anti_Spam_Logger::write( 'wpcf7_invalidate', PHP_EOL . wp_date( 'Y-m-d H:i:s' ) . ' - ' . Exopite_Anti_Spam_Logger::anonymize_ip( $this->main->public->get_visitor_ip() ) . ' - ' . $reason . PHP_EOL . var_export( $this->main->public->get_masked_post_data(), true ) . PHP_EOL . '---' . PHP_EOL );

            /**
             * Leaving a spam log.
             * @link https://contactform7.com/2020/07/18/custom-spam-filtering/
             */
            $submission = WPCF7_Submission::get_instance();

            if ( $submission ) {
                $submission->add_spam_log( array(
                    'agent' => $this->plugin_name,
                    'reason' => $reason,
                ) );
            }

        }

    }

}
