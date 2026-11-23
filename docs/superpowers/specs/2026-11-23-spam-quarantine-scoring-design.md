# Exopite Anti Spam: statisztika, karantén, spam-pontrendszer, e-mail-ellenőrzés, proof-of-work

Dátum: 2026-11-23. Kiinduló verzió: 20261123 (repó: `C:\data\GitHub\Exopite-Anti-Spam`, master).

## Cél és indok

A plugin bármilyen weboldal Contact Form 7 űrlapjához készül, nem egy konkrét site-hoz vagy lead-rendszerhez. Ma a
honeypot, az időbélyeg, a captcha és az egyszer használatos tokenek a generikus botokat megfogják. Két hiányosság van:

1. **Nem látszik, mi történik.** A spamnek jelölt beküldés nyom nélkül eltűnik (csak a kikapcsolt logba kerül), így
   sem a téves riasztás, sem az átjutó spam nem mérhető.
2. **Ami mégis átjut, azt tartalom alapján kellene felismerni**, a technikai jelek már nem elegendők.

A fejlesztés célja: mérés (statisztika), biztonsági háló a téves riasztásokra (karantén kiengedéssel), és új,
generikus felismerési rétegek (pontrendszer, e-mail-ellenőrzés, proof-of-work).

## Célok

- Statisztika űrlaponként és okonként: mi akadt fenn, mi ment át.
- Karantén: a plugin által spamnek jelölt beküldés megmarad, megtekinthető, és egy kattintással kiengedhető úgy,
  "mintha mi sem történt volna" (levél, Flamingo, lead API és minden más, ami az űrlapra akaszkodik, lefut).
- Spam-pontrendszer tartalmi szabályokkal, űrlaponként kapcsolható, állítható küszöbbel.
- E-mail-ellenőrzés: eldobható domainek és nem létező domainek (MX).
- Láthatatlan proof-of-work a tömeges spam megdrágítására.

## Nem cél

- Külső szolgáltatás vagy adattovábbítás (IP-reputáció, StopForumSpam, DNSBL, Akismet-szerű hálózat). Az egyetlen
  kifelé menő adat az MX-ellenőrzés domainneve a szerver saját DNS-feloldójához.
- Ismétlődő tartalom felismerése (azonos szöveg több IP-ről): megvizsgálva, egy oldalon belül kevés haszna van.
  Később, a statisztika alapján visszahozható.
- Csatolt fájlok megőrzése a karanténban.
- A Contact Form 7 saját vagy más pluginek (Akismet, reCAPTCHA) spam-jelöléseinek karanténba tétele.

## Amit el kell kerülni (anti-célok)

- A meglévő site-okon frissítés után semmi ne változzon magától: minden új funkció alapból ki van kapcsolva
  (kivétel a statisztika, ami csak számol, a beküldéseket nem befolyásolja).
- Egyetlen gyenge jel (egy link, egy furcsa név) önmagában soha ne tegyen spamet.
- A pontrendszer ne legyen latin-központú: kínai, orosz stb. oldalon a saját írásuk legyen a normális.
- Kiengedéskor a teszt- és fejlesztői környezetből ne menjen ki valódi lead egy éles lead-rendszerbe. Ez tesztelési
  szabály, nem kódfunkció (a plugin általános, nem tud a lead-rendszerekről), lásd Tesztelés.

## Megkötések

- WordPress 5.3+, PHP 7.0 a header szerint (a kód 8.2-8.5 alatt hibátlan, régi szintaxis nélkül), Contact Form 7 6.1+.
- Apache és nginx: minden tárolás adatbázisban, nincs új fájl a web root alatt.
- DSGVO-konform marad (a felhasználó döntése): nincs süti, nincs `localStorage`/`sessionStorage`/IndexedDB, nincs
  külső szolgáltatás vagy külső kérés a böngészőből; a böngészőoldali állapot csak JS-változókban és rejtett
  űrlapmezőkben él.
- Személyes adat (karantén) csak bekapcsolt karanténnál, korlátozott ideig, adminnak látható.
- A meglévő funkciók, filterek és beállítások változatlanul működnek tovább.
- Felhasználói szövegek: angol msgid, német fordítás (de_DE, de_DE_formal), a plugin eddigi gyakorlata szerint.
- A pluginfájlok CRLF sorvégűek.

## Megfontolt megközelítések

