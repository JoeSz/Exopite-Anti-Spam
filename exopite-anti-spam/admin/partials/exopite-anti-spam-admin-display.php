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

?>
<div class="exopite-anti-spam-wrap">
    <h2>Exopite Anti Spam - <?php esc_attr_e( 'Blacklist Unwanted Emails', 'exopite-anti-spam' ); ?></h2>
    <?php settings_errors(); ?>
    <form method="post" name="<?php echo $this->plugin_name; ?>[<?php echo $this->plugin_name; ?>" action="options.php">
    <?php

        settings_fields( $this->plugin_name );
        do_settings_sections( $this->plugin_name );

        // $list_of_email_fields = isset( $options['form_email_fields'] ) ? $options['form_email_fields'] : '';
        $error_message_email = isset( $options['display_error_message_email'] ) ? $options['display_error_message_email'] : '';
        $error_message_domain = isset( $options['display_error_message_domain'] ) ? $options['display_error_message_domain'] : '';
        $block_domain_list = isset( $options['list_of_block_domains'] ) ? $options['list_of_block_domains'] : '';
        // $spit_domains = explode( ",", $block_domain_list );
        $block_emails_list = isset( $options['list_of_block_emails'] ) ? $options['list_of_block_emails'] : '';
        // $spit_emails = explode( ",", $block_emails_list );

    ?>
    <div class="row">
        <p><?php esc_attr_e( 'The email addresses and domains entered here will be filtered out of all email fields from all Contact Form 7.', 'exopite-anti-spam' ); ?></p>
        <p>
            <ul>
                <li><?php esc_attr_e( 'If you want to block only a specific email field in case there are multiple email fields in the form, you could install', 'exopite-anti-spam' ); ?> <a href="<?php echo get_site_url(); ?>/wp-admin/plugin-install.php?tab=plugin-information&amp;plugin=block-email-cf7" target="_blank">Contact Form 7 – Blacklist Unwanted Email</a></li>
                <li><?php esc_attr_e( 'If you want to block different email addresses and domains in each Contact From 7, you could install', 'exopite-anti-spam' ); ?> <a href="<?php echo get_site_url(); ?>/wp-admin/plugin-install.php?tab=plugin-information&plugin=wp-contact-form7-email-spam-blocker" target="_blank">WP Contact Form7 Email Spam Blocker</a></li>
            </ul>
        </p>
        <p><?php esc_attr_e( 'You could download a list of', 'exopite-anti-spam' ); ?> <a href="https://www.joewein.de/sw/blacklist.htm#bl" target="_blank"><?php esc_attr_e( 'spam domain for blacklist here', 'exopite-anti-spam' ); ?></a>.</p>
    </div>

    <!--
    <div class="row"><label class="eas-row-title">Email field name list to be validate</label><br>
    <input type="text" class="eas-form-field" name="<?php echo $this->plugin_name; ?>[form_email_fields]" value="<?php echo $list_of_email_fields; ?>" placeholder="Email field name to be validate">
    <p class="eas-field-instructions">Please add email field names that you wish to validate, separated by a comma. E.g. your-email, company-email </p>
    </div>
    -->

    <div class="row">
        <label class="eas-row-title"><?php esc_attr_e( 'Add emails that you want to block', 'exopite-anti-spam' ); ?></label><br>
        <textarea class="eas-form-field" name="<?php echo $this->plugin_name; ?>[list_of_block_emails]" cols="100" rows="8" placeholder="<?php esc_attr_e( 'Eg: example@gmail.com, test@hotmail.com', 'exopite-anti-spam' ); ?>"><?php echo $block_emails_list; ?></textarea>
        <p class="eas-field-instructions"><?php esc_attr_e( 'Add list of emails you wish to blacklist/block, separated by a comma. E.g. example@gmail.com, test@hotmail.com, etc.', 'exopite-anti-spam' ); ?></p>
    </div>

    <div class="row">
        <label class="eas-row-title"><?php esc_attr_e( 'Error message text', 'exopite-anti-spam' ); ?></label><br>
        <input type="text" class="eas-form-field" name="<?php echo $this->plugin_name; ?>[display_error_message_email]" value="<?php echo $error_message_email; ?>" placeholder="<?php esc_attr_e( 'Your email is blocked.', 'exopite-anti-spam' ); ?>">
        <p class="eas-field-instructions"><?php esc_attr_e( 'Error message for emails to be displayed on conflicts.', 'exopite-anti-spam' ); ?></p>
    </div>

    <div class="row">
        <label class="eas-row-title"><?php esc_attr_e( 'Add domains that you want to block', 'exopite-anti-spam' ); ?></label><br>
        <textarea class="eas-form-field" name="<?php echo $this->plugin_name; ?>[list_of_block_domains]" cols="100" rows="8" placeholder="<?php esc_attr_e( 'Eg: gmail.com, hotmail.com', 'exopite-anti-spam' ); ?>"><?php echo $block_domain_list; ?></textarea>
        <p class="eas-field-instructions"><?php esc_attr_e( 'Add list of domains you wish to blacklist/block, separated by a comma. E.g. gmail.com, yahoo.com, hotmial.com, etc.', 'exopite-anti-spam' ); ?></p>
    </div>

    <div class="row">
        <label class="eas-row-title"><?php esc_attr_e( 'Error message text', 'exopite-anti-spam' ); ?></label><br>
        <input type="text" class="eas-form-field" name="<?php echo $this->plugin_name; ?>[display_error_message_domain]" value="<?php echo $error_message_domain; ?>" placeholder="<?php  esc_attr_e( 'Your domain is blocked.', 'exopite-anti-spam' ); ?>">
        <p class="eas-field-instructions"><?php esc_attr_e( 'Error message for domains to be displayed on conflicts.', 'exopite-anti-spam' ); ?></p>
    </div>

    <?php submit_button( __( 'Save changes', 'exopite-anti-spam' ), 'primary','submit', TRUE ); ?>
    </form>
</div>
