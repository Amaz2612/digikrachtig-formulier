<?php
/**
 * Digikrachtig formulierensysteem
 * View: wachtwoord invullen, voor beheerders.
 *
 * Plaats dit bestand in: app/views/wachtwoord.php
 *
 * Een beheerder logt in op dezelfde inlogpagina als de studenten, maar
 * vult bij Studentnummer zijn gebruikersnaam in. Dan komt hij hier, in
 * plaats van bij de inlogcode per mail (otp.php).
 *
 * Krijgt van public/index.php:
 *   $gebruikersnaam  de ingevulde gebruikersnaam
 *   $foutmelding     optioneel, bij een fout wachtwoord
 */
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wachtwoord - Digikrachtig</title>
    <link rel="stylesheet" href="css/stijl.css?v=<?= (int) @filemtime(__DIR__ . '/../../public/css/stijl.css') ?>">
</head>
<body>

<?php require __DIR__ . '/kop.php'; ?>

<div class="kaart kaart-smal">

    <span class="merk">Digikrachtig Rivierenland</span>

    <h1>Wachtwoord</h1>

    <p><small>Inloggen als beheerder
        <strong><?= htmlspecialchars((string) $gebruikersnaam) ?></strong>.</small></p>

    <?php if (!empty($foutmelding)): ?>
        <p class="melding-fout"><?= htmlspecialchars($foutmelding) ?></p>
    <?php endif; ?>

    <form method="post" action="?actie=wachtwoord">
        <?= Beveiliging::veld() ?>

        <?php /* Verborgen veld met de naam, zodat een wachtwoordmanager
                 weet bij welke gebruiker dit wachtwoord hoort. */ ?>
        <input type="hidden" name="gebruikersnaam" autocomplete="username"
               value="<?= htmlspecialchars((string) $gebruikersnaam, ENT_QUOTES) ?>">

        <label for="wachtwoord">Wachtwoord</label>
        <input type="password" id="wachtwoord" name="wachtwoord"
               maxlength="255" autocomplete="current-password" required autofocus>

        <div class="knoppen">
            <button type="submit">Inloggen</button>
        </div>
    </form>

    <p><small>Ben je student? Dan heb je geen wachtwoord.
        <a href="?actie=login">Log opnieuw in</a> en vul alleen de cijfers
        van je studentnummer in.</small></p>

</div>

</body>
</html>
