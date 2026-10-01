# Beheerpagina

Met de beheerpagina kan de opdrachtgever de inzendingen bekijken en een inzending resetten. Beheerders loggen in op **dezelfde inlogpagina als de studenten**.

## Installeren

1. Draai `database/admin.sql`, bijvoorbeeld in phpMyAdmin (tabblad SQL) of met:
   ```
   C:\xampp\mysql\bin\mysql.exe -u root < database\admin.sql
   ```
   Dit maakt de tabel `admins` aan en voegt `gereset` toe aan de soorten logregels in `submission_events`. Je mag het vaker draaien: bestaande beheerders blijven staan.

   **Let op:** draai je later `schema .sql` opnieuw, draai daarna ook `admin.sql` weer.

   Sla `admin.sql` niet over. XAMPP draait MariaDB standaard zonder strict mode. Resetten lijkt dan te werken, maar de logregel komt er met een lege soort in te staan in plaats van `gereset`.

2. Heeft de database al studenten van vóór de rv-nummers? Draai dan één keer `database/studentnummers-rv.sql`. Dat zet `rv` voor de bestaande studentnummers. Zonder deze stap krijgt een student die opnieuw inlogt een nieuwe, lege inzending. Hoe je het terugdraait, staat in het bestand zelf.

3. Maak een beheerder aan (zie hieronder).

## Een beheerder aanmaken

Vanuit de map van het project:

```
C:\xampp\php\php.exe tools\maak-admin.php
```

Het script vraagt om:
- een gebruikersnaam: 3 tot 50 tekens (letters, cijfers, `.`, `-` en `_`), en niet alleen cijfers of `rv` met cijfers, want dan lijkt het op een studentnummer;
- een wachtwoord van 12 tot 72 tekens, twee keer.

Bestaat de gebruikersnaam al, dan vraagt het of je een nieuw wachtwoord wilt instellen. Zo zet je ook een vergeten wachtwoord opnieuw.

Goed om te weten:
- Het wachtwoord gaat niet als argument mee, zodat het niet in de geschiedenis van de opdrachtregel komt.
- In de database komt alleen de hash van `password_hash()`. Er staat nergens een wachtwoord of hash in de repo.
- Op Windows is het wachtwoord zichtbaar terwijl je typt.
- Het script werkt alleen vanaf de opdrachtregel. Via de browser geeft het 403.

## Inloggen

Ga naar de gewone inlogpagina:

```
http://localhost/Digikrachtig/public/
```

**Als beheerder:**
1. Vul bij **Studentnummer** je gebruikersnaam in, bijvoorbeeld `beheerder`. Zodra er iets anders dan cijfers staat, verdwijnen de velden Naam en E-mail.
2. Klik op Inloggen. Je krijgt een scherm met je wachtwoord, in plaats van een code per mail.
3. Na het goede wachtwoord kom je op `admin.php`.

Ga je meteen naar `http://localhost/Digikrachtig/public/admin.php` terwijl je niet ingelogd bent, dan word je doorgestuurd naar de inlogpagina.

**Als student:**
Voor het veld Studentnummer staat al vast `rv`. De student typt alleen de cijfers, bijvoorbeeld `2100001`, en het wordt opgeslagen als `rv2100001`. Typt een student toch `rv` erbij, dan is dat ook goed. Daarna gaat alles zoals voorheen: naam, e-mail en de code per mail.

## Wat er op de pagina's staat

**Overzicht**
- Het aantal inzendingen: totaal, concept en ingediend.
- Een tabel met alle inzendingen: studentnummer, status, gestart op, ingediend op en aantal antwoorden.
- De tabel is gesorteerd op **gestart op**, nieuwste bovenaan.
- **Antwoorden** telt beantwoorde vragen, geen rijen in de database. Een checkbox met drie vinkjes telt dus als 1.

**Detail van een inzending**
- Je komt hier via "Bekijken" in het overzicht.
- Alle antwoorden staan in dezelfde volgorde en onder dezelfde sectiekopjes als in het formulier.
- De eerste en de laatste sectie hebben in het formulier geen kopje. Hier heten ze "Gegevens" en "Afsluiting".
- Gekozen werkwijze voor verborgen en lege vragen:
  - **Verborgen vragen worden overgeslagen.** Die hoorden niet bij het pad van deze invuller. Heeft een bedrijf geen Microsoft 365, dan zie je de secties Word t/m OneDrive dus niet.
  - **Zichtbare vragen die leeg zijn gebleven** staan er wel, met *niet ingevuld*.
  - Staat er toch een antwoord bij een vraag die verborgen is, dan wordt het getoond. Wat in de database staat, moet je kunnen zien.
  - Meldingen (alleen tekst) worden overgeslagen.
- Bij checkboxvragen staan alle aangevinkte waarden in een lijstje.
- Onderaan staan het logboek uit `submission_events` en de knop "Inzending resetten".

**Resetten**
1. "Inzending resetten" opent een pagina met de vraag of je het zeker weet. Die pagina verandert nog niets.
2. "Ja, resetten" stuurt een POST met CSRF-token. In één transactie gebeurt dan het volgende:
   - alle antwoorden worden verwijderd;
   - de status gaat terug op `concept`;
   - `ingediend_op` en `huidige_stap` worden leeg;
   - er komt een regel `gereset` in het logboek, met de naam van de beheerder.

   Gaat er iets mis, dan wordt alles teruggedraaid.

