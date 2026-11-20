<?php

/**
 * Log files of the plugin.
 *
 * @link       https://www.joeszalai.org
 * @since      20261001
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/includes
 */

/**
 * Log directory: wp-content/uploads/exopite-anti-spam/logs-<random>/
 *
 * - Outside of the plugin directory, so plugin updates do not remove it.
 * - The random directory name protects the files on servers without .htaccess support (e.g. nginx),
 *   on Apache the .htaccess denies the access too.
 * - Log files older than 30 days are deleted automatically (filter: 'exopite_anti_spam_log_retention_days').
 */
class Exopite_Anti_Spam_Logger {

    const OPTION = 'exopite_anti_spam_log_dir';

    const BASE_DIR = 'exopite-anti-spam';

    /**
     * @param bool $create  Create the directory if it does not exist.
     * @return string|false Absolute path without trailing slash.
     */
    public static function get_dir( $create = true ) {

        $uploads = wp_upload_dir( null, false );

        if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
            return false;
        }

        $name = get_option( self::OPTION );

        if ( ! is_string( $name ) || ! preg_match( '/^logs-[a-zA-Z0-9]{20}$/', $name ) ) {

            if ( ! $create ) {
                return false;
            }

            $name = 'logs-' . wp_generate_password( 20, false );
            update_option( self::OPTION, $name, false );

        }

        $base = trailingslashit( $uploads['basedir'] ) . self::BASE_DIR;
        $dir  = $base . '/' . $name;

        if ( ! is_dir( $dir ) ) {

            if ( ! $create || ! wp_mkdir_p( $dir ) ) {
                return false;
            }

            self::protect( $base );
            self::protect( $dir );

        }

