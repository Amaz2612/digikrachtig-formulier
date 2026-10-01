<?php
/**
 * Test H: CSRF via echte HTTP-verzoeken naar Apache.
 *
 * LET OP: dit script praat met de website zelf, dus met de database
 * uit config/config.php (niet de testdatabase). Het logt in als
 * testgebruiker 9999001. Daarvoor moet 'debug' => true staan, want
 * dan staat de inlogcode op de OTP-pagina en is er geen mail nodig.
 *
 * Draaien: php tests/http_csrf_test.php [basis-url]
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$basis  = $argv[1] ?? 'http://localhost/Digikrachtig/public/';
$koekje = tempnam(sys_get_temp_dir(), 'dk');

echo "\n== CSRF via HTTP ($basis) ==\n";

/**
 * Doet een verzoek en geeft [statuscode, inhoud] terug. Volgt geen
 * doorverwijzingen, zodat we de echte status zien.
 */
function verzoek(string $url, ?array $post = null, bool $metKoekje = true): array
{
    global $koekje;

    $c = curl_init($url);
    curl_setopt_array($c, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    if ($metKoekje) {
        curl_setopt($c, CURLOPT_COOKIEJAR, $koekje);
        curl_setopt($c, CURLOPT_COOKIEFILE, $koekje);
    }

    if ($post !== null) {
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($post));
    }

    $inhoud = (string) curl_exec($c);
    $status = curl_getinfo($c, CURLINFO_RESPONSE_CODE);
    curl_close($c);

    return [$status, $inhoud];
}

function tokenUit(string $html): ?string
{
    return preg_match('/name="csrf_token" value="([0-9a-f]+)"/', $html, $m) ? $m[1] : null;
}

// H1: inloggen zonder token
verzoek($basis . '?actie=login');
[$status, $inhoud] = verzoek($basis . '?actie=inloggen', [
    'studentnummer' => '9999001', 'naam' => 'Test Student', 'email' => 'test@example.com',
]);
test('H1', 'Inloggen zonder csrf_token wordt geweigerd ("verlopen")',
    true, str_contains($inhoud, 'Het formulier is verlopen'));

// Echt inloggen met token en de testcode
[, $inhoud] = verzoek($basis . '?actie=login');
$token = tokenUit($inhoud);
verzoek($basis . '?actie=inloggen', [
    'csrf_token' => $token, 'studentnummer' => '9999001', 'naam' => 'Test Student', 'email' => 'test@example.com',
]);
[, $inhoud] = verzoek($basis . '?actie=otp');
preg_match('/Je code is <strong>(\d+)<\/strong>/', $inhoud, $m);
$code = $m[1] ?? '';
verzoek($basis . '?actie=otp', ['csrf_token' => tokenUit($inhoud), 'code' => $code]);

[$status, $inhoud] = verzoek($basis . '?actie=formulier');
$token = tokenUit($inhoud);
$ingelogd = str_contains($inhoud, 'action="?actie=opslaan"');

test('H2', 'Inloggen met token en testcode lukt (nodig voor de volgende tests)', true, $ingelogd);

// H3: formulier versturen zonder token
[$status, $inhoud] = verzoek($basis . '?actie=opslaan', ['naam_bedrijf' => 'CSRF BV', 'verstuur' => '1']);
test('H3', 'Formulier versturen zonder csrf_token: status 400', [400, true],
    [$status, str_contains($inhoud, 'verlopen')]);

// H4: formulier versturen met verkeerd token
[$status] = verzoek($basis . '?actie=opslaan', ['csrf_token' => str_repeat('0', 64), 'naam_bedrijf' => 'CSRF BV']);
test('H4', 'Formulier versturen met verkeerd csrf_token: status 400', 400, $status);

// H5: automatisch opslaan zonder token
[$status, $inhoud] = verzoek($basis . '?actie=autosave', ['naam_bedrijf' => 'CSRF BV']);
test('H5', 'Automatisch opslaan zonder csrf_token: status 400 met JSON "verlopen"',
    [400, '{"ok":false,"reden":"verlopen"}'], [$status, $inhoud]);

// H6: ter controle: met het goede token werkt het wel (alleen de stap, geen antwoorden)
[$status, $inhoud] = verzoek($basis . '?actie=autosave', ['csrf_token' => $token, 'stap' => 'gegevens']);
test('H6', 'Ter controle: automatisch opslaan met goed token geeft status 200',
    [200, '{"ok":true}'], [$status, $inhoud]);

// H7: niet ingelogd
[$status, $inhoud] = verzoek($basis . '?actie=autosave', ['csrf_token' => $token], false);
test('H7', 'Automatisch opslaan zonder sessie: status 401 met JSON "uitgelogd"',
    [401, '{"ok":false,"reden":"uitgelogd"}'], [$status, $inhoud]);

// H8: GET op opslaan stuurt door
[$status] = verzoek($basis . '?actie=opslaan');
test('H8', 'GET op ?actie=opslaan wordt doorgestuurd (302), niet verwerkt', 302, $status);

verzoek($basis . '?actie=uitloggen');
@unlink($koekje);

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    samenvatting();
}
