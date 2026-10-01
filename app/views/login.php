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
 *
 * Ook beheerders loggen hier in: zij vullen bij Studentnummer hun
 * gebruikersnaam in. Naam en e-mail zijn dan niet nodig; het script
 * onderaan verbergt die velden zodra er geen nummer staat. Daarom
 * staat 'required' niet in de HTML maar wordt het door het script
 * gezet. Staat JavaScript uit, dan controleert de server het toch.
 */
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inloggen - Digikrachtig</title>
    <link rel="stylesheet" href="css/stijl.css?v=<?= (int) @filemtime(__DIR__ . '/../../public/css/stijl.css') ?>">
</head>
<body>

<?php require __DIR__ . '/kop.php'; ?>

<div class="kaart kaart-smal">

    <span class="merk">Digikrachtig Rivierenland</span>

    <h1>Inloggen</h1>

    <p><small>Tijdelijke login voor het testen. Vul je studentnummer, naam en e-mail in.</small></p>

    <?php if (!empty($foutmelding)): ?>
        <p class="melding-fout"><?= htmlspecialchars($foutmelding) ?></p>
    <?php endif; ?>

    <form method="post" action="?actie=inloggen">
        <?= Beveiliging::veld() ?>

        <?php /* 'rv' staat vast voor het veld; de student typt alleen de
                 cijfers. Komt het nummer terug van de server (met rv),
                 dan halen we rv eraf, anders staat het er twee keer. */ ?>
        <label for="studentnummer">Studentnummer</label>
        <div class="veld-voorvoegsel">
            <span aria-hidden="true">rv</span>
            <input type="text" id="studentnummer" name="studentnummer"
                   maxlength="50" autocomplete="username" placeholder="2100001" required
                   value="<?= htmlspecialchars(preg_replace('/^rv/i', '', (string) ($ingevuldNummer ?? '')), ENT_QUOTES) ?>">
        </div>

        <div id="studentvelden">
            <label for="naam">Naam</label>
            <input type="text" id="naam" name="naam" maxlength="60"
                   autocomplete="name" placeholder="Voor- en achternaam"
                   pattern="[\p{L}\p{M}]+( [\p{L}\p{M}]+)+"
                   title="Vul je voor- en achternaam in: alleen letters, minstens twee woorden."
                   value="<?= htmlspecialchars((string) ($ingevuldeNaam ?? ''), ENT_QUOTES) ?>">

            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" maxlength="255"
                   autocomplete="email" placeholder="naam@voorbeeld.nl"
                   value="<?= htmlspecialchars((string) ($ingevuldeEmail ?? ''), ENT_QUOTES) ?>">
        </div>

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
    var veld          = document.getElementById('naam');
    var email         = document.getElementById('email');
    var nummer        = document.getElementById('studentnummer');
    var studentvelden = document.getElementById('studentvelden');

    if (veld === null) {
        return;
    }

    var MELDING = 'Vul je voor- en achternaam in: alleen letters, minstens twee woorden.';

    /* Staat er bij Studentnummer iets anders dan cijfers (met of zonder
       rv), dan is het een beheerder. Die heeft naam en e-mail niet
       nodig. Dezelfde keuze maakt de server in public/index.php. */
    function isBeheerder() {
        var waarde = nummer.value.trim();

        return waarde !== '' && !/^(rv)?[0-9]*$/i.test(waarde);
    }

    function wissel() {
        var beheerder = isBeheerder();

        studentvelden.hidden = beheerder;
        veld.required        = !beheerder;
        email.required       = !beheerder;

        if (beheerder) {
            veld.setCustomValidity('');
        }
    }

    nummer.addEventListener('input', wissel);
    wissel();

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
        if (!isBeheerder()) {
            controleren();
        }

        if (!veld.form.checkValidity()) {
            gebeurtenis.preventDefault();
            veld.form.reportValidity();
        }
    });
}());
</script>

</body>
</html>
