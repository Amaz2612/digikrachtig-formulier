<?php
/**
 * Digikrachtig formulierensysteem
 * Beveiliging van formulierverzoeken (CSRF).
 *
 * Plaats dit bestand in: app/Beveiliging.php
 *
 * Wat is CSRF: een andere website kan een verborgen formulier naar
 * jouw site sturen terwijl de student daar nog ingelogd is. De
 * browser stuurt dan gewoon de sessie mee. Met een token in het
 * formulier weet je zeker dat het verzoek van jouw eigen pagina komt.
 */
class Beveiliging
{
    /**
     * Het token voor deze sessie. Wordt eenmalig aangemaakt.
     */
    public static function token(): string
    {
        Auth::startSessie();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Klopt het token dat is meegestuurd?
     * hash_equals vergelijkt zo dat de tijd niets verraadt.
     */
    public static function tokenKlopt(?string $meegestuurd): bool
    {
        Auth::startSessie();

        if (empty($_SESSION['csrf_token']) || $meegestuurd === null) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $meegestuurd);
    }

    /**
     * Kort schrijven in een view: <?= Beveiliging::veld() ?>
     */
    public static function veld(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }
}
