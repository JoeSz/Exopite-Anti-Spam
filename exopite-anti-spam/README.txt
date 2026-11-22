=== Exopite Anti Spam ===
Contributors: JoeSz
Donate link: https://www.joeszalai.org
Tags: comments, spam
Requires at least: 5.3
Tested up to: 7.1
Requires PHP: 7.0
Stable tag: 20261122
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
* The limit of failed attempts and the acceptance check use the IP address of the direct connection (REMOTE_ADDR). Behind a reverse proxy or CDN all visitors would share one address, the real IP (from a trusted header) can be passed with the filter <code>exopite_anti_spam_visitor_ip</code> (all IP checks and the logs) or <code>exopite_anti_spam_ratelimit_ip</code> (only the limit of failed attempts). For private and local addresses (e.g. 127.0.0.1, 192.168.x.x) the limit is disabled, unless the filter <code>exopite_anti_spam_ratelimit_allow_private_ip</code> returns true.
* Logging is disabled by default. If it is enabled, the log files contain the shortened IP address (e.g. 192.168.1.0) and the submitted data (names, e-mail addresses, phone numbers and street addresses are masked). Log files are deleted after 30 days.

### Simple to use settings page for each contact form
You can turn on/off features in each form.
Default for forms without saved settings: honeypot and timestamp are on, all other features (bad/spam words, image captcha AJAX loading, acceptance check, limit of failed attempts) are off. The defaults can be changed with the filters <code>exopite_enable_honeypot</code>, <code>exopite_enable_timestamp</code>, <code>exopite_enable_badwords</code> and <code>exopite_enable_ratelimit</code>.

### Text and error messages are translatable
Texts and error messages can be translated into any language, so -in this way- they can be personalized.

### Customizable icon and selection amount
You can customize the amount of icons to display and the icons to select.

### Loads with Ajax so as not to be cached by caching plugins
With "AJAX loading" (form settings) the image captcha and the timestamp are loaded with AJAX, your caching plugin will not cache those.<br>
With this option enabled, visitors with JavaScript disabled, can not send any E-Mails with the form.

With a page cache "AJAX loading" should be enabled: a cached page contains the same single use tokens (image captcha, timestamp) for every visitor, only the first submission would work.<br>
* On installation (or the update to this version) "AJAX loading" is enabled for all forms if a page cache is active.<br>
* The "Anti Spam" tab of the form shows the active page cache, new forms have "AJAX loading" enabled by default then.<br>
* If a page cache is activated later, an admin notice recommends "AJAX loading", with a button to enable it for all forms (shown until it is used or dismissed). Nothing is changed automatically.<br>
Detected: WP Rocket, WP Fastest Cache (if the cache is enabled), LiteSpeed Cache, W3 Total Cache, WP Super Cache, Cache Enabler, WP-Optimize, Breeze, Hummingbird and other page cache plugins which set WP_CACHE. Server caches (Varnish, Cloudflare, hosting cache) can not be detected, they can be added with the filter <code>exopite_anti_spam_page_cache_plugins</code> (array of names).
The shortcode <code>[contact-form-7-ajax]</code> (loaded the whole form with AJAX after a click on a link) was removed, pages which still contain it show the form directly, like <code>[contact-form-7]</code>.

### Honeypot with random location
Honeypot is a computer security mechanism. It is a decoy that looks and operates like a normal form field, to protect by attract and detect potential attackers. With honeypot the plugin can detect if they are being targeted by cyber threats.
Basically, it's a extra form field to detect whether the form filled by a genuine person or a spam-bot. The field is an invisible fields on the form. Invisible is different than hidden! Bots understand hidden fields and they will ignore it. The label is set to instruct the end user to absolutely nothing with the field and just leave it empty. The technique rely on the assumption, that an automated bot/script will complete every field in the form. However, some will get through, but not many.
The plugin also display the honeypot field in the form in a random location. Keep moving it around between the valid fields to prevent the spam-bot writer to detect the field easily.

### Spam
Bot submissions (honeypot filled, encrypted data of the timestamp, image captcha or acceptance token missing or manipulated) get the Contact Form 7 status "spam" with the reason in the spam log (e.g. Flamingo), like the spam filters of Contact Form 7 (Akismet, reCAPTCHA, Turnstile). Errors a visitor can fix (sent too fast, session expired, wrong icons) are shown as validation errors. If a plugin skips the spam check of Contact Form 7 (filter wpcf7_skip_spam_check), the spam checks of this plugin are skipped too.

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

### Notes
* Contact Form 7 Conditional Fields: the image captcha <code>[easimagecaptcha]</code> must be placed outside of all <code>[group]</code> tags (the anti spam fields are always checked, also if the browser reports them as hidden)

== Installation ==

1. Upload `exopite-anti-spam` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Screenshots ==

1. Screenshot backend
2. Screenshot frontend

== Changelog ==

