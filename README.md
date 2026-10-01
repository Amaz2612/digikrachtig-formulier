# Formulierensysteem Digikrachtig Rivierenland

Met dit programma vullen stagiairs van ROC Rivor bij hun stagebedrijf een vragenlijst in over digitale vaardigheden (Microsoft 365, website, social media, AI en kenniscafés). De opdrachtgever bekijkt de antwoorden op een beheerpagina.

Gebouwd in PHP (zonder framework) met MySQL/MariaDB.

---

## 1. Wat heb je nodig

- **XAMPP** met Apache en MySQL (getest met PHP 8.2 en MariaDB 10.4)
- Een browser

In deze handleiding staat XAMPP in `C:\xampp`. Staat het bij jou ergens anders, bijvoorbeeld in `C:\Faya`, pas de paden dan aan.

## 2. Installeren

**Stap 1: bestanden neerzetten**
Zet de projectmap in `C:\xampp\htdocs\Digikrachtig`.

**Stap 2: instellingen**
Kopieer `config/config.example.php` naar `config/config.php` en vul hem in:

| Instelling | Wat |
|---|---|
| `db_host`, `db_naam`, `db_gebruiker`, `db_wachtwoord` | Gegevens van de database. Bij XAMPP is dat meestal `localhost`, `digikrachtig`, `root` en een leeg wachtwoord. |
| `crypt_sleutel` | Sleutel waarmee de namen van studenten versleuteld worden. Maak er een met het commando hieronder. **Bewaar hem goed: zonder deze sleutel zijn de opgeslagen namen niet meer te lezen.** |
| `debug` | `true` tijdens het maken en testen, `false` als het live gaat |

Een sleutel maken:
```
C:\xampp\php\php.exe -r "echo base64_encode(random_bytes(32));"
```

`config/config.php` staat in `.gitignore` en komt dus nooit op GitHub.

**Stap 3: database**
1. Start Apache en MySQL in het XAMPP Control Panel.
2. Draai deze bestanden uit de map `database`, **in deze volgorde**, bijvoorbeeld in phpMyAdmin (tabblad SQL → bestand kiezen):

| Volgorde | Bestand | Wat |
|---|---|---|
| 1 | `schema .sql` | Maakt de database en de tabellen. **Let op: dit gooit bestaande gegevens weg.** |
| 2 | `vragenlijst.sql` | Zet de vragenlijst erin. Dit hoort ook op de echte server. |
| 3 | `admin.sql` | Tabel voor de beheerders |
| 4 | `testdata.sql` | Twee testgebruikers. **Alleen lokaal, nooit op de echte server.** |

Bestond de database al met studentnummers zonder `rv` ervoor? Draai dan ook één keer `studentnummers-rv.sql`.

**Stap 4: een beheerder aanmaken**
Open een opdrachtprompt in de projectmap en typ:
```
C:\xampp\php\php.exe tools\maak-admin.php
```
Kies een gebruikersnaam en een wachtwoord van minstens 12 tekens. Met hetzelfde commando zet je later een nieuw wachtwoord als je het vergeten bent.

## 3. Gebruik door studenten

1. Ga naar **http://localhost/Digikrachtig/public/**
2. Vul je **studentnummer** in. Alleen de cijfers: `rv` staat er al voor.
3. Vul je **voor- en achternaam** en je **e-mailadres** in en klik op **Inloggen**.
4. Je krijgt een **code van 6 cijfers** per mail. Vul die in.
   - Staat `debug` op `true`, dan wordt er geen mail verstuurd. De code staat dan op het scherm.
5. Vul de vragenlijst in.
   - Het formulier is opgedeeld in stappen. Ga verder met **Volgende**.
   - Sommige vragen verschijnen pas na een bepaald antwoord. Kies je bijvoorbeeld "nee" bij Microsoft 365, dan worden de stappen Word t/m OneDrive overgeslagen.
   - Je antwoorden worden **automatisch opgeslagen**. Je kunt de pagina sluiten en later verder gaan waar je was.
6. Klik op de laatste stap op **Versturen**. Daarna kun je het formulier niet meer aanpassen.

## 4. Gebruik door de beheerder

1. Ga naar dezelfde inlogpagina: **http://localhost/Digikrachtig/public/**
2. Vul bij **Studentnummer** je **gebruikersnaam** in (dus geen nummer). De velden Naam en E-mail verdwijnen dan.
3. Klik op **Inloggen** en vul daarna je **wachtwoord** in.
4. Je komt op de **beheerpagina**:
   - **Overzicht:** hoeveel inzendingen er zijn (concept en ingediend) en een lijst met alle inzendingen.
   - **Bekijken:** alle antwoorden van één student, per onderdeel, met onderaan een logboek.
   - **Inzending resetten:** wist alle antwoorden van een student en zet de status terug op concept, zodat de student opnieuw kan beginnen. Je krijgt eerst de vraag of je het zeker weet. **Dit kun je niet ongedaan maken.**
5. Klik rechtsboven op **Uitloggen** als je klaar bent.

Na 5 keer een fout wachtwoord moet je opnieuw beginnen bij de inlogpagina.

## 5. Testen

De automatische tests draaien op een aparte database (`digikrachtig_test`). Je eigen gegevens worden daarbij niet aangeraakt.

```
C:\xampp\php\php.exe tests\alles.php
```

Uitleg over de tests en de uitkomsten staat in `docs/Testrapport.md`.

## 6. Problemen oplossen

| Probleem | Oplossing |
|---|---|
| Foutmelding over `config/config.php` ("Failed opening required" of "ontbreekt") | Kopieer `config/config.example.php` naar `config/config.php` (stap 2). |
| "Er is op dit moment geen actief formulier" | `vragenlijst.sql` is niet gedraaid (stap 3). |
| Pagina laadt niet / "Can't connect to MySQL" | Start Apache en MySQL in het XAMPP Control Panel. |
| Student krijgt geen mail | Op een lokale XAMPP is meestal geen mailserver ingesteld. Zet `debug` op `true`, dan staat de code op het scherm. |
| "Het formulier is verlopen" | De pagina stond te lang open. Ga terug, ververs de pagina en probeer opnieuw. |
| Beheerder kan niet inloggen | Bestaat de beheerder wel? Maak hem (opnieuw) aan met `tools\maak-admin.php`. Na 5 foute pogingen moet je opnieuw beginnen op de inlogpagina. |
| Student ziet na inloggen zijn oude antwoorden niet meer | De database is van vóór de rv-nummers. Draai `database/studentnummers-rv.sql`. |

## 7. Voordat het live gaat

- [ ] In `config/config.php`: `debug` op `false`, en een eigen `crypt_sleutel`
- [ ] Mail instellen, zodat de inlogcodes echt verstuurd worden
- [ ] `testdata.sql` **niet** draaien op de echte server
- [ ] De testpagina's weghalen: `public/test-opslaan.php`, `public/test-validatie.php` en `public/controle.php`. Zie `docs/Testrapport.md`, probleem 1.
- [ ] Alleen de map `public` bereikbaar maken via de webserver

## 8. Mappen

| Map | Wat staat erin |
|---|---|
| `public/` | Wat de browser opent: `index.php` (studenten en inloggen), `admin.php` (beheer), CSS, JavaScript en afbeeldingen |
| `app/` | De PHP-code: modellen (database), controllers en views (HTML) |
| `config/` | Instellingen |
| `database/` | SQL-bestanden voor de database |
| `tools/` | `maak-admin.php` om een beheerder aan te maken |
| `tests/` | Automatische tests |
| `docs/` | Testrapport, uitleg over de beheerpagina en planning |
