<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://www.joeszalai.org
 * @since      1.0.0
 *
 * @package    Exopite_Anti_Spam
 * @subpackage Exopite_Anti_Spam/admin/partials
 */
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) die;

//Grab all options
$options = get_option( $this->plugin_name );

$error_message_email = isset( $options['display_error_message_email'] ) ? $options['display_error_message_email'] : '';
$error_message_domain = isset( $options['display_error_message_domain'] ) ? $options['display_error_message_domain'] : '';
$block_domain_list = isset( $options['list_of_block_domains'] ) ? $options['list_of_block_domains'] : '';
$block_emails_list = isset( $options['list_of_block_emails'] ) ? $options['list_of_block_emails'] : '';
$custom_spam_words = isset( $options['custom_spam_words'] ) ? $options['custom_spam_words'] : '';
// Logging enabled: create the directory now, so the real path is shown (not only after the first log entry).
$log_dir = Exopite_Anti_Spam_Logger::get_dir( $this->main->public->is_logging() );

?>
<div class="exopite-anti-spam-wrap">
    <h2>Exopite Anti Spam - <?php esc_html_e( 'Blacklist Unwanted Emails', 'exopite-anti-spam' ); ?></h2>
    <?php settings_errors(); ?>
    <form method="post" name="<?php echo esc_attr( $this->plugin_name ); ?>" action="options.php">
    <?php

        settings_fields( $this->plugin_name );
        do_settings_sections( $this->plugin_name );

    ?>
    <div class="row">
        <p><?php esc_html_e( 'The email addresses and domains entered here will be filtered out of all email fields from all Contact Form 7.', 'exopite-anti-spam' ); ?></p>
        <p>
            <ul>
                <li><?php esc_html_e( 'If you want to block only a specific email field in case there are multiple email fields in the form, you could install', 'exopite-anti-spam' ); ?> <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=block-email-cf7' ) ); ?>" target="_blank">Contact Form 7 – Blacklist Unwanted Email</a></li>
                <li><?php esc_html_e( 'If you want to block different email addresses and domains in each Contact From 7, you could install', 'exopite-anti-spam' ); ?> <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wp-contact-form7-email-spam-blocker' ) ); ?>" target="_blank">WP Contact Form7 Email Spam Blocker</a></li>
            </ul>
        </p>
        <p><?php esc_html_e( 'You could download a list of', 'exopite-anti-spam' ); ?> <a href="https://www.joewein.de/sw/blacklist.htm#bl" target="_blank"><?php esc_html_e( 'spam domain for blacklist here', 'exopite-anti-spam' ); ?></a>.</p>
    </div>

    <div class="row">
        <label class="eas-row-title" for="eas-list-of-block-emails"><?php esc_html_e( 'Add emails that you want to block', 'exopite-anti-spam' ); ?></label><br>
        <textarea id="eas-list-of-block-emails" class="eas-form-field" name="<?php echo esc_attr( $this->plugin_name ); ?>[list_of_block_emails]" cols="100" rows="8" placeholder="<?php esc_attr_e( 'Eg: example@gmail.com, test@hotmail.com', 'exopite-anti-spam' ); ?>"><?php echo esc_textarea( $block_emails_list ); ?></textarea>
        <p class="eas-field-instructions"><?php esc_html_e( 'Add list of emails you wish to blacklist/block, separated by a comma. E.g. example@gmail.com, test@hotmail.com, etc.', 'exopite-anti-spam' ); ?></p>
    </div>

    <div class="row">
        <label class="eas-row-title" for="eas-display-error-message-email"><?php esc_html_e( 'Error message text', 'exopite-anti-spam' ); ?></label><br>
        <input id="eas-display-error-message-email" type="text" class="eas-form-field" name="<?php echo esc_attr( $this->plugin_name ); ?>[display_error_message_email]" value="<?php echo esc_attr( $error_message_email ); ?>" placeholder="<?php esc_attr_e( 'Your email is blocked.', 'exopite-anti-spam' ); ?>">
        <p class="eas-field-instructions"><?php esc_html_e( 'Error message for emails to be displayed on conflicts.', 'exopite-anti-spam' ); ?></p>
    </div>

    <div class="row">
        <label class="eas-row-title" for="eas-list-of-block-domains"><?php esc_html_e( 'Add domains that you want to block', 'exopite-anti-spam' ); ?></label><br>
        <textarea id="eas-list-of-block-domains" class="eas-form-field" name="<?php echo esc_attr( $this->plugin_name ); ?>[list_of_block_domains]" cols="100" rows="8" placeholder="<?php esc_attr_e( 'Eg: gmail.com, hotmail.com', 'exopite-anti-spam' ); ?>"><?php echo esc_textarea( $block_domain_list ); ?></textarea>
        <p class="eas-field-instructions"><?php esc_html_e( 'Add list of domains you wish to blacklist/block, separated by a comma. E.g. gmail.com, yahoo.com, hotmial.com, etc.', 'exopite-anti-spam' ); ?></p>
    </div>

    <div class="row">
        <label class="eas-row-title" for="eas-display-error-message-domain"><?php esc_html_e( 'Error message text', 'exopite-anti-spam' ); ?></label><br>
        <input id="eas-display-error-message-domain" type="text" class="eas-form-field" name="<?php echo esc_attr( $this->plugin_name ); ?>[display_error_message_domain]" value="<?php echo esc_attr( $error_message_domain ); ?>" placeholder="<?php esc_attr_e( 'Your domain is blocked.', 'exopite-anti-spam' ); ?>">
        <p class="eas-field-instructions"><?php esc_html_e( 'Error message for domains to be displayed on conflicts.', 'exopite-anti-spam' ); ?></p>
    </div>

    <div class="row">
        <label class="eas-row-title" for="eas-custom-spam-words"><?php esc_html_e( 'Own spam words', 'exopite-anti-spam' ); ?></label><br>
        <textarea id="eas-custom-spam-words" class="eas-form-field" name="<?php echo esc_attr( $this->plugin_name ); ?>[custom_spam_words]" cols="100" rows="8"><?php echo esc_textarea( $custom_spam_words ); ?></textarea>
        <p class="eas-field-instructions"><?php esc_html_e( 'One word or phrase per line, plain text, not case-sensitive. These words are checked in addition to the built-in list in text and textarea fields, if the bad/spam words filtering is activated in the form ("Anti Spam" tab). They are stored in the database, so plugin updates do not overwrite them.', 'exopite-anti-spam' ); ?></p>
    </div>

    <?php submit_button( __( 'Save changes', 'exopite-anti-spam' ), 'primary','submit', TRUE ); ?>
    </form>

    <div class="row">
        <h3><?php esc_html_e( 'Logging', 'exopite-anti-spam' ); ?></h3>
        <?php if ( $this->main->public->is_logging() ) : ?>
            <?php if ( $log_dir ) : ?>
                <p><?php esc_html_e( 'Logging is enabled. Log directory:', 'exopite-anti-spam' ); ?> <code><?php echo esc_html( wp_normalize_path( $log_dir ) ); ?></code></p>
            <?php else : ?>
                <p><?php esc_html_e( 'Logging is enabled, but the log directory could not be created. Please check the write permissions of the uploads directory.', 'exopite-anti-spam' ); ?></p>
            <?php endif; ?>
        <?php else : ?>
            <p><?php esc_html_e( 'Logging is disabled. To enable it, add the following line to wp-config.php:', 'exopite-anti-spam' ); ?> <code>define( 'EXOPITE_ANTI_SPAM_LOG', true );</code></p>
        <?php endif; ?>
        <p class="eas-field-instructions"><?php
            /* translators: %d: number of days */
            printf( esc_html__( 'Log files older than %d days are deleted automatically. Names, e-mail addresses, phone numbers and street addresses are masked in the log.', 'exopite-anti-spam' ), intval( Exopite_Anti_Spam_Logger::get_retention_days() ) );
        ?></p>
    </div>

</div>
