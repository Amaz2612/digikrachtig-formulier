<?php
/**
 * Test C: de controller met CSRF-token, automatisch opslaan en versturen.
 * Elke stap draait in een eigen PHP-proces (zie lib/controller_stap.php).
 *
 * Verwacht dat database_test.php eerst gedraaid is.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

echo "\n== Controller (CSRF, autosave, versturen) ==\n";

/**
 * Voert een controllermethode uit als ingelogde testgebruiker.
 */
function stap(string $methode, int $gebruikerId, array $post, ?string $sessieToken = 'goedtoken'): array
{
    $sessie = ['gebruiker_id' => $gebruikerId, 'studentnummer' => 'x', 'naam' => 'Test Student', 'email' => 'student@test.nl'];

    if ($sessieToken !== null) {
        $sessie['csrf_token'] = $sessieToken;
    }

    $proces = proc_open(
        [PHP_BINARY, __DIR__ . '/lib/controller_stap.php'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pijpen
    );

    fwrite($pijpen[0], json_encode(['methode' => $methode, 'sessie' => $sessie, 'post' => $post]));
    fclose($pijpen[0]);
    $uit = stream_get_contents($pijpen[1]);
    proc_close($proces);

    return json_decode($uit, true) ?? ['status' => null, 'uitvoer' => $uit];
}

$pdo   = testPdo();
$model = new InzendingModel($pdo);

function antwoordenVan(int $inzending): array
{
    return (new InzendingModel(testPdo()))->antwoorden($inzending);
}

function inzendingRij(int $inzending): array
{
    $s = testPdo()->prepare('SELECT status, huidige_stap, ingediend_op FROM form_submissions WHERE id = ?');
    $s->execute([$inzending]);
    return $s->fetch();
}

$goed = [
    'naam_bedrijf'     => 'Testbedrijf BV',
    'ingevuld_door'    => 'Jan Jansen',
    'functie'          => 'Directeur',
    'email'            => 'jan@testbedrijf.nl',
    'm365_gebruik'     => 'nee',
    'website_heeft'    => 'ja',
    'website_beheer'   => 'uitbesteed',
    'website_tevreden' => 'ja',
    'website_scan'     => 'nee',
    'social_actief'    => 'nee',
    'social_interesse' => 'nee',
    'ai_gebruik'       => 'nee',
    'ai_wil_weten'     => 'nee',
];

// --- CSRF ---
[, $gebruiker, $inzending] = nieuweInzending('9200001');

$r = stap('verwerk', $gebruiker, $goed + ['verstuur' => '1']);
test('C1', 'Versturen zonder csrf_token: status 400 en niets opgeslagen',
    [400, true, [], 'concept'],
    [$r['status'], str_contains($r['uitvoer'], 'verlopen'), antwoordenVan($inzending), inzendingRij($inzending)['status']]);

$r = stap('verwerk', $gebruiker, $goed + ['verstuur' => '1', 'csrf_token' => 'fouttoken']);
test('C2', 'Versturen met verkeerd csrf_token: status 400 en niets opgeslagen',
    [400, [], 'concept'],
    [$r['status'], antwoordenVan($inzending), inzendingRij($inzending)['status']]);

$r = stap('autosave', $gebruiker, $goed);
test('C3', 'Automatisch opslaan zonder csrf_token: status 400, JSON "verlopen", niets opgeslagen',
    [400, '{"ok":false,"reden":"verlopen"}', []],
    [$r['status'], $r['uitvoer'], antwoordenVan($inzending)]);

$r = stap('autosave', $gebruiker, $goed + ['csrf_token' => ''], null);
test('C4', 'Sessie zonder token en leeg token meegestuurd: status 400',
    400, $r['status']);

// --- Automatisch opslaan ---
$r = stap('autosave', $gebruiker, ['csrf_token' => 'goedtoken', 'naam_bedrijf' => 'Testbedrijf BV', 'email' => 'jan@testbedrijf.nl', 'stap' => 'website']);
test('C5', 'Automatisch opslaan met goed token: ok, antwoorden en stap bewaard',
    [200, '{"ok":true}', 'Testbedrijf BV', 'website'],
    [$r['status'], $r['uitvoer'], antwoordenVan($inzending)['naam_bedrijf'] ?? null, inzendingRij($inzending)['huidige_stap']]);

stap('autosave', $gebruiker, ['csrf_token' => 'goedtoken', 'naam_bedrijf' => 'Testbedrijf BV', 'email' => 'jan@testbedrijf.nl', 'stap' => 'bestaat_niet']);
test('C6', 'Automatisch opslaan met onbekende stap: oude stap blijft staan',
    'website', inzendingRij($inzending)['huidige_stap']);

stap('autosave', $gebruiker, ['csrf_token' => 'goedtoken', 'naam_bedrijf' => 'Testbedrijf BV', 'email' => 'jan@']);
$na = antwoordenVan($inzending);
test('C7', 'Automatisch opslaan met half ingetypt e-mailadres: eerder opgeslagen geldig adres blijft bewaard',
    ['Testbedrijf BV', 'jan@testbedrijf.nl'],
    [$na['naam_bedrijf'] ?? null, $na['email'] ?? null]);

// --- Tussentijds opslaan via het formulier (zonder de knop Versturen) ---
$r = stap('verwerk', $gebruiker, ['csrf_token' => 'goedtoken', 'naam_bedrijf' => 'Tussendoor BV']);
test('C8', 'Tussentijds opslaan via formulier met lege verplichte velden: opgeslagen, status blijft concept',
    ['Tussendoor BV', 'concept'],
    [antwoordenVan($inzending)['naam_bedrijf'] ?? null, inzendingRij($inzending)['status']]);

// --- Versturen ---
$events = $pdo->prepare("SELECT COUNT(*) FROM submission_events WHERE submission_id = ? AND event_type = 'validatie_mislukt'");

$r = stap('verwerk', $gebruiker, array_merge($goed, ['csrf_token' => 'goedtoken', 'verstuur' => '1', 'functie' => '', 'naam_bedrijf' => 'Mag niet opgeslagen']));
$events->execute([$inzending]);
test('C9', 'Versturen met leeg verplicht veld: foutmelding op de pagina, niets opgeslagen, gelogd als validatie_mislukt',
    [true, 'Tussendoor BV', 'concept', 1],
    [
        str_contains($r['uitvoer'], 'Deze vraag is verplicht.'),
        antwoordenVan($inzending)['naam_bedrijf'] ?? null,
        inzendingRij($inzending)['status'],
        (int) $events->fetchColumn(),
    ]);

$r = stap('verwerk', $gebruiker, $goed + ['csrf_token' => 'goedtoken', 'verstuur' => '1', 'website_interesse' => 'ja']);
$rij = inzendingRij($inzending);
$na  = antwoordenVan($inzending);
test('C10', 'Versturen met alles goed: status ingediend, datum gezet, verborgen vraag niet opgeslagen',
    ['ingediend', true, 'Testbedrijf BV', false],
    [$rij['status'], $rij['ingediend_op'] !== null, $na['naam_bedrijf'] ?? null, isset($na['website_interesse'])]);

// --- Na het versturen ---
$r = stap('autosave', $gebruiker, ['csrf_token' => 'goedtoken', 'naam_bedrijf' => 'Achteraf BV']);
test('C11', 'Automatisch opslaan na versturen: status 409 en niets veranderd',
    [409, 'Testbedrijf BV'],
    [$r['status'], antwoordenVan($inzending)['naam_bedrijf'] ?? null]);

stap('verwerk', $gebruiker, array_merge($goed, ['csrf_token' => 'goedtoken', 'verstuur' => '1', 'naam_bedrijf' => 'Achteraf BV']));
test('C12', 'Nog een keer versturen na indienen: antwoorden niet veranderd',
    'Testbedrijf BV', antwoordenVan($inzending)['naam_bedrijf'] ?? null);

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    samenvatting();
}
