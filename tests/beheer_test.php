<?php
/**
 * Test A: de beheerpagina (BeheerAuth, BeheerModel, BeheerController).
 *
 * Verwacht dat database_test.php eerst gedraaid is (die draait ook
 * database/admin.sql op de testdatabase).
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

echo "\n== Beheer ==\n";

$pdo          = testPdo();
$beheerModel  = new BeheerModel($pdo);
$inzendModel  = new InzendingModel($pdo);
$formModel    = new FormulierModel($pdo);

/**
 * Voert een methode van BeheerController uit in een eigen proces.
 */
function beheerStap(string $methode, array $sessie, array $post = [], array $get = [], bool $isPost = true): array
{
    $proces = proc_open(
        [PHP_BINARY, __DIR__ . '/lib/controller_stap.php'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pijpen
    );

    fwrite($pijpen[0], json_encode([
        'controller'   => 'BeheerController',
        'methode'      => $methode,
        'sessie'       => $sessie,
        'post'         => $post,
        'get'          => $get,
        'post_verzoek' => $isPost,
    ]));
    fclose($pijpen[0]);
    $uit = stream_get_contents($pijpen[1]);
    proc_close($proces);

    return json_decode($uit, true) ?? ['status' => null, 'uitvoer' => $uit];
}

function aantalAntwoordRegels(int $inzending): int
{
    $s = testPdo()->prepare('SELECT COUNT(*) FROM answers WHERE submission_id = ?');
    $s->execute([$inzending]);
    return (int) $s->fetchColumn();
}

// --- Beheerder aanmaken en inloggen ---

$beheerders = new BeheerderModel($pdo);
$beheerders->maak('testbeheer', password_hash('Een-lang-wachtwoord-1', PASSWORD_DEFAULT));

$opgeslagen = $beheerders->zoekOpGebruikersnaam('testbeheer')['wachtwoord_hash'];
test('A1', 'Wachtwoord opgeslagen als hash, niet als platte tekst',
    [false, true],
    [str_contains($opgeslagen, 'Een-lang-wachtwoord-1'), password_get_info($opgeslagen)['algo'] !== null]);

// De sessie is al gestart in bootstrap.php.
$_SESSION = [];
test('A2', 'Inloggen met fout wachtwoord en met onbekende naam lukt niet',
    [false, false, false],
    [
        BeheerAuth::login($pdo, 'testbeheer', 'fout-wachtwoord'),
        BeheerAuth::login($pdo, 'bestaatniet', 'Een-lang-wachtwoord-1'),
        BeheerAuth::isIngelogd(),
    ]);

test('A3', 'Inloggen met goed wachtwoord: beheerder ingelogd, maar geen student',
    [true, true, 'testbeheer', false],
    [
        @BeheerAuth::login($pdo, 'testbeheer', 'Een-lang-wachtwoord-1'),
        BeheerAuth::isIngelogd(),
        BeheerAuth::gebruikersnaam(),
        Auth::isIngelogd(),
    ]);

$_SESSION = ['gebruiker_id' => 1, 'studentnummer' => '2100001'];
test('A4', 'Ingelogde student is geen beheerder', [true, false], [Auth::isIngelogd(), BeheerAuth::isIngelogd()]);

// --- Testdata: twee inzendingen ---

[$formulierId, , $ingediend] = nieuweInzending('9300001');
[, , $concept]               = nieuweInzending('9300002');
$vragen = $formModel->vragenPerCode($formulierId);

$inzendModel->slaAntwoordenOp($ingediend, $vragen, [
    'naam_bedrijf'         => '<script>alert(1)</script> BV',
    'ingevuld_door'        => 'Jan Jansen',
    'functie'              => '',
    'email'                => 'jan@testbedrijf.nl',
    'm365_gebruik'         => 'nee',
    'website_heeft'        => 'ja',
    'kenniscafe_interesse' => ['AI in het algemeen', 'Anders, namelijk:'],
    'kenniscafe_anders'    => "Regel een\nRegel twee",
]);
$inzendModel->slaStapOp($ingediend, 'kenniscafes');
$inzendModel->dienIn($ingediend);
$pdo->prepare('UPDATE form_submissions SET gestart_op = gestart_op - INTERVAL 1 DAY WHERE id = ?')->execute([$ingediend]);

$telling = $beheerModel->telling();
test('A5', 'Telling: totaal, concept en ingediend',
    [true, true],
    [$telling['totaal'] === $telling['concept'] + $telling['ingediend'], $telling['ingediend'] >= 1]);

$lijst = $beheerModel->inzendingen();
$rijIngediend = array_values(array_filter($lijst, fn ($r) => (int) $r['id'] === $ingediend))[0];
test('A6', 'Overzicht telt vragen, geen rijen: checkbox met 2 vinkjes telt als 1 (7 vragen, 8 rijen)',
    [7, 8], [(int) $rijIngediend['aantal_antwoorden'], aantalAntwoordRegels($ingediend)]);

$datums = array_column($lijst, 'gestart_op');
$gesorteerd = $datums;
rsort($gesorteerd);
test('A7', 'Overzicht gesorteerd op gestart_op, nieuwste bovenaan', $gesorteerd, $datums);

// --- Toegang ---

$beheerder = ['beheerder' => ['id' => 1, 'gebruikersnaam' => 'testbeheer'], 'csrf_token' => 'goedtoken'];
$student   = ['gebruiker_id' => 1, 'studentnummer' => '2100001', 'csrf_token' => 'goedtoken'];

$uitkomsten = [];
foreach (['overzicht', 'detail', 'resetBevestigen'] as $methode) {
    $r = beheerStap($methode, $student, [], ['id' => (string) $ingediend], false);
    $uitkomsten[$methode] = $r['uitvoer'] === '' ? 'doorgestuurd' : 'pagina getoond';
}
test('A8', 'Ingelogde student opent beheerpagina\'s: steeds doorgestuurd naar inloggen',
    ['overzicht' => 'doorgestuurd', 'detail' => 'doorgestuurd', 'resetBevestigen' => 'doorgestuurd'],
    $uitkomsten);

// --- Detailpagina ---

$r = beheerStap('detail', $beheerder, [], ['id' => (string) $ingediend], false);
$html = $r['uitvoer'];

test('A9', 'Detail: script-tag in een antwoord wordt geëscaped',
    [false, true],
    [str_contains($html, '<script>alert(1)</script>'), str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt; BV')]);

test('A10', 'Detail: beide aangevinkte waarden van de checkbox staan erin',
    [true, true],
    [str_contains($html, '<li>AI in het algemeen</li>'), str_contains($html, '<li>Anders, namelijk:</li>')]);

test('A11', 'Detail: zichtbare maar lege vraag (functie) als "niet ingevuld", verborgen vragen overgeslagen',
    [true, false, false],
    [
        (bool) preg_match('/Functie:<\/p>\s*<p class="antwoord-leeg">niet ingevuld/', $html),
        str_contains($html, 'Wordt er gewerkt met Word?'),           // verborgen: m365 = nee
        str_contains($html, 'Heeft u interesse in een website'),     // verborgen: website = ja
    ]);

test('A12', 'Detail: sectie Word helemaal weg, sectiekoppen in de volgorde van het formulier',
    [false, true],
    [
        str_contains($html, '<h2>Word</h2>'),
        strpos($html, '<h2>Gegevens</h2>') < strpos($html, '<h2>Microsoft 365/Office</h2>')
            && strpos($html, '<h2>Microsoft 365/Office</h2>') < strpos($html, '<h2>Website</h2>')
            && strpos($html, '<h2>Website</h2>') < strpos($html, '<h2>Kenniscafés, speeddates en bijeenkomsten</h2>'),
    ]);

test('A13', 'Detail: meerregelig antwoord met <br>, logboek onderaan met "ingediend"',
    [true, true],
    [str_contains($html, "Regel een<br />\nRegel twee"), strpos($html, '<h2>Logboek</h2>') < strpos($html, '<td>ingediend</td>')]);

$r = beheerStap('detail', $beheerder, [], ['id' => '999999'], false);
$r2 = beheerStap('detail', $beheerder, [], ['id' => '1 OR 1=1'], false);
test('A14', 'Detail met onbekend of raar id: 404', [404, 404], [$r['status'], $r2['status']]);

// --- Resetten ---

$r = beheerStap('resetBevestigen', $beheerder, [], ['id' => (string) $ingediend], false);
test('A15', 'Resetknop (GET) toont alleen een bevestigingsvraag, verandert niets',
    [true, 'ingediend', 8],
    [str_contains($r['uitvoer'], 'Inzending resetten?'), $beheerModel->inzending($ingediend)['status'], aantalAntwoordRegels($ingediend)]);

$r = beheerStap('reset', $beheerder, ['id' => (string) $ingediend]);
$r2 = beheerStap('reset', $beheerder, ['id' => (string) $ingediend, 'csrf_token' => 'fouttoken']);
test('A16', 'Reset zonder of met fout CSRF-token: 400, niets veranderd',
    [400, 400, 'ingediend', 8],
    [$r['status'], $r2['status'], $beheerModel->inzending($ingediend)['status'], aantalAntwoordRegels($ingediend)]);

$r = beheerStap('reset', $student, ['id' => (string) $ingediend, 'csrf_token' => 'goedtoken']);
test('A17', 'Reset als student (met geldig token): doorgestuurd, niets veranderd',
    ['', 'ingediend', 8],
    [$r['uitvoer'], $beheerModel->inzending($ingediend)['status'], aantalAntwoordRegels($ingediend)]);

// Transactie: als het logboek niet lukt, mag er niets verwijderd zijn.
// Een tijdelijke trigger laat het schrijven van de logregel mislukken.
$pdo->exec(
    "CREATE TRIGGER test_log_faalt BEFORE INSERT ON submission_events
     FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test: logboek faalt'"
);
try {
    $beheerModel->reset($ingediend, 'testbeheer');
    $fout = 'geen fout';
} catch (PDOException $e) {
    $fout = 'fout gegooid';
} finally {
    $pdo->exec('DROP TRIGGER test_log_faalt');
}

test('A18', 'Reset die halverwege misgaat (logregel faalt): alles teruggedraaid',
    ['fout gegooid', 'ingediend', 8],
    [$fout, $beheerModel->inzending($ingediend)['status'], aantalAntwoordRegels($ingediend)]);

$r = beheerStap('reset', $beheerder, ['id' => (string) $ingediend, 'csrf_token' => 'goedtoken']);
$na = $beheerModel->inzending($ingediend);
$log = $beheerModel->logboek($ingediend);
$laatste = end($log);
test('A19', 'Reset met token: antwoorden weg, status concept, datums en stap leeg, logregel erbij',
    ['', 0, 'concept', null, null, 'gereset', 'Door beheerder testbeheer, 8 antwoordregels verwijderd'],
    [$r['uitvoer'], aantalAntwoordRegels($ingediend), $na['status'], $na['ingediend_op'], $na['huidige_stap'], $laatste['event_type'], $laatste['opmerking']]);

$r = beheerStap('reset', $beheerder, ['id' => '999999', 'csrf_token' => 'goedtoken']);
test('A20', 'Reset van een inzending die niet bestaat: 404', 404, $r['status']);

$r = beheerStap('reset', $beheerder, ['id' => (string) $concept, 'csrf_token' => 'goedtoken']);
test('A21', 'Reset van een concept zonder antwoorden gaat ook goed', 'concept', $beheerModel->inzending($concept)['status']);

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    samenvatting();
}
