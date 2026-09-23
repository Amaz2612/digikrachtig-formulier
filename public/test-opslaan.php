<?php
/**
 * Digikrachtig formulierensysteem
 * Tijdelijke testpagina voor het opslaan.
 *
 * Plaats dit bestand in: public/test-opslaan.php
 * Open daarna http://localhost/Digikrachtig/public/test-opslaan.php
 *
 * Dit slaat een paar verzonnen antwoorden op voor testgebruiker 1 en
 * laat zien wat er in de database terechtkomt. VERWIJDEREN voordat
 * het project live gaat.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Voorwaarden.php';
require_once __DIR__ . '/../app/models/FormulierModel.php';
require_once __DIR__ . '/../app/models/InzendingModel.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = Database::verbinding();

    $formulierModel = new FormulierModel($pdo);
    $inzendingModel = new InzendingModel($pdo);

    $formulier = $formulierModel->actiefFormulier();

    if ($formulier === null) {
        exit('Geen actief formulier gevonden.');
    }

    $formulierId   = (int) $formulier['id'];
    $vragenPerCode = $formulierModel->vragenPerCode($formulierId);

    // Testgebruiker 1 uit testdata.sql
    $inzending   = $inzendingModel->haalOfMaak($formulierId, 1);
    $inzendingId = (int) $inzending['id'];

    // Verzonnen antwoorden. Let op de laatste twee: die horen bij
    // vragen die NIET zichtbaar zijn, want het bedrijf heeft wel een
    // website. Ze horen dus niet opgeslagen te worden.
    $antwoorden = [
        'naam_bedrijf'      => 'Testbedrijf BV',
        'ingevuld_door'     => 'Jan Jansen',
        'functie'           => 'Directeur',
        'email'             => 'jan@testbedrijf.nl',
        'naam_student'      => 'Zakaria',
        'm365_gebruik'      => 'ja, Microsoft 365',
        'word_gebruik'      => 'ja',
        'word_niveau'       => 'voldoende',
        'website_heeft'     => 'ja',
        'website_beheer'    => 'uitbesteed',
        'kenniscafe_interesse' => [
            'AI in het algemeen',
            'AI-chatbots als ChatGPT gebruiken',
        ],

        // Deze twee horen NIET opgeslagen te worden:
        'website_interesse' => 'ja',   // alleen bij website_heeft = nee
        'website_hulp'      => 'ja',   // hangt daar weer onder
    ];

    $aantal = $inzendingModel->slaAntwoordenOp(
        $inzendingId,
        $vragenPerCode,
        $antwoorden
    );

    echo '<h1>Opslaan gelukt</h1>';
    echo '<p>Inzending ' . $inzendingId . ', ' . $aantal
        . ' antwoordregels opgeslagen.</p>';

    echo '<h2>Wat er nu in de database staat</h2><ul>';

    foreach ($inzendingModel->antwoorden($inzendingId) as $code => $waarde) {
        $tekst = is_array($waarde) ? implode(' + ', $waarde) : $waarde;
        echo '<li><b>' . htmlspecialchars((string) $code) . '</b>: '
            . htmlspecialchars((string) $tekst) . '</li>';
    }

    echo '</ul>';

    $opgeslagen = $inzendingModel->antwoorden($inzendingId);

    echo '<h2>Controle op verborgen vragen</h2>';

    foreach (['website_interesse', 'website_hulp'] as $code) {
        $goed = !isset($opgeslagen[$code]);
        echo '<p>' . htmlspecialchars($code) . ': '
            . ($goed ? 'GOED, niet opgeslagen' : 'FOUT, staat er wel in')
            . '</p>';
    }

} catch (Throwable $fout) {
    echo '<h1>Er ging iets mis</h1>';
    echo '<pre>' . htmlspecialchars($fout->getMessage()) . '</pre>';
}
