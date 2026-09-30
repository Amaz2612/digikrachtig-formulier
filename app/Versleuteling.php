<?php
/**
 * Digikrachtig formulierensysteem
 * Versleuteling van de naam van de student.
 *
 * Plaats dit bestand in: app/Versleuteling.php
 *
 * De naam wordt versleuteld opgeslagen in user_profiles
 * (username_enc + username_iv). Methode: AES-256-GCM. GCM voegt een
 * controlegetal (tag, 16 bytes) toe; dat plakken we achter de
 * versleutelde tekst, zodat het in de ene kolom past.
 *
 * De sleutel staat in config/config.php onder 'crypt_sleutel' en is
 * 32 willekeurige bytes in base64. Raak je die sleutel kwijt, dan zijn
 * de opgeslagen namen niet meer te lezen.
 */
class Versleuteling
{
    private const METHODE   = 'aes-256-gcm';
    private const TAG_LENGTE = 16;

    /**
     * @return array{enc: string, iv: string}
     */
    public static function versleutel(string $tekst, string $sleutel): array
    {
        $iv = random_bytes(12);
        $tag = '';

        $versleuteld = openssl_encrypt(
            $tekst,
            self::METHODE,
            self::sleutelBytes($sleutel),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTE
        );

        if ($versleuteld === false) {
            throw new RuntimeException('Versleutelen mislukt.');
        }

        return ['enc' => $versleuteld . $tag, 'iv' => $iv];
    }

    /**
     * Geeft null terug als het niet te ontsleutelen is (verkeerde
     * sleutel of aangepaste data).
     */
    public static function ontsleutel(string $enc, string $iv, string $sleutel): ?string
    {
        if (strlen($enc) <= self::TAG_LENGTE) {
            return null;
        }

        $tekst = openssl_decrypt(
            substr($enc, 0, -self::TAG_LENGTE),
            self::METHODE,
            self::sleutelBytes($sleutel),
            OPENSSL_RAW_DATA,
            $iv,
            substr($enc, -self::TAG_LENGTE)
        );

        return $tekst === false ? null : $tekst;
    }

    private static function sleutelBytes(string $sleutel): string
    {
        $bytes = base64_decode($sleutel, true);

        if ($bytes === false || strlen($bytes) !== 32) {
            throw new RuntimeException(
                'De crypt_sleutel in config/config.php is ongeldig. '
                . 'Maak er een met: openssl rand -base64 32'
            );
        }

        return $bytes;
    }
}
