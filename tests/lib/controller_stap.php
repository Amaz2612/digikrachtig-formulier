<?php
/**
 * Hulpscript voor controller_test.php en beheer_test.php: voert een
 * methode van een controller uit in een eigen PHP-proces, omdat de
 * controllers exit aanroepen bij doorsturen en fouten.
 *
 * Invoer (JSON via stdin):
 *   {"controller": "FormulierController" (standaard) of "BeheerController",
 *    "methode": "...", "sessie": {...}, "post": {...}, "get": {...},
 *    "post_verzoek": true (standaard) of false}
 * Uitvoer (JSON): {"status": 200, "uitvoer": "..."}
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$invoer = json_decode(stream_get_contents(STDIN), true);

gebruikTestdatabase();

// De sessie is al gestart in bootstrap.php.
$_SESSION                 = $invoer['sessie'];
$_POST                     = $invoer['post'] ?? [];
$_GET                      = $invoer['get'] ?? [];
$_SERVER['REQUEST_METHOD'] = ($invoer['post_verzoek'] ?? true) ? 'POST' : 'GET';

ob_start();

register_shutdown_function(function () {
    $uitvoer = ob_get_clean();

    // In de CLI houdt PHP geen headers bij, dus de Location-header is
    // niet te zien. De controllers sturen door met exit zonder uitvoer:
    // een lege uitvoer betekent hier dus "doorgestuurd".
    echo json_encode([
        // In de CLI geeft http_response_code() false als er niets is
        // gezet; een webserver stuurt dan gewoon 200.
        'status'  => http_response_code() ?: 200,
        'uitvoer' => $uitvoer,
    ]);
});

$klasse     = $invoer['controller'] ?? 'FormulierController';
$controller = new $klasse(testPdo());
$controller->{$invoer['methode']}();
