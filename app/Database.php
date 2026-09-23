<?php
/**
 * Digikrachtig formulierensysteem
 * Databaseverbinding.
 *
 * Plaats dit bestand in: app/Database.php
 *
 * Er is maar een verbinding nodig per pagina-aanvraag. Die wordt
 * de eerste keer gemaakt en daarna hergebruikt.
 *
 * Gebruik:
 *   $pdo = Database::verbinding();
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function verbinding(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $configBestand = __DIR__ . '/../config/config.php';

        if (!file_exists($configBestand)) {
            throw new RuntimeException(
                'config/config.php ontbreekt. Kopieer config/config.example.php '
                . 'naar config/config.php en vul je gegevens in.'
            );
        }

        $config = require $configBestand;

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $config['db_host'],
            $config['db_naam']
        );

        self::$pdo = new PDO(
            $dsn,
            $config['db_gebruiker'],
            $config['db_wachtwoord'],
            [
                // Gooi een foutmelding bij een fout, in plaats van
                // stilletjes false teruggeven.
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

                // Resultaten altijd als associatieve array.
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                // Echte prepared statements laten gebruiken door MySQL
                // zelf. Dit is de belangrijkste instelling tegen
                // SQL-injectie.
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        return self::$pdo;
    }
}
