<?php
/**
 * Digikrachtig formulierensysteem
 * Tijdelijke login.
 *
 * Plaats dit bestand in: app/Auth.php
 *
 * LET OP: dit is een testlogin zonder wachtwoord of controle.
 * In productie wordt dit vervangen door de login van Padgin
 * (studentnummer + code per mail). Alles wat de rest van de code
 * nodig heeft is het id van de ingelogde student, dus bij de
 * overstap hoeft alleen dit bestand te veranderen.
 */
class Auth
{
    public static function startSessie(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Logt in op studentnummer. De gebruiker wordt aangemaakt als
     * hij nog niet bestaat, zodat je makkelijk kunt testen.
     */
    public static function login(PDO $pdo, string $studentnummer): bool
    {
        $studentnummer = trim($studentnummer);

        if (!preg_match('/^[0-9]{4,20}$/', $studentnummer)) {
            return false;
        }

        $zoek = $pdo->prepare(
            'SELECT id FROM users WHERE studentnummer = :nummer'
        );
        $zoek->execute(['nummer' => $studentnummer]);
        $rij = $zoek->fetch();

        if ($rij === false) {
            $maak = $pdo->prepare(
                'INSERT INTO users (studentnummer) VALUES (:nummer)'
            );
            $maak->execute(['nummer' => $studentnummer]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $id = (int) $rij['id'];
        }

        self::startSessie();

        // Nieuw sessie-id na inloggen, tegen session fixation.
        session_regenerate_id(true);

        $_SESSION['gebruiker_id']  = $id;
        $_SESSION['studentnummer'] = $studentnummer;

        return true;
    }

    public static function isIngelogd(): bool
    {
        self::startSessie();

        return isset($_SESSION['gebruiker_id']);
    }

    public static function gebruikerId(): ?int
    {
        self::startSessie();

        return isset($_SESSION['gebruiker_id'])
            ? (int) $_SESSION['gebruiker_id']
            : null;
    }

    public static function studentnummer(): ?string
    {
        self::startSessie();

        return $_SESSION['studentnummer'] ?? null;
    }

    public static function uitloggen(): void
    {
        self::startSessie();
        $_SESSION = [];
        session_destroy();
    }
}
