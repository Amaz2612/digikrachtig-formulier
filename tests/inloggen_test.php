<?php
/**
 * Test L: de gezamenlijke inlogpagina. Studentnummer met rv, en de
 * wachtwoordstap voor beheerders (BeheerAuth).
 *
 * Verwacht dat database_test.php eerst gedraaid is.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

echo "\n== Inloggen (rv-nummer en wachtwoordstap) ==\n";

$pdo = testPdo();

$schoon = fn (string $invoer) => Auth::schoonStudentnummer($invoer);

test('L1', 'Studentnummer zonder rv krijgt rv ervoor',
    'rv2100001', $schoon('2100001'));

test('L2', 'rv zelf getypt (ook met hoofdletters of spaties eromheen) geeft hetzelfde',
    ['rv2100001', 'rv2100001', 'rv2100001'],
    [$schoon('rv2100001'), $schoon('RV2100001'), $schoon('  2100001 ')]);

test('L3', 'Te kort, te lang, letters of alleen rv: geen studentnummer',
    [null, null, null, null, 'rv' . str_repeat('1', 18)],
    [$schoon('123'), $schoon(str_repeat('1', 19)), $schoon('beheerder'), $schoon('rv'), $schoon(str_repeat('1', 18))]);

test('L4', 'Auth::login weigert een nummer zonder rv',
    false, Auth::login($pdo, '2100001', 'Test Student', 'a@b.nl', 'sleutel'));

test('L5', 'Wat lijkt op een gebruikersnaam',
    [true, true, false, false, false],
    [
        BeheerAuth::lijktGebruikersnaam('beheerder'),
        BeheerAuth::lijktGebruikersnaam('jan.de-vries_2'),
        BeheerAuth::lijktGebruikersnaam('ab'),
        BeheerAuth::lijktGebruikersnaam('jan de vries'),
        BeheerAuth::lijktGebruikersnaam('<script>'),
    ]);

// --- De wachtwoordstap ---

(new BeheerderModel($pdo))->maak('stapbeheer', password_hash('Een-lang-wachtwoord-2', PASSWORD_DEFAULT));
$_SESSION = [];

BeheerAuth::startPoging('stapbeheer');
test('L6', 'Fout wachtwoord: "fout", stap blijft open, niet ingelogd',
    ['fout', true, false],
    [BeheerAuth::controleerPoging($pdo, 'fout'), BeheerAuth::pogingBezig(), BeheerAuth::isIngelogd()]);

$uitkomsten = [];
for ($i = 0; $i < 4; $i++) {
    $uitkomsten[] = BeheerAuth::controleerPoging($pdo, 'fout');
}
test('L7', 'Vijfde fout wachtwoord: "te_vaak" en de stap is gesloten',
    [['fout', 'fout', 'fout', 'te_vaak'], false],
    [$uitkomsten, BeheerAuth::pogingBezig()]);

test('L8', 'Daarna helpt ook het goede wachtwoord niet meer zonder opnieuw te beginnen',
    ['verlopen', false],
    [BeheerAuth::controleerPoging($pdo, 'Een-lang-wachtwoord-2'), BeheerAuth::isIngelogd()]);

BeheerAuth::startPoging('stapbeheer');
$_SESSION['beheer_poging']['verloopt'] = time() - 1;
test('L9', 'Stap langer dan 5 minuten open: "verlopen"',
    'verlopen', BeheerAuth::controleerPoging($pdo, 'Een-lang-wachtwoord-2'));

BeheerAuth::startPoging('bestaat_niet');
test('L10', 'Onbekende naam: gewoon "fout", net als een fout wachtwoord',
    'fout', BeheerAuth::controleerPoging($pdo, 'Een-lang-wachtwoord-2'));

$_SESSION = ['gebruiker_id' => 7, 'studentnummer' => 'rv2100001'];
BeheerAuth::startPoging('stapbeheer');
// @: in de CLI is er al tekst geprint, dan geeft session_regenerate_id()
// een waarschuwing. Op de webserver gebeurt dat niet.
$uitkomst = @BeheerAuth::controleerPoging($pdo, 'Een-lang-wachtwoord-2');
test('L11', 'Goed wachtwoord: ingelogd als beheerder, stap gesloten',
    ['goed', true, 'stapbeheer', false],
    [$uitkomst, BeheerAuth::isIngelogd(), BeheerAuth::gebruikersnaam(), BeheerAuth::pogingBezig()]);

@BeheerAuth::uitloggen();
test('L12', 'Beheerder uitloggen laat een student in dezelfde sessie ingelogd',
    [false, true], [BeheerAuth::isIngelogd(), Auth::isIngelogd()]);

$_SESSION = [];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    samenvatting();
}
