# Testrapport formulierensysteem Digikrachtig Rivierenland

Datum: 1 oktober 2026
Versie van de code: commit `3280be2` (OTP-inlog, gegevens vooraf invullen, navbar, logo en achtergrond)

---

## 1. Inleiding

Dit rapport gaat over het formulierensysteem dat wij bouwen voor Digikrachtig Rivierenland. Stagiairs van ROC Rivor vullen hiermee bij hun stagebedrijf een vragenlijst in over hoe het bedrijf met digitale middelen werkt (Microsoft 365, website, social media, AI en kenniscafés).

Het systeem in het kort:

- De student logt in met studentnummer, naam en e-mail, en krijgt een code per mail (OTP).
- De vragen staan in de database (`questions`), niet in de code. Een vraag kan afhangen van het antwoord op een andere vraag ("Bij ja" / "Bij nee").
- Het formulier wordt in stappen getoond, één stap per sectie. JavaScript laat vragen verschijnen en verdwijnen en slaat automatisch tussentijds op.
- De server rekent opnieuw uit welke vragen zichtbaar zijn, controleert de antwoorden en slaat alleen zichtbare antwoorden op.
- Na het versturen staat de inzending op `ingediend` en kan hij niet meer veranderd worden.

We hebben getest:

- of de database klopt (aantallen, foreign keys, voorwaarden);
- of de voorwaardelijke vragen goed werken, ook bij ketens van meerdere vragen;
- of antwoorden op verborgen vragen echt niet worden opgeslagen;
- of de controle op de server foute invoer tegenhoudt;
- of checkboxen met meerdere antwoorden goed worden opgeslagen en teruggelezen;
- of verzoeken zonder geldig CSRF-token worden geweigerd.

We testen dit omdat de gegevens later door een analysegroep gebruikt worden. Als er antwoorden in de database staan op vragen die de invuller nooit heeft gezien, of als er rare waarden in staan, kloppen de cijfers niet meer.

## 2. Testomgeving

| Onderdeel | Versie |
|---|---|
| Besturingssysteem | Windows 11 Home (10.0.26200) |
| Webserver | Apache 2.4.58 (Win64), uit XAMPP |
| PHP | 8.2.12 (CLI, uit XAMPP) |
| Database | MariaDB 10.4.32 (uit XAMPP) |
| Browser | *nog invullen bij de handmatige tests* |
| Testdatabase | `digikrachtig_test`, elke keer opnieuw opgebouwd uit `database/schema .sql`, `database/vragenlijst.sql` en `database/testdata.sql` |

De automatische tests draaien op een aparte database (`digikrachtig_test`), zodat de database waar we mee ontwikkelen niet verandert. Alleen de CSRF-test via HTTP (H1 t/m H8) gaat via Apache en gebruikt dus de gewone database `digikrachtig`. Die test logt in als testgebruiker `9999001`.

Er is niet getest op MySQL 8. Volgens het schema moet het daar ook werken, maar dat hebben we niet gecontroleerd.

## 3. Testaanpak

Er wordt op twee plekken gecontroleerd: in de browser en op de server.

**In de browser** (`public/js/voorwaarden.js`):
- vragen tonen en verbergen terwijl je invult;
- melding onder een vraag als je op Volgende klikt en er nog iets leeg is;
- automatisch opslaan;
- voortgangsbalk.

Dit is er voor het gemak van de invuller. Hij ziet meteen wat er mis is en hoeft niet te wachten tot hij op Versturen drukt.

**Op de server** (`app/Voorwaarden.php`, `app/Validatie.php`, `app/models/InzendingModel.php`):
- opnieuw uitrekenen welke vragen zichtbaar zijn;
- controleren of verplichte vragen zijn ingevuld, of een e-mailadres geldig is, of een keuze in de lijst staat;
- alleen zichtbare antwoorden opslaan;
- CSRF-token controleren.

Dit is er voor de betrouwbaarheid van de data. Wat de browser opstuurt kun je niet vertrouwen: iemand kan JavaScript uitzetten, de pagina aanpassen in de ontwikkelaarstools, of zelf een verzoek sturen met bijvoorbeeld Postman of curl. Dan wordt de controle in de browser gewoon overgeslagen.

Daarom zijn ze **allebei** nodig. Alleen de browser is onveilig. Alleen de server is onhandig voor de invuller, want die ziet dan pas na het versturen dat er iets fout is.