**Kiengedés a karanténból**
- A) Újrafuttatás a Contact Form 7-en keresztül, eltárolt mezőkkel és oldal-adatokkal. Minden úgy fut le, mint egy
  normál beküldésnél. **Ezt választottuk** (a felhasználó döntése: "mintha mi sem történt volna").
- B) Továbbítás e-mailben az űrlap címzettjének: lead-rendszerbe nem kerül be. Elvetve.
- C) Csak megtekintés: nem véd a lead-vesztés ellen. Elvetve.

**Tartalmi felismerés**
- Pontrendszer tartalmi szabályokkal, küszöbbel. **Ezt választottuk.**
- Egyszerű tiltó szabályok pontozás nélkül (pl. "írások tiltása"): kevésbé árnyalt. Elvetve, de a kapcsolható
  szabályok miatt a pontrendszer ezt is lefedi.

**Ismétlődő tartalom:** elvetve, lásd Nem cél.

## A tervezett működés

### Kapcsolók: csak űrlaponként

**Minden új funkció csak űrlaponként kapcsolható, az űrlap "Anti Spam" fülén, és alapból ki van kapcsolva**
(a felhasználó döntése). Nincs globális be/ki kapcsoló és nincs kapcsoló-filter, mert a kétszintű kapcsolás
megtévesztő (globálisan ki, az űrlapon be, vagy fordítva). Ez a plugin eddigi logikája is: a védelmi funkciók
(időbélyeg, honeypot, spamszavak, rate limit) űrlaponként kapcsolhatók, a Blacklist oldalon csak listák és értékek
vannak.

| Funkció | Űrlaponként ("Anti Spam" fül), alapból **ki** | Globális érték (Blacklist oldal, nem kapcsoló) |
|---|---|---|
| Statisztika | nincs kapcsoló, mindig fut (a felhasználó döntése) | nincs |
| Karantén | be/ki | megőrzési idő, maximum bejegyzés (a tábla közös) |
| Pontrendszer | be/ki, küszöb, további írások | nincs |
| Eldobható e-mail | be/ki | nincs |
| Nem létező domain (MX) | be/ki | nincs |
| Proof-of-work | be/ki, nehézség | nincs |

- Frissítés után így semmi nem változik magától, egyetlen űrlapon sem. A statisztika kivétel, mert csak számol: a
  beküldések viselkedése nem változik, személyes adatot nem tárol.
- A karantén **listája** egy közös, globális lista minden űrlap bejegyzéseivel (lásd Admin-felület); az űrlaponkénti
  kapcsoló csak azt dönti el, hogy az adott űrlap spamje bekerül-e.
- A meglévő funkciók (időbélyeg, honeypot, captcha stb.) kapcsolói változatlanok maradnak.

### 1. Statisztika

- **Kapcsoló:** nincs, mindig fut, minden űrlapon (a felhasználó döntése).
- **Költség:** beküldésenként 1-3 rövid `INSERT ... ON DUPLICATE KEY UPDATE` (okonként egy), összesen kb. 1 ms;
  oldalmegtekintéskor semmi nem fut. A takarítás naponta egyszer egy `DELETE`. A tábla kicsi: naponta űrlaponként és
  okonként egy sor (pl. 10 űrlap, 90 nap: néhány ezer sor).
- **Tárolás:** új tábla `{prefix}eas_cf7_stats`: `stat_date DATE`, `form_id BIGINT UNSIGNED`, `reason VARCHAR(40)`,
  `count INT UNSIGNED`, elsődleges kulcs (`stat_date`, `form_id`, `reason`). Növelés egyetlen
  `INSERT ... ON DUPLICATE KEY UPDATE count = count + 1` lekérdezéssel. Személyes adat nincs benne.
- **Mit számol:** minden beküldést egyszer, a `wpcf7_submit` végén, a végeredmény szerint:
  - `sent` (mail_sent), `failed` (mail_failed), `aborted`,
  - spam esetén a plugin spam-okai stabil kódokkal: `honeypot`, `timestamp`, `captcha`, `acceptance`, `fields_missing`
    (beküldés az anti-spam mezők nélkül), `score`, `pow`, illetve `spam_other` (más plugin jelölte spamnek),
  - validációs hiba esetén a plugin okai: `too_fast`, `expired`, `token_used`, `captcha_wrong`, `badwords`,
    `blacklist`, `ratelimit`, `email_disposable`, `email_domain` (nincs MX), illetve `invalid_other` (CF7 saját mezőhibája).
  Egy beküldésnél több ok is lehet, mindegyik eggyel nő; a `sent`/`failed`/`aborted` mellett nem számolódik ok.
