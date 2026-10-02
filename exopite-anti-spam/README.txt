=== Plugin Name ===
Contributors: JoeSz
Donate link: https://www.joeszalai.org
Tags: comments, spam
Requires at least: 5.3
Tested up to: 7.1
Requires PHP: 7.0
Stable tag: 4.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Anti Spam plugin with timestamp, honeypot (random location), token matching, bad/spam word filtering, email and domain blacklist and an image captcha.

== Description ==

Get rid of the Spam

This solution won't stop all spam on your site, but it should reject most of it. There is evidence to suggest that forms without CAPTCHA receive 20% - 30% more conversions, so you might want to disable CAPTCHA for further conversions due to the small costs of extra spam. Not to mention that the legal status of Google's reCaptcha is questionable under the current GDPR.

The plugin adds a timestamp, a honeypot with random location, token matching, bad/spam word filtering, email and domain blacklist and an image captcha to your Contact Form 7 forms by adding the shortcode <code>[easimagecaptcha]</code> to the form editor where you want the captcha to appear. This plugin uses the Font Awesome fonts as SVGs to display the icons used in the captcha.

## Features
* GDPR compliant
* Simple to use settings page for each contact form
* Text and error messages are translatable
* New image CAPTCHA each time form loads or submitted
* Customizable icon amount
* Customizable selection amount
* Loads with Ajax so as not to be cached by caching plugins
* Hidden honeypot field with a random position
* Timestamp against bots (min and max time can be set for each form)
* Single use tokens (image captcha, acceptance, timestamp)
* Limit of failed attempts per visitor
* Own spam words on the settings page
* Optional logging with masked personal data
* Token matching
* Bad/spam word filtering
* Honeypot with random location
* E-Mail and domain blacklist


### GDPR compliant
The plugin does not use sessions or cookies.
* To prevent the reuse of tokens, the used tokens are stored with the submission time, the form ID and the user ID of logged in users (no IP address) for max. 31 days.
* The limit of failed attempts stores only a hash of the IP address in a temporary entry (max. 30 minutes).
* Logging is disabled by default. If it is enabled, the log files contain the IP address and the submitted data (names, e-mail addresses, phone numbers and street addresses are masked). Log files are deleted after 30 days.

### Simple to use settings page for each contact form
You can turn on/off features in each form.

### Text and error messages are translatable
Texts and error messages can be translated into any language, so -in this way- they can be personalized.

### Customizable icon and selection amount
You can customize the amount of icons to display and the icons to select.

### Loads with Ajax so as not to be cached by caching plugins
You can load the whole Contact Form 7 with AJAX or only the image captcha and the timestamp. <br>
If you load the whole Contact Form 7 with AJAX the spam-bots can not find any form to work with.<br>
To activate this function please use the <code>[contact-form-7-ajax id="YOUR-CF7-ID" title="YOUR-CF7-TITLE"]</code> shortcode.
If you load the image captcha and the timestamp with AJAX your caching plugin will not cache those.<br>
With this option enabled, visitors with JavaScript disabled, can not send any E-Mails with the form.

### Honeypot with random location
Honeypot is a computer security mechanism. It is a decoy that looks and operates like a normal form field, to protect by attract and detect potential attackers. With honeypot the plugin can detect if they are being targeted by cyber threats.
Basically, it's a extra form field to detect whether the form filled by a genuine person or a spam-bot. The field is an invisible fields on the form. Invisible is different than hidden! Bots understand hidden fields and they will ignore it. The label is set to instruct the end user to absolutely nothing with the field and just leave it empty. The technique rely on the assumption, that an automated bot/script will complete every field in the form. However, some will get through, but not many.
The plugin also display the honeypot field in the form in a random location. Keep moving it around between the valid fields to prevent the spam-bot writer to detect the field easily.

### Timestamp
The plugin also apply a timestamp as a hidden input on the form to ensure the minimum and maximum age of the "session". On submission, the plugin will compare the submitted timestamp with the timestamp when the form was displayed. If it is more than the maximum or less than the minimum time (can be set for each form, default: 2 seconds and 10 minutes), then it is very likely an automated bot/script, because a bot 'types' much faster than a human.

### Token matching
The plugin will generate an anonymous "token" on each form request, this is essentially a unique secret code. This token will be encrypted with a random salt and also is going to be applied as a hidden input on the form when it is generated in the browser. After the submission of the form, first the token will be checked against the database and then stored for a months. What this does is ensure that, on every submission of the form, is your form and not some automated bot/script try to submission the from a different server. It also ensure that, every form used only one time. Spammer can download the form and submit it multiple times.


### Bad/spam word filtering
Spam emails are different from email written by humans. Most of the time significantly different. Especially using words like "vicodin" or "viagra". Those words are useful indicators for spam. The plugin will search this words in text and textarea fields. If any found, then it is very likely written by an automated bot/script.

### E-Mail and domain blacklist
The plugin allows you to filter certain email addresses and domains.

## Compatibility
* Contact Form 7 - Repeatable Fields
* Drag and Drop Multiple File Upload - Contact Form 7
* Really Simple CAPTCHA
* Popup for Contact Form 7
* Redirection for Contact Form 7
* Advanced CF7 DB
* Contact Form 7 - Dynamic Text Extension
* Contact Form 7 Syntax Highlighting
* Contact Form 7 - Blacklist Unwanted Email
* Contact Form 7 - Show Page
* Contact Form 7 Email Spam Blocker
* Contact Form 7 Database
* Contact Form CFDB7
* Contact Form Entries

