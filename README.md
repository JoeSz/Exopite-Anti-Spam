# Exopite Anti Spam
Anti spam plugin for Contact Form 7 with timestamp, honeypot (random location), single use tokens, bad/spam word filtering, email and domain blacklist, limit of failed attempts and an image captcha.

## Description
Get rid of the Spam

This solution won't stop all spam on your site, but it should reject most of it. There is evidence to suggest that forms without CAPTCHA receive 20% - 30% more conversions, so you might want to disable CAPTCHA for further conversions due to the small costs of extra spam. Not to mention that the legal status of Google's reCaptcha is questionable under the current GDPR.

The plugin adds a timestamp, a honeypot with random location, token matching, bad/spam word filtering, email and domain blacklist and an image captcha to your Contact Form 7 forms. The image captcha is added with the shortcode `[easimagecaptcha]` (or the "image captcha" button in the form editor) where you want the captcha to appear. This plugin uses the Font Awesome icons as SVGs to display the icons used in the captcha.

No external service, no cookies, no data sent to third parties.

## Features
* GDPR compliant
* Simple to use settings page for each contact form ("Anti Spam" tab in the form editor)
* Text and error messages are translatable (German translation included)
* New image CAPTCHA each time form loads or submitted, icons with a slightly different SVG markup on every render
* Customizable icon amount
* Customizable selection amount
* Loads with Ajax so as not to be cached by caching plugins
* Hidden honeypot field with a random position
* Timestamp against bots (min and max time can be set for each form)
* Single use tokens (image captcha, acceptance, timestamp)
* Limit of failed attempts per visitor
* Bad/spam word filtering (built-in list and own words)
* E-Mail and domain blacklist
* Optional logging with masked personal data

### GDPR compliant
The plugin does not use sessions or cookies and does not send any data to third parties.
* To prevent the reuse of tokens, the used tokens are stored with the submission time, the form ID and the user ID of logged in users (no IP address) for max. 31 days.
* The limit of failed attempts stores only a hash of the IP address in a temporary entry (max. 30 minutes).
* Logging is disabled by default. If it is enabled, the log files contain the IP address and the submitted data (names, e-mail addresses, phone numbers and street addresses are masked). Log files are deleted after 30 days.

### Simple to use settings page for each contact form
You can turn on/off features in each form, in the "Anti Spam" tab of the Contact Form 7 form editor.

### Text and error messages are translatable
Texts and error messages can be translated into any language, so -in this way- they can be personalized. German translation is included.

### Image captcha
The visitor has to select the named icons, e.g. "Please select two icons, the camera and the umbrella".
* Customizable icon amount (2-10) and selection amount: `[easimagecaptcha icon:6 choose:3]`
* The "image captcha" button in the form editor creates the shortcode (Contact Form 7 6+)
* Every captcha can be used only once (also with parallel requests), it expires after 24 hours. After an unsuccessful submission a new captcha is loaded automatically.
* The SVG markup of the icons is slightly different on every render (invisible for humans), so bots can not identify the icons by comparing the markup.

### Loads with Ajax so as not to be cached by caching plugins
You can load the whole Contact Form 7 with AJAX or only the image captcha and the timestamp.<br>
If you load the whole Contact Form 7 with AJAX the spam-bots can not find any form to work with.<br>
To activate this function please use the `[contact-form-7-ajax id="YOUR-CF7-ID" title="YOUR-CF7-TITLE"]` shortcode.<br>
If you load the image captcha and the timestamp with AJAX your caching plugin will not cache those (recommended if the page is cached).<br>
With this option enabled, visitors with JavaScript disabled, can not send any E-Mails with the form.

### Honeypot with random location
Honeypot is a computer security mechanism. It is a decoy that looks and operates like a normal form field, to protect by attract and detect potential attackers.
Basically, it's an extra form field to detect whether the form is filled by a genuine person or a spam-bot. The field is invisible on the form. Invisible is different than hidden! Bots understand hidden fields and they will ignore it. The technique relies on the assumption, that an automated bot/script will complete every field in the form. However, some will get through, but not many.
The plugin displays the honeypot field in the form in a random location, to prevent the spam-bot writer to detect the field easily. The field is hidden for screen readers too, so blind visitors do not fill it out by mistake.

