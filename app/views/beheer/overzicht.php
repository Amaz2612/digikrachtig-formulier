<?php
/**
 * Digikrachtig formulierensysteem
 * View: overzicht van alle inzendingen.
 *
 * Plaats dit bestand in: app/views/beheer/overzicht.php
 *
 * Krijgt van de controller:
 *   $telling      totaal, concept, ingediend
 *   $inzendingen  rijen uit BeheerModel::inzendingen()
 */
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inzendingen - Digikrachtig beheer</title>
    <link rel="stylesheet" href="css/stijl.css?v=<?= (int) @filemtime(__DIR__ . '/../../../public/css/stijl.css') ?>">
</head>
<body>

<?php require __DIR__ . '/../kop.php'; ?>

<div class="kaart kaart-breed">

    <?php require __DIR__ . '/balk.php'; ?>

    <h1>Inzendingen</h1>

    <div class="cijfers">
        <p><strong><?= (int) $telling['totaal'] ?></strong> totaal</p>
        <p><strong><?= (int) $telling['concept'] ?></strong> concept</p>
        <p><strong><?= (int) $telling['ingediend'] ?></strong> ingediend</p>
    </div>

    <?php if ($inzendingen === []): ?>
        <p>Er zijn nog geen inzendingen.</p>
    <?php else: ?>
        <?php /* De tabel mag op een telefoon zijwaarts scrollen,
                 de pagina zelf niet. */ ?>
        <div class="tabel-scroll">
            <table class="lijst">
                <thead>
                    <tr>
                        <th>Studentnummer</th>
                        <th>Status</th>
                        <th>Gestart op</th>
                        <th>Ingediend op</th>
                        <th>Antwoorden</th>
                        <th><span class="verborgen">Bekijken</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inzendingen as $rij): ?>
                        <tr>
                            <td><?= htmlspecialchars($rij['studentnummer']) ?></td>
                            <td>
                                <span class="status status-<?= htmlspecialchars($rij['status'], ENT_QUOTES) ?>">
                                    <?= htmlspecialchars($rij['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($rij['gestart_op']))) ?></td>
                            <td>
                                <?= $rij['ingediend_op'] === null
                                    ? '-'
                                    : htmlspecialchars(date('d-m-Y H:i', strtotime($rij['ingediend_op']))) ?>
                            </td>
                            <td><?= (int) $rij['aantal_antwoorden'] ?></td>
                            <td>
                                <a href="?actie=detail&amp;id=<?= (int) $rij['id'] ?>">Bekijken</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

</body>
</html>