- **Kiengedés a statisztikában:** a karantén kiengedése a `released` számlálót növeli (a kiengedett bejegyzés eredeti
  okaival együtt, `released:<ok>` kóddal), így a téves riasztások aránya okonként látszik. A kiengedés `wpcf7_submit`-je
  nem számolódik `sent`-nek (kiengedés jelzővel kihagyva), így semmi nem számolódik kétszer.
- **Takarítás:** 90 napnál régebbi sorok törlése, naponta legfeljebb egyszer.
- **Megjelenítés:** lásd Admin-felület.
- *Az én döntésem:* a meglévő `add_spam()` / `invalidate_log()` hívásai kapnak egy stabil okkódot (a szöveges
  logüzenet változatlan marad), és a statisztika ezekből számol.

### 2. Karantén

- **Kapcsoló:** űrlaponként, alapból **ki**. Kikapcsolva a spam úgy viselkedik, mint ma. Így érzékeny adatokat
  kérő űrlapoknál (pl. jelentkezés) a karantén kihagyható. Megőrzési idő és maximum globálisan a Blacklist oldalon.
- **Mi kerül bele** (a felhasználó döntése): csak azok a spam-okok, ahol ember is fennakadhat: `score`
  (pontrendszer), `honeypot` (jelszókezelő vagy automatikus kitöltés is kitöltheti) és `pow`. A biztos botjelek
  (`acceptance` = manipulált token, `fields_missing`, `timestamp`-manipuláció) nem kerülnek be, csak a statisztikában
  látszanak. Így a botok nem töltik meg a keretet, és a ritka valódi téves riasztás megmarad. Egy beküldés akkor kerül
  be, ha legalább egy oka ezek közül való. A látogató minden esetben ugyanazt a CF7 spam-hibaüzenetet kapja,
  mint ma (a felhasználó döntése: a fennakadt beküldésnél jelezni kell, hogy nem ment el). Az e-mail-ellenőrzés
  validációs hibát ad, nem spamet, így nem kerül a karanténba.
- **Tárolás:** új tábla `{prefix}eas_cf7_quarantine`:
  - `id`, `created` (DATETIME), `form_id`, `reasons` (a spam-okok kódjai és szövege, JSON), `score` (INT, a pontrendszer
    összege, ha futott), `posted_data` (a beküldött `$_POST` a belső mezők nélkül, JSON), `meta` (JSON: oldal-URL,
    referer, `_wpcf7_container_post`, `_wpcf7_unit_tag`, `_wpcf7_locale`, felhasználói ügynök, IP-cím),
    `has_files` (volt-e fájlmező), `release_error` (az utolsó sikertelen kiengedés oka).
  - Kizárt belső mezők: a timestamp, az acceptance token, a captcha mezői, a honeypot és a proof-of-work mezői.
    A `_wpcf7*` mezők a meta-ba kerülnek.
- **Takarítás:** óránként legfeljebb egyszer törli a megőrzési időnél régebbi bejegyzéseket (alapérték 30 nap,
  állítható 1-365), és a legrégebbieket, ha a bejegyzések száma meghaladja a maximumot (alapérték 1000, állítható
  100-10000).
