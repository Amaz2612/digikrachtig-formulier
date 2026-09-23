<?php
/**
 * Digikrachtig formulierensysteem
 * Tijdelijke controlepagina.
 *
 * Plaats dit bestand in: public/controle.php
 * Open daarna http://localhost/Digikrachtig/public/controle.php
 *
 * Dit bestand is alleen om te controleren of de verbinding en het
 * model werken. VERWIJDEREN voordat het project live gaat.
 */

declare(strict_types=1);

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/models/FormulierModel.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $model = new FormulierModel(Database::verbinding());

    $formulier = $model->actiefFormulier();

    if ($formulier === null) {
        exit('Geen actief formulier gevonden. Is vragenlijst.sql gedraaid?');
    }

    $structuur = $model->structuur((int) $formulier['id']);

    echo '<h1>' . htmlspecialchars($formulier['naam']) . '</h1>';
    echo '<p>Versie ' . (int) $formulier['versie'] . '</p>';

    $aantalVragen = 0;

    foreach ($structuur as $sectie) {
        echo '<h2>' . htmlspecialchars($sectie['titel'] ?: '(geen kopje)') . '</h2>';

        if ($sectie['intro'] !== null) {
            echo '<p><em>' . nl2br(htmlspecialchars($sectie['intro'])) . '</em></p>';
        }

        echo '<ol>';

        foreach ($sectie['vragen'] as $vraag) {
            $aantalVragen++;

            echo '<li>';
            echo htmlspecialchars($vraag['label']);
            echo ' <small>[' . htmlspecialchars($vraag['type']);
            echo $vraag['verplicht'] ? ', verplicht' : '';
            echo ']</small>';

            if ($vraag['opties'] !== []) {
                echo '<br><small>Opties: '
                    . htmlspecialchars(implode(' / ', $vraag['opties']))
                    . '</small>';
            }

            if ($vraag['toon_als_code'] !== null) {
                echo '<br><small>Alleen tonen als <b>'
                    . htmlspecialchars($vraag['toon_als_code'])
                    . '</b> = '
                    . htmlspecialchars(implode(' of ', $vraag['toon_als_waarden']))
                    . '</small>';
            }

            echo '</li>';
        }

        echo '</ol>';
    }

    echo '<hr><p><b>Totaal: ' . count($structuur) . ' secties, '
        . $aantalVragen . ' regels.</b></p>';

} catch (Throwable $fout) {
    echo '<h1>Er ging iets mis</h1>';
    echo '<pre>' . htmlspecialchars($fout->getMessage()) . '</pre>';
}