        return $dir;
    }

    /**
     * Deny web access (Apache 2.2 and 2.4) and directory listing.
     */
    public static function protect( $dir ) {

        if ( ! file_exists( $dir . '/.htaccess' ) ) {
            file_put_contents( $dir . '/.htaccess', "# Apache 2.4\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n# Apache 2.2\n<IfModule !mod_authz_core.c>\n\tOrder Allow,Deny\n\tDeny from all\n</IfModule>\n" );
        }

        if ( ! file_exists( $dir . '/index.php' ) ) {
            file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
        }

    }

    /**
     * Append to <log dir>/<name>_<Y-m-d>.log
     */
    public static function write( $name, $content ) {

        $dir = self::get_dir();

        if ( ! $dir ) {
            return false;
        }

        self::maybe_cleanup( $dir );

        $file = $dir . '/' . sanitize_file_name( $name ) . '_' . wp_date( 'Y-m-d' ) . '.log';

        // Bots could fill the disk: max. size per file and day (filter: 'exopite_anti_spam_log_max_size', bytes).
        $max_size = intval( apply_filters( 'exopite_anti_spam_log_max_size', 5 * MB_IN_BYTES ) );
        if ( $max_size > 0 && file_exists( $file ) && filesize( $file ) > $max_size ) {
            return false;
        }

        return file_put_contents( $file, $content, FILE_APPEND | LOCK_EX );
    }

    public static function get_retention_days() {

        return max( 1, intval( apply_filters( 'exopite_anti_spam_log_retention_days', 30 ) ) );

    }

    /**
     * Delete old log files, at most once a day.
     */
    public static function maybe_cleanup( $dir ) {

        if ( get_transient( 'exopite_anti_spam_log_cleanup' ) ) {
            return;
        }

        set_transient( 'exopite_anti_spam_log_cleanup', 1, DAY_IN_SECONDS );

        $max_age = self::get_retention_days() * DAY_IN_SECONDS;

        foreach ( (array) glob( $dir . '/*.log' ) as $file ) {
            if ( is_file( $file ) && filemtime( $file ) < ( time() - $max_age ) ) {
                wp_delete_file( $file );
            }
        }

    }

    /**
     * Mask personal data (names, e-mail addresses, phone numbers, street addresses) before logging.
     * Field names are matched as substrings, e.g. "name" matches "your-name", "vorname", "nachname".
     * In all other fields e-mail addresses and phone numbers inside the text are masked.
     *
     * @param mixed    $data             E.g. $_POST.
     * @param string[] $internal_fields  Field names without personal data, not masked (CF7 fields "_wpcf7..." are never masked).
     * @param string   $field            Field name of $data.
     */
    public static function mask_data( $data, $internal_fields = array(), $field = '' ) {

        if ( is_array( $data ) ) {

            $masked = array();

            foreach ( $data as $key => $value ) {
                // Nested values (e.g. checkbox arrays) inherit the field name of their parent.
                $masked[ $key ] = self::mask_data( $value, $internal_fields, is_string( $key ) ? $key : $field );
            }

            return $masked;
        }

        if ( ! is_scalar( $data ) || '' === (string) $data ) {
            return $data;
        }

        $value = (string) $data;
        $field = strtolower( (string) $field );

        // Long values (bots) are shortened.
        if ( strlen( $value ) > 1000 ) {
            $value = substr( $value, 0, 1000 ) . '... (' . strlen( $value ) . ' bytes)';
        }

        if ( 0 === strpos( $field, '_wpcf7' ) || in_array( $field, array_map( 'strtolower', (array) $internal_fields ), true ) ) {
            return $value;
        }

        $phone_fields = array( 'tel', 'phone', 'fon', 'handy', 'mobil', 'fax' );
        $mask_fields  = array_merge( array( 'name', 'mail', 'strasse', 'straße', 'street', 'adresse', 'address' ), $phone_fields );
        $mask_fields  = apply_filters( 'exopite_anti_spam_log_mask_fields', $mask_fields );

        $is_mask_field  = false;
        $is_phone_field = false;

        foreach ( $mask_fields as $mask_field ) {
            if ( '' !== $mask_field && false !== strpos( $field, strtolower( $mask_field ) ) ) {
                $is_mask_field = true;
                $is_phone_field = $is_phone_field || in_array( $mask_field, $phone_fields, true );
            }
        }

        if ( $is_mask_field ) {

            if ( false !== strpos( $value, '@' ) ) {
                return self::mask_emails_in_text( $value );
            }

            if ( $is_phone_field ) {
                return self::mask_phone( $value );
            }

            return self::mask_words( $value );
        }

        return self::mask_phones_in_text( self::mask_emails_in_text( $value ) );
    }

    /**
     * john.doe@example.com -> j***@example.com (domain is kept, useful for the blacklist).
     */
    public static function mask_emails_in_text( $text ) {

        $masked = preg_replace_callback( '/([^\s@<>"\',;:]+)@([^\s@<>"\',;:]+\.[a-z]{2,})/iu', static function( $matches ) {
            return mb_substr( $matches[1], 0, 1 ) . '***@' . $matches[2];
        }, $text );

        return ( null === $masked ) ? '***' : $masked;
    }

    /**
     * Phone numbers inside free text (at least 7 digits).
     */
    public static function mask_phones_in_text( $text ) {

        $masked = preg_replace_callback( '/(?<![\w.])\+?\d[\d \/()-]{5,}\d(?![\w.])/', static function( $matches ) {
            if ( strlen( preg_replace( '/\D/', '', $matches[0] ) ) < 7 ) {
                return $matches[0];
            }
            return self::mask_phone( $matches[0] );
        }, $text );

        return ( null === $masked ) ? '***' : $masked;
    }

    /**
     * 015901057332 -> 01********32
     */
    public static function mask_phone( $phone ) {

        $digits = preg_replace( '/\D/', '', $phone );

        if ( strlen( $digits ) < 5 ) {
            return '***';
        }

        return substr( $digits, 0, 2 ) . str_repeat( '*', strlen( $digits ) - 4 ) . substr( $digits, -2 );
    }

    /**
     * Max Mustermann -> M*** M***
     */
    public static function mask_words( $text ) {

        $masked = preg_replace_callback( '/\S+/u', static function( $matches ) {
            return mb_substr( $matches[0], 0, 1 ) . '***';
        }, $text );

        return ( null === $masked ) ? '***' : $masked;
    }

    /**
     * IP address for the log, shortened (personal data): IPv4 192.168.1.0, IPv6 the last 64 bits are zero (wp_privacy_anonymize_ip()).
     */
    public static function anonymize_ip( $ip ) {

        $ip = (string) $ip;

        return function_exists( 'wp_privacy_anonymize_ip' ) ? wp_privacy_anonymize_ip( $ip ) : preg_replace( '/\.\d+$/', '.0', $ip );
    }

    /**
     * Remove all log files and directories (uninstall).
     */
    public static function delete_all() {

        $uploads = wp_upload_dir( null, false );

        if ( empty( $uploads['basedir'] ) ) {
            return;
        }

        $base = trailingslashit( $uploads['basedir'] ) . self::BASE_DIR;

        if ( is_dir( $base ) ) {

            foreach ( (array) glob( $base . '/logs-*', GLOB_ONLYDIR ) as $dir ) {
                foreach ( array_merge( (array) glob( $dir . '/*' ), (array) glob( $dir . '/.htaccess' ) ) as $file ) {
                    if ( is_file( $file ) ) {
                        wp_delete_file( $file );
                    }
                }
                @rmdir( $dir );
            }

            wp_delete_file( $base . '/.htaccess' );
            wp_delete_file( $base . '/index.php' );
            @rmdir( $base );

        }

        delete_option( self::OPTION );
        delete_transient( 'exopite_anti_spam_log_cleanup' );

    }

}
