<?php
/**
 * Digikrachtig formulierensysteem
 * Voorbeeldconfiguratie.
 *
 * Kopieer dit bestand naar config/config.php en vul je eigen
 * gegevens in. config.php staat in .gitignore en komt dus NOOIT
 * in GitHub, want daar staan wachtwoorden in.
 */

return [
    // Database
    'db_host'     => 'localhost',
    'db_naam'     => 'digikrachtig',
    'db_gebruiker' => 'root',
    'db_wachtwoord' => '',

    // Sleutel voor het versleutelen van de gebruikersnaam.
    // Maak een eigen sleutel met: openssl rand -base64 32
    'crypt_sleutel' => 'VUL_HIER_JE_EIGEN_SLEUTEL_IN',

    // Zet op false zodra het project live gaat.
    // Bij true worden foutmeldingen op het scherm getoond.
    'debug' => true,
];
