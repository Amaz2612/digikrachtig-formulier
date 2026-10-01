<?php
/**
 * Digikrachtig formulierensysteem
 * Ingang van de beheerpagina: alle verzoeken voor beheer komen hier.
 *
 * Plaats dit bestand in: public/admin.php
 * Open daarna http://localhost/Digikrachtig/public/admin.php
 *
 * Dit is een losse ingang naast index.php. Inloggen gebeurt op de
 * gewone inlogpagina: vul bij Studentnummer je gebruikersnaam in, dan
 * volgt een stap met je wachtwoord (zie BeheerAuth). Inloggen als
 * student geeft hier geen toegang, en andersom.
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
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/Beveiliging.php';
require_once __DIR__ . '/../app/BeheerAuth.php';
require_once __DIR__ . '/../app/Voorwaarden.php';
require_once __DIR__ . '/../app/models/FormulierModel.php';
require_once __DIR__ . '/../app/models/InzendingModel.php';
require_once __DIR__ . '/../app/models/BeheerderModel.php';
require_once __DIR__ . '/../app/models/BeheerModel.php';
require_once __DIR__ . '/../app/controllers/BeheerController.php';

header('Content-Type: text/html; charset=utf-8');

// Hier staan persoonlijke gegevens: niet bewaren in de browsercache,
// niet in een frame van een andere site, niet in zoekmachines.
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex, nofollow');

Auth::startSessie();

$actie   = $_GET['actie'] ?? 'overzicht';
$isPost  = $_SERVER['REQUEST_METHOD'] === 'POST';

try {
    $controller = new BeheerController(Database::verbinding());

    switch ($actie) {
        // Er is geen aparte inlogpagina meer voor beheer. Oude links en
        // bladwijzers komen zo toch goed uit.
        case 'inloggen':
            header('Location: index.php?actie=login');
            exit;

        case 'uitloggen':
            if (!$isPost) {
                header('Location: ?actie=overzicht');
                exit;
            }

            $controller->uitloggen();
            break;

        case 'detail':
            $controller->detail();
            break;

        // GET = de vraag "weet je het zeker?", POST = echt resetten.
        case 'reset':
            $isPost ? $controller->reset() : $controller->resetBevestigen();
            break;

        case 'overzicht':
        default:
            $controller->overzicht();
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
