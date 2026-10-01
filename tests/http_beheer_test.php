<?php
/**
 * Test AH: de beheerpagina via echte HTTP-verzoeken naar Apache.
 *
 * Beheerders loggen in op de gewone inlogpagina (index.php): in het
 * veld Studentnummer de gebruikersnaam, daarna het wachtwoord.
 *
 * LET OP: dit praat met de database uit config/config.php (niet de
 * testdatabase). Het logt in als student rv9999001 en reset daarna de
 * inzending van die student.
 *
 * De beheerder komt uit omgevingsvariabelen, zodat er geen wachtwoord
 * in de repo staat:
 *   set BEHEER_NAAM=...
 *   set BEHEER_WACHTWOORD=...
 *   php tests/http_beheer_test.php [basis-url]
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$basis      = $argv[1] ?? 'http://localhost/Digikrachtig/public/';
$naam       = getenv('BEHEER_NAAM') ?: '';
$wachtwoord = getenv('BEHEER_WACHTWOORD') ?: '';

echo "\n== Beheer via HTTP ($basis) ==\n";

if ($naam === '' || $wachtwoord === '') {
    // return en geen exit: zo loopt tests/alles.php gewoon door.
    echo "BEHEER_NAAM en BEHEER_WACHTWOORD zijn niet gezet, test overgeslagen.\n";
    return;
}

/**
 * Doet een verzoek met een eigen koekjestrommel. Geeft status, headers
 * en inhoud terug. Volgt geen doorverwijzingen.
 */
function beheerVerzoek(string $koekje, string $url, ?array $post = null): array
{
    $c = curl_init($url);
    curl_setopt_array($c, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER         => true,
        CURLOPT_COOKIEJAR      => $koekje,
        CURLOPT_COOKIEFILE     => $koekje,
    ]);

    if ($post !== null) {
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post));
    }

    $antwoord = (string) curl_exec($c);
    $status   = curl_getinfo($c, CURLINFO_RESPONSE_CODE);
    $grens    = curl_getinfo($c, CURLINFO_HEADER_SIZE);
    curl_close($c);

    return [$status, substr($antwoord, 0, $grens), substr($antwoord, $grens)];
}

function beheerToken(string $html): ?string
{
    return preg_match('/name="csrf_token" value="([0-9a-f]+)"/', $html, $m) ? $m[1] : null;
}

function locatie(string $headers): ?string
{
    return preg_match('/^Location: (.+)$/mi', $headers, $m) ? trim($m[1]) : null;
}

function sessieId(string $koekje): ?string
{
    return preg_match('/\tPHPSESSID\t(\S+)/', (string) @file_get_contents($koekje), $m) ? $m[1] : null;
}

/**
 * Vult op de inlogpagina alleen het veld Studentnummer in, zoals een
 * beheerder doet. Geeft [status, headers] terug.
 */
function vulNaamIn(string $koekje, string $basis, string $naam, bool $metToken = true): array
{
    [, , $html] = beheerVerzoek($koekje, $basis . '?actie=login');
    $post = ['studentnummer' => $naam, 'naam' => '', 'email' => ''];

    if ($metToken) {
        $post['csrf_token'] = beheerToken($html);
    }

    [$status, $headers, $inhoud] = beheerVerzoek($koekje, $basis . '?actie=inloggen', $post);

    return [$status, $headers, $inhoud];
}

function vulWachtwoordIn(string $koekje, string $basis, string $wachtwoord): array
{
    [, , $html] = beheerVerzoek($koekje, $basis . '?actie=wachtwoord');

    return beheerVerzoek($koekje, $basis . '?actie=wachtwoord', [
        'csrf_token' => beheerToken($html),
        'wachtwoord' => $wachtwoord,
    ]);
}

$admin   = tempnam(sys_get_temp_dir(), 'dkb');
$student = tempnam(sys_get_temp_dir(), 'dks');
$vreemd  = tempnam(sys_get_temp_dir(), 'dkv');
$url     = $basis . 'admin.php';