Zo hebben we getest:

- **Servertests (automatisch):** kleine PHP-scripts in de map `tests/`. Die roepen de echte classes aan (`Voorwaarden`, `Validatie`, `FormulierModel`, `InzendingModel`, `FormulierController`) op de testdatabase en vergelijken de uitkomst met wat we verwachten. De controller-tests sturen nep-POST-verzoeken met en zonder token.
- **HTTP-test (automatisch):** `tests/http_csrf_test.php` stuurt echte verzoeken naar Apache, zoals een browser of een aanvaller dat zou doen.
- **Browsertests (handmatig):** tonen, verbergen, opmaak en mobiel kunnen we niet met een script testen. Die staan in de tabel met een lege kolom "werkelijk resultaat". Die vullen we zelf in.

Alle servertests opnieuw draaien:

```
C:\xampp\php\php.exe tests\alles.php          (alleen testdatabase)
C:\xampp\php\php.exe tests\alles.php --http   (ook CSRF via Apache)
```

Let op: bij ons staat XAMPP in `C:\Faya`, dus daar is het `C:\Faya\php\php.exe`.

## 4. Testgevallen

Uitslag van de automatische tests op 1 oktober 2026: **75 van de 78 geslaagd, 3 mislukt** (O9, W20, C7).

### 4.1 Database (`tests/database_test.php`)

| Nr | Omschrijving | Stappen | Verwacht resultaat | Werkelijk resultaat | Geslaagd |
|---|---|---|---|---|---|
| D1 | Aantal secties | Testdatabase opbouwen, `COUNT(*)` op `sections` | 13 | 13 | ja |
| D2 | Aantal rijen in `questions` | `COUNT(*)` op `questions` | 83 | 83 | ja |
| D3 | Aantal meldingen | `COUNT(*)` waar `type = 'melding'` | 7 | 7 | ja |
| D4 | Aantal echte vragen | `COUNT(*)` waar `type <> 'melding'` | 76 | 76 | ja |
| D5 | Aantal rijen met voorwaarde | `COUNT(*)` waar `toon_als_question_id IS NOT NULL` | 72 | 72 | ja |
| D6 | Voorwaarde altijd compleet | Rijen waar wel een oudervraag maar geen waarde staat, of andersom | 0 | 0 | ja |
| D7 | Alle foreign keys aanwezig | Query op `information_schema.REFERENTIAL_CONSTRAINTS` | 10 sleutels: answers (2), form_submissions (2), questions (3), sections (1), submission_events (1), user_profiles (1) | Precies dezelfde 10 | ja |
| D8 | Voorwaarde verwijst naar bestaande optie | Voor elke `toon_als_waarde` kijken of die in de `opties` van de oudervraag staat | Geen afwijkingen | Geen afwijkingen | ja |
| D9 | Volgorde voorwaarden | Zoeken naar vragen die afhangen van een vraag die later in het formulier komt | Geen | Geen | ja |
| D10 | Eén inzending per student | Twee keer een `form_submissions`-rij voor dezelfde student invoegen | Tweede keer geweigerd (fout 1062, dubbele sleutel) | Geweigerd (1062) | ja |
| D11 | Foreign key op antwoorden | Antwoord invoegen bij niet-bestaande inzending en vraag | Geweigerd (fout 1452) | Geweigerd (1452) | ja |

### 4.2 Voorwaardelijke vragen op de server (`tests/voorwaarden_test.php`)