- **Kiengedés ("Freigeben"):**
  - Csak `manage_options` joggal és nonce-szal.
  - Visszaállítja a kérést: `$_POST` = eltárolt mezők + `_wpcf7*` mezők, `$_SERVER['HTTP_REFERER']`,
    `$_SERVER['HTTP_USER_AGENT']`, `$_SERVER['REQUEST_URI']` (hogy a CF7 `url` metája az eredeti oldal legyen),
    a CF7 IP-címe a `wpcf7_remote_ip_addr` filterrel, a globális `$post` a container post.
  - Egy "kiengedés folyamatban" jelzővel minden anti-spam ellenőrzés kimarad: a plugin összes validációs és
    spam-ellenőrzése (időbélyeg, token, captcha, rate limit, pontrendszer, e-mail, proof-of-work), és a CF7 teljes
    spam-ellenőrzése a `wpcf7_skip_spam_check` filterrel (ez a más pluginek `wpcf7_spam` szűrőit, pl. Akismet, is
    kihagyja). Az admin ellenőrizte és elfogadta a beküldést, ezért a kiengedés **nem minősülhet újra spamnek**
    (a felhasználó döntése). A CF7 saját mezővalidálása (kötelező mezők, formátum) lefut.
  - `$contact_form->submit()` futtatja le a teljes beküldést (és nem közvetlenül `WPCF7_Submission::get_instance()`):
    így érvényes az űrlap `skip_mail: on` beállítása, és minden hook lefut, amire más pluginek akaszkodnak
    (`wpcf7_before_send_mail`, `wpcf7_mail_sent`, `wpcf7_submit`: Flamingo, lead API vagy bármilyen API-továbbítás),
    pontosan úgy, mint egy normál beküldésnél.
  - Siker (`mail_sent`): a bejegyzés törlődik, az admin üzenetet kap. Hiba: a bejegyzés marad, a `release_error`-ba
    kerül az ok (pl. "mail_failed" vagy a mezőhibák), és megjelenik a listában.
  - Fájlmezők: ha az űrlapnak kötelező fájlmezője van (`[file* ...]`), a bejegyzés nem engedhető ki (a CF7 validálása
    a fájl hiánya miatt mindig elbukna); a gomb helyett magyarázat jelenik meg. Nem kötelező fájlmezőnél a kiengedés
    figyelmeztetéssel lehetséges: a csatolmányok nem kerülnek továbbításra.
  - *Az én döntésem:* sikeres kiengedés után törlés (adattakarékosság), nem "kiengedve" státusz.
- **Törlés:** egyenként és "Alle löschen" (megerősítéssel).
- **Uninstall:** a tábla törlődik (ideiglenes adat), mint a token-tábla.

### 3. Spam-pontrendszer

- **Kapcsolók:** űrlaponként az "Anti Spam" fülön: be/ki (alapból **ki**) és a küszöb (alapból **10**, 1-100).
- **Mikor fut:** a CF7 validálás után, a `wpcf7_spam` szűrőben (ugyanott, ahol a meglévő spam-jelölés), csak ha az
  összes többi ellenőrzés átment. A küszöb elérése spam (CF7 spam-üzenet), az ok `score`, a részletek (melyik szabály
  hány pontot adott) a spam-logba és a karanténba kerülnek.
- **Mely mezők:** szöveges mezők (`text`, `textarea`, `email`, `tel`, `url` és ezek kötelező változatai), a rejtett és
  belső mezők nélkül. A "név mezőt" a mezőnévből ismeri fel (ugyanazok a minták, mint a log maszkolásánál: name, vorname,
  nachname stb.; filterrel bővíthető).
- **Szabályok és pontok** (a pontok filterrel állíthatók, a szabályok egyenként kikapcsolhatók filterrel):

| Szabály | Mérés | Pont |
|---|---|---|
| Kevert betűk egy szón belül | Latin szóban cirill hasonmás betű (`а е о р с у х і ј ѕ` és nagybetűs párjaik), pl. `vіagra`. Nem számít: CJK/thai szöveg, görög betű (mértékegység), latin betű cirill szóban (ukrán `i`). | 10 |
| BBCode- vagy HTML-link | `[url=`, `[link=`, `<a href=` bármelyik mezőben | 5 |
| URL vagy e-mail a névben | `http(s)://`, `www.` vagy `@` a név mezőben | 5 |
| Idegen írás | A szöveges mezők betűinek több mint fele nem az űrlap nyelvének írásával (és nem latinnal) íródott. Az űrlap nyelve a CF7 űrlap `locale()`-ja; a nyelv-írás megfeleltetés: cirill (ru, uk, bg, sr, mk, be, kk), kínai (zh), japán (ja: kandzsi, hiragana, katakana), koreai (ko), arab (ar, fa, ur), héber (he), görög (el), thai (th), egyéb esetben latin. Latin mindig megengedett. Űrlaponként további írások engedélyezhetők. | 5 |
| Csak link az üzenet | Egy textarea-mező linkeken kívül 20 betűnél kevesebbet tartalmaz | 4 |
| Ugyanaz több mezőben | Legalább két különböző szöveges mező (pl. név és üzenet) azonos, nem üres értéket tartalmaz | 3 |
| Értelmetlen név | Mezőnként: egy névszó gyanús, ha legalább 5 mássalhangzó követi egymást, vagy szón belül legalább 3 kis-nagybetű-váltás van, vagy 7+ betűnél a magánhangzók aránya 15% alatt van | 2 mezőnként |
| Túl sok link | Az összes szövegmezőben az első 2 link pontmentes, minden további | 2 linkenként |

