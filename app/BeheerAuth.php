<?php
/**
 * Digikrachtig formulierensysteem
 * Inloggen voor beheerders.
 *
 * Plaats dit bestand in: app/BeheerAuth.php
 *
 * Beheerders loggen in op dezelfde inlogpagina als de studenten
 * (public/index.php). Typt iemand in het veld Studentnummer een
 * gebruikersnaam in plaats van een nummer, dan volgt er een stap met
 * het wachtwoord in plaats van een code per mail. Klopt het wachtwoord,
 * dan gaat hij naar public/admin.php.
 *
 * Student en beheerder delen dezelfde sessie, maar staan onder een
 * eigen sleutel:
 *   - de student in $_SESSION['gebruiker_id'] (zie Auth)
 *   - de beheerder in $_SESSION['beheerder'] (hier)
 * De studentenkant kijkt alleen naar de eerste, de beheerpagina alleen
 * naar de tweede. Een student is dus nooit vanzelf beheerder, en een
 * beheerder nooit vanzelf student.
 */
class BeheerAuth
{
    private const SESSIE_SLEUTEL = 'beheerder';
    private const POGING_SLEUTEL = 'beheer_poging';

    /** Na zoveel foute wachtwoorden moet je opnieuw beginnen. */
    private const MAX_POGINGEN = 5;

    /** Zo lang blijft de wachtwoordstap open. */
    private const GELDIG_SECONDEN = 300;

    /**
     * Kan dit een gebruikersnaam van een beheerder zijn? Dezelfde regel
     * als in tools/maak-admin.php. Een studentnummer valt hier ook onder,
     * dus eerst Auth::schoonStudentnummer() proberen.
     */
    public static function lijktGebruikersnaam(string $tekst): bool
    {
        return preg_match('/^[A-Za-z0-9._-]{3,50}$/', $tekst) === 1;
    }

    // --- De wachtwoordstap ---

    /**
     * Begint de wachtwoordstap voor deze gebruikersnaam.
     *
     * Hier wordt nog niet gekeken of de naam bestaat. Anders zie je al
     * aan het wel of niet krijgen van de wachtwoordstap welke namen er
     * zijn. Dat blijkt pas als het wachtwoord gecontroleerd wordt.
     */
    public static function startPoging(string $gebruikersnaam): void
    {
        Auth::startSessie();

        $_SESSION[self::POGING_SLEUTEL] = [
            'gebruikersnaam' => $gebruikersnaam,
            'verloopt'       => time() + self::GELDIG_SECONDEN,
            'fouten'         => 0,
        ];
    }

    public static function pogingBezig(): bool
    {
        Auth::startSessie();

        return isset($_SESSION[self::POGING_SLEUTEL]['gebruikersnaam']);
    }

    public static function pogingGebruikersnaam(): ?string
    {
        Auth::startSessie();

        return $_SESSION[self::POGING_SLEUTEL]['gebruikersnaam'] ?? null;
    }

    public static function stopPoging(): void
    {
        Auth::startSessie();

        unset($_SESSION[self::POGING_SLEUTEL]);
    }

    /**
     * Controleert het wachtwoord van de lopende poging.
     *
     * Geeft terug:
     *   'goed'      ingelogd als beheerder
     *   'fout'      wachtwoord (of gebruikersnaam) klopt niet, nog een keer
     *   'te_vaak'   te vaak fout, opnieuw beginnen
     *   'verlopen'  de stap stond te lang open, opnieuw beginnen
     */
    public static function controleerPoging(PDO $pdo, string $wachtwoord): string
    {
        Auth::startSessie();

        $poging = $_SESSION[self::POGING_SLEUTEL] ?? null;

        if ($poging === null || time() > $poging['verloopt']) {
            self::stopPoging();

            return 'verlopen';
        }

        if (
            $wachtwoord !== ''
            && strlen($wachtwoord) <= 255
            && self::login($pdo, $poging['gebruikersnaam'], $wachtwoord)
        ) {
            self::stopPoging();

            return 'goed';
        }

        $_SESSION[self::POGING_SLEUTEL]['fouten']++;

        if ($_SESSION[self::POGING_SLEUTEL]['fouten'] >= self::MAX_POGINGEN) {
            self::stopPoging();

            return 'te_vaak';
        }

        return 'fout';
    }

    // --- Ingelogd zijn ---

    /**
     * Controleert gebruikersnaam en wachtwoord en logt in als het klopt.
     */
    public static function login(PDO $pdo, string $gebruikersnaam, string $wachtwoord): bool
    {
        $model     = new BeheerderModel($pdo);
        $beheerder = $model->zoekOpGebruikersnaam($gebruikersnaam);

        if ($beheerder === null) {
            // Toch een hash controleren, zodat een onbekende naam even
            // lang duurt als een fout wachtwoord. Anders kun je aan de
            // tijd zien welke gebruikersnamen bestaan.
            password_verify($wachtwoord, password_hash('niet-bestaand', PASSWORD_DEFAULT));

            return false;
        }

        if (!password_verify($wachtwoord, $beheerder['wachtwoord_hash'])) {
            return false;
        }

        // Is PHP sinds het aanmaken sterker gaan hashen, dan meteen bijwerken.
        if (password_needs_rehash($beheerder['wachtwoord_hash'], PASSWORD_DEFAULT)) {
            $model->zetWachtwoord(
                (int) $beheerder['id'],
                password_hash($wachtwoord, PASSWORD_DEFAULT)
            );
        }

        Auth::startSessie();

        // Nieuw sessie-id na inloggen, tegen session fixation.
        session_regenerate_id(true);

        $_SESSION[self::SESSIE_SLEUTEL] = [
            'id'             => (int) $beheerder['id'],
            'gebruikersnaam' => $beheerder['gebruikersnaam'],
        ];

        return true;
    }

    public static function isIngelogd(): bool
    {
        Auth::startSessie();

        return isset($_SESSION[self::SESSIE_SLEUTEL]['id']);
    }

    public static function gebruikersnaam(): ?string
    {
        Auth::startSessie();

        return $_SESSION[self::SESSIE_SLEUTEL]['gebruikersnaam'] ?? null;
    }

    /**
     * Uitloggen als beheerder. Alleen het beheerdersdeel van de sessie
     * gaat weg; is in dezelfde browser ook een student ingelogd, dan
     * blijft die ingelogd.
     */
    public static function uitloggen(): void
    {
        Auth::startSessie();

        unset($_SESSION[self::SESSIE_SLEUTEL]);
        session_regenerate_id(true);
    }
}