| Nr | Omschrijving | Stappen | Verwacht resultaat | Werkelijk resultaat | Geslaagd |
|---|---|---|---|---|---|
| V1 | Website-keten, niets ingevuld | `Voorwaarden::isZichtbaar()` zonder antwoorden | `website_interesse`, `website_hulp`, `website_melding_hulp` verborgen | Alle drie verborgen | ja |
| V2 | Keten stap 1 | `website_heeft = nee` | Alleen `website_interesse` zichtbaar | Alleen `website_interesse` zichtbaar | ja |
| V3 | Keten helemaal open | `website_heeft = nee`, `website_interesse = ja`, `website_hulp = ja` | Alle drie zichtbaar | Alle drie zichtbaar | ja |
| V4 | Keten dicht na terugzetten | Daarna `website_heeft = ja`, de andere antwoorden blijven staan | Alle drie weer verborgen | Alle drie verborgen | ja |
| V5 | Keten stopt halverwege | `website_heeft = nee`, `website_interesse = nee`, `website_hulp = ja` | `website_hulp` en melding verborgen | Verborgen | ja |
| V6 | Keten via Microsoft 365 | `m365_gebruik = nee`, `word_gebruik = ja` meegestuurd | `word_gebruik` en `word_niveau` verborgen | Beide verborgen | ja |
| V7 | Voorwaarde met twee waarden | `m365_gebruik` = "ja, Microsoft 365" en apart "ja, Microsoft Office" | `word_gebruik` beide keren zichtbaar | Beide keren zichtbaar | ja |
| V8 | Melding bij Microsoft 365 | `m365_gebruik = nee` | `m365_alternatief` zichtbaar, `m365_melding` verborgen | Zo | ja |
| V9 | Voorwaarde op checkbox (aan) | `kenniscafe_interesse` = ["AI in het algemeen", "Anders, namelijk:"] | `kenniscafe_anders` zichtbaar | Zichtbaar | ja |
| V10 | Voorwaarde op checkbox (uit) | Alleen "AI in het algemeen" aangevinkt | `kenniscafe_anders` verborgen | Verborgen | ja |
| V11 | Social media-keten | `social_actief=ja`, `tevreden=nee`, `hulp_verbetering=ja`; daarna `social_actief=nee` | Melding eerst zichtbaar, daarna verborgen | Zo | ja |
| V12 | Hoofdletters | `website_heeft = Nee` (met hoofdletter) | `website_interesse` verborgen | Verborgen | ja |
| V13 | Rondje in voorwaarden | Nep-vragen a → b → a | Beide verborgen, geen oneindige lus | Beide verborgen, script liep gewoon door | ja |
| V14 | Startsituatie | `zichtbareCodes()` zonder antwoorden | 11 (alle vragen zonder voorwaarde) | 11 | ja |

### 4.3 Opslaan en teruglezen (`tests/opslaan_test.php`)

| Nr | Omschrijving | Stappen | Verwacht resultaat | Werkelijk resultaat | Geslaagd |
|---|---|---|---|---|---|
| O1 | Verborgen vragen niet opgeslagen | `slaAntwoordenOp()` met `website_heeft=ja`, `m365=nee` en daarnaast antwoorden op `website_interesse`, `website_hulp`, `word_gebruik`, `word_niveau` | Die vier staan niet in de database | Niet in de database | ja |
| O2 | Verborgen geraakt na wijziging | Eerst opslaan met `website_heeft=nee` + interesse/hulp, daarna opnieuw met `website_heeft=ja` | Eerst opgeslagen, daarna verdwenen | Eerst "ja"/"ja", daarna leeg | ja |
| O3 | Melding heeft geen antwoord | Waarde meesturen voor `website_melding_heeft` | Niet opgeslagen | Niet opgeslagen | ja |
| O4 | Verzonnen veld | Waarde meesturen voor `bestaat_niet` | Niet opgeslagen | Niet opgeslagen | ja |
| O5 | Checkbox, meerdere vinkjes | 3 vinkjes bij `kenniscafe_interesse` | 3 rijen in `answers` | 3 rijen | ja |
| O6 | Checkbox teruglezen | `antwoorden()` na O5 | Dezelfde lijst in dezelfde volgorde, ë in "kopiëren" intact | Zelfde lijst, ë goed | ja |
| O7 | Vervolgvraag op checkbox | `kenniscafe_anders` invullen met "Anders" aangevinkt | Opgeslagen | "Cursus 3D-printen" opgeslagen | ja |
| O8 | Checkbox met één vinkje | Eén vinkje opslaan en teruglezen | Lijst met één waarde (geen losse tekst) | `["AI in het algemeen"]` | ja |
| O9 | Dubbel vinkje | Zelfde optie twee keer meesturen (kan alleen met een aangepast verzoek) | Eén keer opgeslagen | **Twee keer opgeslagen**: `["AI in het algemeen","AI in het algemeen"]` | **nee** |
| O10 | Spaties | `functie` = alleen spaties, `naam_bedrijf` met spaties eromheen | `functie` niet opgeslagen, naam zonder spaties | Zo | ja |
| O11 | Opnieuw opslaan | Twee keer hetzelfde opslaan | 6 rijen, geen dubbele | 6 rijen | ja |
| O12 | Transactie | Opslaan met een kapot vraag-id zodat de foreign key faalt | Fout, oude antwoorden blijven staan | Fout gegooid, oude 6 antwoorden staan er nog | ja |
| O13 | Logboek | Opslaan met `log=false` en met `log=true` | 0 en 1 nieuwe regel in `submission_events` | 0 en 1 | ja |

