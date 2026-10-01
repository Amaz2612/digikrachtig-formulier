<?php
/**
 * Digikrachtig formulierensysteem
 * View-onderdeel: de balk bovenin de kaart op de beheerpagina.
 *
 * Plaats dit bestand in: app/views/beheer/balk.php
 *
 * Zelfde opbouw als de balk boven het formulier van de student
 * (.balk-boven uit public/css/stijl.css). Uitloggen gaat hier via POST
 * met een CSRF-token, omdat het iets verandert.
 */
?>
<div class="balk-boven">
    <span class="merk">Digikrachtig beheer</span>

    <div class="gebruiker">
        <span class="studentnaam">
            <?= htmlspecialchars((string) BeheerAuth::gebruikersnaam()) ?>
        </span>

        <form method="post" action="?actie=uitloggen">
            <?= Beveiliging::veld() ?>
            <button type="submit" class="knop-uitloggen">Uitloggen</button>
        </form>
    </div>
</div>
