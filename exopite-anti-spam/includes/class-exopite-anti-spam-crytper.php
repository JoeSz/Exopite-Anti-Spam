<?php

/**
 * Encryption of the tokens (AES-256-CBC with HMAC) and the encryption key.
 *
 * @link       https://www.joeszalai.org
 * @since      1.0.0
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/includes
 */

class Exopite_Anti_Spam_Crypter {

    /**
     * Option with the encryption key ('token').
     */
    public $option_name;

    public function __construct( $option_name = 'exopite-anti-spam' ) {

        $this->option_name = $option_name;

    }

    /**
     * Encryption key, created on first use, saved in the plugin options ('token').
     * Keys of versions before 20261006 were created with mt_rand() (predictable seed), they are replaced once ('key_version').
     * Forms open at that moment get one token error, the JS loads new tokens.
     */
    public function get_key() {

        $options = get_option( $this->option_name );

        if( ! isset( $options['token'] ) || empty( $options['key_version'] ) ) {

            if ( ! is_array( $options) ) $options = array();

            $options['token'] = $this->generate_token( 40 );
            $options['key_version'] = 2;
            update_option( $this->option_name, $options );

        }

        return $options['token'];

    }

    /**
     * Convert a hex value from the request to binary.
     * Manipulated values (array, not hex, odd length) return false without PHP warnings,
     * the caller rejects the submission and writes the reason to the plugin log (with IP).
     */
    public function request_hex2bin( $value ) {

        if ( ! is_string( $value ) || '' === $value || 0 !== strlen( $value ) % 2 || ! preg_match( '/^[0-9a-f]+$/i', $value ) ) {
            return false;
        }

        return hex2bin( $value );
    }

    /**
     * Encrypt with the plugin key, hex encoded (for hidden fields and AJAX responses).
     */
    public function encrypt_hex( $pure_string ) {

        return bin2hex( $this->encrypt( $pure_string, $this->get_key() ) );
    }

    /**
     * Decrypt a hex value from the request with the plugin key, false if it is invalid or manipulated.
     */
    public function decrypt_hex( $value ) {

        return $this->decrypt( $this->request_hex2bin( $value ), $this->get_key() );
    }

    public function generate_token( $length ) {

        $chars =  'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789`-=~!@#$%^&*()_+,./<>?;:[]{}\|';

        $str = '';
        $max = strlen($chars) - 1;

        // random_int: cryptographically secure (mt_rand is predictable), this is the encryption key.
        for ($i=0; $i < $length; $i++)
        $str .= $chars[random_int(0, $max)];

        return $str;
    }

    /**
     * Encrypt and Decrypt a PHP String.
     * PHP 7 ready version. It uses openssl_encrypt function from PHP OpenSSL Library.
     *
     * @link https://stackoverflow.com/questions/16600708/how-do-you-encrypt-and-decrypt-a-php-string/57249681#57249681
     */
    public function encrypt ($pure_string, $encryption_key) {
        $cipher     = 'AES-256-CBC';
        $options    = OPENSSL_RAW_DATA;
        $hash_algo  = 'sha256';
        $sha2len    = 32;
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt($pure_string, $cipher, $encryption_key, $options, $iv);
        $hmac = $this->mac( $iv, $ciphertext_raw, $encryption_key );
        return $iv.$hmac.$ciphertext_raw;
    }

    /**
     * HMAC over IV and ciphertext: with CBC, a changed IV changes the first block of the plaintext,
     * so the IV must be authenticated too (otherwise e.g. a single use token could be modified and replayed).
     * Own key for the HMAC, derived from the encryption key.
     */
    private function mac( $iv, $ciphertext_raw, $encryption_key ) {
        $mac_key = hash_hmac( 'sha256', 'exopite-anti-spam-mac', $encryption_key, true );
        return hash_hmac( 'sha256', $iv . $ciphertext_raw, $mac_key, true );
    }

    public function decrypt ($encrypted_string, $encryption_key) {
        $cipher     = 'AES-256-CBC';
        $options    = OPENSSL_RAW_DATA;
        $hash_algo  = 'sha256';
        $sha2len    = 32;
        $ivlen = openssl_cipher_iv_length($cipher);

        // Missing or too short (manipulated) data: no openssl warnings, just invalid.
        if ( ! is_string( $encrypted_string ) || strlen( $encrypted_string ) <= ( $ivlen + $sha2len ) ) {
            return false;
        }

        $iv = substr($encrypted_string, 0, $ivlen);
        $hmac = substr($encrypted_string, $ivlen, $sha2len);
        $ciphertext_raw = substr($encrypted_string, $ivlen+$sha2len);
        $calcmac = $this->mac( $iv, $ciphertext_raw, $encryption_key );

        // Check the HMAC first, decrypt only authentic data.
        $valid = function_exists('hash_equals') ? hash_equals($hmac, $calcmac) : $this->hash_equals_custom($hmac, $calcmac);

        if ( ! $valid ) {
            return false;
        }

        return openssl_decrypt($ciphertext_raw, $cipher, $encryption_key, $options, $iv);
    }

    /**
     * (Optional)
     * hash_equals() function polyfilling.
     * PHP 5.6+ timing attack safe comparison
     */
    public function hash_equals_custom($knownString, $userString) {
        if (function_exists('mb_strlen')) {
            $kLen = mb_strlen($knownString, '8bit');
            $uLen = mb_strlen($userString, '8bit');
        } else {
            $kLen = strlen($knownString);
            $uLen = strlen($userString);
        }
        if ($kLen !== $uLen) {
            return false;
        }
        $result = 0;
        for ($i = 0; $i < $kLen; $i++) {
            $result |= (ord($knownString[$i]) ^ ord($userString[$i]));
        }
        return 0 === $result;
    }

}
