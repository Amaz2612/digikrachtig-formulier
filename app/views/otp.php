<?php
/**
 * Digikrachtig formulierensysteem
 * View: inlogcode (OTP) invullen, de stap na de inlogpagina.
 *
 * Plaats dit bestand in: app/views/otp.php
 *
 * Krijgt van public/index.php:
 *   $poging       gegevens van de lopende inlogpoging (Otp::gegevens)
 *   $foutmelding  optioneel, bij een verkeerde code
 */
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inlogcode - Digikrachtig</title>
    <link rel="stylesheet" href="css/stijl.css?v=<?= (int) @filemtime(__DIR__ . '/../../public/css/stijl.css') ?>">
</head>
<body>

<?php require __DIR__ . '/kop.php'; ?>

<div class="kaart kaart-smal">

    <span class="merk">Digikrachtig Rivierenland</span>

    <h1>Inlogcode</h1>

    <p><small>We hebben een code van 6 cijfers gestuurd naar
        <strong><?= htmlspecialchars($poging['email']) ?></strong>.
        Vul die hieronder in. De code is 10 minuten geldig.</small></p>

    <?php if (!empty($poging['testcode'])): ?>
        <p class="melding-goed">Testmodus (debug staat aan): er is geen mail
            verstuurd. Je code is <strong><?= htmlspecialchars($poging['testcode']) ?></strong>.</p>
    <?php endif; ?>

    <?php if (!empty($poging['mail_mislukt'])): ?>
        <p class="melding-fout">De mail kon niet worden verstuurd. Probeer
            een nieuwe code aan te vragen of neem contact op met je docent.</p>
    <?php endif; ?>

    <?php if (!empty($foutmelding)): ?>
        <p class="melding-fout"><?= htmlspecialchars($foutmelding) ?></p>
    <?php elseif (isset($_GET['opnieuw'])): ?>
        <p class="melding-goed">Er is een nieuwe code verstuurd.</p>
    <?php elseif (isset($_GET['wacht'])): ?>
        <p class="melding-fout">Wacht een minuut voordat je een nieuwe code aanvraagt.</p>
    <?php endif; ?>

    <form method="post" action="?actie=otp">
        <?= Beveiliging::veld() ?>

        <label for="code">Code</label>
        <input type="text" id="code" name="code" maxlength="6"
               inputmode="numeric" autocomplete="one-time-code"
               pattern="[0-9]{6}" placeholder="123456" required autofocus
               title="Vul de 6 cijfers uit de mail in.">

        <div class="knoppen">
            <button type="submit">Bevestigen</button>
        </div>
    </form>

    <form method="post" action="?actie=otp-opnieuw">
        <?= Beveiliging::veld() ?>
        <p><small>Geen mail gekregen? Kijk ook in je spam.
            <button type="submit" class="knop-link">Stuur een nieuwe code</button>
            of <a href="?actie=login">log opnieuw in</a>.</small></p>
    </form>

</div>

<script>
/* Alleen cijfers in het codeveld; plakken van "123 456" werkt ook. */
(function () {
    var veld = document.getElementById('code');

    veld.addEventListener('input', function () {
        veld.value = veld.value.replace(/[^0-9]/g, '').slice(0, 6);
    });
}());
</script>

</body>
</html>