// AH1: niet ingelogd
[$status, $headers] = beheerVerzoek($admin, $url);
test('AH1', 'admin.php zonder inloggen: doorgestuurd naar de gewone inlogpagina',
    [302, 'index.php?actie=login'], [$status, locatie($headers)]);

[$status, $headers] = beheerVerzoek($admin, $url . '?actie=inloggen');
test('AH2', 'Oude link admin.php?actie=inloggen stuurt ook door naar de gewone inlogpagina',
    [302, 'index.php?actie=login'], [$status, locatie($headers)]);

// AH3: inlogpagina heeft het vaste rv-veld
[, , $html] = beheerVerzoek($admin, $basis . '?actie=login');
test('AH3', 'Inlogpagina: "rv" vast voor het veld Studentnummer, naam en e-mail in een eigen blok',
    [true, true],
    [
        (bool) preg_match('/<div class="veld-voorvoegsel">\s*<span aria-hidden="true">rv<\/span>/', $html),
        str_contains($html, '<div id="studentvelden">'),
    ]);

// AH4: zonder token
[, , $inhoud] = vulNaamIn($admin, $basis, $naam, false);
test('AH4', 'Gebruikersnaam invullen zonder CSRF-token: geweigerd', true, str_contains($inhoud, 'verlopen'));

// AH5: gebruikersnaam in het nummerveld
[$status, $headers] = vulNaamIn($admin, $basis, $naam);
test('AH5', 'Gebruikersnaam in het nummerveld, naam en e-mail leeg: door naar de wachtwoordstap',
    [302, '?actie=wachtwoord'], [$status, locatie($headers)]);

// AH6: fout wachtwoord
[$status, , $inhoud] = vulWachtwoordIn($admin, $basis, 'fout-wachtwoord');
test('AH6', 'Fout wachtwoord: melding, niet ingelogd',
    [true, 302], [str_contains($inhoud, 'Gebruikersnaam of wachtwoord klopt niet.'), beheerVerzoek($admin, $url)[0]]);

// AH7: goed wachtwoord
$voor = sessieId($admin);
[$status, $headers] = vulWachtwoordIn($admin, $basis, $wachtwoord);
$na = sessieId($admin);
test('AH7', 'Goed wachtwoord: doorgestuurd naar admin.php, nieuw sessie-id',
    [302, 'admin.php', true], [$status, locatie($headers), $voor !== null && $na !== null && $voor !== $na]);

// AH8: overzicht klopt met de database
[$status, $headers, $html] = beheerVerzoek($admin, $url);
$telling = (new BeheerModel(Database::verbinding()))->telling();
preg_match_all('/<strong>(\d+)<\/strong> (totaal|concept|ingediend)/', $html, $m);
test('AH8', 'Overzicht: 200, tellingen gelijk aan de database, niet in de cache',
    [200, [$telling['totaal'], $telling['concept'], $telling['ingediend']], true],
    [$status, array_map('intval', $m[1]), (bool) preg_match('/^Cache-Control: no-store/mi', $headers)]);

// AH9: onbekende naam geeft dezelfde wachtwoordstap en dezelfde melding
[$status, $headers] = vulNaamIn($vreemd, $basis, 'bestaat_niet_' . bin2hex(random_bytes(3)));
[, , $inhoud] = vulWachtwoordIn($vreemd, $basis, 'iets');
test('AH9', 'Onbekende gebruikersnaam: ook een wachtwoordstap en dezelfde melding (niet te zien welke namen bestaan)',
    ['?actie=wachtwoord', true], [locatie($headers), str_contains($inhoud, 'Gebruikersnaam of wachtwoord klopt niet.')]);

