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

        return file_put_contents( $dir . '/' . sanitize_file_name( $name ) . '_' . wp_date( 'Y-m-d' ) . '.log', $content, FILE_APPEND | LOCK_EX );
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
