<?php
/**
 * Digikrachtig formulierensysteem
 * Bepaalt welke vragen zichtbaar zijn bij de gegeven antwoorden.
 *
 * Plaats dit bestand in: app/Voorwaarden.php
 *
 * Waarom dit bestaat:
 * In de browser laat JavaScript vragen verschijnen en verdwijnen.
 * Maar wat de browser opstuurt mag je nooit vertrouwen: iemand kan
 * de pagina aanpassen of zelf een verzoek versturen. Daarom rekenen
 * we hier op de server opnieuw uit welke vragen zichtbaar hadden
 * moeten zijn. Alleen die vragen worden opgeslagen en gecontroleerd.
 */
class Voorwaarden
{
    /**
     * Is een vraag zichtbaar bij deze antwoorden?
     *
     * @param string $code           code van de vraag
     * @param array  $vragenPerCode  alle vragen, met hun code als sleutel
     * @param array  $antwoorden     ingevulde antwoorden, code => waarde
     *                               (bij een checkbox is de waarde een array)
     */
    public static function isZichtbaar(
        string $code,
        array $vragenPerCode,
        array $antwoorden
    ): bool {
        return self::bepaal($code, $vragenPerCode, $antwoorden, []);
    }

    /**
     * Alle codes van vragen die nu zichtbaar zijn.
     */
    public static function zichtbareCodes(
        array $vragenPerCode,
        array $antwoorden
    ): array {
        $codes = [];

        foreach ($vragenPerCode as $code => $vraag) {
            if (self::isZichtbaar((string) $code, $vragenPerCode, $antwoorden)) {
                $codes[] = (string) $code;
            }
        }

        return $codes;
    }

    /**
     * Het echte rekenwerk.
     *
     * Een vraag is zichtbaar als:
     *   1. hij geen voorwaarde heeft, OF
     *   2. de vraag waar hij van afhangt zelf zichtbaar is, EN
     *      het antwoord daarop een van de toegestane waarden is.
     *
     * Punt 2 is belangrijk voor ketens. Bij de website hangt
     * website_hulp af van website_interesse, en die weer van
     * website_heeft. Zegt iemand dat hij wel een website heeft, dan
     * verdwijnen die twee allebei, ook al staat er nog een antwoord.
     *
     * @param array $bezocht  onthoudt welke codes we al aan het
     *                        uitrekenen zijn, zodat een verkeerd
     *                        ingevoerde voorwaarde die naar zichzelf
     *                        wijst niet tot een oneindige lus leidt
     */
    private static function bepaal(
        string $code,
        array $vragenPerCode,
        array $antwoorden,
        array $bezocht
    ): bool {
        // Vraag bestaat niet: dan is er ook niets te tonen.
        if (!isset($vragenPerCode[$code])) {
            return false;
        }

        // Rondje in de voorwaarden: veiligheidshalve verbergen.
        if (isset($bezocht[$code])) {
            return false;
        }

        $bezocht[$code] = true;

        $vraag      = $vragenPerCode[$code];
        $ouderCode  = $vraag['toon_als_code'] ?? null;

        // Geen voorwaarde: altijd zichtbaar.
        if ($ouderCode === null) {
            return true;
        }

        // De vraag waar dit van afhangt moet zelf ook zichtbaar zijn.
        if (!self::bepaal($ouderCode, $vragenPerCode, $antwoorden, $bezocht)) {
            return false;
        }

        $gegeven   = $antwoorden[$ouderCode] ?? null;
        $toegestaan = $vraag['toon_als_waarden'] ?? [];

        if ($gegeven === null || $toegestaan === []) {
            return false;
        }

        // Bij een checkbox is het antwoord een array. Dan is de
        // voorwaarde waar als een van de aangevinkte waarden erbij zit.
        if (is_array($gegeven)) {
            foreach ($gegeven as $waarde) {
                if (in_array($waarde, $toegestaan, true)) {
                    return true;
                }
            }

            return false;
        }

        return in_array($gegeven, $toegestaan, true);
    }
}