### Timestamp
The plugin applies an encrypted timestamp as a hidden input on the form to ensure the minimum and maximum age of the "session". On submission, the plugin will compare the submitted timestamp with the timestamp when the form was displayed. If it is more than the maximum or less than the minimum time (can be set for each form, default: 2 seconds and 10 minutes), then it is very likely an automated bot/script, because a bot 'types' much faster than a human.
If the time has expired, a new timestamp is loaded automatically and the visitor can submit the form again without losing the entered data.

### Token matching
The plugin generates an anonymous "token" on each form request, this is essentially a unique secret code. This token is encrypted and applied as a hidden input on the form. After the submission of the form, first the token is checked against the database and then stored for a month. This ensures that every form is used only one time, also if a spammer downloads the form and submits it multiple times (also with parallel requests).

### Limit failed attempts
After 5 failed attempts (e.g. wrong image captcha, honeypot, token error) within 10 minutes, the IP address of the visitor is blocked for 30 minutes. This prevents bots from guessing the image captcha. Typical human errors (expired time, forgotten selection, invalid e-mail address) are not counted. Can be deactivated for each form, deactivate it if the website is behind a proxy or CDN, which does not pass the IP address of the visitors.

### Bad/spam word filtering
Spam messages are different from messages written by humans. Most of the time significantly different. Especially using words like "vicodin" or "viagra". Those words are useful indicators for spam. The plugin searches these words in text and textarea fields. If any is found, then it is very likely written by an automated bot/script.
Own words can be added on the "Blacklist" settings page (Contact menu), they are stored in the database, so plugin updates do not overwrite them.

### E-Mail and domain blacklist
The plugin allows you to block certain email addresses and domains in all email fields of all forms ("Blacklist" settings page, Contact menu).

### Logging
To find out why a submission was rejected, logging can be enabled in `wp-config.php`:
```php
define( 'EXOPITE_ANTI_SPAM_LOG', true );
```
The log files are stored in `wp-content/uploads/exopite-anti-spam/logs-<random>/` (the path is shown on the "Blacklist" settings page). The directory is protected with `.htaccess` (Apache) and a random name (nginx), log files older than 30 days are deleted automatically, personal data is masked.

## Developers

### Constants (wp-config.php)

| Constant | Description |
|---|---|
| `EXOPITE_ANTI_SPAM_LOG` | Enable logging |
| `EXOPITE_ANTI_SPAM_UNINSTALL_DELETE_ALL` | Remove also the settings (blacklist, encryption key, form settings) on uninstall. By default only the token table and the logs are removed. |

### Filters

| Filter | Default | Description |
|---|---|---|
| `exopite_enable_timestamp` | `false` | Timestamp enabled, if the form settings were never saved |
| `exopite_enable_honeypot` | `false` | Honeypot enabled, if the form settings were never saved |
| `exopite_enable_badwords` | `false` | Bad/spam word filtering enabled, if the form settings were never saved |
| `exopite_enable_ratelimit` | `true` | Limit of failed attempts enabled, if the form settings were never saved |
| `exopite_anti_spam_timestamp` | form setting | Timestamp enabled (`$enabled, $tag, $contact_form`) |
| `exopite_anti_spam_honeypot` | form setting | Honeypot enabled (`$enabled, $tag, $contact_form`) |
| `exopite_anti_spam_easacceptance` | form setting | Acceptance AJAX check enabled (`$enabled, $tag, $contact_form`) |
| `exopite_anti_spam_ajaxload` | form setting | Load the image captcha via AJAX (`$enabled, $contact_form`) |
| `exopite_anti_spam_honeypot_name` | `eashpc_website_url` | Name of the honeypot field |
| `exopite_anti_spam_icons_amount` | tag option `icon` | Number of icons (`$amount, $tag, $contact_form`) |
| `exopite_anti_spam_selected_amount` | tag option `choose` | Number of icons to select (`$amount, $tag, $contact_form`) |
| `exopite_anti_spam_exanspsel_icons` | built-in icons | Icons of the image captcha (`$icons, $amount`) |
| `exopite_anti_spam_exanspsel_icons_html` | | HTML of the image captcha (`$html, $icons`) |
| `exopite_anti_spam_randomize_icons` | `true` | Slightly different SVG markup on every render |
| `exopite_anti_spam_captcha_max_age` | `DAY_IN_SECONDS` | Max. age of an image captcha in seconds |
| `exopite_anti_spam_acceptance_max_age` | `DAY_IN_SECONDS` | Max. age of an acceptance token in seconds |
| `exopite_anti_spam_bad_words` | built-in list | Bad/spam words, regex fragments (`$words, $tag, $contact_form`) |
| `exopite_anti_spam_blacklisted_domains` | settings | Blocked domains (`$domains, $tag, $result, $contact_form`) |
| `exopite_anti_spam_blacklisted_emails` | settings | Blocked e-mail addresses (`$emails, $tag, $result, $contact_form`) |
| `exopite_anti_spam_ratelimit_max_failures` | `5` | Failed attempts before the visitor is blocked |
| `exopite_anti_spam_ratelimit_window` | `600` | Time window for the failed attempts in seconds |
| `exopite_anti_spam_ratelimit_block` | `1800` | Block time in seconds |
| `exopite_anti_spam_ratelimit_ip` | `REMOTE_ADDR` | IP address of the visitor (e.g. behind a trusted proxy) |
| `exopite_anti_spam_logging` | `false` | Enable logging |
| `exopite_anti_spam_log_retention_days` | `30` | Log files older than this are deleted |
| `exopite_anti_spam_log_mask_fields` | name, mail, phone, address fields | Field names (substrings) which are masked in the log |