### 4.4 Controle op de server (`tests/validatie_test.php`)

| Nr | Omschrijving | Stappen | Verwacht resultaat | Werkelijk resultaat | Geslaagd |
|---|---|---|---|---|---|
| W1 | Alles goed | Volledig pad zonder fouten (`m365=nee`, website ja, social nee, AI nee) | Geen fouten | Geen fouten | ja |
| W2 | Verplicht veld leeg | `naam_bedrijf = ''`, definitief | "Deze vraag is verplicht." | Die melding | ja |
| W3 | Verplicht veld alleen spaties | `naam_bedrijf = '    '` | "Deze vraag is verplicht." | Die melding | ja |
| W4 | Verplicht veld niet meegestuurd | `functie` weggelaten | "Deze vraag is verplicht." | Die melding | ja |
| W5 | Ongeldig e-mailadres | `email = jan[apenstaartje]test` | "Vul een geldig e-mailadres in." | Die melding | ja |
| W6 | Half e-mailadres | `email = jan@` | "Vul een geldig e-mailadres in." | Die melding | ja |
| W7 | Keuze niet in de lijst | `website_beheer = misschien` | "Kies een van de gegeven antwoorden." | Die melding | ja |
| W8 | Keuze met hoofdletter | `m365_gebruik = Nee` | Fout | Fout | ja |
| W9 | Tussentijds, verplicht leeg | `naam_bedrijf = ''`, niet definitief | Geen fout | Geen fout | ja |
| W10 | Tussentijds, alles leeg | Lege invoer, niet definitief | Geen fout | Geen fout | ja |
| W11 | Tussentijds, ongeldig e-mail | `email = jan@`, niet definitief | Wel een fout | Fout bij e-mail | ja |
| W12 | Checkbox, optie bestaat niet | "Gratis pizza" bij `kenniscafe_interesse` | "Ongeldige keuze." | Die melding | ja |
| W13 | Checkbox, geldige opties | Twee bestaande opties + `kenniscafe_anders` | Geen fout | Geen fout | ja |
| W14 | Verborgen verplichte vraag | `word_niveau` leeg bij `m365=nee` | Geen fout | Geen fout | ja |
| W15 | Zichtbare verplichte vervolgvragen | `m365 = ja, Microsoft 365`, verder niets over Office | Fout bij word, excel, powerpoint, outlook, teams, onedrive `_gebruik` | Precies die zes | ja |
| W16 | Foute keuze op verborgen vraag | `website_interesse = misschien` terwijl `website_heeft = ja` | Geen fout (vraag telt niet mee) | Geen fout | ja |
| W17 | Telefoonnummer | "abc" en "+31 (0)6-12345678" | Eerste fout, tweede goed | Zo | ja |
| W18 | Te lange tekst | 256 tekens in `functie`, 5001 in `opmerkingen` | Beide fout | Beide fout | ja |
| W19 | Lijst bij radiovraag | `website_beheer = ['zelf']` | "Ongeldig antwoord." | Die melding | ja |
| W20 | Lijst in een lijst bij checkbox | `kenniscafe_interesse = [['AI in het algemeen']]` | "Ongeldige keuze." en geen PHP-waarschuwing | Wel "Ongeldige keuze.", maar **PHP geeft de waarschuwing "Array to string conversion"** | **nee** |

### 4.5 Controller: CSRF, automatisch opslaan en versturen (`tests/controller_test.php`)

Deze tests roepen `FormulierController::verwerk()` en `autosave()` aan met een nep-sessie en nep-POST, op de testdatabase.

