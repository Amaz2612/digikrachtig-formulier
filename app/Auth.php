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
     * Maakt de ingevulde naam schoon en controleert hem. De student
     * mag invullen wat hij wil (meerdere studenten mogen dezelfde naam
     * hebben); de naam is alleen om hem makkelijker te herkennen.
     * Geeft null terug als er niets bruikbaars staat.
     */
    public static function schoonNaam(string $naam): ?string
    {
        $naam = trim(preg_replace('/\s+/u', ' ', $naam) ?? '');

        if ($naam === '' || mb_strlen($naam) > 60) {
            return null;
        }

        // Geen stuurtekens; en de versleutelde kolom is 255 bytes.
        if (preg_match('/[\x00-\x1F\x7F]/', $naam) || strlen($naam) > 200) {
            return null;
        }

        return $naam;
    }

    /**
     * Studentnummer: alleen cijfers, 4 tot 20 lang. Geeft null terug
     * als het niet klopt.
     */
    public static function schoonStudentnummer(string $studentnummer): ?string
    {
        $studentnummer = trim($studentnummer);

        return preg_match('/^[0-9]{4,20}$/', $studentnummer) ? $studentnummer : null;
    }

    /**
     * Controleert het ingevulde e-mailadres. Geeft null terug als het
     * geen geldig adres is. Het adres wordt alleen in de sessie bewaard
     * om het formulier vooraf in te vullen; bij Padgin is het al bekend.
     */
    public static function schoonEmail(string $email): ?string
    {
        $email = trim($email);

        if ($email === '' || mb_strlen($email) > 255) {
            return null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) === false
            ? null
            : $email;
    }

    /**
     * Logt in op studentnummer en bewaart de naam (versleuteld). De
     * gebruiker wordt aangemaakt als hij nog niet bestaat, zodat je
     * makkelijk kunt testen. Vult de student bij een volgende keer een
     * andere naam in, dan wordt de naam bijgewerkt.
     *
     * @param string $naam    al schoongemaakt met schoonNaam()
     * @param string $email   al gecontroleerd met schoonEmail()
     * @param string $sleutel crypt_sleutel uit config/config.php
     */
    public static function login(
        PDO $pdo,
        string $studentnummer,
        string $naam,
        string $email,
        string $sleutel
    ): bool {
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

        $versleuteld = Versleuteling::versleutel($naam, $sleutel);

        $bewaar = $pdo->prepare(
            'INSERT INTO user_profiles (user_id, username_enc, username_iv)
             VALUES (:user_id, :enc, :iv)
             ON DUPLICATE KEY UPDATE
                username_enc = VALUES(username_enc),
                username_iv  = VALUES(username_iv)'
        );
        $bewaar->bindValue('user_id', $id, PDO::PARAM_INT);
        $bewaar->bindValue('enc', $versleuteld['enc'], PDO::PARAM_LOB);
        $bewaar->bindValue('iv', $versleuteld['iv'], PDO::PARAM_LOB);
        $bewaar->execute();

        self::startSessie();

        // Nieuw sessie-id na inloggen, tegen session fixation.
        session_regenerate_id(true);

        $_SESSION['gebruiker_id']  = $id;
        $_SESSION['studentnummer'] = $studentnummer;
        $_SESSION['naam']          = $naam;
        $_SESSION['email']         = $email;

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

    public static function naam(): ?string
    {
        self::startSessie();

        return $_SESSION['naam'] ?? null;
    }

    public static function email(): ?string
    {
        self::startSessie();

        return $_SESSION['email'] ?? null;
    }

    public static function uitloggen(): void
    {
        self::startSessie();
        $_SESSION = [];
        session_destroy();
    }
}
