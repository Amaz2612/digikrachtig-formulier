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
require_once __DIR__ . '/../app/Otp.php';
require_once __DIR__ . '/../app/Beveiliging.php';
require_once __DIR__ . '/../app/BeheerAuth.php';
require_once __DIR__ . '/../app/models/BeheerderModel.php';
require_once __DIR__ . '/../app/Voorwaarden.php';
require_once __DIR__ . '/../app/Validatie.php';
require_once __DIR__ . '/../app/models/FormulierModel.php';
require_once __DIR__ . '/../app/models/InzendingModel.php';
require_once __DIR__ . '/../app/controllers/FormulierController.php';

header('Content-Type: text/html; charset=utf-8');

Auth::startSessie();

$pdo   = Database::verbinding();
$actie = $_GET['actie'] ?? 'formulier';

/**
 * Stuurt de inlogcode naar het e-mailadres van de lopende poging. Met
 * debug aan gaat er geen mail weg en komt de code op de OTP-pagina te
 * staan, zodat je zonder mailserver kunt testen.
 */
$stuurCode = function (string $code) use ($config): void {
    if ($config['debug']) {
        $_SESSION['otp']['testcode'] = $code;

        return;
    }

    $_SESSION['otp']['mail_mislukt'] = !Otp::verstuur($_SESSION['otp']['email'], $code);
};

try {
    // Inloggen verwerken
    if ($actie === 'inloggen' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Beveiliging::tokenKlopt($_POST['csrf_token'] ?? null)) {
            exit('Het formulier is verlopen. Ga terug en probeer het opnieuw.');
        }

        $ingevuldNummer = trim((string) ($_POST['studentnummer'] ?? ''));
        $ingevuldeNaam  = trim((string) ($_POST['naam'] ?? ''));
        $ingevuldeEmail = trim((string) ($_POST['email'] ?? ''));
        $studentnummer  = Auth::schoonStudentnummer($ingevuldNummer);
        $naam           = Auth::schoonNaam($ingevuldeNaam);
        $email          = Auth::schoonEmail($ingevuldeEmail);

        // Geen studentnummer, maar wel iets dat een gebruikersnaam kan
        // zijn: dan logt er een beheerder in. Naam en e-mail zijn dan niet
        // nodig; de volgende stap vraagt om het wachtwoord.
        if ($studentnummer === null && BeheerAuth::lijktGebruikersnaam($ingevuldNummer)) {
            Otp::stop();
            BeheerAuth::startPoging($ingevuldNummer);
            header('Location: ?actie=wachtwoord');
            exit;
        }

        if ($studentnummer === null) {
            $foutmelding = 'Vul een geldig studentnummer in (alleen cijfers).';
        } elseif ($naam === null) {
            $foutmelding = 'Vul je voor- en achternaam in: alleen letters, minstens twee woorden.';
        } elseif ($email === null) {
            $foutmelding = 'Vul een geldig e-mailadres in.';
        } else {
            // Nog niet inloggen: eerst de code per mail controleren.
            BeheerAuth::stopPoging();
            $stuurCode(Otp::start($studentnummer, $naam, $email));
            header('Location: ?actie=otp');
            exit;
        }

        require __DIR__ . '/../app/views/login.php';
        exit;
    }

    // De pagina waar de student de code uit de mail invult
    if ($actie === 'otp') {
        if (!Otp::bezig()) {
            header('Location: ?actie=login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Beveiliging::tokenKlopt($_POST['csrf_token'] ?? null)) {
                exit('Het formulier is verlopen. Ga terug en probeer het opnieuw.');
            }

            $uitkomst = Otp::controleer((string) ($_POST['code'] ?? ''));

            if ($uitkomst === 'goed') {
                $poging = Otp::gegevens();
                Otp::stop();
                Auth::login(
                    $pdo,
                    $poging['studentnummer'],
                    $poging['naam'],
                    $poging['email'],
                    (string) $config['crypt_sleutel']
                );
                header('Location: ?actie=formulier');
                exit;
            }

            if ($uitkomst === 'fout') {
                $foutmelding = 'Deze code klopt niet. Probeer het opnieuw.';
            } else {
                // Verlopen of te vaak fout: helemaal opnieuw beginnen,
                // maar de ingevulde gegevens wel laten staan.
                $poging         = Otp::gegevens();
                $ingevuldNummer = $poging['studentnummer'];
                $ingevuldeNaam  = $poging['naam'];
                $ingevuldeEmail = $poging['email'];
                Otp::stop();
                $foutmelding = $uitkomst === 'verlopen'
                    ? 'Je code is verlopen. Log opnieuw in.'
                    : 'Te vaak een verkeerde code ingevuld. Log opnieuw in.';
                require __DIR__ . '/../app/views/login.php';
                exit;
            }
        }

        $poging = Otp::gegevens();
        require __DIR__ . '/../app/views/otp.php';
        exit;
    }

    // De pagina waar een beheerder zijn wachtwoord invult. Klopt het,
    // dan gaat hij naar de beheerpagina (admin.php).
    if ($actie === 'wachtwoord') {
        if (!BeheerAuth::pogingBezig()) {
            header('Location: ?actie=login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Beveiliging::tokenKlopt($_POST['csrf_token'] ?? null)) {
                http_response_code(400);
                exit('Het formulier is verlopen. Ga terug en probeer het opnieuw.');
            }

            $uitkomst = BeheerAuth::controleerPoging($pdo, (string) ($_POST['wachtwoord'] ?? ''));

            if ($uitkomst === 'goed') {
                header('Location: admin.php');
                exit;
            }

            if ($uitkomst === 'fout') {
                // Bewust geen verschil tussen een onbekende naam en een
                // fout wachtwoord: niet verraden welke namen bestaan.
                $foutmelding = 'Gebruikersnaam of wachtwoord klopt niet.';
            } else {
                $foutmelding = $uitkomst === 'verlopen'
                    ? 'Dit duurde te lang. Log opnieuw in.'
                    : 'Te vaak een verkeerd wachtwoord ingevuld. Log opnieuw in.';
                require __DIR__ . '/../app/views/login.php';
                exit;
            }
        }

        $gebruikersnaam = BeheerAuth::pogingGebruikersnaam();
        require __DIR__ . '/../app/views/wachtwoord.php';
        exit;
    }

    if ($actie === 'otp-opnieuw' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Beveiliging::tokenKlopt($_POST['csrf_token'] ?? null)) {
            exit('Het formulier is verlopen. Ga terug en probeer het opnieuw.');
        }

        if (!Otp::bezig()) {
            header('Location: ?actie=login');
            exit;
        }

        if (Otp::magOpnieuwSturen()) {
            $stuurCode(Otp::nieuweCode());
            header('Location: ?actie=otp&opnieuw=1');
        } else {
            header('Location: ?actie=otp&wacht=1');
        }
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