- Példák 10-es küszöbbel: 5 link: 6 pont, átmegy; 5 link + BBCode: 11, spam; egy kevert betűs szó: 10, spam.
- **Költség:** néhány regex a beküldés szövegén, 1 ms alatt.

### 4. E-mail-ellenőrzés

- **Kapcsolók:** űrlaponként az "Anti Spam" fülön, két külön kapcsoló, alapból **ki**: "Eldobható e-mail-címek
  elutasítása" és "Nem létező e-mail-domainek elutasítása".
- **Hatás:** validációs hiba az e-mail-mezőnél (a látogató javíthat), saját, az eddigi e-mail-feketelista mintájára
  állítható hibaüzenettel. *Az én döntésem:* validációs hiba és nem spam, mert az elgépelés (`gmial.cmo`) és az
  eldobható cím ember által javítható. Statisztikai ok: `email_disposable`, `email_domain`.
- **Eldobható domainek:** beépített lista `lists/disposable-domains.txt` (nyílt forrású lista, a licencet a beépítés
  előtt ellenőrizni kell; CC0 vagy hasonló kell), a domain és minden szülődomainje ellen ellenőrizve
  (`x.mailinator.com`). Betöltés egyszer kérésenként, kulcsos tömbként.
- **Nem létező domain:** `dns_get_record()` MX-re, ha nincs, A/AAAA-ra (RFC 5321 fallback). IDN-domain
  `idn_to_ascii()`-val, ha elérhető. **Hiba esetén elfogad** (ha a DNS-lekérdezés nem válaszol vagy `false`, a cím
  érvényesnek számít), hogy egy DNS nélküli szerveren ne utasítson el mindent. Eredmény domainenként 24 órára
  transientben gyorsítótárazva. Csak akkor fut, ha az e-mail-cím formailag érvényes és az eldobható-ellenőrzés átment.
- **Költség:** lista 1-2 ms; DNS 5-50 ms gyorsítótár nélkül, lassú DNS-nél több.

### 5. Proof-of-work

- **Kapcsolók:** űrlaponként az "Anti Spam" fülön, alapból **ki**, mellette a nehézség (választómező, alapérték:
  telefonon kb. 1 mp; filterrel is).
- **Folyamat:**
  1. A szerver feladatot ad: véletlen só, nehézség (vezető nulla bitek száma), lejárat, a meglévő titkosítással aláírva
     és titkosítva (mint az időbélyeg). Kiadás a meglévő AJAX-végponton (`eap_reload_cf7_fields`), így cache-elt
     oldalon is friss; a token-kérés limit erre is vonatkozik.
  2. A böngésző egy Web Workerben keres egy számot, amivel `SHA-256(só + szám)` a megadott számú nulla bittel kezdődik.
     `crypto.subtle` (HTTPS), egyébként beépített JS SHA-256. Indítás a látogató első valódi interakciójára az űrlappal
     (mint az acceptance token), nem oldalbetöltéskor (akkumulátor, CPU).
  3. A megoldás rejtett mezőbe kerül. Ha a látogató előbb küldene, a meglévő capture-fázisú submit-kezelő
     (dupla-küldés védelem) visszatartja a küldést, amíg kész nincs, a gomb közben töltést jelez.
  4. A szerver ellenőrzi: aláírás, lejárat, egyetlen hash, és egyszer használatosnak jelöli (token-tábla, új típus
     `pow`, séma-verzió emelés).
- **Hibás vagy hiányzó megoldás:** spam (ok `pow`), mert ember ezt nem tudja elrontani. Lejárt megoldás: validációs
  hiba és új feladat (mint a lejárt időbélyeg).
- **Algoritmus:** az ALTCHA (MIT licenc) elvét követi, a saját felülete nélkül.
- **Költség:** szerver 1 ms alatt; böngésző a nehézségtől függően, telefonon kb. 0,5-2 mp, a kitöltés alatt.
- **Korlát:** JavaScript nélkül az űrlap nem küldhető el (mint az "AJAX loading" és az acceptance-ellenőrzés esetén);
  ezt az admin-leírás jelzi.

