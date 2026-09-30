<?php
/**
 * Digikrachtig formulierensysteem
 * Front controller: alle verzoeken komen hier binnen.
 *
 * Plaats dit bestand in: public/index.php
 * Open daarna http://localhost/Digikrachtig/public/
 */

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';

if ($config['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/Versleuteling.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/Beveiliging.php';
require_once __DIR__ . '/../app/Voorwaarden.php';
require_once __DIR__ . '/../app/Validatie.php';
require_once __DIR__ . '/../app/models/FormulierModel.php';
require_once __DIR__ . '/../app/models/InzendingModel.php';
require_once __DIR__ . '/../app/controllers/FormulierController.php';

header('Content-Type: text/html; charset=utf-8');

Auth::startSessie();

$pdo   = Database::verbinding();
$actie = $_GET['actie'] ?? 'formulier';

try {
    // Inloggen verwerken
    if ($actie === 'inloggen' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Beveiliging::tokenKlopt($_POST['csrf_token'] ?? null)) {
            exit('Het formulier is verlopen. Ga terug en probeer het opnieuw.');
        }

        $ingevuldNummer = trim((string) ($_POST['studentnummer'] ?? ''));
        $ingevuldeNaam  = trim((string) ($_POST['naam'] ?? ''));
        $naam           = Auth::schoonNaam($ingevuldeNaam);

        if ($naam === null) {
            $foutmelding = 'Vul je voor- en achternaam in: alleen letters, minstens twee woorden.';
        } elseif (Auth::login($pdo, $ingevuldNummer, $naam, (string) $config['crypt_sleutel'])) {
            header('Location: ?actie=formulier');
            exit;
        } else {
            $foutmelding = 'Vul een geldig studentnummer in (alleen cijfers).';
        }

        require __DIR__ . '/../app/views/login.php';
        exit;
    }

    if ($actie === 'uitloggen') {
        Auth::uitloggen();
        header('Location: ?actie=login');
        exit;
    }

    // Automatisch opslaan verwacht JSON terug, geen inlogpagina.
    if ($actie === 'autosave' && !Auth::isIngelogd()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'reden' => 'uitgelogd']);
        exit;
    }

    // Alles hieronder is alleen voor wie ingelogd is
    if (!Auth::isIngelogd()) {
        require __DIR__ . '/../app/views/login.php';
        exit;
    }

    $controller = new FormulierController($pdo);

    switch ($actie) {
        case 'opslaan':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                header('Location: ?actie=formulier');
                exit;
            }

            $controller->verwerk();
            break;

        case 'autosave':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                exit;
            }

            $controller->autosave();
            break;

        case 'klaar':
            $controller->klaar();
            break;

        case 'login':
            header('Location: ?actie=formulier');
            exit;

        case 'formulier':
        default:
            $controller->toon();
            break;
    }
} catch (Throwable $fout) {
    http_response_code(500);

    if ($config['debug']) {
        echo '<h1>Er ging iets mis</h1><pre>'
            . htmlspecialchars($fout->getMessage()) . "\n\n"
            . htmlspecialchars($fout->getTraceAsString()) . '</pre>';
    } else {
        echo '<h1>Er ging iets mis</h1>'
            . '<p>Probeer het later opnieuw.</p>';
    }
}
