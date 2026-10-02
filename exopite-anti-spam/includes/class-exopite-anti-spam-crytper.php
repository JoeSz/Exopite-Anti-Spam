<?php

/**
 * The file that defines the icon plugin class
 *
 * @link       https://www.joeszalai.org
 * @since      1.0.0
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/includes
 */

class Exopite_Anti_Spam_Crypter {

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
        $hmac = hash_hmac($hash_algo, $ciphertext_raw, $encryption_key, true);
        return $iv.$hmac.$ciphertext_raw;
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
        $calcmac = hash_hmac($hash_algo, $ciphertext_raw, $encryption_key, true);

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
