<?php
/**
 * Digikrachtig formulierensysteem
 * Eenmalige inlogcode (OTP) per e-mail.
 *
 * Plaats dit bestand in: app/Otp.php
 *
 * Werkwijze: na de inlogpagina maken we een code van 6 cijfers en
 * sturen die naar het ingevulde e-mailadres. De gegevens van de
 * inlogpoging staan zolang in de sessie. Pas als de code klopt wordt
 * de student echt ingelogd (Auth::login).
 *
 * De code zelf staat alleen als hash in de sessie, verloopt na
 * GELDIG_SECONDEN en na MAX_POGINGEN foute pogingen moet de student
 * opnieuw inloggen.
 *
 * Staat 'debug' aan in config/config.php, dan wordt er geen mail
 * verstuurd maar staat de code op de pagina, zodat je lokaal zonder
 * mailserver kunt testen.
 */
class Otp
{
    private const LENGTE          = 6;
    private const GELDIG_SECONDEN = 600;
    private const MAX_POGINGEN    = 5;
    private const WACHT_SECONDEN  = 60;

    /**
     * Start een inlogpoging: bewaart de gegevens en maakt een code.
     * Geeft de code terug zodat die verstuurd kan worden.
     */
    public static function start(string $studentnummer, string $naam, string $email): string
    {
        Auth::startSessie();

        $_SESSION['otp'] = [
            'studentnummer' => $studentnummer,
            'naam'          => $naam,
            'email'         => $email,
        ];

        return self::nieuweCode();
    }

    /**
     * Maakt een nieuwe code voor de lopende inlogpoging. De oude code
     * werkt daarna niet meer.
     */
    public static function nieuweCode(): string
    {
        $code = str_pad(
            (string) random_int(0, 10 ** self::LENGTE - 1),
            self::LENGTE,
            '0',
            STR_PAD_LEFT
        );

        $_SESSION['otp']['hash']      = password_hash($code, PASSWORD_DEFAULT);
        $_SESSION['otp']['verloopt']  = time() + self::GELDIG_SECONDEN;
        $_SESSION['otp']['pogingen']  = 0;
        $_SESSION['otp']['gemaakt']   = time();

        return $code;
    }

    /**
     * Is er een inlogpoging die op een code wacht?
     */
    public static function bezig(): bool
    {
        Auth::startSessie();

        return isset($_SESSION['otp']['hash']);
    }

    /**
     * De gegevens van de lopende inlogpoging (studentnummer, naam, email).
     */
    public static function gegevens(): ?array
    {
        return self::bezig() ? $_SESSION['otp'] : null;
    }

    /**
     * Mag er al een nieuwe code gestuurd worden? Voorkomt dat iemand
     * de mailbox van een ander volspamt.
     */
    public static function magOpnieuwSturen(): bool
    {
        return self::bezig()
            && time() - (int) $_SESSION['otp']['gemaakt'] >= self::WACHT_SECONDEN;
    }

    /**
     * Controleert de ingevulde code.
     *
     * @return string 'goed', 'fout', 'verlopen' of 'geblokkeerd'
     */
    public static function controleer(string $ingevuld): string
    {
        if (!self::bezig()) {
            return 'verlopen';
        }

        if (time() > (int) $_SESSION['otp']['verloopt']) {
            return 'verlopen';
        }

        if ((int) $_SESSION['otp']['pogingen'] >= self::MAX_POGINGEN) {
            return 'geblokkeerd';
        }

        $ingevuld = preg_replace('/\s+/', '', $ingevuld) ?? '';

        if (preg_match('/^[0-9]{' . self::LENGTE . '}$/', $ingevuld)
            && password_verify($ingevuld, $_SESSION['otp']['hash'])
        ) {
            return 'goed';
        }

        $_SESSION['otp']['pogingen']++;

        return (int) $_SESSION['otp']['pogingen'] >= self::MAX_POGINGEN
            ? 'geblokkeerd'
            : 'fout';
    }

    public static function stop(): void
    {
        Auth::startSessie();
        unset($_SESSION['otp']);
    }

    /**
     * Stuurt de code naar de student. Geeft false terug als de mail
     * niet verstuurd kon worden.
     */
    public static function verstuur(string $email, string $code): bool
    {
        $onderwerp = 'Je inlogcode voor Digikrachtig';
        $tekst     = "Je inlogcode is: {$code}\r\n\r\n"
            . 'De code is ' . (self::GELDIG_SECONDEN / 60) . " minuten geldig.\r\n"
            . "Heb je niet geprobeerd in te loggen? Dan kun je deze mail negeren.\r\n";
        $headers   = "Content-Type: text/plain; charset=utf-8\r\n"
            . 'From: Digikrachtig <digikrachtig@rocrivor.nl>';

        return @mail($email, $onderwerp, $tekst, $headers);
    }
}