### Not with whole form AJAX loading
* Contact Form 7 Conditional Fields
* Contact Form 7 Datepicker

== Installation ==

1. Upload `exopite-anti-spam` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Screenshots ==

1. Screenshot backend
2. Screenshot frontend

== Changelog ==

= 20261002 =
* New: Contact Form 7 is a required plugin ("Requires Plugins" header, WordPress 6.5+): Anti Spam can only be activated if CF7 is active, CF7 can not be deactivated while Anti Spam is active
* New: message on the settings page if the log directory can not be created (with German translation)
* Fix: forms without image captcha failed with "Conditional Fields for Contact Form 7" active ("Please make your selection.")
* Fix: with "Conditional Fields for Contact Form 7", errors of the anti spam fields are kept if the form has hidden groups, fields inside hidden groups are skipped
* Fix: "image captcha" button in the Contact Form 7 form editor (tag generator for CF7 6+)
* Fix: log entries and log file names use the WordPress timezone (were UTC)
* Fix: the log directory is created when the "Blacklist" settings page is opened with logging enabled, so the real path is shown (was only a placeholder until the first log entry)
* Fix: no PHP fatal error in the captcha AJAX reload if Contact Form 7 was removed without the plugins page (e.g. via FTP)
* Docs: disclaimer.txt renamed (was disclamer.txt), typos fixed, liability clause for applicable law added
* Docs: README license (GPLv2 or later), GDPR and timestamp description updated, "Requires at least: 5.3", "Tested up to: 7.1"
* Docs: README.md updated (features, developer filters, changelog), LICENSE (GPLv2) added

= 20261001 =
* Security: shortcode injection in the AJAX form loading fixed
* Security: image captcha, acceptance and timestamp tokens are single use and expire, also with parallel requests (MySQL lock)
* Security: encryption key generated with a cryptographically secure random generator, HMAC is checked before decryption
* New: limit failed attempts per visitor (IP hash), can be deactivated in the "Anti Spam" tab of each form
* New: captcha icons have a slightly different SVG markup on every render (invisible for humans)
* New: own spam words on the "Blacklist" settings page
* New: logging can be enabled in wp-config.php (EXOPITE_ANTI_SPAM_LOG), logs in wp-content/uploads with random directory name, personal data masked, deleted after 30 days
* Fix: an expired or used timestamp is reloaded automatically, the visitor can submit again without losing the entered data
* Fix: fatal error with "choose:1" or manipulated captcha data, no PHP warnings with manipulated requests
* Fix: optional e-mail field, case-insensitive blacklist
* Fix: settings of multiple forms on one page, validation runs only if the function is enabled
* Fix: bad/spam words filtering disabled by default if the form settings were never saved, "[url]" entry of the word list
* Fix: [contact-form-7-ajax] with Contact Form 7 5.4+
* Fix: admin output escaped, texts translatable (German translation)
* Accessibility: honeypot hidden for screen readers
* Privacy: IP addresses are not stored in the token table
* Uninstall: token table and logs are removed (settings are kept, see uninstall.php)
* Remove: Plugin Update Checker (updates only manually)

= 20260519 =
* Allow to override honeypot and timestamp defaults with PHP (Hook)

= 20230203 =
* Compatibility update for CF7 5.7.3
* Change "Timestamp" from 5 seconds to 3 seconds. Some user save forms in browser.

= 20220620 =
* Better error messages and logging.

= 20201127 =
* Fix: The image captcha creates a PHP error if only one icon needs to be selected.

= 20200921 =
* Fix: "WordPress database error You have an error in your SQL syntax;"
* Remove: add_filter( 'wpcf7_verify_nonce', '__return_true' )

= 20191111 =
* Add Plugin Update Checker 4.8

= 20191104 =
* Initial release

== License ==

The GPL license of Exopite Anti Spam grants you the right to use, study, share (copy), modify and (re)distribute the software, as long as these license terms are retained.

== SUPPORT/UPDATES ==

If you use my program(s), I would **greatly appreciate it if you kindly give me some suggestions/feedback**. If you solve some issue or fix some bugs or add a new feature, please share with me or make a pull request. (But I don't have to agree with you or necessarily follow your advice.)<br/>
**Before open an issue** please read the readme (if any :) ), use google and your brain to try to solve the issue by yourself. After all, Github is for developers.<br/>
My **updates will be irregular**, because if the current stage of the program fulfills all of my needs or I do not encounter any bugs, then I have nothing to do.<br/>
**I provide no support.** I wrote these programs for myself. For fun. For free. In my free time. It does not have to work for everyone. However, that does not mean that I do not want to help.<br/>
I've always tested my codes very hard, but it's impossible to test all possible scenarios. Most of the problem could be solved by a simple google search in a matter of minutes. I do the same thing if I download and use a plugin and I run into some errors/bugs.

== DISCLAIMER ==

NO WARRANTY OF ANY KIND! USE THIS SOFTWARE AND INFORMATION AT YOUR OWN RISK! READ DISCLAIMER.TXT! https://www.joeszalai.org/disclaimer/ <br />
License: GNU General Public License v2 or later