## Admin-felület

- **Blacklist oldal (beállítások):** új sor "Quarantäne": megőrzési idő és maximum bejegyzés (értékek, nem
  kapcsolók). Mellette rövid adatvédelmi megjegyzés
  (a karantén személyes adatot tárol, az adatvédelmi nyilatkozatban említendő).
- **Új oldal a Contact menüben: "Anti Spam"**, két füllel (*az én döntésem*: a globális értékek maradnak a Blacklist
  oldalon). Az oldal mindig látható (`manage_options`):
  - **Quarantäne:** egy közös lista minden űrlap bejegyzéseivel: dátum, **űrlap (címmel, link a szerkesztőre)**, okok,
    pontszám, a kiengedési hiba, ha volt; szűrés űrlapra; lapozás. Bejegyzés megnyitása a mezőkkel és oldal-adatokkal;
    gombok: Freigeben, Löschen, Alle löschen (a szűrt űrlapé vagy mind). Ha egyetlen űrlapon sincs bekapcsolva a
    karantén és a lista üres, egy tájékoztató mondja meg, hol kapcsolható be. Egy űrlap kapcsolójának kikapcsolása
    után a meglévő bejegyzései megmaradnak a megőrzési idő végéig.
  - **Statistik:** táblázat az utolsó 7 és 30 napról, űrlaponként, okonként; összesen sor.
- **"Anti Spam" fül az űrlapszerkesztőben:** új sorok, mind alapból kikapcsolva: karantén, pontrendszer
  (kapcsoló, küszöb, engedélyezett további írások), eldobható e-mail, nem létező domain, proof-of-work (kapcsoló,
  nehézség).

## Adatvédelem

- Statisztika: csak számlálók.
- Karantén: a teljes beküldés és az IP-cím a megőrzési ideig; űrlaponként, alapból kikapcsolva; csak admin látja;
  uninstallnál törlődik.
- MX-ellenőrzés: a domainnév a szerver DNS-feloldójához megy.
- A README GDPR-szakaszát ennek megfelelően frissíteni kell.

## Tesztelés

- test1 (írható). A kiengedést **csak lead API nélküli űrlapon** szabad tesztelni (pl. a 5226-os tesztűrlap), mert a
  test1 űrlapjainak többsége a valódi lead API-ra küld. Ha lead API-s kiengedést kell tesztelni, csak
  `MKT_CF7_LEAD_API_TEST_MODE` mellett.
- PHP 8.2 WordPress-szel (test1), PHP 8.5 szintaxis és a WordPress nélküli osztály-harness.
- Böngészős próbaküldés: karanténba kerülés, kiengedés (levél a log-emails-ben), pontrendszer-példák, proof-of-work
  telefon-méretű nézetben, e-mail-elutasítások.

## Az ellenőrző kör után eldöntve (az én döntéseim, javíthatók)

- Az e-mail-ellenőrzés mindig validációs hiba, nem spam, és nem kerül karanténba.
- A teszt-lead védelem tesztelési szabály, nem kódfunkció.
- A kiengedés `released` számlálót kap a statisztikában, és nem számolódik kétszer.
- Kötelező fájlmezős űrlap bejegyzése nem engedhető ki.
- Kiengedés `$contact_form->submit()`-tel (`skip_mail` és `wpcf7_submit` miatt).
- A felhasználó döntései: a karanténba csak a `score`, `honeypot` és `pow` okú spam kerül (a), legrégebbi törlődik
  előbb; a kiengedett beküldés nem minősülhet újra spamnek; minden új funkció csak űrlaponként kapcsolható, alapból
  kikapcsolva, globális kapcsoló nélkül; a statisztika kapcsoló nélkül mindig fut; a karantén listája közös, űrlap
  szerint jelölve és szűrhető.

## Az építőre hagyva

- A meglévő spam- és validációs okok leképezése a stabil okkódokra.
- Az eldobható-domain lista kiválasztása (licenc!) és frissítési módja.
- A proof-of-work nehézség pontos alapértéke mérés alapján.
- Az admin-listák lapozása, a táblák indexei a takarításhoz.
- A verziószám, a changelog és a fordítások.
- A megvalósítás sorrendje (javasolt: statisztika, karantén, pontrendszer, e-mail, proof-of-work), mindegyik külön
  commit, külön teszttel.
