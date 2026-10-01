<?php
/**
 * Digikrachtig formulierensysteem
 * View: één inzending met alle antwoorden en het logboek.
 *
 * Plaats dit bestand in: app/views/beheer/detail.php
 *
 * Krijgt van de controller:
 *   $inzending  rij uit BeheerModel::inzending()
 *   $secties    per sectie een titel en regels (label, type, waarden)
 *               Welke vragen erin staan, is uitgelegd bij
 *               BeheerController::antwoordenPerSectie().
 *   $logboek    rijen uit submission_events
 *   $gereset    true als de inzending net gereset is
 */
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inzending <?= htmlspecialchars($inzending['studentnummer']) ?> - Digikrachtig beheer</title>
    <link rel="stylesheet" href="css/stijl.css?v=<?= (int) @filemtime(__DIR__ . '/../../../public/css/stijl.css') ?>">
</head>
<body>

<?php require __DIR__ . '/../kop.php'; ?>

<div class="kaart">

    <?php require __DIR__ . '/balk.php'; ?>

    <p><a href="?actie=overzicht">&larr; Terug naar het overzicht</a></p>

    <h1>Inzending van <?= htmlspecialchars($inzending['studentnummer']) ?></h1>

    <?php if ($gereset): ?>
        <p class="melding-goed">De inzending is gereset. De student kan het
           formulier opnieuw invullen.</p>
    <?php endif; ?>

    <table class="gegevens">
        <tr>
            <th>Formulier</th>
            <td><?= htmlspecialchars($inzending['formulier_naam']) ?>
                (versie <?= (int) $inzending['formulier_versie'] ?>)</td>
        </tr>
        <tr>
            <th>Status</th>
            <td>
                <span class="status status-<?= htmlspecialchars($inzending['status'], ENT_QUOTES) ?>">
                    <?= htmlspecialchars($inzending['status']) ?>
                </span>
            </td>
        </tr>
        <tr>
            <th>Gestart op</th>
            <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($inzending['gestart_op']))) ?></td>
        </tr>
        <tr>
            <th>Ingediend op</th>
            <td>
                <?= $inzending['ingediend_op'] === null
                    ? '-'
                    : htmlspecialchars(date('d-m-Y H:i', strtotime($inzending['ingediend_op']))) ?>
            </td>
        </tr>
    </table>

    <?php if ($secties === []): ?>
        <p>Er zijn nog geen antwoorden.</p>
    <?php endif; ?>

    <?php foreach ($secties as $sectie): ?>

        <h2><?= htmlspecialchars($sectie['titel']) ?></h2>

        <?php foreach ($sectie['regels'] as $regel): ?>
            <div class="antwoord">
                <p class="antwoord-vraag"><?= htmlspecialchars($regel['label']) ?></p>

                <?php if ($regel['waarden'] === []): ?>
                    <p class="antwoord-leeg">niet ingevuld</p>
                <?php elseif ($regel['type'] === 'checkbox'): ?>
                    <ul>
                        <?php foreach ($regel['waarden'] as $waarde): ?>
                            <li><?= htmlspecialchars($waarde) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <?php /* nl2br pas na htmlspecialchars, anders wordt
                             de <br> zelf ook omgezet. */ ?>
                    <p><?= nl2br(htmlspecialchars($regel['waarden'][0])) ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

    <?php endforeach; ?>

    <h2>Logboek</h2>

    <?php if ($logboek === []): ?>
        <p>Geen regels in het logboek.</p>
    <?php else: ?>
        <div class="tabel-scroll">
            <table class="lijst">
                <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Gebeurtenis</th>
                        <th>Opmerking</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logboek as $regel): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d-m-Y H:i:s', strtotime($regel['created_at']))) ?></td>
                            <td><?= htmlspecialchars(str_replace('_', ' ', $regel['event_type'])) ?></td>
                            <td><?= htmlspecialchars((string) $regel['opmerking']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php /* Deze knop verandert nog niets: hij opent de pagina met de
             vraag of het echt de bedoeling is. Daarom GET. */ ?>
    <form method="get" class="knoppen">
        <input type="hidden" name="actie" value="reset">
        <input type="hidden" name="id" value="<?= (int) $inzending['id'] ?>">
        <button type="submit" class="knop-gevaar">Inzending resetten</button>
    </form>

</div>

</body>
</html>
