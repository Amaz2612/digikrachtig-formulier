<?php
/**
 * Digikrachtig formulierensysteem
 * View: inlogpagina (tijdelijk, wordt vervangen door Padgin).
 *
 * Plaats dit bestand in: app/views/login.php
 *
 * Zelfde opmaak als de rest van de site: een witte kaart op de
 * lichtblauwe achtergrond met de golven. De smalle variant van de
 * kaart (.kaart-smal) staat al in public/css/stijl.css.
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

    <p><small>Tijdelijke login voor het testen. Vul je studentnummer en je naam in.</small></p>

    <?php if (!empty($foutmelding)): ?>
        <p class="melding-fout"><?= htmlspecialchars($foutmelding) ?></p>
    <?php endif; ?>

    <form method="post" action="?actie=inloggen">
        <?= Beveiliging::veld() ?>

        <label for="studentnummer">Studentnummer</label>
        <input type="text" id="studentnummer" name="studentnummer"
               inputmode="numeric" placeholder="2100001" required
               value="<?= htmlspecialchars((string) ($ingevuldNummer ?? ''), ENT_QUOTES) ?>">

        <label for="naam">Naam</label>
        <input type="text" id="naam" name="naam" maxlength="60"
               autocomplete="name" placeholder="Voor- en achternaam" required
               pattern="[\p{L}\p{M}]+( [\p{L}\p{M}]+)+"
               title="Vul je voor- en achternaam in: alleen letters, minstens twee woorden."
               value="<?= htmlspecialchars((string) ($ingevuldeNaam ?? ''), ENT_QUOTES) ?>">

        <div class="knoppen">
            <button type="submit">Inloggen</button>
        </div>
    </form>

</div>

<script>
/* De naam mag alleen uit letters en spaties bestaan, minstens twee
   woorden. Andere tekens komen er bij het typen niet in. De server
   controleert dit ook (Auth::schoonNaam). */
(function () {
    var veld = document.getElementById('naam');

    if (veld === null) {
        return;
    }

    var MELDING = 'Vul je voor- en achternaam in: alleen letters, minstens twee woorden.';

    function opschonen() {
        veld.value = veld.value
            .replace(/[^\p{L}\p{M} ]/gu, '')   // alleen letters en spaties
            .replace(/^ +/, '')                 // niet beginnen met een spatie
            .replace(/ {2,}/g, ' ');            // niet twee spaties achter elkaar
        veld.setCustomValidity('');
    }

    function controleren() {
        var woorden = veld.value.trim().split(' ').filter(Boolean);

        veld.setCustomValidity(woorden.length < 2 ? MELDING : '');
    }

    veld.addEventListener('input', opschonen);
    veld.addEventListener('change', controleren);
    veld.form.addEventListener('submit', function (gebeurtenis) {
        controleren();

        if (!veld.form.checkValidity()) {
            gebeurtenis.preventDefault();
            veld.form.reportValidity();
        }
    });
}());
</script>

</body>
</html>
