<?php
/**
 * Digikrachtig formulierensysteem
 * Tijdelijke testpagina voor de validatie.
 *
 * Plaats dit bestand in: public/test-validatie.php
 * Open daarna http://localhost/Digikrachtig/public/test-validatie.php
 *
 * Draait vijf testgevallen en laat per geval zien of het resultaat
 * is wat je verwacht. VERWIJDEREN voordat het project live gaat.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Voorwaarden.php';
require_once __DIR__ . '/../app/Validatie.php';
require_once __DIR__ . '/../app/models/FormulierModel.php';

header('Content-Type: text/html; charset=utf-8');

$formulierModel = new FormulierModel(Database::verbinding());
$formulier      = $formulierModel->actiefFormulier();

if ($formulier === null) {
    exit('Geen actief formulier gevonden.');
}

$vragen = $formulierModel->vragenPerCode((int) $formulier['id']);

// Een volledig goed ingevuld begin, als startpunt voor de tests.
$goed = [
    'naam_bedrijf'  => 'Testbedrijf BV',
    'ingevuld_door' => 'Jan Jansen',
    'functie'       => 'Directeur',
    'email'         => 'jan@testbedrijf.nl',
    'naam_student'  => 'Zakaria',
    'm365_gebruik'  => 'nee',
    'website_heeft' => 'ja',
    'website_beheer' => 'uitbesteed',
    'website_tevreden' => 'ja',
    'website_scan'  => 'nee',
    'social_actief' => 'nee',
    'social_interesse' => 'nee',
    'ai_gebruik'    => 'nee',
    'ai_wil_weten'  => 'nee',
];

$tests = [
    [
        'naam'    => 'Alles goed ingevuld',
        'verwacht' => 'geen fouten',
        'invoer'  => $goed,
    ],
    [
        'naam'    => 'Verplichte vraag leeg gelaten',
        'verwacht' => 'fout bij naam_bedrijf',
        'invoer'  => array_merge($goed, ['naam_bedrijf' => '']),
    ],
    [
        'naam'    => 'Ongeldig e-mailadres',
        'verwacht' => 'fout bij email',
        'invoer'  => array_merge($goed, ['email' => 'jan[apenstaartje]test']),
    ],
    [
        'naam'    => 'Keuze die niet bestaat',
        'verwacht' => 'fout bij website_beheer',
        'invoer'  => array_merge($goed, ['website_beheer' => 'misschien']),
    ],
    [
        'naam'    => 'Verplichte vraag leeg, maar tussentijds opslaan',
        'verwacht' => 'geen fouten',
        'invoer'  => array_merge($goed, ['naam_bedrijf' => '']),
        'definitief' => false,
    ],
];

echo '<h1>Validatietests</h1>';

foreach ($tests as $nummer => $test) {
    $fouten = Validatie::controleer(
        $vragen,
        $test['invoer'],
        $test['definitief'] ?? true
    );

    echo '<h2>' . ($nummer + 1) . '. ' . htmlspecialchars($test['naam']) . '</h2>';
    echo '<p>Verwacht: ' . htmlspecialchars($test['verwacht']) . '</p>';

    if ($fouten === []) {
        echo '<p>Resultaat: geen fouten</p>';
    } else {
        echo '<p>Resultaat:</p><ul>';

        foreach ($fouten as $code => $melding) {
            echo '<li><b>' . htmlspecialchars((string) $code) . '</b>: '
                . htmlspecialchars($melding) . '</li>';
        }

        echo '</ul>';
    }
}
