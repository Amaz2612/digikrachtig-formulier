<?php
/**
 * Digikrachtig formulierensysteem
 * View: inlogpagina (tijdelijk, wordt vervangen door Padgin).
 *
 * Plaats dit bestand in: app/views/login.php
 */
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inloggen - Digikrachtig</title>
    <link rel="stylesheet" href="css/stijl.css">
</head>
<body>

<div class="kaart kaart-smal">

    <span class="merk">Digikrachtig Rivierenland</span>

    <h1>Inloggen</h1>

    <p><small>Tijdelijke login voor het testen. Vul je studentnummer in.</small></p>

    <?php if (!empty($foutmelding)): ?>
        <p class="melding-fout"><?= htmlspecialchars($foutmelding) ?></p>
    <?php endif; ?>

    <form method="post" action="?actie=inloggen">
        <?= Beveiliging::veld() ?>

        <label for="studentnummer">Studentnummer</label>
        <input type="text" id="studentnummer" name="studentnummer"
               inputmode="numeric" placeholder="2100001" required>

        <div class="knoppen">
            <button type="submit">Inloggen</button>
        </div>
    </form>

</div>

</body>
</html>
