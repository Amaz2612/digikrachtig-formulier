<?php
/**
 * Digikrachtig formulierensysteem
 * View: de gevraagde inzending bestaat niet.
 *
 * Plaats dit bestand in: app/views/beheer/niet-gevonden.php
 */
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Niet gevonden - Digikrachtig beheer</title>
    <link rel="stylesheet" href="css/stijl.css?v=<?= (int) @filemtime(__DIR__ . '/../../../public/css/stijl.css') ?>">
</head>
<body>

<?php require __DIR__ . '/../kop.php'; ?>

<div class="kaart kaart-smal">

    <span class="merk">Digikrachtig beheer</span>

    <h1>Inzending niet gevonden</h1>

    <p>Deze inzending bestaat niet (meer).</p>

    <p><a href="?actie=overzicht">&larr; Terug naar het overzicht</a></p>

</div>

</body>
</html>