= 20261122 =
* Security: the image captcha icons are rewritten on every render (class Exopite_Anti_Spam_Svg_Path): the same shape, but completely different SVG markup. Subpaths in random order, closed subpaths start at a random corner, segments split in two, lines written as curves, absolute / relative commands and the precision mixed; the icon lands in a new square viewBox of random size and origin, slightly rotated and scaled on the points themselves. Before only the numbers moved a little, so a short script could recognise every icon by its command letters (the icon set is public, Font Awesome 4.7). Icons with arcs (custom icons via filter) keep the simple randomizer
* Security: a little visual noise in the icons (3-6 small dots, 1-2 thin strokes as part of the icon path, slight shear): humans see the icon at once, comparing the rendered image with the icon set gets harder. Filter exopite_anti_spam_icon_noise (default true) switches it off; exopite_anti_spam_randomize_icons still switches off the whole randomizer
* New: the limit of failed attempts can be set per form in the "Anti Spam" tab: failed attempts (1-50), time window and block time in minutes (1-1440). Forms saved before keep the defaults 5 attempts / 10 minutes / 30 minutes; the filters exopite_anti_spam_ratelimit_max_failures, _window and _block get the value of the form and can still change it
* Tested: Contact Form 7 6.2.5 and 6.1.7, PHP 8.3; syntax checked on PHP 7.3 and 8.4

