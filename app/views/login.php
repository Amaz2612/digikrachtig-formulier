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
</head>
<body>
    <h1>Inloggen</h1>

    <p>Tijdelijke login voor het testen. Vul een studentnummer in.</p>

    <?php if (!empty($foutmelding)): ?>
        <p><strong><?= htmlspecialchars($foutmelding) ?></strong></p>
    <?php endif; ?>

    <form method="post" action="?actie=inloggen">
        <?= Beveiliging::veld() ?>

        <label for="studentnummer">Studentnummer</label><br>
        <input type="text" id="studentnummer" name="studentnummer"
               inputmode="numeric" required><br><br>

        <button type="submit">Inloggen</button>
    </form>
</body>
</html>