| Nr | Omschrijving | Stappen | Verwacht resultaat | Werkelijk resultaat | Geslaagd |
|---|---|---|---|---|---|
| C1 | Versturen zonder token | POST naar `verwerk()` zonder `csrf_token` | Status 400, tekst "verlopen", niets opgeslagen, status blijft concept | Zo | ja |
| C2 | Versturen met fout token | `csrf_token = fouttoken` | Status 400, niets opgeslagen | Zo | ja |
| C3 | Autosave zonder token | POST naar `autosave()` zonder token | Status 400, `{"ok":false,"reden":"verlopen"}`, niets opgeslagen | Zo | ja |
| C4 | Geen token in sessie | Sessie zonder token, leeg token meegestuurd | Status 400 | 400 | ja |
| C5 | Autosave met goed token | Token + `naam_bedrijf`, `email`, `stap=website` | 200, `{"ok":true}`, antwoord en stap bewaard | Zo | ja |
| C6 | Onbekende stap | `stap = bestaat_niet` | Oude stap (`website`) blijft staan | Blijft `website` | ja |
| C7 | Autosave met half e-mailadres | Eerst `jan@testbedrijf.nl` opgeslagen (C5), daarna autosave met `email = jan@` | Het eerder opgeslagen geldige adres blijft bewaard | **E-mail is helemaal weg uit de database** (naam bedrijf wel bewaard) | **nee** |
| C8 | Tussentijds via formulier | POST zonder `verstuur`, alleen `naam_bedrijf` | Opgeslagen, status blijft concept | Zo | ja |
| C9 | Versturen met leeg verplicht veld | `verstuur=1`, `functie = ''` | Foutmelding op de pagina, niets opgeslagen, regel `validatie_mislukt` in logboek | Zo | ja |
| C10 | Versturen, alles goed | `verstuur=1`, compleet pad + antwoord op verborgen `website_interesse` | Status `ingediend`, datum gezet, verborgen vraag niet opgeslagen | Zo | ja |
| C11 | Autosave na versturen | Autosave met nieuwe naam | Status 409, niets veranderd | 409, niets veranderd | ja |
| C12 | Opnieuw versturen | Nog een keer `verwerk()` met andere naam | Antwoorden niet veranderd | Niet veranderd | ja |

### 4.6 CSRF via echte HTTP-verzoeken (`tests/http_csrf_test.php`)

| Nr | Omschrijving | Stappen | Verwacht resultaat | Werkelijk resultaat | Geslaagd |
|---|---|---|---|---|---|
| H1 | Inloggen zonder token | POST naar `?actie=inloggen` zonder token | Geweigerd met "Het formulier is verlopen" | Geweigerd met die tekst (maar met status 200, zie probleem 7) | ja |
| H2 | Inloggen met token | Login-pagina ophalen, token meesturen, testcode van OTP-pagina invullen | Formulier wordt getoond | Formulier getoond | ja |
| H3 | Versturen zonder token | Ingelogd, POST naar `?actie=opslaan` zonder token | Status 400, "verlopen" | 400, "verlopen" | ja |
| H4 | Versturen met fout token | Token van 64 nullen | Status 400 | 400 | ja |
| H5 | Autosave zonder token | Ingelogd, POST naar `?actie=autosave` zonder token | 400, `{"ok":false,"reden":"verlopen"}` | Zo | ja |
| H6 | Controle: met goed token | Autosave met het token uit de pagina | 200, `{"ok":true}` | Zo | ja |
| H7 | Niet ingelogd | Autosave zonder sessie-cookie | 401, `{"ok":false,"reden":"uitgelogd"}` | Zo | ja |
| H8 | GET op opslaan | GET naar `?actie=opslaan` | Doorsturen (302), niets verwerken | 302 | ja |

### 4.7 Handmatige browsertests (nog zelf invullen)

Deze tests kunnen niet met een script. Vul de kolommen "werkelijk resultaat" en "geslaagd" zelf in. Zet bij elke test ook de browser en versie erbij.

