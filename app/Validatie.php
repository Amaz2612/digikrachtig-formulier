<?php
/**
 * Digikrachtig formulierensysteem
 * Validatie van de antwoorden aan de serverkant.
 *
 * Plaats dit bestand in: app/Validatie.php
 *
 * Deze controles staan los van wat de browser doet. JavaScript in
 * de browser is voor het gemak van de gebruiker, deze controle is
 * voor de betrouwbaarheid van de data.
 *
 * Gebruik:
 *   $fouten = Validatie::controleer($vragenPerCode, $antwoorden, true);
 *   if ($fouten === []) { ... opslaan ... }
 *
 * Teruggegeven wordt een array met de code van de vraag als sleutel
 * en de foutmelding als waarde, klaar om bij het veld te tonen.
 */
class Validatie
{
    /** Maximale lengte van een kort tekstveld. */
    private const MAX_TEKST = 255;

    /** Maximale lengte van een groot tekstvak. */
    private const MAX_TEKSTVAK = 5000;

    /**
     * @param array $vragenPerCode uit FormulierModel::vragenPerCode()
     * @param array $antwoorden    code => waarde (array bij checkbox)
     * @param bool  $definitief    true bij versturen, false bij tussentijds
     *                             opslaan. Bij tussentijds opslaan wordt
     *                             niet geklaagd over lege verplichte velden,
     *                             want de student is nog bezig.
     */
    public static function controleer(
        array $vragenPerCode,
        array $antwoorden,
        bool $definitief = true
    ): array {
        $fouten    = [];
        $zichtbaar = Voorwaarden::zichtbareCodes($vragenPerCode, $antwoorden);

        foreach ($zichtbaar as $code) {
            $vraag = $vragenPerCode[$code];

            // Meldingen zijn alleen tekst, daar valt niets te controleren.
            if ($vraag['is_melding']) {
                continue;
            }

            $waarde = $antwoorden[$code] ?? null;
            $leeg   = self::isLeeg($waarde);

            if ($leeg) {
                if ($definitief && $vraag['verplicht']) {
                    $fouten[$code] = 'Deze vraag is verplicht.';
                }

                // Niets ingevuld: verder niets te controleren.
                continue;
            }

            $fout = self::controleerWaarde($vraag, $waarde);

            if ($fout !== null) {
                $fouten[$code] = $fout;
            }
        }

        return $fouten;
    }

    /**
     * Controleert een ingevulde waarde op zijn type.
     * Geeft null terug als er niets mis is.
     */
    private static function controleerWaarde(array $vraag, $waarde): ?string
    {
        // Checkbox: meerdere waarden, allemaal uit de lijst met opties.
        if ($vraag['type'] === 'checkbox') {
            if (!is_array($waarde)) {
                return 'Ongeldige keuze.';
            }

            foreach ($waarde as $enkele) {
                if (!in_array((string) $enkele, $vraag['opties'], true)) {
                    return 'Ongeldige keuze.';
                }
            }

            return null;
        }

        // Alle andere types verwachten een enkele waarde.
        if (is_array($waarde)) {
            return 'Ongeldig antwoord.';
        }

        $tekst = trim((string) $waarde);

        switch ($vraag['type']) {
            case 'radio':
            case 'select':
                // De waarde moet echt een van de aangeboden opties zijn.
                // Zo kan niemand via een aangepaste pagina iets anders
                // in de database krijgen.
                if (!in_array($tekst, $vraag['opties'], true)) {
                    return 'Kies een van de gegeven antwoorden.';
                }
                break;

            case 'email':
                if (!filter_var($tekst, FILTER_VALIDATE_EMAIL)) {
                    return 'Vul een geldig e-mailadres in.';
                }

                if (mb_strlen($tekst) > self::MAX_TEKST) {
                    return 'Dit e-mailadres is te lang.';
                }
                break;

            case 'telefoon':
                // Bewust ruim: +31, spaties, haakjes en streepjes mogen.
                if (!preg_match('/^[0-9 +()\-]{6,20}$/', $tekst)) {
                    return 'Vul een geldig telefoonnummer in.';
                }
                break;

            case 'getal':
                if (!is_numeric($tekst)) {
                    return 'Vul een getal in.';
                }
                break;

            case 'datum':
                if (!self::isDatum($tekst)) {
                    return 'Vul een geldige datum in (jjjj-mm-dd).';
                }
                break;

            case 'tekstvak':
                if (mb_strlen($tekst) > self::MAX_TEKSTVAK) {
                    return 'Dit antwoord is te lang (maximaal '
                        . self::MAX_TEKSTVAK . ' tekens).';
                }
                break;

            case 'tekst':
            default:
                if (mb_strlen($tekst) > self::MAX_TEKST) {
                    return 'Dit antwoord is te lang (maximaal '
                        . self::MAX_TEKST . ' tekens).';
                }
                break;
        }

        return null;
    }

    /**
     * Is er niets ingevuld? Een lege array, een lege tekst of alleen
     * spaties tellen allemaal als leeg.
     */
    private static function isLeeg($waarde): bool
    {
        if ($waarde === null) {
            return true;
        }

        if (is_array($waarde)) {
            foreach ($waarde as $enkele) {
                if (trim((string) $enkele) !== '') {
                    return false;
                }
            }

            return true;
        }

        return trim((string) $waarde) === '';
    }

    /**
     * Controleert of de tekst een echte datum is in jjjj-mm-dd.
     * 2026-02-31 is niet geldig, ook al ziet het er goed uit.
     */
    private static function isDatum(string $tekst): bool
    {
        $datum = DateTime::createFromFormat('Y-m-d', $tekst);

        return $datum !== false && $datum->format('Y-m-d') === $tekst;
    }
}
