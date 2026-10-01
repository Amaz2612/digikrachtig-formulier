<?php
/**
 * Digikrachtig formulierensysteem
 * Maakt een beheerder aan, of geeft een bestaande beheerder een nieuw
 * wachtwoord.
 *
 * Plaats dit bestand in: tools/maak-admin.php
 *
 * Draaien vanaf de opdrachtregel, in de map van het project:
 *   C:\xampp\php\php.exe tools\maak-admin.php
 *
 * Het script vraagt zelf om de gebruikersnaam en het wachtwoord. Het
 * wachtwoord gaat bewust NIET als argument mee: dan komt het in de
 * geschiedenis van de opdrachtregel terecht. In de database komt
 * alleen de hash van password_hash().
 *
 * Eerst database/admin.sql draaien, anders bestaat de tabel nog niet.
 */

declare(strict_types=1);

// Alleen vanaf de opdrachtregel, nooit via de browser.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Dit script werkt alleen vanaf de opdrachtregel.');
}

require_once __DIR__ . '/../app/Database.php';
require_once __DIR__ . '/../app/models/BeheerderModel.php';

const MIN_LENGTE_WACHTWOORD = 12;

/**
 * Stopt met een melding en foutcode 1, zodat een script dat dit
 * aanroept kan zien dat het niet gelukt is.
 */
function stop(string $melding): void
{
    fwrite(STDERR, $melding);
    exit(1);
}

/**
 * Stelt een vraag en geeft het antwoord terug, zonder enter erachter.
 * Bij $verbergen wordt het getypte niet getoond, als dat lukt.
 */
function vraag(string $tekst, bool $verbergen = false): string
{
    echo $tekst;

    // Op Linux en macOS kan stty de invoer verbergen. Windows heeft dat
    // niet, daar is het getypte wachtwoord zichtbaar.
    $verborgen = false;

    if ($verbergen && PHP_OS_FAMILY !== 'Windows') {
        exec('stty -echo 2>/dev/null', $uitvoer, $code);
        $verborgen = $code === 0;
    }

    $antwoord = fgets(STDIN);

    if ($verborgen) {
        exec('stty echo');
        echo "\n";
    }

    if ($antwoord === false) {
        stop("\nGeen invoer, gestopt.\n");
    }

    return rtrim($antwoord, "\r\n");
}

$gebruikersnaam = trim(vraag('Gebruikersnaam: '));

// Letters, cijfers, punt, streepje en liggend streepje; 3 tot 50 tekens.
if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $gebruikersnaam)) {
    stop("Gebruik 3 tot 50 tekens: letters, cijfers, punt, - of _.\n");
}

// Beheerders loggen in via het veld Studentnummer. Een naam die op een
// studentnummer lijkt (alleen cijfers, of rv met cijfers) wordt daar
// als student gezien, en zo'n beheerder zou nooit kunnen inloggen.
if (preg_match('/^(rv)?[0-9]+$/i', $gebruikersnaam)) {
    stop("Deze naam lijkt op een studentnummer. Gebruik ook andere letters dan rv.\n");
}

$model     = new BeheerderModel(Database::verbinding());
$bestaande = $model->zoekOpGebruikersnaam($gebruikersnaam);

if ($bestaande !== null) {
    $keuze = strtolower(trim(vraag(
        'Beheerder "' . $gebruikersnaam . '" bestaat al. Nieuw wachtwoord instellen? (j/n): '
    )));

    if ($keuze !== 'j') {
        exit("Niets veranderd.\n");
    }
}

if (PHP_OS_FAMILY === 'Windows') {
    echo "Let op: op Windows is het wachtwoord zichtbaar terwijl je typt.\n";
}

$wachtwoord = vraag('Wachtwoord (minstens ' . MIN_LENGTE_WACHTWOORD . ' tekens): ', true);

if (mb_strlen($wachtwoord) < MIN_LENGTE_WACHTWOORD) {
    stop('Het wachtwoord is te kort, gebruik minstens ' . MIN_LENGTE_WACHTWOORD . " tekens.\n");
}

if (strlen($wachtwoord) > 72) {
    // bcrypt (de standaard van password_hash) kijkt alleen naar de
    // eerste 72 bytes. Langer heeft dus geen zin en is verwarrend.
    stop("Het wachtwoord is te lang, gebruik maximaal 72 tekens.\n");
}

if (vraag('Herhaal het wachtwoord: ', true) !== $wachtwoord) {
    stop("De wachtwoorden zijn niet hetzelfde. Niets veranderd.\n");
}

$hash = password_hash($wachtwoord, PASSWORD_DEFAULT);

if ($bestaande !== null) {
    $model->zetWachtwoord((int) $bestaande['id'], $hash);
    echo 'Nieuw wachtwoord ingesteld voor "' . $gebruikersnaam . "\".\n";
} else {
    $model->maak($gebruikersnaam, $hash);
    echo 'Beheerder "' . $gebruikersnaam . "\" aangemaakt.\n";
}