| Nr | Omschrijving | Stappen | Verwacht resultaat | Werkelijk resultaat | Geslaagd |
|---|---|---|---|---|---|
| B1 | Website-keten openklappen | Bij "Heeft uw bedrijf een website?" nee kiezen, dan interesse ja, dan hulp ja | Vragen verschijnen één voor één, daarna de melding "Wij nemen contact met u op..." | | |
| B2 | Website-keten dichtklappen | Na B1 de eerste vraag op ja zetten | Interesse, hulp en melding verdwijnen; de vragen voor "Als uw bedrijf een website heeft" verschijnen | | |
| B3 | Stappen overslaan | Bij Microsoft 365 "nee" kiezen en op Volgende klikken | Word t/m OneDrive worden overgeslagen, je komt bij Website | | |
| B4 | Checkbox "Anders" | Bij kenniscafés "Anders, namelijk:" aanvinken en weer uitvinken | Tekstveld verschijnt en verdwijnt | | |
| B5 | Volgende met leeg verplicht veld | Naam bedrijf leeg laten, op Volgende klikken | Rode melding onder de vraag, je blijft op dezelfde stap, cursor staat in het veld | | |
| B6 | Niet-verplicht veld leeg | Telefoonnummer leeg laten (in de database niet verplicht), rest invullen, Volgende | Volgens de database mag je door. Let op: in `voorwaarden.js` staat `ALLES_VERPLICHT = true`, zie probleem 6 | | |
| B7 | Ongeldig e-mailadres | "jan@" invullen, Volgende | Melding van de browser onder de vraag | | |
| B8 | Automatisch opslaan | Iets invullen, even wachten | "Automatisch opgeslagen" verschijnt bovenaan | | |
| B9 | Verversen | Halverwege (bijv. stap Excel) de pagina verversen | Je komt terug op dezelfde stap met je antwoorden | | |
| B10 | Pagina sluiten tijdens typen | Iets typen en meteen het tabblad sluiten, opnieuw openen | Het getypte antwoord staat er nog | | |
| B11 | Voortgang | Door het formulier klikken met m365 = nee | "Stap x van y" telt overgeslagen stappen niet mee, balk loopt op | | |
| B12 | Versturen-knop | Door alle stappen gaan | Versturen-knop alleen zichtbaar op de laatste stap | | |
| B13 | Enter in tekstveld | Op Enter drukken in "Naam bedrijf" | Formulier wordt niet verstuurd | | |
| B14 | Fout na versturen | Met ontwikkelaarstools een verplicht veld leegmaken en versturen | Pagina komt terug, opent bij de stap met de eerste fout, melding bovenaan | | |
| B15 | JavaScript uit | JavaScript uitzetten in de browser, formulier invullen en versturen | Eén lange pagina, versturen werkt, server geeft fouten bij lege verplichte vragen | | |
| B16 | Bedankpagina | Formulier compleet versturen, daarna op Terug klikken | Bedankpagina; teruggaan geeft niet opnieuw een invulbaar formulier | | |
| B17 | Vooraf ingevuld | Inloggen met naam en e-mail, formulier openen | "Ingevuld door" en "E-mail" zijn al ingevuld met de login-gegevens | | |
| B18 | Inloggen met OTP | Verkeerde code invullen, daarna de goede | Eerst melding "Deze code klopt niet", daarna het formulier | | |
| B19 | Mobiel | In de ontwikkelaarstools een telefoon van 375 px breed kiezen, hele formulier doorlopen | Geen horizontaal scrollen, knoppen en keuzerondjes goed aan te tikken, tekst leesbaar | | |
| B20 | Opmaak in andere browsers | Formulier openen in Chrome, Firefox en Edge | Zelfde opmaak, logo en achtergrond zichtbaar | | |

## 5. Gevonden problemen

| Nr | Probleem | Ernst | Gevonden bij |
|---|---|---|---|
| 1 | Testpagina's staan openbaar in `public/` | blokkerend (voor livegang) | Code lezen + per ongeluk bevestigd |
| 2 | Lokale database loopt achter op de repo | hinderlijk | Query op `digikrachtig` |
| 3 | Autosave wist een eerder goed opgeslagen antwoord | hinderlijk | C7 |
| 4 | Browser strenger dan server (`ALLES_VERPLICHT`) | hinderlijk (nog te bevestigen met B6) | Code lezen |
| 5 | Dubbel vinkje wordt dubbel opgeslagen | klein | O9 |
| 6 | PHP-waarschuwing bij lijst in een lijst | klein | W20 |
| 7 | Mislukte CSRF bij inloggen geeft status 200 | klein | H1 + losse curl |
| 8 | Losse en verkeerd genoemde bestanden | klein | Code lezen |
| 9 | Vooraf invullen van `naam_student`, die vraag bestaat niet meer | klein | Code lezen |