= 20261007 =
* Fix: Contact Form 7 6.2+: the page "Blacklist" is in the Contact Form 7 menu again (the top-level menu is now "wpcf7-dashboard", older versions still use "wpcf7")
* Fix: a second submit while the first one is still running (double click on the submit button, Enter) is ignored by the JS: the second request got "Token error (10)" and its error message replaced the "mail sent" message, so the visitor could send the message twice
* New: filter exopite_anti_spam_visitor_ip for the real IP address behind a reverse proxy or CDN (trusted header), used by the acceptance token IP check, the limit of failed attempts and the logs; exopite_anti_spam_ratelimit_ip still changes only the limit of failed attempts
* New: optional automatic updates with Plugin Update Checker 6.0 (vendor/plugin-update-checker), switched off by default: only active if wp-config.php sets define( 'EXOPITE_ANTI_SPAM_UPDATE_CHECKER', true ); (metadata URL https://update.joeszalai.org/). Plugin Update Checker 6.0 requires PHP 7.4, on older PHP versions it is not loaded
* Remove: the empty folder "logs" in the plugin directory (left over from older versions, the logs are in wp-content/uploads/exopite-anti-spam/ since 20261001)

= 20261006 =
* Change: bot submissions get the Contact Form 7 status "spam" (filter wpcf7_spam, like Akismet, reCAPTCHA and Turnstile), the reasons are added to the spam log of the submission (e.g. shown in Flamingo): honeypot filled, timestamp, image captcha or acceptance token missing, not decryptable or manipulated, image captcha with an other number of icons, submission without the anti spam fields. Before these were validation errors (01, 02, 04-09, 11, 14, 15). Errors a visitor can fix stay validation errors: sent too fast, session expired, token already used, wrong icons, other IP address. Contact Form 7 checks spam only if the validation was successful; spam counts as failed attempt for the limit of failed attempts
* Change: the timestamp and the acceptance token are hidden fields of Contact Form 7 (filter wpcf7_form_hidden_fields), not form tags added to the form template anymore; their errors are shown at the first field of the form (timestamp) or at the checked acceptance checkbox. The filters exopite_anti_spam_timestamp and exopite_anti_spam_easacceptance get null as form tag. [eastimestamp] and [easacceptance] in a form template are ignored. The honeypot stays a form tag at a random position (a text field hidden with CSS, bots ignore hidden fields)
* Security: the encryption HMAC also covers the IV (a modified IV could change the first block of the decrypted token)
* Security: with "Conditional Fields for Contact Form 7" the anti spam fields (image captcha, timestamp, acceptance, honeypot) are never skipped as hidden (the list of hidden fields is sent by the browser and could be manipulated, this disabled the checks). The image captcha must not be placed inside a [group], it makes no sense there
* Security: the anti spam fields are only added to forms on the frontend, in AJAX requests and in the Contact Form 7 REST feedback/refill requests (not in other REST API responses, in the admin area, WP-CLI or cron). The route is matched case-insensitive like WordPress does (e.g. /Contact-Form-7/v1/... would have skipped the timestamp and honeypot checks), a submission in a request without the anti spam fields is rejected as spam
* Security: an image captcha or acceptance token sent as timestamp caused a PHP fatal error (HTTP 500), the timestamp must be a number now
* Security: with "Conditional Fields for Contact Form 7" a bot could remove the errors of the bad words and e-mail checks by sending the field names as hidden, these errors are restored too
* Security: the image captcha must have the number of icons of the form (icon:, choose:), an easier default captcha from the AJAX reload is rejected as spam
* Security: the encryption key is replaced once (older versions created it with mt_rand(), which is predictable); forms open during the update get one token error, the JS loads new tokens
* Security: an image captcha or acceptance token which can not be stored as used (database error) is rejected
* Security: log files (only if logging is enabled): max. 5 MB per file and day (filter exopite_anti_spam_log_max_size), values longer than 1000 characters are shortened, IP addresses are shortened (GDPR, e.g. 192.168.1.0), no DEBUG log of the form settings anymore
* Change: the anti spam settings of a form can be saved by everyone who can edit the form in Contact Form 7 (default: editors and administrators), before only administrators (the changes of editors were discarded without a message). The global settings (blacklist) stay for administrators
* New: page cache detection (WP Rocket, WP Fastest Cache, LiteSpeed Cache, W3 Total Cache, WP Super Cache, Cache Enabler, WP-Optimize, Breeze, Hummingbird, WP_CACHE): "AJAX loading" is enabled for all forms on installation/update if a page cache is active, the form settings show the active cache, an admin notice with a button recommends "AJAX loading" if a cache is activated later (on cached pages all visitors got the same single use tokens, only the first submission worked); filter exopite_anti_spam_page_cache_plugins for server caches
* Removed: [contact-form-7-ajax] (loaded the whole form with AJAX after a click on a "Load Contact Form 7" link, visitors had to click before they saw the form, the AJAX endpoint rendered any form for anyone). "AJAX loading" in the form settings keeps cached pages working. Pages which still contain the shortcode show the form directly, like [contact-form-7]
* Change: defaults for forms without saved settings: honeypot and timestamp are on (were off), the limit of failed attempts is off (was on); forms with saved settings are not changed
* Fix: limit of failed attempts: only the IP address of the direct connection (REMOTE_ADDR) is used, no spoofable headers; disabled for private and local IP addresses (new filter exopite_anti_spam_ratelimit_allow_private_ip)
* Fix: errors are attached to the first named field of the form (forms starting with a nameless tag, e.g. [submit] or [response], showed no error message)
* Fix: acceptance token: also requested for a checkbox checked by default ("default:on") and after keyboard, touch or focus interaction (only real, trusted events of the visitor); no double requests; optional acceptance checkboxes left unchecked are not checked
* Fix: new tokens are loaded after every unsuccessful submission (also "spam", "mail failed", "unaccepted" and "aborted"), a new timestamp also if it was missing or invalid (errors 08, 09)
* Fix: the settings of a new contact form are saved on the first save (the form ID did not exist yet), and only if the "Anti Spam" panel was submitted
* Fix: saving the global settings does not delete the encryption key anymore
* Fix: e-mail blacklist: error messages with "&" or apostrophes were shown encoded (&amp;), blocked addresses with these characters were not found
* Fix: token table: form and user IDs above 32767 (BIGINT), index for the clean up
* Fix: limit of failed attempts: IPv6 addresses of the same /64 network are counted together
* Fix: the page cache check runs only for administrators (not for every user in the admin area)
* Fix: no fatal error if another plugin contains the class RemoteAddress, the logs use the IP address like the limit of failed attempts (REMOTE_ADDR)
* Fix: honeypot position for forms without other fields
* Fix: e-mail check uses the Contact Form 7 e-mail validation (if available)
* Change: spam word list rewritten: only words and phrases which (almost) always come from spam (pharma, adult, gambling, SEO/backlink offers, crypto and money scams, fake products and diplomas, bot links like [url=]), also in German (e.g. "Potenzmittel", "ohne Rezept", "Kredit ohne Schufa"). Common business phrases which caused false positives are removed (e.g. "free quote", "best price", "terms and conditions", "luxury car", "loans", "mortgage", "medication", "for you"). Site-specific words can be added on the settings page. The filter is active only if it is enabled for the form.
* Fix: German translation: "Please select %s" and "Please select the correct %s" added to the translation template, e-mail blacklist description no longer marked as fuzzy (was not shown in German)
* Docs: README header (plugin name, stable tag), rate limit and proxy notes
* Code: the public class is split into classes by task, the behaviour, filters and settings are unchanged:
  - Exopite_Anti_Spam_Public_Validate_Fields (public/class-exopite-anti-spam-public-validate-fields.php): validation of all anti spam fields, Conditional Fields compatibility
  - Exopite_Anti_Spam_Token (includes/class-exopite-anti-spam-token.php): single use tokens (database, locks)
  - Exopite_Anti_Spam_Public_Rate_Limiter (public/class-exopite-anti-spam-public-rate-limiter.php): limit of failed attempts
  - Exopite_Anti_Spam_Page_Cache (includes/class-exopite-anti-spam-page-cache.php): page cache detection, admin notice
  - Exopite_Anti_Spam_Crypter: also the encryption key (get_key(), was Exopite_Anti_Spam_Public::get_token())
  - Exopite_Anti_Spam_Logger: also the masking of personal data for the log (mask_data())
  - Exopite_Anti_Spam_Public_Fields: also the AJAX request of the acceptance token; unused duplicate of get_cf7_meta() removed
  - Exopite_Anti_Spam_Public: scripts and styles, form settings, saving the timestamp token after the mail was sent, logging and helpers

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