## Beveiliging

- **Eén sessie, eigen sleutels.** Student en beheerder delen dezelfde sessie, maar staan onder een eigen sleutel: de student in `$_SESSION['gebruiker_id']`, de beheerder in `$_SESSION['beheerder']`. De studentenkant kijkt alleen naar de eerste, de beheerpagina alleen naar de tweede. Een student is dus nooit vanzelf beheerder, en andersom.
  - De eerdere versie gebruikte een eigen cookie voor beheer. Dat kan niet meer nu het inloggen op dezelfde pagina gebeurt.
- **Uitloggen.** Uitloggen als beheerder haalt alleen het beheerdersdeel weg. Uitloggen als student (de bestaande knop) gooit de hele sessie weg, dus dan ben je ook als beheerder uitgelogd.
- **Namen raden.** Elke gebruikersnaam krijgt de wachtwoordstap, ook een naam die niet bestaat, en de foutmelding is altijd dezelfde. Zo kun je niet uitproberen welke beheerdersnamen er zijn.
- **Te vaak fout.** Na 5 foute wachtwoorden, of als de stap langer dan 5 minuten openstaat, moet je opnieuw beginnen. Let op: dit telt per sessie. Wie steeds een nieuwe sessie begint, kan blijven proberen. Gebruik dus een sterk wachtwoord.
- **Na inloggen** krijg je een nieuw sessie-id.
- **Toegang.** Elke beheerpagina controleert als eerste of er een beheerder is ingelogd. Anders word je doorgestuurd naar de inlogpagina.
- **CSRF.** Alles wat iets verandert (inloggen, wachtwoord, uitloggen, resetten) gaat via POST met een CSRF-token.
- **Queries en uitvoer.** Alle SQL gebruikt prepared statements, en alles op het scherm gaat door `htmlspecialchars`.

## Toegevoegde bestanden

| Bestand | Wat |
|---|---|
| `public/admin.php` | Ingang van de beheerpagina (routing via `?actie=`) |
| `app/BeheerAuth.php` | Wachtwoordstap, inloggen en uitloggen van beheerders |
| `app/models/BeheerderModel.php` | SQL voor de tabel `admins` |
| `app/models/BeheerModel.php` | SQL voor overzicht, detail, logboek en resetten |
| `app/controllers/BeheerController.php` | Controller van de beheerpagina |
| `app/views/wachtwoord.php` | Wachtwoordstap voor beheerders (na de gewone inlogpagina) |
| `app/views/beheer/balk.php` | Balk met naam en uitlogknop |
| `app/views/beheer/overzicht.php` | Overzicht van alle inzendingen |
| `app/views/beheer/detail.php` | Eén inzending met antwoorden en logboek |
| `app/views/beheer/reset.php` | Bevestigingsvraag voor resetten |
| `app/views/beheer/niet-gevonden.php` | Melding als een inzending niet bestaat (404) |
| `database/admin.sql` | Tabel `admins` en `gereset` in `submission_events` |
| `database/studentnummers-rv.sql` | Eenmalig: `rv` voor bestaande studentnummers |
| `tools/maak-admin.php` | Beheerder aanmaken of wachtwoord wijzigen |
| `tests/beheer_test.php`, `tests/inloggen_test.php` | Tests op de testdatabase |
| `tests/http_beheer_test.php` | Tests via Apache |
| `docs/Beheerpagina.md` | Dit bestand |

## Gewijzigde bestanden

| Bestand | Wat |
|---|---|
| `public/index.php` | Herkent een gebruikersnaam in het veld Studentnummer en heeft de nieuwe actie `?actie=wachtwoord` |
| `app/views/login.php` | Vaste `rv` voor het studentnummer. Naam en e-mail staan in een eigen blok dat verdwijnt bij een gebruikersnaam; `required` wordt daarom door het script gezet. |
| `app/Auth.php` | `schoonStudentnummer()` geeft `rv` + cijfers terug (4 tot 18 cijfers), `login()` controleert op die vorm |
| `database/schema .sql` | `gereset` toegevoegd aan `submission_events.event_type` |
| `database/testdata.sql` | Testgebruikers `rv2100001` en `rv2100002` |
| `public/css/stijl.css` | Het `rv`-blokje voor het veld, en onderaan het blok "Beheerpagina" |
| `tests/bootstrap.php`, `tests/lib/controller_stap.php`, `tests/alles.php` | De tests kunnen ook de beheercontroller aanroepen |

## Testen

```
C:\xampp\php\php.exe tests\alles.php
```

Dit draait alle tests op de testdatabase `digikrachtig_test`.

De tests via Apache hebben een beheerder nodig. Geef die mee via omgevingsvariabelen:

```
set BEHEER_NAAM=beheerder
set BEHEER_WACHTWOORD=jouwwachtwoord
C:\xampp\php\php.exe tests\alles.php --http
```

**Let op:** deze test reset de inzending van testgebruiker `rv9999001` in de gewone database.
