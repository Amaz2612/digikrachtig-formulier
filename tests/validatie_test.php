<?php
/**
 * Test W: controle van de antwoorden op de server (app/Validatie.php).
 *
 * Verwacht dat database_test.php eerst gedraaid is.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

echo "\n== Validatie ==\n";

$model  = new FormulierModel(testPdo());
$vragen = $model->vragenPerCode((int) $model->actiefFormulier()['id']);

// Een volledig ingevuld pad met alle verplichte vragen die zichtbaar zijn.
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

$controleer = fn (array $antwoorden, bool $definitief = true) => Validatie::controleer($vragen, $antwoorden, $definitief);

// Waarschuwingen van PHP (bijvoorbeeld "Array to string conversion")
// opvangen, zodat we ze kunnen melden.
$waarschuwingen = [];
set_error_handler(function (int $nr, string $tekst) use (&$waarschuwingen) {
    $waarschuwingen[] = $tekst;
    return true;
});

test('W1', 'Alles goed ingevuld: geen fouten', [], $controleer($goed));

test('W2', 'Verplicht veld leeg bij versturen',
    ['naam_bedrijf' => 'Deze vraag is verplicht.'],
    $controleer(array_merge($goed, ['naam_bedrijf' => ''])));

test('W3', 'Verplicht veld met alleen spaties bij versturen',
    ['naam_bedrijf' => 'Deze vraag is verplicht.'],
    $controleer(array_merge($goed, ['naam_bedrijf' => '    '])));

test('W4', 'Verplicht veld helemaal niet meegestuurd',
    ['functie' => 'Deze vraag is verplicht.'],
    $controleer(array_diff_key($goed, ['functie' => 1])));

test('W5', 'Ongeldig e-mailadres "jan[apenstaartje]test"',
    ['email' => 'Vul een geldig e-mailadres in.'],
    $controleer(array_merge($goed, ['email' => 'jan[apenstaartje]test'])));

test('W6', 'Ongeldig e-mailadres "jan@" (half ingetypt)',
    ['email' => 'Vul een geldig e-mailadres in.'],
    $controleer(array_merge($goed, ['email' => 'jan@'])));

test('W7', 'Keuze die niet in de optielijst staat (website_beheer = "misschien")',
    ['website_beheer' => 'Kies een van de gegeven antwoorden.'],
    $controleer(array_merge($goed, ['website_beheer' => 'misschien'])));

test('W8', 'Keuze met andere hoofdletters (m365_gebruik = "Nee") wordt geweigerd',
    true,
    isset($controleer(array_merge($goed, ['m365_gebruik' => 'Nee']))['m365_gebruik']));

test('W9', 'Tussentijds opslaan met leeg verplicht veld: geen fout',
    [],
    $controleer(array_merge($goed, ['naam_bedrijf' => '']), false));

test('W10', 'Tussentijds opslaan met bijna alles leeg: geen fout',
    [],
    $controleer([], false));

test('W11', 'Tussentijds opslaan met ongeldig e-mailadres: wel een fout',
    ['email' => 'Vul een geldig e-mailadres in.'],
    $controleer(array_merge($goed, ['email' => 'jan@']), false));

test('W12', 'Checkbox met een optie die niet in de lijst staat',
    ['kenniscafe_interesse' => 'Ongeldige keuze.'],
    $controleer($goed + ['kenniscafe_interesse' => ['AI in het algemeen', 'Gratis pizza']]));

test('W13', 'Checkbox met alleen geldige opties: geen fout',
    [],
    $controleer($goed + ['kenniscafe_interesse' => ['AI in het algemeen', 'Anders, namelijk:'], 'kenniscafe_anders' => 'Iets']));

test('W14', 'Verborgen verplichte vraag (word_niveau bij m365=nee) mag leeg blijven',
    [],
    $controleer($goed));

test('W15', 'Zichtbare verplichte vervolgvragen worden wel gecontroleerd (m365 = ja, rest leeg)',
    ['excel_gebruik', 'onedrive_gebruik', 'outlook_gebruik', 'powerpoint_gebruik', 'teams_gebruik', 'word_gebruik'],
    (function () use ($controleer, $goed) {
        $codes = array_keys($controleer(array_merge($goed, ['m365_gebruik' => 'ja, Microsoft 365'])));
        sort($codes);
        return $codes;
    })());

test('W16', 'Ongeldige keuze op een verborgen vraag geeft geen fout (wordt ook niet opgeslagen)',
    [],
    $controleer($goed + ['website_interesse' => 'misschien']));

test('W17', 'Telefoonnummer "abc" geweigerd, "+31 (0)6-12345678" goedgekeurd',
    [true, false],
    [
        isset($controleer($goed + ['telefoonnummer' => 'abc'])['telefoonnummer']),
        isset($controleer($goed + ['telefoonnummer' => '+31 (0)6-12345678'])['telefoonnummer']),
    ]);

test('W18', 'Kort tekstveld van 256 tekens en tekstvak van 5001 tekens geweigerd',
    [true, true],
    [
        isset($controleer(array_merge($goed, ['functie' => str_repeat('a', 256)]))['functie']),
        isset($controleer($goed + ['opmerkingen' => str_repeat('a', 5001)])['opmerkingen']),
    ]);

test('W19', 'Lijst meegestuurd bij een radiovraag geeft "Ongeldig antwoord."',
    ['website_beheer' => 'Ongeldig antwoord.'],
    $controleer(array_merge($goed, ['website_beheer' => ['zelf']])));

$waarschuwingen = [];
$uitkomst = $controleer($goed + ['kenniscafe_interesse' => [['AI in het algemeen']]]);

test('W20', 'Checkbox met een lijst in een lijst: fout en geen PHP-waarschuwingen',
    [['kenniscafe_interesse' => 'Ongeldige keuze.'], []],
    [$uitkomst, array_values(array_unique($waarschuwingen))]);

restore_error_handler();

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    samenvatting();
}
