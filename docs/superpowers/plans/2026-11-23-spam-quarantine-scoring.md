# Statisztika, karantén, pontrendszer, e-mail-ellenőrzés, proof-of-work: megvalósítási terv

> **For the implementer:** Work through the tasks in order. Steps use checkbox (`- [ ]`) syntax for tracking; tick each one when done.

**Goal:** Az Exopite Anti Spam pluginba öt új funkció: statisztika (mindig fut), karantén kiengedéssel, spam-pontrendszer,
e-mail-ellenőrzés (eldobható domain, MX) és láthatatlan proof-of-work, mind (a statisztikát kivéve) űrlaponként
kapcsolható, alapból kikapcsolva.

**Architecture:** A meglévő ellenőrzések stabil okkódokat kapnak (`spam_codes`, `invalid_codes` a validáló osztályon),
ezekből számol a statisztika és ezek alapján kerül karanténba egy beküldés, mindkettő a `wpcf7_submit` végén. Minden új
funkció saját, egy felelősségű osztály, a `Exopite_Anti_Spam` fő osztályon publikus tulajdonságként elérhető, a hookok a
meglévő loaderen keresztül. A kiengedés a CF7 `$contact_form->submit()`-ját futtatja egy `releasing` jelzővel, ami minden
anti-spam ellenőrzést kikapcsol.

**Tech Stack:** WordPress 5.3+, PHP 7.0 szintaxis (8.2-8.5 alatt hibátlan), Contact Form 7 6.1+, MySQL (`dbDelta`,
`$wpdb`), jQuery (a meglévő public JS jQuery-plugin), vanilla JS Web Worker a proof-of-workhöz.

**Spec:** `docs/superpowers/specs/2026-11-23-spam-quarantine-scoring-design.md` (ffbf90a)

## Global Constraints

- Repó: `C:\data\GitHub\Exopite-Anti-Spam`, a plugin a `exopite-anti-spam/` mappában. Minden útvonal ehhez képest relatív.
- PHP 7.0-kompatibilis szintaxis (nincs típusos property, nincs `?->`, nincs `match`, nincs arrow function, nincs
  named argument); `php -l` PHP 8.5-tel (`C:\php\php.exe -l <fájl>`) minden módosított PHP-fájlra hibátlan.
- A pluginfájlok **CRLF** sorvégűek; új fájl is CRLF. Szerkesztés Edit/Write eszközzel, nem `sed -i`-vel.
- Kódkommentek angolul. Felhasználói szövegek angol msgid-vel, text domain `exopite-anti-spam`, német fordítás a
  12. feladatban (de_DE és de_DE_formal azonos, "Sie").
- **DSGVO:** nincs süti, nincs `localStorage`/`sessionStorage`/IndexedDB/Cache API, nincs kérés külső szerverre
  (a worker és minden fájl a saját domainről). A böngészőben minden állapot csak a JS-változókban és a rejtett
  űrlapmezőkben él. Ellenőrzés a 12. feladatban: a fenti szavakra (`setcookie`, `document.cookie`, `localStorage`,
  `sessionStorage`, `indexedDB`, `caches.`, `$_COOKIE`) a plugin mappájában (vendor nélkül) nincs találat, és a
  böngésző Application/Network nézete nem mutat új sütit vagy külső kérést.
- Minden új funkció alapból **ki**, csak űrlaponként kapcsolható, globális kapcsoló és kapcsoló-filter nélkül.
  Kivétel: a statisztika, kapcsoló nélkül mindig fut.
- Az űrlap-beállítások a meglévő `exopite-anti-spam` post meta tömbjében, `'yes'`/`'no'` értékekkel, mint a
  meglévők. Globális értékek a meglévő `exopite-anti-spam` opcióban.
- Új táblák: `{prefix}eas_cf7_stats`, `{prefix}eas_cf7_quarantine`; a token-tábla `type` enum új értéke `pow`.
- Meglévő funkció, filter, beállítás nem változhat (a "Never remove functionality" szabály).
- Coding standards: `coding-standards` skill, lint: `bash $HOME/.claude/skills/coding-standards/tools/lint.sh --profile=wp`
  (új fájlok `--fix`-szel, meglévő fájlokban csak a módosított sorok `--lines=`-szel).
