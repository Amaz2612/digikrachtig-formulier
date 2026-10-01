<?php
/**
 * Test V: welke vragen zichtbaar zijn (app/Voorwaarden.php), met de
 * echte vragen uit de testdatabase.
 *
 * Verwacht dat database_test.php eerst gedraaid is.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

echo "\n== Voorwaarden ==\n";

$model  = new FormulierModel(testPdo());
$vragen = $model->vragenPerCode((int) $model->actiefFormulier()['id']);

$zichtbaar = fn (string $code, array $antwoorden) => Voorwaarden::isZichtbaar($code, $vragen, $antwoorden);

// Keten website_heeft -> website_interesse -> website_hulp -> website_melding_hulp
$keten = ['website_interesse', 'website_hulp', 'website_melding_hulp'];
$staat = function (array $antwoorden) use ($keten, $zichtbaar) {
    $uit = [];
    foreach ($keten as $code) {
        $uit[$code] = $zichtbaar($code, $antwoorden);
    }
    return $uit;
};

test('V1', 'Niets ingevuld: hele website-keten verborgen',
    ['website_interesse' => false, 'website_hulp' => false, 'website_melding_hulp' => false],
    $staat([]));

test('V2', 'website_heeft=nee: alleen website_interesse zichtbaar',
    ['website_interesse' => true, 'website_hulp' => false, 'website_melding_hulp' => false],
    $staat(['website_heeft' => 'nee']));

test('V3', 'website_heeft=nee, interesse=ja, hulp=ja: hele keten zichtbaar',
    ['website_interesse' => true, 'website_hulp' => true, 'website_melding_hulp' => true],
    $staat(['website_heeft' => 'nee', 'website_interesse' => 'ja', 'website_hulp' => 'ja']));

test('V4', 'Daarna website_heeft=ja (oude antwoorden blijven staan): hele keten weer verborgen',
    ['website_interesse' => false, 'website_hulp' => false, 'website_melding_hulp' => false],
    $staat(['website_heeft' => 'ja', 'website_interesse' => 'ja', 'website_hulp' => 'ja']));

test('V5', 'website_heeft=nee, interesse=nee: website_hulp verborgen',
    ['website_interesse' => true, 'website_hulp' => false, 'website_melding_hulp' => false],
    $staat(['website_heeft' => 'nee', 'website_interesse' => 'nee', 'website_hulp' => 'ja']));

// Keten van drie via Microsoft 365
test('V6', 'm365=nee: word_gebruik en word_niveau verborgen, ook als word_gebruik=ja meegestuurd wordt',
    [false, false],
    [
        $zichtbaar('word_gebruik', ['m365_gebruik' => 'nee', 'word_gebruik' => 'ja']),
        $zichtbaar('word_niveau', ['m365_gebruik' => 'nee', 'word_gebruik' => 'ja']),
    ]);

test('V7', 'Voorwaarde met twee waarden: word_gebruik zichtbaar bij "ja, Microsoft 365" en bij "ja, Microsoft Office"',
    [true, true],
    [
        $zichtbaar('word_gebruik', ['m365_gebruik' => 'ja, Microsoft 365']),
        $zichtbaar('word_gebruik', ['m365_gebruik' => 'ja, Microsoft Office']),
    ]);

test('V8', 'm365=nee: m365_alternatief zichtbaar, m365_melding verborgen',
    [true, false],
    [
        $zichtbaar('m365_alternatief', ['m365_gebruik' => 'nee']),
        $zichtbaar('m365_melding', ['m365_gebruik' => 'nee']),
    ]);

// Voorwaarde op een checkbox
$anders = 'Anders, namelijk:';

test('V9', 'kenniscafe_anders zichtbaar als "Anders, namelijk:" een van de vinkjes is',
    true,
    $zichtbaar('kenniscafe_anders', ['kenniscafe_interesse' => ['AI in het algemeen', $anders]]));

test('V10', 'kenniscafe_anders verborgen als "Anders" niet is aangevinkt',
    false,
    $zichtbaar('kenniscafe_anders', ['kenniscafe_interesse' => ['AI in het algemeen']]));

// Social media: keten van drie
test('V11', 'social_actief=ja, tevreden=nee, hulp=ja: melding zichtbaar; daarna social_actief=nee: verborgen',
    [true, false],
    [
        $zichtbaar('social_melding_verbetering', ['social_actief' => 'ja', 'social_tevreden' => 'nee', 'social_hulp_verbetering' => 'ja']),
        $zichtbaar('social_melding_verbetering', ['social_actief' => 'nee', 'social_tevreden' => 'nee', 'social_hulp_verbetering' => 'ja']),
    ]);

// Hoofdlettergevoelig: 'Ja' is geen 'ja'
test('V12', 'Antwoord met andere hoofdletters ("Nee") maakt de vervolgvraag niet zichtbaar',
    false,
    $zichtbaar('website_interesse', ['website_heeft' => 'Nee']));

// Rondje in de voorwaarden mag geen oneindige lus geven
$rondje = [
    'a' => ['toon_als_code' => 'b', 'toon_als_waarden' => ['ja']],
    'b' => ['toon_als_code' => 'a', 'toon_als_waarden' => ['ja']],
];

test('V13', 'Voorwaarde die in een rondje wijst: vraag verborgen, geen oneindige lus',
    [false, false],
    [
        Voorwaarden::isZichtbaar('a', $rondje, ['a' => 'ja', 'b' => 'ja']),
        Voorwaarden::isZichtbaar('b', $rondje, ['a' => 'ja', 'b' => 'ja']),
    ]);

// Aantal zichtbare vragen bij een paar volledige paden
$tel = fn (array $antwoorden) => count(Voorwaarden::zichtbareCodes($vragen, $antwoorden));

test('V14', 'Aantal zichtbare rijen zonder antwoorden (alleen vragen zonder voorwaarde)',
    11,
    $tel([]));

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    samenvatting();
}