**1. Testpagina's staan openbaar in `public/` (blokkerend voor livegang)**
`public/test-opslaan.php`, `public/test-validatie.php` en `public/controle.php` zijn zonder inloggen te openen (alle drie gaven status 200). `test-opslaan.php` is het ergst: die overschrijft de antwoorden van gebruiker 1 met verzonnen antwoorden, **ook als die inzending al is ingediend**. Dit is tijdens het testen per ongeluk echt gebeurd: bij het controleren of de pagina bereikbaar was is een HEAD-verzoek gestuurd, en PHP voert het script dan gewoon uit. Daardoor zijn in de lokale database de 39 antwoorden van testgebruiker 2100001 (ingediend op 24-09) vervangen door 12 testantwoorden. Er was geen back-up en geen binlog, dus dit is niet terug te zetten. Het ging om testdata, maar het laat zien wat er op een echte server zou kunnen gebeuren.
Oplossing: deze drie bestanden weghalen uit `public/`. De tests in `tests/` doen hetzelfde, maar staan buiten de webmap.

**2. Lokale database loopt achter op de repo (hinderlijk)**
In `digikrachtig` staan 77 vragen, 0 meldingen en 65 vragen met een voorwaarde, en het type `melding` zit niet in de lijst van de kolom `type`. Er staat nog een vraag `naam_student` in. Volgens `database/vragenlijst.sql` moeten het 76 vragen + 7 meldingen = 83 rijen zijn, waarvan 72 met een voorwaarde. Wie de site lokaal in de browser test, test dus een oude vragenlijst: de meldingen ("Wij nemen contact met u op...") verschijnen niet. Ook het getal "77 vragen" in de opdracht komt uit deze oude versie.
Oplossing: `schema .sql`, `vragenlijst.sql` en `testdata.sql` opnieuw draaien. Let op: daarmee gaan de huidige inzendingen in de lokale database weg.

**3. Autosave wist een eerder goed opgeslagen antwoord (hinderlijk)**
Bij automatisch opslaan worden ongeldige antwoorden overgeslagen. Maar `slaAntwoordenOp()` gooit eerst alle antwoorden weg en zet daarna alleen de geldige terug. Typt de student een nieuw e-mailadres of telefoonnummer en is het nog niet af, dan verdwijnt het oude goede antwoord uit de database (test C7). Sluit de student op dat moment de pagina, dan is het antwoord weg. Bij e-mail valt het waarschijnlijk mee: volgens de code in `toon()` wordt een leeg e-mailveld de volgende keer weer gevuld vanuit de login (niet apart getest). Bij het telefoonnummer gaat het echt verloren.
Mogelijke oplossing: in `FormulierController::autosave()` bij een fout het oude opgeslagen antwoord terugzetten in plaats van het veld weg te laten.

**4. Browser strenger dan server: `ALLES_VERPLICHT = true` (hinderlijk, nog te bevestigen met B6)**
In `public/js/voorwaarden.js` staat `ALLES_VERPLICHT = true`. Dan moet in de browser elke zichtbare vraag ingevuld zijn, ook het telefoonnummer, alle toelichtingen, de kenniscafés en "Overige vragen of opmerkingen". In de database zijn maar 32 van de 76 vragen verplicht. De server dwingt alleen die 32 af. Het commentaar in het script noemt "51 van de 84 vragen" niet verplicht; de echte aantallen zijn 44 van de 76. Dit moet met de opdrachtgever besproken worden: is het de bedoeling dat een bedrijf geen opmerking mag overslaan?

**5. Dubbel vinkje wordt dubbel opgeslagen (klein)**
Stuurt iemand met een aangepast verzoek dezelfde checkbox-optie twee keer mee, dan komen er twee gelijke rijen in `answers` (test O9). Via het gewone formulier kan dit niet. Het kan wel de tellingen van de analysegroep verstoren. Oplossing: `array_unique()` op de waarden van een checkbox voordat ze worden opgeslagen.

**6. PHP-waarschuwing bij een lijst in een lijst (klein)**
Bij `kenniscafe_interesse[][]=...` geeft `Validatie` wel netjes "Ongeldige keuze.", maar PHP geeft ook de waarschuwing "Array to string conversion" (test W20). Met `debug => true` komt die waarschuwing op het scherm. Kan alleen met een aangepast verzoek.