// AH10: na 5 keer fout opnieuw beginnen
for ($i = 0; $i < 4; $i++) {
    vulWachtwoordIn($vreemd, $basis, 'iets');
}
[, $headers] = beheerVerzoek($vreemd, $basis . '?actie=wachtwoord');
test('AH10', 'Na 5 foute wachtwoorden is de wachtwoordstap weg (terug naar inloggen)',
    '?actie=login', locatie($headers));

// AH11: student logt in zonder rv, wordt opgeslagen met rv
[, , $html] = beheerVerzoek($student, $basis . '?actie=login');
beheerVerzoek($student, $basis . '?actie=inloggen', ['csrf_token' => beheerToken($html), 'studentnummer' => '9999001', 'naam' => 'Test Student', 'email' => 'test@example.com']);
[, , $html] = beheerVerzoek($student, $basis . '?actie=otp');
preg_match('/Je code is <strong>(\d+)<\/strong>/', $html, $code);
beheerVerzoek($student, $basis . '?actie=otp', ['csrf_token' => beheerToken($html), 'code' => $code[1] ?? '']);
[, , $html] = beheerVerzoek($student, $basis . '?actie=formulier');

$s = Database::verbinding()->prepare(
    "SELECT s.id FROM form_submissions s JOIN users u ON u.id = s.user_id WHERE u.studentnummer = 'rv9999001'"
);
$s->execute();
$testId = (int) $s->fetchColumn();

test('AH11', 'Student logt in met 9999001: ingelogd als rv9999001',
    [true, true], [str_contains($html, 'rv9999001'), $testId > 0]);

// AH12: student is geen beheerder
[$status, $headers] = beheerVerzoek($student, $url);
test('AH12', 'Ingelogde student opent admin.php: doorgestuurd naar inloggen',
    [302, 'index.php?actie=login'], [$status, locatie($headers)]);

// AH13: beheerder is geen student
[, , $html] = beheerVerzoek($admin, $basis . '?actie=formulier');
test('AH13', 'Ingelogde beheerder opent het studentenformulier: krijgt de inlogpagina',
    true, str_contains($html, 'name="studentnummer"'));

// AH14: detail en resetten
[$status, , $html] = beheerVerzoek($admin, $url . '?actie=detail&id=' . $testId);
test('AH14', 'Detailpagina van rv9999001: 200 met logboek en resetknop',
    [200, true, true], [$status, str_contains($html, '<h2>Logboek</h2>'), str_contains($html, 'Inzending resetten')]);

[, , $html] = beheerVerzoek($admin, $url . '?actie=reset&id=' . $testId);
$token = beheerToken($html);
[$status] = beheerVerzoek($admin, $url . '?actie=reset', ['id' => (string) $testId]);
test('AH15', 'Reset zonder CSRF-token: 400', 400, $status);

[$status] = beheerVerzoek($admin, $url . '?actie=reset', ['id' => (string) $testId, 'csrf_token' => $token]);
[, , $html] = beheerVerzoek($admin, $url . '?actie=detail&id=' . $testId . '&gereset=1');
test('AH16', 'Reset met token: doorgestuurd, melding en "gereset" in het logboek',
    [302, true, true],
    [$status, str_contains($html, 'De inzending is gereset'), str_contains($html, '<td>gereset</td>')]);

// AH17: uitloggen
$nogIngelogdNaGet = (function () use ($admin, $url) {
    beheerVerzoek($admin, $url . '?actie=uitloggen');
    return beheerVerzoek($admin, $url)[0] === 200;
})();
[$status, $headers] = beheerVerzoek($admin, $url . '?actie=uitloggen', ['csrf_token' => $token]);
test('AH17', 'Uitloggen via GET doet niets, via POST met token wel (terug naar inlogpagina)',
    [true, 302, 'index.php?actie=login', 302],
    [$nogIngelogdNaGet, $status, locatie($headers), beheerVerzoek($admin, $url)[0]]);

beheerVerzoek($student, $basis . '?actie=uitloggen');

foreach ([$admin, $student, $vreemd] as $bestand) {
    @unlink($bestand);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    samenvatting();
}
