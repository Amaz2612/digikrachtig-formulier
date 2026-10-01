<?php
/**
 * Digikrachtig formulierensysteem
 * View: bevestigen voordat een inzending gereset wordt.
 *
 * Plaats dit bestand in: app/views/beheer/reset.php
 *
 * Pas de knop op deze pagina doet echt iets: een POST met CSRF-token
 * naar ?actie=reset.
 */
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inzending resetten - Digikrachtig beheer</title>
    <link rel="stylesheet" href="css/stijl.css?v=<?= (int) @filemtime(__DIR__ . '/../../../public/css/stijl.css') ?>">
</head>
<body>

<?php require __DIR__ . '/../kop.php'; ?>

<div class="kaart kaart-smal">

    <span class="merk">Digikrachtig beheer</span>

    <h1>Inzending resetten?</h1>

    <p class="melding-fout">Alle antwoorden van student
       <strong><?= htmlspecialchars($inzending['studentnummer']) ?></strong>
       worden verwijderd en de status gaat terug naar concept. Dit kun je
       niet ongedaan maken.</p>

    <form method="post" action="?actie=reset" class="knoppen">
        <?= Beveiliging::veld() ?>
        <input type="hidden" name="id" value="<?= (int) $inzending['id'] ?>">

        <a href="?actie=detail&amp;id=<?= (int) $inzending['id'] ?>">Annuleren</a>
        <button type="submit" class="knop-gevaar">Ja, resetten</button>
    </form>

</div>

</body>
</html>
