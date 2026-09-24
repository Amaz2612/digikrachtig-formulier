<?php
/**
 * Digikrachtig formulierensysteem
 * View: bedankpagina na het versturen.
 *
 * Plaats dit bestand in: app/views/klaar.php
 *
 * Zelfde opmaak als de inlogpagina: de smalle variant van de kaart
 * (.kaart-smal) uit public/css/stijl.css. Deze pagina komt ook in
 * beeld als iemand die al heeft ingediend het formulier opnieuw
 * opent, dus hij moet los van het versturen te lezen zijn.
 */
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bedankt - Digikrachtig</title>
    <link rel="stylesheet" href="css/stijl.css">
</head>
<body>

<div class="kaart kaart-smal">

    <span class="merk">Digikrachtig Rivierenland</span>

    <h1>Hartelijk dank voor het invullen van de vragenlijst!</h1>

    <p class="melding-goed">Je antwoorden zijn verstuurd en opgeslagen.
       Je kunt het formulier niet nog een keer invullen.</p>

    <p><small>Hulp nodig met digitale vraagstukken? Ga naar
       <a href="https://www.digikrachtig.nl">www.digikrachtig.nl</a> en
       stel je vraag bij 'Stel je vraag', of mail naar
       <a href="mailto:digikrachtig@rocrivor.nl">digikrachtig@rocrivor.nl</a>.</small></p>

    <?php /* Net als in de balk boven het formulier: een echte knop,
             en daarvoor is een eigen formuliertje nodig. */ ?>
    <form method="get" class="knoppen">
        <input type="hidden" name="actie" value="uitloggen">
        <button type="submit">Uitloggen</button>
    </form>

</div>

</body>
</html>