- Commit minden feladat végén a repóban, **push nélkül**, `Co-Authored-By` és AI-sor nélkül.
- Tesztoldal: test1 (`C:\Data\laragon\www\test1`, https://www.test1.test). Lead API csak `lead_api_active = yes`
  űrlapon fut: a tesztek saját, ideiglenes űrlapot hoznak létre, amin nincs lead API, és `skip_mail: on`.
- Szinkron a test1-re a tesztek előtt (a WordPress a test1 példányát tölti be):
  `robocopy C:\data\GitHub\Exopite-Anti-Spam\exopite-anti-spam C:\Data\laragon\www\test1\wp-content\plugins\exopite-anti-spam /MIR /XF disclamer.txt /NFL /NDL /NJH /NJS`
  (a `disclamer.txt` a felhasználó fájlja a test1-en, marad).
- PHP a WordPress CLI-tesztekhez: `C:/Data/laragon/bin/php/php-8.2.35-nts-Win32-vs16-x64/php.exe`.
- Tesztfuttatás: feladatonként egyszer, csak az érintett tesztfájl; a teljes kör egyszer, a 12. feladatban.

## Review Focus

1. **Kiengedés, miután az űrlap megváltozott vagy törölték:** törölt űrlapnál a kiengedés nem fut le, érthető üzenetet
   ad, a bejegyzés marad; megváltozott mezőknél a CF7 validálása dönt, a hiba a `release_error`-ba kerül. (5. feladat,
   `test_release_deleted_form`, `test_release_validation_error`.)
2. **Tömeges spam bekapcsolt karanténnal:** a tábla soha nem nő a maximum fölé, a legrégebbi törlődik. (4. feladat,
   `test_quarantine_cap`.)
3. **DNS nélküli vagy hibás DNS-ű szerver:** az MX-ellenőrzés soha nem utasít el, ha a lekérdezés hibát ad. (9. feladat,
   `test_mx_fail_open`.)
4. **Nem latin oldal (pl. orosz űrlap-locale) orosz szöveggel:** nincs `foreign_script` pont, a tisztán cirill szó nem
   `homoglyph`. (7. feladat, `test_russian_form_russian_text`.)
5. **Küldés, mielőtt a proof-of-work kész:** a küldés vár, utána egyszer, automatikusan elmegy; sikertelen beküldés után
   új feladat jön. A dupla kattintás a kiengedés gombján nem enged ki kétszer. (11. feladat böngészős lépés; 5. feladat
   `test_release_locked`.)

---

## Okkódok (az 1. feladat leképezése)

| Hívás helye | Mostani szöveg | Kód | Fajta |
|---|---|---|---|
| `wpcf7_validate()` | submission without the anti spam fields | `fields_missing` | spam |
| `validate_acceptance_token()` | acceptance token empty / can not be decrypted / is invalid | `acceptance` | spam |
| `validate_acceptance_token()` | acceptance IP mismatch | `token_used` | invalid |
| `validate_acceptance_token()` | acceptance token expired | `expired` | invalid |
| `validate_acceptance_token()` | acceptance token already used | `token_used` | invalid |
| `validate_easimagecaptcha()` | captcha auth empty / can not be decrypted / is invalid / amounts / answer invalid | `captcha` | spam |
| `validate_easimagecaptcha()` | Please make your selection. / Please select the correct icon(s). / Please select the correct (n) | `captcha_wrong` | invalid |
| `validate_easimagecaptcha()` | captcha token expired | `expired` | invalid |
| `validate_easimagecaptcha()` | captcha token already used | `token_used` | invalid |
| `validate_timestamp()` | timestamp empty / can not be decrypted | `timestamp` | spam |
| `validate_timestamp()` | token is in use / token already exist | `token_used` | invalid |
| `validate_timestamp()` | Timeout | `expired` | invalid |
| `validate_timestamp()` | Timestamp ... smaller (túl gyors) | `too_fast` | invalid |
| `wpcf7_validate_honeypot()` | honeypot is not empty | `honeypot` | spam |
| `validate_text_textarea_bad_words()` | banned words (mindkét lista) | `badwords` | invalid |
| `validate_text_email_blacklist()` | invalid_email | `invalid_other` | invalid |
| `validate_text_email_blacklist()` | domain / email blocked | `blacklist` | invalid |
| `Rate_Limiter::wpcf7_validate()` | rate limit: blocked until | `ratelimit` | invalid |
| `Rate_Limiter::wpcf7_submit()` | rate limit: N failed attempts, blocked | nincs kód | (napló) |

Új kódok a későbbi feladatokban: `score` (spam), `pow` (spam), `email_disposable`, `email_domain` (invalid); a
proof-of-work lejárata `expired`, ismételt használata `token_used`. Státuszkódok: `sent`, `failed`, `aborted`,
`spam_other` (spam más plugintól, saját kód nélkül), `invalid_other` (validációs hiba saját kód nélkül), `released`,
`released:<kód>`.

---

### Task 0: CLI-tesztkeret

**Files:**
- Modify: `exopite-anti-spam/exopite-anti-spam.php:99-105` (a példány globálisan elérhető)
- Create: `tests/cli/bootstrap.php`
- Create: `tests/cli/test-bootstrap.php`

**Interfaces:**
- Produces:
  - `exopite_anti_spam(): Exopite_Anti_Spam` (a futó példány; `run_exopite_anti_spam()` eltárolja egy `static`
    változóban vagy `$GLOBALS['exopite_anti_spam']`-ban)
  - `tests/cli/bootstrap.php`: betölti `C:/Data/laragon/www/test1/wp-load.php`-t (vagy az első argumentum
    útvonalát), és ezeket adja:
    - `eas_assert( bool $condition, string $label ): void` (PASS/FAIL sor, a hibák számolása)
    - `eas_done(): void` (összegző sor `N passed, M failed`, kilépési kód 1 hibánál)
    - `eas_create_form( string $template, array $anti_spam_meta ): int` (ideiglenes CF7 űrlap, additional settings
      `skip_mail: on`, címzett `test@example.test`, visszaadja az ID-t; az ID-t felveszi a takarítási listára)
    - `eas_submit( int $form_id, array $post ): array` (beállítja `$_POST`-ot a `_wpcf7`, `_wpcf7_unit_tag`,
      `_wpcf7_locale`, `_wpcf7_container_post` mezőkkel együtt, visszaállítja a `WPCF7_Submission` singletont
      reflectionnel és a plugin kérésenkénti állapotát (`validate->spam_reasons`, `spam_codes`, `invalid_codes`,
      `timestamp_token`, `tokens->used`, `ratelimiter->failed/blocked`, `public->cf7_meta`), lefuttatja
      `wpcf7_contact_form( $form_id )->submit()`-et, visszaadja a CF7 eredménytömbjét)
    - `eas_timestamp( int $age_seconds = 10 ): string` (érvényes titkosított időbélyeg, `$age_seconds`-szel ezelőtti)
    - `eas_cleanup(): void` (törli az ideiglenes űrlapokat; `register_shutdown_function`-nel automatikusan fut)

- [ ] **Step 1: `exopite_anti_spam()` hozzáadása** a bootstrap fájl végén, a `run_exopite_anti_spam()` eltárolja a példányt.
- [ ] **Step 2: `tests/cli/bootstrap.php` megírása** a fenti függvényekkel. A tesztűrlap alap-sablonja:
  `[text* your-name] [email* your-email] [textarea your-message] [submit "Send"]`.
- [ ] **Step 3: `tests/cli/test-bootstrap.php`**: ideiglenes űrlap honeypot=yes, timestamp=yes metával; egy
  beküldés érvényes időbélyeggel és kitöltött mezőkkel → `status === 'mail_sent'`; egy beküldés kitöltött honeypottal
  → `status === 'spam'`.
- [ ] **Step 4: Szinkron a test1-re és egyszeri futtatás**
  Run: `C:/Data/laragon/bin/php/php-8.2.35-nts-Win32-vs16-x64/php.exe tests/cli/test-bootstrap.php`
  Expected: `2 passed, 0 failed`, és a `wp_posts`-ban nem marad `wpcf7_contact_form` a tesztből.
- [ ] **Step 5: Commit** (`Add CLI test helpers for test1`)

### Task 1: Okkódok és a közös "ellenőrzések kihagyása" kapcsoló

**Files:**
- Modify: `exopite-anti-spam/public/class-exopite-anti-spam-public-validate-fields.php` (minden `add_spam()` és
  `invalidate_log()` hívás, a fenti táblázat szerint)
- Modify: `exopite-anti-spam/public/class-exopite-anti-spam-public-rate-limiter.php:225-252`
- Modify: `exopite-anti-spam/public/class-exopite-anti-spam-public.php` (`skip_checks()`, `wpcf7_mail_sent()`)
- Modify: `exopite-anti-spam/public/class-exopite-anti-spam-public-fields.php:90-100` (`is_timestamp_active()`)
- Modify: `exopite-anti-spam/includes/class-exopite-anti-spam.php` (`public $releasing = false;`)
- Test: `tests/cli/test-reason-codes.php`

**Interfaces:**
- Produces:
  - `Exopite_Anti_Spam::$releasing` (bool, a karantén kiengedése alatt `true`)
  - `Exopite_Anti_Spam_Public::skip_checks(): bool` = `$this->is_user_logged_in() || $this->main->releasing`
  - `Exopite_Anti_Spam_Public_Validate_Fields::$spam_codes` és `::$invalid_codes` (string[], kérésenként)
  - `add_spam( $reason, $code = '' )`, `invalidate_log( $reason, $count_failure = true, $code = '' )`: nem üres
    kódnál a megfelelő tömbbe teszik. A logüzenetek szövege nem változik.
  - `Exopite_Anti_Spam_Public_Validate_Fields::get_codes(): array` → `array( 'spam' => string[], 'invalid' => string[] )`,
    `array_values( array_unique() )`-kel.

- [ ] **Step 1: Teszt** `tests/cli/test-reason-codes.php`, ideiglenes űrlap (timestamp, honeypot, badwords = yes):
  - honeypot kitöltve → `get_codes()['spam'] === array( 'honeypot' )`
  - időbélyeg nélkül → `'timestamp'` a spam kódok között
  - `eas_timestamp( 0 )` (túl gyors) → `'too_fast'` az invalid kódok között
  - "viagra" az üzenetben → `'badwords'` az invalid kódok között
  - `exopite_anti_spam()->releasing = true` mellett kitöltött honeypot és időbélyeg nélkül → `status === 'mail_sent'`,
    mindkét kódtömb üres; utána `releasing = false`.
- [ ] **Step 2: Kódok beírása** a táblázat szerint; `skip_checks()` és `$releasing` hozzáadása.
- [ ] **Step 3: `is_user_logged_in()` cseréje `skip_checks()`-re** a validálási úton: `Validate_Fields::wpcf7_validate()`,
  `validate_easimagecaptcha()`, `wpcf7_validate_honeypot()`, `validate_text_textarea_bad_words()`,
  `validate_text_email_blacklist()`, `Public_Fields::is_timestamp_active()`, `Rate_Limiter::applies()`,
  `Public::wpcf7_mail_sent()`. A megjelenítő függvényekben (`wpcf7_form_hidden_fields()`, form-tag handlerek) marad
  az `is_user_logged_in()`. A `wpcf7_validate()` a `skip_checks()` után azonnal visszatér (a `should_inject_fields()`
  az admin-post kérésben hamis, ezért különben `fields_missing` lenne).
- [ ] **Step 4: Szinkron, futtatás egyszer:** `php.exe tests/cli/test-reason-codes.php` → `0 failed`.
- [ ] **Step 5: Commit** (`Stable reason codes for all anti spam checks`)

### Task 2: Statisztika: tábla és számlálás

**Files:**
- Create: `exopite-anti-spam/includes/class-exopite-anti-spam-stats.php`
- Modify: `exopite-anti-spam/includes/class-exopite-anti-spam-activator.php` (`DB_VERSION` `'4'`, két új tábla)
- Modify: `exopite-anti-spam/includes/class-exopite-anti-spam.php` (require, `public $stats`, hook)
- Modify: `exopite-anti-spam/uninstall.php` (`DROP TABLE` a két új táblára)
- Test: `tests/cli/test-stats.php`

**Interfaces:**
- Consumes: `Validate_Fields::get_codes()`, `Exopite_Anti_Spam::$releasing`
- Produces:
  - Táblák (`dbDelta`, a token-tábla mellé, ugyanabban az `activate()`-ben):
    - `{prefix}eas_cf7_stats`: `stat_date DATE NOT NULL`, `form_id BIGINT UNSIGNED NOT NULL`,
      `reason VARCHAR(40) NOT NULL`, `count INT UNSIGNED NOT NULL DEFAULT 0`, `PRIMARY KEY  (stat_date,form_id,reason)`
    - `{prefix}eas_cf7_quarantine`: `id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT`, `created DATETIME NOT NULL`,
      `form_id BIGINT UNSIGNED NOT NULL`, `reasons TEXT NOT NULL`, `score INT NOT NULL DEFAULT 0`,
      `posted_data LONGTEXT NOT NULL`, `meta TEXT NOT NULL`, `has_files TINYINT(1) NOT NULL DEFAULT 0`,
      `release_error TEXT NULL`, `PRIMARY KEY  (id)`, `KEY created (created)`, `KEY form_id (form_id)`
  - `class Exopite_Anti_Spam_Stats` (`__construct( $plugin_main )`):
    - `const KEEP_DAYS = 90;`
    - `record( int $form_id, array $codes ): void` (kódonként egy `INSERT ... ON DUPLICATE KEY UPDATE count = count + 1`,
      dátum `wp_date( 'Y-m-d' )`; utána `maybe_clean_up()`)
    - `wpcf7_submit( $contact_form, $result ): void` (hook `wpcf7_submit`, prioritás 100, 2 argumentum)
    - `get_codes_for_result( array $result, array $codes, bool $releasing, array $released_codes ): string[]`
      (tiszta függvény, a szabályok alább)
    - `maybe_clean_up(): void` (transient `eas_stats_cleanup` 1 napra; `DELETE ... WHERE stat_date < %s`)
    - `get_summary( int $days ): array` (a 3. feladat használja: sorok `form_id`, `reason`, `total`,
      `stat_date >= wp_date( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS )`, `GROUP BY form_id, reason`)
  - `Exopite_Anti_Spam::$stats`
  - A `released_codes`-t a karantén adja (5. feladat): `Exopite_Anti_Spam_Quarantine::$released_codes` (string[]).
    Ebben a feladatban a `wpcf7_submit()` üres tömböt ad át, ha még nincs karantén osztály.

`get_codes_for_result()` szabályai (`$result['status']` szerint):
- `$releasing`: csak `mail_sent`-nél `array( 'released' )` + `'released:' . $kód` minden `$released_codes`-ra; egyébként üres.
- `mail_sent` → `sent`; `mail_failed` → `failed`; `aborted` → `aborted`.
- `spam` → a spam kódok; ha nincs saját, `spam_other`.
- `validation_failed`, `acceptance_missing` és minden más → az invalid és a spam kódok együtt; ha egyik sincs, `invalid_other`.

- [ ] **Step 1: Teszt** `tests/cli/test-stats.php`:
  - `get_codes_for_result( array( 'status' => 'spam' ), array( 'spam' => array(), 'invalid' => array() ), false, array() ) === array( 'spam_other' )`
  - `status mail_sent` + `releasing true` + `released_codes array( 'score' )` → `array( 'released', 'released:score' )`
  - `status validation_failed`, kódok nélkül → `array( 'invalid_other' )`
  - két `record( $form, array( 'sent' ) )` után a táblában `count = 2` a mai sorban
  - beküldés kitöltött honeypottal ideiglenes űrlapon → a mai `honeypot` sor eggyel nő
  - egy 91 napos sor (`INSERT` közvetlenül) a `eas_stats_cleanup` transient törlése és `maybe_clean_up()` után eltűnik
  - takarítás: a teszt törli a saját űrlapjainak sorait
- [ ] **Step 2: Tábla és osztály megírása**, hook regisztrálása, uninstall bővítése.
- [ ] **Step 3: Szinkron, futtatás egyszer:** `php.exe tests/cli/test-stats.php` → `0 failed`; a
  `exopite_anti_spam_db_version` opció `4`.
- [ ] **Step 4: Commit** (`Statistics: count submissions per day, form and reason`)

### Task 3: Admin "Anti Spam" oldal, "Statistik" fül

**Files:**
- Create: `exopite-anti-spam/admin/class-exopite-anti-spam-admin-overview.php`
- Create: `exopite-anti-spam/admin/partials/exopite-anti-spam-admin-statistics.php`
- Modify: `exopite-anti-spam/includes/class-exopite-anti-spam.php` (require, `public $overview`, `admin_menu` hook)
- Modify: `exopite-anti-spam/admin/css/exopite-anti-spam-admin.css` (csak ha a WP `widefat striped` nem elég)

**Interfaces:**
- Consumes: `Exopite_Anti_Spam_Stats::get_summary( int $days )`
- Produces:
  - `class Exopite_Anti_Spam_Admin_Overview` (`__construct( $plugin_name, $plugin_main )`):
    - `const PAGE = 'exopite-anti-spam-overview';`
    - `add_menu(): void` (almenü a CF7 menüben, ugyanazzal a szülő-logikával, mint a Blacklist; cím
      "Anti Spam", jog `manage_options`)
    - `render(): void` (fülek: `tab=quarantine` alapértelmezett, `tab=statistics`; a karantén fül tartalma a 6. feladatban,
      addig egy üres helyőrző)
    - `get_reason_label( string $code ): string` (fordítható címkék minden kódra a fenti listából; `released:<kód>` →
      "Released (<címke>)"; ismeretlen kód → maga a kód)
  - Statisztika-nézet: két táblázat, "Last 7 days" és "Last 30 days"; fölöttük egy összegző sor (Sent, Spam, Invalid,
    Released összesen); a táblázat oszlopai Form, Reason, Count; űrlap szerint csoportosítva, csoporton belül darabszám
    szerint csökkenő; az űrlap címe link a CF7 szerkesztőre, törölt űrlapnál "#ID (deleted)". Üres adatnál egy mondat:
    "No submissions counted yet."

- [ ] **Step 1: Osztály, partial és menü megírása.** Minden kimenet escape-elve (`esc_html`, `esc_url`).
- [ ] **Step 2: Ellenőrzés böngészőben** (test1, `claude_test` admin a `c:\data\Claude.ai\test-users.txt`-ből):
  a 2. feladat tesztje után maradt adatokkal vagy egy kézi beküldés után a Contact → Anti Spam → Statistik fülön a sor
  látszik a helyes űrlapnévvel; `read_console_messages` hibamentes; PHP-hiba nincs a `debug.log`-ban.
- [ ] **Step 3: Lint** (új fájlok `--fix`), **commit** (`Admin page "Anti Spam" with statistics`)

### Task 4: Karantén: tárolás, takarítás, korlát

**Files:**
- Create: `exopite-anti-spam/includes/class-exopite-anti-spam-quarantine.php`
- Modify: `exopite-anti-spam/includes/class-exopite-anti-spam.php` (require, `public $quarantine`, hook)
- Modify: `exopite-anti-spam/admin/class-exopite-anti-spam-admin.php` (űrlap-kapcsoló, globális értékek mentése)
- Modify: `exopite-anti-spam/admin/partials/exopite-anti-spam-admin-display.php` (két szám-mező)
- Test: `tests/cli/test-quarantine-store.php`

**Interfaces:**
- Consumes: `Validate_Fields::get_codes()`, a 2. feladat táblája
- Produces:
  - `class Exopite_Anti_Spam_Quarantine` (`__construct( $plugin_main )`):
    - `const DEFAULT_DAYS = 30; const DEFAULT_MAX = 1000;`
    - `const STORED_CODES = array( 'score', 'honeypot', 'pow' );` (PHP 7.0: osztálykonstans tömb megengedett)
    - `public $released_codes = array();`
    - `is_active( $options ): bool` (`isset( $options['quarantine'] ) && 'yes' === $options['quarantine']`)
    - `wpcf7_submit( $contact_form, $result ): void` (hook `wpcf7_submit`, prioritás 90): tárol, ha nem `releasing`,
      a státusz `spam`, az űrlapon aktív, és a spam kódok metszete `STORED_CODES`-szal nem üres
    - `store( WPCF7_ContactForm $contact_form, array $codes, int $score ): int|false` (beszúrt ID)
    - `get_settings(): array` → `array( 'days' => int, 'max' => int )` (opció `quarantine_days`, `quarantine_max`)
    - `maybe_clean_up( bool $force = false ): void` (transient `eas_quarantine_cleanup` 1 órára; a megőrzési időnél
      régebbiek törlése, majd ha `COUNT(*) > max`, a legrégebbiek törlése `ORDER BY id ASC LIMIT (count - max)`)
    - `get_internal_fields(): string[]` (`eastimestamp`, `easacceptance`, `exanspsel`, `exanspsel-auth`, a honeypot neve,
      `easpowc`, `easpows`)
  - A tárolt adatok: `reasons` = JSON `array( 'codes' => string[], 'log' => string[] )` (a `spam_reasons` szövegei);
    `score` = `$this->main->scoring->last_total` ha létezik (8. feladat), különben 0;
    `posted_data` = JSON a `wp_unslash( $_POST )`-ból a belső és a `_wpcf7*` mezők nélkül;
    `meta` = JSON `url` (`$submission->get_meta( 'url' )`), `referer` (`$_SERVER['HTTP_REFERER']`), `user_agent`,
    `remote_ip` (`get_meta`), `container_post` (`_wpcf7_container_post`), `unit_tag`, `locale` (`_wpcf7_locale`);
    `has_files` = 1, ha az űrlapnak van `file` vagy `file*` tagje; `created` = `current_time( 'mysql' )`.
    JSON: `wp_json_encode`.
  - Űrlap-beállítás: `quarantine` (`'yes'`/`'no'`), panel-sor "Quarantine" a meglévő `panel_switch_row()`-val, a rate
    limit sora után. Leírás: a spamnek jelölt beküldés (pontrendszer, honeypot, proof-of-work) a karanténba kerül, a
    Contact → Anti Spam oldalon megtekinthető és kiengedhető; személyes adatot tárol a megőrzési ideig.
  - Globális értékek: `quarantine_days` (1-365, alap 30), `quarantine_max` (100-10000, alap 1000), a meglévő
    `$number_fields` mintára a `validate()`-ben, sor "Quarantine" a Blacklist oldalon a token-limit után, a
    leírásban az adatvédelmi megjegyzéssel.

- [ ] **Step 1: Teszt** `tests/cli/test-quarantine-store.php`:
  - honeypottal spam, `quarantine = yes` → 1 új sor, `reasons.codes` tartalmazza `honeypot`-ot, `posted_data` tartalmazza
    `your-name`-et, és nem tartalmazza a honeypot nevét és az `eastimestamp`-et
  - ugyanez `quarantine = no` mellett → nincs új sor
  - időbélyeg nélkül (`timestamp` kód, biztos botjel) → nincs új sor
  - `test_quarantine_cap`: `quarantine_max` ideiglenesen 100, 105 sor beszúrása, `maybe_clean_up( true )` → `COUNT = 100`
    és a legkisebb ID-k hiányoznak; az opció visszaállítva
  - 31 napos bejegyzés `maybe_clean_up( true )` után törölve
- [ ] **Step 2: Osztály, hook, beállítások megírása.**
- [ ] **Step 3: Szinkron, futtatás egyszer:** `php.exe tests/cli/test-quarantine-store.php` → `0 failed`.
- [ ] **Step 4: Commit** (`Quarantine: store spam of enabled forms`)

### Task 5: Karantén: kiengedés és törlés

**Files:**
- Modify: `exopite-anti-spam/includes/class-exopite-anti-spam-quarantine.php`
- Test: `tests/cli/test-quarantine-release.php`

**Interfaces:**
- Consumes: `Exopite_Anti_Spam::$releasing`, `Stats::wpcf7_submit()` (olvassa `$released_codes`-t)
- Produces:
  - `get_entry( int $id ): array|null` (dekódolt JSON mezőkkel)
  - `get_entries( int $form_id, int $page, int $per_page ): array` → `array( 'items' => array[], 'total' => int )`,
    `ORDER BY id DESC`
  - `get_form_ids(): int[]` (`SELECT DISTINCT form_id`)
  - `is_releasable( array $entry ): true|string` (`true`, vagy a magyarázó üzenet: törölt űrlap; kötelező `file*` mező)
  - `release( int $id ): array` → `array( 'ok' => bool, 'status' => string, 'message' => string )`
  - `delete( int $id ): bool`, `delete_all( int $form_id = 0 ): int` (0 = mind)

`release()` menete (a sorrend számít):
1. `GET_LOCK( 'eas_q_' . md5( $wpdb->prefix . $id ), 0 )`; ha nem sikerül: `ok false`, `status 'locked'`.
2. Bejegyzés betöltése; ha nincs: `status 'not_found'`. `is_releasable()`; ha nem: `status 'not_releasable'`, a
   bejegyzés marad.
3. Mentés, majd felülírás: `$_POST` = `wp_slash( posted_data + array( '_wpcf7' => form_id, '_wpcf7_version' =>
   WPCF7_VERSION, '_wpcf7_locale' => meta.locale, '_wpcf7_unit_tag' => meta.unit_tag, '_wpcf7_container_post' =>
   meta.container_post ) )`; `$_SERVER['HTTP_REFERER']`, `$_SERVER['HTTP_USER_AGENT']`, `$_SERVER['REQUEST_URI']`
   (az `url` path+query része); `add_filter( 'wpcf7_remote_ip_addr', ... meta.remote_ip )`;
   `add_filter( 'wpcf7_skip_spam_check', '__return_true' )`; `$GLOBALS['post'] = get_post( container_post )`.
4. `$this->main->releasing = true; $this->released_codes = reasons.codes;`
   `$result = wpcf7_contact_form( form_id )->submit();`
5. `finally`-szerűen (PHP 7.0: try/finally megengedett) minden visszaállítása, `releasing = false`, filterek le, lock
   elengedve.
6. `mail_sent` → `delete( $id )`, `ok true`. Más státusz → `release_error` = státusz + a `$result['invalid_fields']`
   üzenetei (`; `-vel), `ok false`.

- [ ] **Step 1: Teszt** `tests/cli/test-quarantine-release.php` (ideiglenes űrlap `skip_mail: on`, `quarantine = yes`,
  honeypot = yes):
  - honeypottal spam → bejegyzés; `release( $id )` → `ok true`, `status 'mail_sent'`, a bejegyzés törölve
  - a kiengedésnél futó `wpcf7_mail_sent` hookban (tesztben felakasztott closure) `$_POST['your-name']` az eredeti érték,
    `WPCF7_Submission::get_instance()->get_meta( 'remote_ip' )` az eredeti IP, `get_meta( 'url' )` az eredeti URL
  - a stats tábla mai `released` és `released:honeypot` sora eggyel nőtt, `sent` nem nőtt
  - `test_release_validation_error`: bejegyzés, amelyből a `your-email` hiányzik → `ok false`, `release_error` nem üres,
    a bejegyzés megmarad
  - `test_release_deleted_form`: az űrlap törlése után `release()` → `status 'not_releasable'`, a bejegyzés marad
  - `test_release_locked`: kézi `GET_LOCK` ugyanarra a névre egy második `$wpdb` kapcsolaton (`new wpdb(...)`) →
    `status 'locked'`
  - `delete_all( $form_id )` csak az adott űrlap bejegyzéseit törli
- [ ] **Step 2: Megírás.**
- [ ] **Step 3: Szinkron, futtatás egyszer:** `php.exe tests/cli/test-quarantine-release.php` → `0 failed`.
- [ ] **Step 4: Commit** (`Quarantine: release through the full Contact Form 7 submission`)

### Task 6: Admin "Quarantäne" fül

**Files:**
- Modify: `exopite-anti-spam/admin/class-exopite-anti-spam-admin-overview.php`
- Create: `exopite-anti-spam/admin/partials/exopite-anti-spam-admin-quarantine.php`
- Modify: `exopite-anti-spam/admin/js/exopite-anti-spam-admin.js` (megerősítő kérdés)
- Modify: `exopite-anti-spam/includes/class-exopite-anti-spam.php` (`admin_post_eas_quarantine` hook,
  `removable_query_args`)

**Interfaces:**
- Consumes: `Quarantine::get_entries()`, `get_entry()`, `get_form_ids()`, `is_releasable()`, `release()`, `delete()`,
  `delete_all()`, `maybe_clean_up()`
- Produces:
  - `Admin_Overview::handle_action(): void` (hook `admin_post_eas_quarantine`): `manage_options` ellenőrzés,
    `do` ∈ `release|delete|delete_all`, nonce `check_admin_referer( 'eas_quarantine_' . $do . '_' . $id )`
    (`delete_all`-nál `$id` = a szűrt `form_id` vagy 0), redirect vissza a listára `eas_msg` és `eas_status` query
    arggal (`wp_safe_redirect`), amelyeket a `removable_query_args` eltávolít.
  - Lista (oldalanként 50, `paginate_links`): Date, Form (link), Reasons (címkék a `get_reason_label()`-lel), Score,
    Release error, és egy "View" link (`&entry=ID`). Szűrő: űrlap-választó a `get_form_ids()`-ből. Gombok: Release
    (csak ha `is_releasable() === true`, különben a magyarázat), Delete, "Delete all" (a szűrt űrlapé vagy mind).
  - Részletnézet: minden `posted_data` mező (tömb `implode( ', ' )`), a meta (URL, referer, IP, user agent), az okok és
    a naplószövegek; nem kötelező fájlmezőnél figyelmeztetés, hogy a csatolmányok nem kerülnek továbbításra.
  - Ha a lista üres és egyetlen űrlapon sincs `quarantine = yes`: tájékoztató, hogy az űrlap "Anti Spam" fülén
    kapcsolható be.
  - A fül megnyitásakor `maybe_clean_up()`.
  - Megerősítés: a "Delete all" és a "Delete" gomb `data-eas-confirm="<szöveg>"` attribútumot kap, az admin JS
    `confirm()`-mel kérdez (nincs inline JS).

- [ ] **Step 1: Megírás.** Minden kimenet escape-elve; a gombok POST űrlapok `admin-post.php`-re, nonce-szal.
- [ ] **Step 2: Ellenőrzés böngészőben** (test1, ideiglenes űrlap `quarantine = yes`, honeypot kitöltve a
  `javascript_tool`-lal → spam): a bejegyzés a listában; View mutatja a mezőket; Release → siker-üzenet, a bejegyzés
  eltűnt, a levél a log-emails-ben (vagy `skip_mail`-nél a stats `released` sora); egy második bejegyzés Delete-tel
  törölhető; "Delete all" megerősítést kér; nonce nélküli kérés (`admin-post.php?action=eas_quarantine&do=delete&id=1`)
  hibát ad; konzol hibamentes. Utána az ideiglenes űrlap törölve.
- [ ] **Step 3: Lint, commit** (`Admin: quarantine list, view, release and delete`)

### Task 7: Pontrendszer: a szabályok (tiszta osztály)

**Files:**
- Create: `exopite-anti-spam/includes/class-exopite-anti-spam-scorer.php`
- Test: `tests/cli/test-scorer.php`

**Interfaces:**
- Produces: `class Exopite_Anti_Spam_Scorer` (WordPress-függés nélkül, hogy a PHP 8.5 harness is futtathassa)
  - `const DEFAULT_POINTS = array( 'homoglyph' => 10, 'bbcode_link' => 5, 'name_link' => 5, 'foreign_script' => 5,
    'link_only' => 4, 'same_value' => 3, 'gibberish_name' => 2, 'links' => 2 );`
  - `__construct( array $points = array() )` (összefésülés a `DEFAULT_POINTS`-szal; 0 pont = a szabály kikapcsolva)
  - `score( array $fields, string $language, array $extra_scripts = array() ): array` →
    `array( 'total' => int, 'hits' => array<string,int> )` (csak a pontot adó szabályok)
    - `$fields`: `array( array( 'name' => string, 'type' => 'text'|'textarea'|'email'|'tel'|'url', 'value' => string,
      'is_name' => bool ), ... )`
    - `$language`: két betűs nyelvkód (`de`, `ru`, `zh` ...)
    - `$extra_scripts`: a `script_for_language()` kulcsai közül
  - `script_for_language( string $language ): string` → `cyrillic` (ru uk bg sr mk be kk), `han` (zh), `japanese` (ja),
    `hangul` (ko), `arabic` (ar fa ur), `hebrew` (he), `greek` (el), `thai` (th), egyébként `latin`
  - `get_scripts(): array` → a fenti kulcsok és a hozzájuk tartozó PCRE-tulajdonságok (`japanese` = `\p{Han}`,
    `\p{Hiragana}`, `\p{Katakana}`)

A szabályok pontos mérése (minden regex `/u`; "betű" = `\p{L}`; URL = `#(?:https?://|www\.)\S+#iu`):
- `homoglyph` (egyszer): van olyan `\p{L}+` szó, amelyben van latin és cirill betű, a latin betűk száma nagyobb a
  cirillekénél, és minden cirill betűje a `аеорсухіјѕАВЕКМНОРСТХІЈЅ` halmazból való.
- `bbcode_link` (egyszer): `/\[(?:url|link)=|<a\s[^>]*href\s*=/iu` bármely mezőben.
- `name_link` (egyszer): `is_name` mezőben `#https?://|www\.|@#iu`.
- `foreign_script` (egyszer): az összes mező betűi közül legalább 10 van, és több mint 50%-uk nem `latin`, nem a
  `script_for_language( $language )` írása és nem `$extra_scripts`-beli (a 10 betűs alsó határ ennek a tervnek a
  döntése: egy-két betűs bevitel ne döntsön).
- `link_only` (egyszer): van olyan `textarea`, amelyben van URL, és az URL-ek eltávolítása után 20-nál kevesebb betű marad.
- `same_value` (egyszer): legalább két különböző `text`/`textarea` mező azonos, legalább 5 karakteres értéke
  (`trim` + `mb_strtolower` után). Az `email`/`tel`/`url` mezők kimaradnak (e-mail megerősítés legitim).
- `gibberish_name` (mezőnként): `is_name` és `text` típusú mező, amelyben van olyan szó, ahol 5+ latin mássalhangzó
  (`[bcdfghjklmnpqrstvwxz]`, kis-nagybetű mindegy) áll egymás után, vagy legalább 3 kisbetű→nagybetű váltás van
  (`McDonald` = 1, nem gyanús), vagy 7+ latin betűnél a magánhangzók (`aeiouy` és ékezetes párjaik:
  `àáâãäåæèéêëìíîïòóôõöøœùúûüűőýÿ`) aránya 15% alatt.
- `links` (darabonként): az összes mezőben talált URL-ek száma mínusz 2, ha pozitív.

- [ ] **Step 1: Teszt** `tests/cli/test-scorer.php` (`require` csak az osztályfájlt, WordPress nélkül; PHP 8.2 és 8.5
  alatt is fut):
  - `vіagra` (cirill і) egy latin szövegben, `de` → `hits['homoglyph'] === 10`, `total >= 10`
  - `test_russian_form_russian_text`: orosz mondat (`Здравствуйте, хочу заказать...`), `ru` → nincs `homoglyph`, nincs
    `foreign_script`
  - ugyanez `de` mellett → `hits['foreign_script'] === 5`
  - kínai szöveg `de` mellett, `extra_scripts array( 'han' )` → nincs `foreign_script`
  - 5 link → `hits['links'] === 6`, `total === 6` (küszöb alatt); 5 link + `[url=` → `total === 11`
  - `Peter`, `McDonald`, `Müller` névmezőben → nincs `gibberish_name`; `xKqLmPz` → `2`; `Bcdfgh` → `2`
  - név `www.spam.test` → `hits['name_link'] === 5`
  - textarea csak `https://x.test` → `link_only` 4
  - név = üzenet = `Hello world` → `same_value` 3; két azonos e-mail mező → nincs
  - pontérték 0 a konstruktorban → a szabály nem szerepel a `hits`-ben
- [ ] **Step 2: Az osztály megírása.**
- [ ] **Step 3: Futtatás egyszer, két interpreterrel párhuzamosan:**
  `C:\php\php.exe tests/cli/test-scorer.php` és `php-8.2.35 ... tests/cli/test-scorer.php` → mindkettő `0 failed`.
- [ ] **Step 4: Commit** (`Spam scorer with generic, locale aware content rules`)

### Task 8: Pontrendszer: bekötés és űrlap-beállítások

**Files:**
- Create: `exopite-anti-spam/public/class-exopite-anti-spam-public-scoring.php`
- Modify: `exopite-anti-spam/includes/class-exopite-anti-spam.php` (require, `public $scoring`, hook)
- Modify: `exopite-anti-spam/admin/class-exopite-anti-spam-admin.php` (panel-sor, mentés)
- Modify: `exopite-anti-spam/admin/js/exopite-anti-spam-admin.js` (értékek mutatása/elrejtése)
- Test: `tests/cli/test-scoring.php`

**Interfaces:**
- Consumes: `Exopite_Anti_Spam_Scorer`, `Validate_Fields::add_spam( $reason, 'score' )`
- Produces:
  - `class Exopite_Anti_Spam_Public_Scoring` (`__construct( $plugin_main )`):
    - `public $last_total = 0; public $last_hits = array();`
    - `wpcf7_spam( $spam, $submission = null )` (hook `wpcf7_spam`, prioritás 20, 2 argumentum): ha `$spam` már igaz,
      `skip_checks()`, vagy az űrlapon nincs `scoring = yes`: változatlan. Különben pontoz; a küszöb elérésekor
      `add_spam( 'score ' . $total . ': ' . <szabály=pont lista>, 'score' )`, spam log a submissionre
      (`agent` = plugin neve), `return true`.
    - `get_fields( WPCF7_ContactForm $form, WPCF7_Submission $submission ): array` (a `Scorer::score()` formátuma;
      basetype `text`, `textarea`, `email`, `tel`, `url`; érték `get_posted_data( name )`, tömb `implode( ' ' )`;
      `is_name`: a mezőnév tartalmazza a `exopite_anti_spam_scoring_name_patterns` filter mintáinak egyikét, alap
      `array( 'name' )`)
    - `get_language( WPCF7_ContactForm $form ): string` (`$form->locale()`, üresnél `get_locale()`, az `_` előtti rész)
  - Filter `exopite_anti_spam_scoring_points` (tömb, a `Scorer` konstruktorának).
  - Űrlap-beállítások: `scoring` (yes/no), `scoring_threshold` (1-100, alap 10, tartományon kívül az alap),
    `scoring_scripts` (string[], csak a `Scorer::get_scripts()` kulcsai). Panel-sor "Spam score" a karantén sora után:
    kapcsoló, alatta (`eas-scoring-values`, a timestamp mintájára JS-sel mutatva) a küszöb és jelölőnégyzetek a további
    írásokhoz (Cyrillic, Chinese, Japanese, Korean, Arabic, Hebrew, Greek, Thai). Leírás: a szabályok és pontok rövid
    listája, hogy egyetlen gyenge jel nem elég, és hogy a spam a karanténba kerül, ha az be van kapcsolva.

- [ ] **Step 1: Teszt** `tests/cli/test-scoring.php` (ideiglenes űrlap, honeypot és timestamp ki, `scoring = yes`):
  - 5 link + `[url=` az üzenetben → `status 'spam'`, `get_codes()['spam']` tartalmazza `score`-t, `last_total === 11`
  - 5 link → `status 'mail_sent'`
  - `scoring = no` mellett az 5 link + BBCode → `mail_sent`
  - `quarantine = yes` mellett a pontos spam bekerül, a sor `score` oszlopa 11
- [ ] **Step 2: Megírás.**
- [ ] **Step 3: Szinkron, futtatás egyszer:** `php.exe tests/cli/test-scoring.php` → `0 failed`.
- [ ] **Step 4: Commit** (`Spam score per form`)

### Task 9: E-mail-ellenőrzés

**Files:**
- Create: `exopite-anti-spam/public/class-exopite-anti-spam-public-email-check.php`
- Create: `exopite-anti-spam/lists/disposable-domains.txt`
- Modify: `exopite-anti-spam/includes/class-exopite-anti-spam.php` (require, `public $email_check`, hookok)
- Modify: `exopite-anti-spam/admin/class-exopite-anti-spam-admin.php` (két panel-sor, mentés, két üzenetmező a `validate()`-ben)
- Modify: `exopite-anti-spam/admin/partials/exopite-anti-spam-admin-display.php` (két hibaüzenet-mező)
- Test: `tests/cli/test-email-check.php`

**Interfaces:**
- Consumes: `Validate_Fields::invalidate_log( $reason, false, $code )`, `$this->main->validate->invalidated_fields`
- Produces:
  - `class Exopite_Anti_Spam_Public_Email_Check` (`__construct( $plugin_main )`):
    - `validate( $result, $tag )` (hook `wpcf7_validate_email` és `wpcf7_validate_email*`, prioritás 25, 2 argumentum):
      `skip_checks()` → változatlan; üres vagy már érvénytelen mező (`! $result->is_valid( $tag->name )`) → változatlan;
      `email_disposable = yes` és `is_disposable()` → hiba, kód `email_disposable`; különben `email_mx = yes` és
      `! domain_exists()` → hiba, kód `email_domain`. A hiba nem számít sikertelen kísérletnek
      (`invalidate_log( ..., false, ... )`), a mezőnév a `invalidated_fields`-be kerül (CF7CF-kompatibilitás).
    - `is_disposable( string $domain ): bool` (a domain és minden szülődomainje, a TLD-t kivéve)
    - `domain_exists( string $domain ): bool`: `pre_exopite_anti_spam_domain_exists` filter (nem `null` → azt adja vissza,
      a tesztekhez); IDN `idn_to_ascii( $domain, 0, INTL_IDNA_VARIANT_UTS46 )`, ha létezik; transient
      `eas_mx_` . md5( domain ) (`'1'`/`'0'`, 1 nap); `lookup( $domain, DNS_MX )`: `false` → `true` és nincs
      cache (fail open); nem üres → `true`; üres tömb → `DNS_A`, majd `DNS_AAAA` ugyanígy; mindkettő üres tömb → `false`.
    - `protected function lookup( string $domain, int $type ): array|false` (`@dns_get_record( $domain, $type )`;
      a tesztek alosztállyal cserélik)
    - `get_domains(): array` (a lista egyszer kérésenként, `#`-kal kezdődő és üres sorok nélkül, `array_flip`)
  - Űrlap-beállítások: `email_disposable`, `email_mx` (yes/no). Két panel-sor: "Reject disposable e-mail addresses",
    "Reject e-mail domains that do not exist" (leírás: DNS-lekérdezés a domainre, hiba esetén elfogad).
  - Globális hibaüzenetek: `display_error_message_disposable` (alap "Please use a permanent e-mail address."),
    `display_error_message_mx` (alap "The domain of this e-mail address does not exist."), a meglévő e-mail-üzenetek
    mintájára (`$text_fields` a `validate()`-ben, mezők a Blacklist oldalon).
  - A lista fájl fejléce: `# Source: <URL>`, `# License: <licenc>`, `# Updated: <dátum>`.

- [ ] **Step 1: Lista kiválasztása.** Javaslat: `github.com/disposable-email-domains/disposable-email-domains`
  (`disposable_email_blocklist.conf`). **Letöltés előtt** a licencet (`LICENSE` fájl) el kell olvasni: csak CC0 vagy
  azzal egyenértékű (közkincs) licenc fogadható el; és a letöltéshez a felhasználó engedélye kell (fájlnév, forrás,
  méret megnevezésével). Ha a licenc nem megfelelő, a felhasználót kell kérdezni.
- [ ] **Step 2: Teszt** `tests/cli/test-email-check.php` (ideiglenes űrlap, honeypot és timestamp ki):
  - `email_disposable = yes`, `x@mailinator.com` és `x@sub.mailinator.com` → `validation_failed`, kód `email_disposable`
  - `email_disposable = no` → `mail_sent`
  - `email_mx = yes`, a `pre_...` filter `false`-t ad → `validation_failed`, kód `email_domain`
  - `test_mx_fail_open`: a tesztben egy alosztály felülírja a `lookup()`-ot, és mindig `false`-t ad (DNS-hiba) →
    `domain_exists( 'example.test' ) === true`, és nem jön létre `eas_mx_` transient; egy másik alosztály mindig
    `array()`-t ad → `false`.
  - `test@gmail.com` `email_mx = yes` mellett → `mail_sent` (valódi DNS; ha a gép offline, fail open miatt is átmegy)
- [ ] **Step 3: Megírás.**
- [ ] **Step 4: Szinkron, futtatás egyszer:** `php.exe tests/cli/test-email-check.php` → `0 failed`.
- [ ] **Step 5: Commit** (`E-mail check: disposable domains and domains without MX/A record`)

### Task 10: Proof-of-work: szerveroldal

**Files:**
- Create: `exopite-anti-spam/public/class-exopite-anti-spam-public-pow.php`
- Modify: `exopite-anti-spam/includes/class-exopite-anti-spam-activator.php` (`DB_VERSION` `'5'`, enum + `'pow'`)
- Modify: `exopite-anti-spam/includes/class-exopite-anti-spam-token.php` (`clean_up()`: `pow` a `captcha` ágában)
- Modify: `exopite-anti-spam/public/class-exopite-anti-spam-public-fields.php` (rejtett mezők, AJAX, data-attribútumok)
- Modify: `exopite-anti-spam/public/class-exopite-anti-spam-public-validate-fields.php` (`wpcf7_validate()` hívja a
  `validate_acceptance_token()` után)
- Modify: `exopite-anti-spam/includes/class-exopite-anti-spam.php` (require, `public $pow`)
- Modify: `exopite-anti-spam/admin/class-exopite-anti-spam-admin.php` (panel-sor, mentés)
- Test: `tests/cli/test-pow.php`

**Interfaces:**
- Consumes: `crypter->encrypt_hex()/decrypt_hex()`, `tokens->consume( $token, 'pow' )`, `tokens->get_max_age( 'pow' )`,
  `Validate_Fields::add_spam()/invalidate_log()/invalidate_visible()`
- Produces:
  - `class Exopite_Anti_Spam_Public_Pow` (`__construct( $plugin_main )`):
    - `const DIFFICULTIES = array( 'low' => 13, 'medium' => 15, 'high' => 17 );` (vezető nulla bitek; a végleges
      számok a 11. feladat mérése szerint)
    - `is_active( $options ): bool` (`pow = yes`)
    - `get_bits( $options ): int` (`pow_difficulty`, alap `medium`; filter `exopite_anti_spam_pow_bits`)
    - `create_challenge( int $form_id ): array|null` → `array( 'c' => string, 's' => string, 'b' => int )`:
      `s` = `bin2hex( random_bytes( 16 ) )`, `c` = `encrypt_hex( wp_json_encode( array( 'pow', $s, $bits, $form_id,
      time() ) ) )`; `null`, ha az űrlap nem létezik vagy nincs rajta `pow`
    - `check_solution( string $salt, string $nonce, int $bits ): bool` (`hash( 'sha256', $salt . $nonce, true )`
      első `$bits` bitje nulla; `$nonce` csak számjegy, max. 12)
    - `validate( $result, $tags )`: nem aktív → változatlan. `easpowc` vagy `easpows` hiányzik, nem dekódolható,
      nem `'pow'` típusú, más `form_id`, `bits` kisebb az űrlapénál, vagy rossz megoldás → `add_spam( ..., 'pow' )`.
      Lejárt (`time() - created > tokens->get_max_age( 'pow' )`) → `invalidate_visible()` "Your session has timed out.
      Please submit the form again.", kód `expired`, nem számít sikertelen kísérletnek. Már használt
      (`! consume( $salt, 'pow' )`) → `invalidate_visible()` "Token error. (14)", kód `token_used`.
  - Rejtett mezők (`wpcf7_form_hidden_fields`, csak aktív `pow`-nál): `easpowc` = `''`, `easpows` = `''`.
  - `.eas-ajax-url` div új attribútumai (csak aktív `pow`-nál): `data-pow="1"`, `data-pow-worker="<a worker JS URL-je
    verzióval>"`, `data-form-id="<id>"`.
  - AJAX `eap_reload_cf7_fields`: ha `$_POST['pow']` pozitív egész, a válaszban `pow` = `create_challenge( (int) $_POST['pow'] )`
    (a token-kérés limit már az elején fut).
  - Űrlap-beállítás: `pow` (yes/no), `pow_difficulty` (`low|medium|high`). Panel-sor "Invisible proof-of-work" a
    nehézség-választóval (`eas-pow-values`, JS-sel mutatva); leírás: a böngésző a kitöltés alatt egy számítási
    feladatot old meg (telefonon kb. 1 mp), JavaScript nélkül az űrlap nem küldhető el.

- [ ] **Step 1: Teszt** `tests/cli/test-pow.php` (ideiglenes űrlap, `pow = yes`, `pow_difficulty = low`, honeypot és
  timestamp ki; a megoldást a teszt PHP-ban keresi ki egy ciklussal):
  - helyes `easpowc` + `easpows` → `mail_sent`
  - ugyanaz a megoldás újra → `validation_failed`, kód `token_used`
  - rossz `easpows` → `spam`, kód `pow`
  - mezők nélkül → `spam`, kód `pow`
  - másik űrlap `form_id`-jával kiadott feladat → `spam`, kód `pow`
  - `low` szinten kiadott feladat `high` beállítás mellett → `spam`, kód `pow`
  - lejárt feladat (`exopite_anti_spam_pow_max_age` filter 60-ra, a `created` 120 mp-cel korábbi, a titkosítás a
    tesztben) → `validation_failed`, kód `expired`
  - `pow = no` mellett mezők nélkül → `mail_sent`
- [ ] **Step 2: Megírás;** `DB_VERSION` emelése után `maybe_update_db()` frissíti az enumot.
- [ ] **Step 3: Szinkron, futtatás egyszer:** `php.exe tests/cli/test-pow.php` → `0 failed`; a token-tábla
  `type` oszlopa `enum('acceptance','sent','captcha','pow')`.
- [ ] **Step 4: Commit** (`Proof-of-work: challenge, verification, single use`)

### Task 11: Proof-of-work: böngészőoldal

**Files:**
- Create: `exopite-anti-spam/public/js/exopite-anti-spam-pow-worker.js` (vanilla JS, jQuery nélkül)
- Modify: `exopite-anti-spam/public/js/exopite-anti-spam-public.js`
- Modify: `exopite-anti-spam/public/css/exopite-anti-spam-public.css` (várakozó gomb állapota)
- Modify: `exopite-anti-spam/public/class-exopite-anti-spam-public-pow.php` (`DIFFICULTIES` a mérés szerint)

**Interfaces:**
- Consumes: a 10. feladat data-attribútumai, rejtett mezői és az AJAX `pow` válasza (`c`, `s`, `b`)
- Produces:
  - Worker üzenetek: be `{ salt: string, bits: int }`, ki `{ nonce: string }`. `crypto.subtle.digest( 'SHA-256' )`,
    ha `self.crypto && crypto.subtle` létezik (HTTPS), különben beépített JS SHA-256 ugyanabban a fájlban.
    A `nonce` 0-tól felfelé számol, decimális string; a feltétel ugyanaz, mint a `check_solution()`-é.
  - A jQuery pluginban:
    - `plugin.pow = { active, workerUrl, formId, ready, waiting, worker }`
    - `requestPow()`: AJAX `eap_reload_cf7_fields` `pow: formId`-vel; a válasz `c`-je az `easpowc`-be, a `s`/`b` a
      workernek; a worker válasza az `easpows`-ba, `ready = true`; ha `waiting`, a küldés újraindítása
      (`plugin.element.requestSubmit()`, ha létezik, különben a submit gomb `click()`-je).
    - Indítás az első megbízható interakciónál (a meglévő `mousemove keydown touchstart focusin click` kezelőben, a
      `movedOrPressed` mellett), nem oldalbetöltéskor.
    - A meglévő capture-fázisú submit-kezelőben, a dupla-küldés ellenőrzés előtt: ha `pow.active && ! pow.ready`:
      `preventDefault()`, `stopImmediatePropagation()`, `waiting = true`, a submit gomb `eas-pow-waiting` osztályt
      kap, és ha még nincs folyamatban, `requestPow()`.
    - A meglévő `wpcf7mailsent` és `wpcf7invalid wpcf7spam wpcf7mailfailed wpcf7unaccepted wpcf7aborted`
      kezelőkben: `ready = false`, a két mező ürítése, a futó worker leállítása (`terminate()`), és ha volt interakció,
      új `requestPow()`.
    - AJAX-hiba (pl. 429): `waiting` marad, a gomb állapota visszaáll, a következő küldés újra kér.

- [ ] **Step 1: Megírás.**
- [ ] **Step 2: Mérés és nehézség:** test1-en ideiglenes oldal egy `pow = yes` űrlappal; a böngészőben
  (`resize_window` mobile preset és desktop) a `javascript_tool`-lal a worker idejének mérése mindhárom szinten, 5-5
  futás átlaga. A `medium` legyen kb. 1 mp a mobil nézetben; a `DIFFICULTIES` értékeit ehhez igazítani.
- [ ] **Step 3: Böngészős ellenőrzés** (test1, ideiglenes űrlap `pow = yes`, `skip_mail: on`):
  - kitöltés után küldés → `mail_sent`, egyetlen POST a hálózati listában
  - azonnali küldés kitöltés közben (a mező fókuszálása után rögtön Enter) → a gomb várakozik, utána egyetlen POST,
    `mail_sent`
  - sikertelen küldés (kötelező mező üres) után javítás és újraküldés → új `easpowc` érték, `mail_sent`
  - HTTP alatt (`http://www.test1.test`, ha elérhető) a JS SHA-256 ágon is működik
  - konzol hibamentes; utána az ideiglenes oldal és űrlap törölve, `resize_window` desktop
- [ ] **Step 4: Lint** (worker `--fix`, a public JS csak a módosított sorok), **commit** (`Proof-of-work: Web Worker
  and held submit`)

### Task 12: Fordítás, dokumentáció, verzió, teljes kör

**Files:**
- Modify: `exopite-anti-spam/languages/exopite-anti-spam.pot`, `exopite-anti-spam-de_DE.po`, `exopite-anti-spam-de_DE_formal.po`,
  és a két `.mo`
- Modify: `exopite-anti-spam/README.txt` (changelog, GDPR-szakasz, Stable tag)
- Modify: `exopite-anti-spam/exopite-anti-spam.php` (verzió a header-ben és a `EXOPITE_ANTI_SPAM_VERSION`-ben)

- [ ] **Step 1: Fordítás.** Minden új msgid a `.pot`-ba és mindkét `.po`-ba (azonos német szöveg, "Sie"), CRLF; a `.mo`
  újrafordítása a WordPress `MO` osztályával (ahogy a 20261123-as fordításnál: a `scratchpad`-beli `eas-i18n-add.php`
  és `eas-i18n-diff.php` mintájára). Ellenőrzés: a `eas-i18n-diff.php` nem talál fordítatlan új msgid-t.
- [ ] **Step 2: README.** Changelog az aktuális dátummal (`YYYYMMDD`), funkciónként egy-két sor; GDPR-szakasz: a
  statisztika csak számlálókat tárol, a karantén személyes adatot tárol a megőrzési ideig (alapból ki, űrlaponként), az
  MX-ellenőrzés a domainnevet a szerver DNS-feloldójához küldi; Stable tag és verzió = a changelog dátuma.
- [ ] **Step 3: Teljes kör egyszer, párhuzamosan:** minden `tests/cli/test-*.php` a PHP 8.2-vel, a `test-scorer.php` a
  PHP 8.5-tel is; `C:\php\php.exe -l` minden módosított PHP-fájlra; a PHP 8.5 harness
  (`scratchpad\eas-php85-harness.php`, ha még megvan) a plugin mappára.
  Expected: minden teszt `0 failed`, `php -l` hibamentes, 0 deprecation.
- [ ] **Step 4: Böngészős végpróba** test1-en (a felhasználó tesztűrlapja, 5226, nem módosul; ideiglenes űrlap minden új
  funkcióval bekapcsolva): sikeres küldés, honeypot-spam a karanténban, kiengedés, statisztika-sorok, eldobható cím
  elutasítva, a meglévő captcha és dupla-küldés védelem változatlanul működik. DSGVO-ellenőrzés: a Global Constraints
  keresőszavaira nincs találat a plugin mappájában (vendor nélkül); a `javascript_tool`-lal `document.cookie` a
  beküldés előtt és után azonos, `localStorage.length` és `sessionStorage.length` nem nő; a hálózati listában
  (`read_network_requests`) csak a test1 domainjére megy kérés.
- [ ] **Step 5: Szinkron** a test1-re (robocopy) és a Claude.ai mappába
  (`robocopy C:\data\GitHub\Exopite-Anti-Spam\exopite-anti-spam C:\Data\Claude.ai\exopite-anti-spam /MIR /NFL /NDL /NJH /NJS`),
  előtte `diff -rq` mindhárom között; ha a Claude.ai vagy a test1 mappában nem csak saját változás van, kérdezni.
- [ ] **Step 6: Commit** (`Translations, readme and version for the new features`); push nélkül.
