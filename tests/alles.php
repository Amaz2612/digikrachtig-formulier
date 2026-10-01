<?php
/**
 * Draait alle servertests achter elkaar op de testdatabase.
 *
 *   php tests/alles.php            alleen de tests op de testdatabase
 *   php tests/alles.php --http     ook de tests via Apache (CSRF, en de
 *                                  beheerpagina als BEHEER_NAAM en
 *                                  BEHEER_WACHTWOORD gezet zijn)
 *
 * Let op: database_test.php bouwt digikrachtig_test elke keer
 * opnieuw op. De gewone database wordt niet aangeraakt, behalve door
 * --http (die logt in als testgebruiker rv9999001).
 */

declare(strict_types=1);

require __DIR__ . '/database_test.php';
require __DIR__ . '/voorwaarden_test.php';
require __DIR__ . '/opslaan_test.php';
require __DIR__ . '/validatie_test.php';
require __DIR__ . '/controller_test.php';
require __DIR__ . '/beheer_test.php';
require __DIR__ . '/inloggen_test.php';

if (in_array('--http', $argv, true)) {
    $argv = [$argv[0]];
    require __DIR__ . '/http_csrf_test.php';
    require __DIR__ . '/http_beheer_test.php';
}

samenvatting();