**7. Mislukte CSRF bij inloggen geeft status 200 (klein)**
Bij `?actie=opslaan` en `?actie=autosave` geeft een fout token status 400. Bij `?actie=inloggen` stopt het script met de goede tekst, maar met status 200 (getest met curl). Volgens de code gebeurt hetzelfde bij `otp` en `otp-opnieuw`; dat hebben we niet apart getest. Het verzoek wordt wel geweigerd, dus het is geen beveiligingsprobleem. Het is alleen niet gelijk. Oplossing: `http_response_code(400)` toevoegen in `public/index.php`.

**8. Losse en verkeerd genoemde bestanden (klein)**
- `database/schema .sql` heeft een spatie in de naam. Overal (ook in `vragenlijst.sql`) staat `schema.sql`.
- `app/views/login (1).php` lijkt een per ongeluk gemaakte kopie.

**9. Vooraf invullen van `naam_student` (klein)**
`FormulierController::toon()` vult `naam_student` vooraf in, en `public/test-opslaan.php` gebruikt het ook. Die vraag staat niet meer in `vragenlijst.sql`. Het geeft geen fout, maar het is code die niets doet.

## 6. Wat niet getest is en waarom

| Onderdeel | Waarom niet getest |
|---|---|
| Alle browsertests (B1 t/m B20): tonen/verbergen, stappen, opmaak, mobiel, JavaScript uit | Kan niet met een script vanaf de opdrachtregel. Staan als handmatige tests in 4.7. |
| `voorwaarden.js` los (zelfde regels als de server?) | Er is geen JavaScript-testomgeving. Wel gelezen: de regels voor zichtbaarheid zijn hetzelfde opgebouwd als in `Voorwaarden.php`. Wordt gecontroleerd met B1 t/m B4. |
| OTP per echte mail | Er is lokaal geen mailserver. Met `debug => true` staat de code op het scherm, en zo is in H2 ingelogd. |
| OTP-grenzen (verlopen code, te vaak fout, opnieuw sturen) | Niet aan toegekomen. Voor een deel in B18. |
| Versleuteling van de naam (`Versleuteling.php`) | Is bij het inloggen in H2 wel uitgevoerd, maar we hebben niet gecontroleerd of ontsleutelen de goede naam teruggeeft. |
| Login van Padgin | Bestaat nog niet; nu wordt een tijdelijke login gebruikt. |
| MySQL 8 | Alleen MariaDB 10.4 (XAMPP) beschikbaar. |
| Productie-instellingen (`debug => false`) | Niet getest; alle tests zijn met `debug => true` gedaan. |
| Veel gebruikers tegelijk / snelheid | Valt buiten deze testronde. |
| Toegankelijkheid (screenreader, alleen toetsenbord) | Valt buiten deze testronde. |

## 7. Conclusie

De belangrijkste onderdelen werken. Van de 78 automatische tests zijn er 75 geslaagd.

- De database klopt met de vragenlijst: 13 secties, 76 vragen, 7 meldingen, 72 voorwaarden en alle 10 foreign keys zijn er.
- Voorwaardelijke vragen werken op de server, ook in ketens (`website_heeft` → `website_interesse` → `website_hulp`) en bij een checkbox.
- Antwoorden op verborgen vragen worden niet opgeslagen. Ook niet als ze eerst zichtbaar waren en later verborgen raakten.
- De validatie houdt lege verplichte velden, ongeldige e-mailadressen en keuzes buiten de lijst tegen. Bij tussentijds opslaan mogen verplichte velden leeg blijven.
- Checkboxen met meerdere antwoorden worden goed opgeslagen en teruggelezen.
- Verzoeken zonder geldig CSRF-token worden geweigerd, zowel bij versturen als bij automatisch opslaan.

Wat beter moet:

1. **Voor de livegang moeten de testpagina's uit `public/` weg** (probleem 1). Dit is het enige blokkerende probleem. Dat het echt misgaat is tijdens deze test gebleken.
2. De lokale database moet opnieuw opgebouwd worden, anders testen we in de browser een oude vragenlijst (probleem 2). Dit moet gebeuren **voordat** de handmatige browsertests worden gedaan.
3. Autosave moet een goed antwoord niet wissen als er een half antwoord binnenkomt (probleem 3).
4. Met de opdrachtgever afspreken of alle vragen in de browser verplicht moeten zijn (probleem 4).

De browsertests in 4.7 moeten nog gedaan worden. Pas daarna kunnen we zeggen of het formulier ook voor de invuller goed werkt.