## Compatibility
* Contact Form 7 - Repeatable Fields
* Conditional Fields for Contact Form 7 (tested with 2.7.13)
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

## Screenshot

![](assets/screenshot-1.jpg)
![](assets/screenshot-2.jpg)

## Installation

1. Upload the content of `exopite-anti-spam` to the `/wp-content/plugins/exopite-anti-spam/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress (Contact Form 7 must be active)
3. Set the anti spam features of each form in the "Anti Spam" tab of the form editor

## Requirements

Server

* WordPress 5.3+ (6.5+ for the plugin dependency check)
* PHP 7.0+ (Required)
* Contact Form 7 (required, tested with 6.1.7)

Tested with WordPress 7.1 and Contact Form 7 6.1.7.

## Browsers

* Modern browsers (Chrome, Firefox, Safari, Edge)
* JavaScript is required for the AJAX loading and the acceptance check

## Changelog

### 20261002
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

### 20261001
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

### 20260519
* Allow to override honeypot and timestamp defaults with PHP (Hook)

### 20230203
* Compatibility update for CF7 5.7.3
* Change "Timestamp" from 5 seconds to 3 seconds. Some user save forms in browser.

### 20220620
* Better error messages and logging.

### 20201127
* Fix: The image captcha creates a PHP error if only one icon needs to be selected.

### 20200921
* Fix: "WordPress database error You have an error in your SQL syntax;"
* Remove: add_filter( 'wpcf7_verify_nonce', '__return_true' )

### 20191111
* Add Plugin Update Checker 4.8

### 20191104
* Initial release

## LICENSE DETAILS

The GPL license of Exopite Anti Spam grants you the right to use, study, share (copy), modify and (re)distribute the software, as long as these license terms are retained.

License: GNU General Public License v2 or later, see [LICENSE](LICENSE).

## SUPPORT/UPDATES/CONTRIBUTIONS

If you use my program(s), I would **greatly appreciate it if you kindly give me some suggestions/feedback**. If you solve some issue or fix some bugs or add a new feature, please share with me or make a pull request. (But I don't have to agree with you or necessarily follow your advice.)

**Before open an issue** please read the readme (if any :) ), use google and your brain to try to solve the issue by yourself. After all, Github is for developers.

My **updates will be irregular**, because if the current stage of the program fulfills all of my needs or I do not encounter any bugs, then I have nothing to do.

**I provide no support.** I wrote these programs for myself. For fun. For free. In my free time. It does not have to work for everyone. However, that does not mean that I do not want to help.

I've always tested my codes very hard, but it's impossible to test all possible scenarios. Most of the problem could be solved by a simple google search in a matter of minutes. I do the same thing if I download and use a plugin and I run into some errors/bugs.

## DISCLAIMER

NO WARRANTY OF ANY KIND! USE THIS SOFTWARE AND INFORMATION AT YOUR OWN RISK!
[READ DISCLAIMER!](https://joe.szalai.org/disclaimer/) and [disclaimer.txt](disclaimer.txt)

[![forthebadge](http://forthebadge.com/images/badges/built-by-developers.svg)](http://forthebadge.com) [![forthebadge](http://forthebadge.com/images/badges/for-you.svg)](http://forthebadge.com)
