<?php
/**
 * Digikrachtig formulierensysteem
 * Gedeelde code voor de testscripts in deze map.
 *
 * De tests draaien op een APARTE database (digikrachtig_test), zodat
 * de database waar je mee ontwikkelt niet wordt aangeraakt.
 *
 * Draaien vanaf de opdrachtregel, bijvoorbeeld:
 *   C:\xampp\php\php.exe tests\alles.php
 */

declare(strict_types=1);

const TEST_DB         = 'digikrachtig_test';
const TEST_HOST       = 'localhost';
const TEST_GEBRUIKER  = 'root';
const TEST_WACHTWOORD = '';

// Sessie meteen starten, voordat er iets geprint wordt. Daarna kan het
// niet meer ("headers already sent"). Zonder cookie, het is de CLI.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_cookies', '0');
    ini_set('session.save_path', sys_get_temp_dir());
    session_start();
}

$basis = dirname(__DIR__);

require_once $basis . '/app/Database.php';
require_once $basis . '/app/Versleuteling.php';
require_once $basis . '/app/Auth.php';
require_once $basis . '/app/Beveiliging.php';
require_once $basis . '/app/Voorwaarden.php';
require_once $basis . '/app/Validatie.php';
require_once $basis . '/app/models/FormulierModel.php';
require_once $basis . '/app/models/InzendingModel.php';
require_once $basis . '/app/controllers/FormulierController.php';
require_once $basis . '/app/BeheerAuth.php';
require_once $basis . '/app/models/BeheerderModel.php';
require_once $basis . '/app/models/BeheerModel.php';
require_once $basis . '/app/controllers/BeheerController.php';

/**
 * Verbinding met de testdatabase, met dezelfde instellingen als
 * app/Database.php.
 */
function testPdo(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . TEST_HOST . ';dbname=' . TEST_DB . ';charset=utf8mb4',
            TEST_GEBRUIKER,
            TEST_WACHTWOORD,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    return $pdo;
}

/**
 * Laat Database::verbinding() de testdatabase teruggeven in plaats
 * van de database uit config/config.php.
 */
function gebruikTestdatabase(): void
{
    $eigenschap = new ReflectionProperty(Database::class, 'pdo');
    $eigenschap->setAccessible(true);
    $eigenschap->setValue(null, testPdo());
}

/**
 * Bouwt de testdatabase opnieuw op uit de SQL-bestanden in database/.
 * De naam 'digikrachtig' wordt daarbij vervangen door de testnaam.
 */
function bouwTestdatabase(): array
{
    $mysqli = new mysqli(TEST_HOST, TEST_GEBRUIKER, TEST_WACHTWOORD);
    $mysqli->set_charset('utf8mb4');

    // admin.sql laat bestaande beheerders staan (CREATE TABLE IF NOT
    // EXISTS). Voor de tests willen we elke keer een lege tabel.
    // (Bij de allereerste keer bestaat de database nog niet; dat geeft niet.)
    try {
        $mysqli->query('DROP TABLE IF EXISTS ' . TEST_DB . '.admins');
    } catch (mysqli_sql_exception $e) {
    }

    $bestanden = ['schema .sql', 'vragenlijst.sql', 'testdata.sql', 'admin.sql'];
    $laatsteRij = null;

    foreach ($bestanden as $bestand) {
        $sql = file_get_contents(dirname(__DIR__) . '/database/' . $bestand);
        $sql = str_replace(
            ['CREATE DATABASE IF NOT EXISTS digikrachtig', 'USE digikrachtig;'],
            ['CREATE DATABASE IF NOT EXISTS ' . TEST_DB, 'USE ' . TEST_DB . ';'],
            $sql
        );

        if (!$mysqli->multi_query($sql)) {
            throw new RuntimeException($bestand . ': ' . $mysqli->error);
        }

        do {
            $resultaat = $mysqli->store_result();

            if ($resultaat instanceof mysqli_result) {
                $laatsteRij = $resultaat->fetch_assoc();
                $resultaat->free();
            }
        } while ($mysqli->more_results() && $mysqli->next_result());

        if ($mysqli->errno) {
            throw new RuntimeException($bestand . ': ' . $mysqli->error);
        }
    }

    $mysqli->close();

    // De controle-SELECT onderaan vragenlijst.sql
    return $laatsteRij ?? [];
}

// --- Bijhouden van de resultaten ---

$GLOBALS['uitslagen'] = [];

/**
 * Legt een testgeval vast en print het meteen.
 */
function test(string $nummer, string $omschrijving, $verwacht, $werkelijk): bool
{
    $geslaagd = $verwacht === $werkelijk;

    $GLOBALS['uitslagen'][] = [$nummer, $omschrijving, $geslaagd];

    printf(
        "[%s] %-6s %s\n         verwacht:  %s\n         werkelijk: %s\n",
        $geslaagd ? 'OK  ' : 'FOUT',
        $nummer,
        $omschrijving,
        toonWaarde($verwacht),
        toonWaarde($werkelijk)
    );

    return $geslaagd;
}

function toonWaarde($waarde): string
{
    return json_encode($waarde, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function samenvatting(): void
{
    $totaal  = count($GLOBALS['uitslagen']);
    $fout    = array_filter($GLOBALS['uitslagen'], fn ($u) => !$u[2]);

    echo "\n" . ($totaal - count($fout)) . " van de $totaal geslaagd.\n";

    foreach ($fout as $u) {
        echo "  mislukt: {$u[0]} {$u[1]}\n";
    }
}

/**
 * Maakt een testgebruiker met een lege inzending en geeft de id's terug.
 */
function nieuweInzending(string $studentnummer): array
{
    $pdo = testPdo();
    $pdo->prepare('INSERT INTO users (studentnummer) VALUES (?)')->execute([$studentnummer]);
    $gebruikerId = (int) $pdo->lastInsertId();

    $formulier = (new FormulierModel($pdo))->actiefFormulier();
    $inzending = (new InzendingModel($pdo))->haalOfMaak((int) $formulier['id'], $gebruikerId);

    return [(int) $formulier['id'], $gebruikerId, (int) $inzending['id']];
}
