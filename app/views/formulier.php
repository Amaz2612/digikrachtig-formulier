<?php
/**
 * Digikrachtig formulierensysteem
 * View: het formulier zelf.
 *
 * Plaats dit bestand in: app/views/formulier.php
 * (vervangt de vorige versie)
 *
 * Voor Rares: de opmaak zit in public/css/stijl.css en het tonen en
 * verbergen in public/js/voorwaarden.js. Voor gewone aanpassingen
 * aan het uiterlijk hoef je dit bestand niet aan te raken.
 *
 * Bij elke voorwaardelijke vraag staan twee attributen:
 *   data-toon-als        code van de vraag waar dit van afhangt
 *   data-toon-als-waarde de antwoorden die dit veld tonen,
 *                        meerdere gescheiden door |
 */
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($formulier['naam']) ?></title>
    <link rel="stylesheet" href="css/stijl.css">
</head>
<body>

<div class="kaart">

    <div class="balk-boven">
        <span class="merk">Digikrachtig Rivierenland</span>
        <div class="gebruiker">
            <span class="studentnummer">
                <?= htmlspecialchars((string) Auth::studentnummer()) ?>
            </span>

            <?php /* Een eigen formuliertje, zodat dit een echte knop
                     is en geen link. Het staat bewust buiten het
                     formulier met de vragen: een formulier in een
                     formulier mag niet en werkt ook niet. */ ?>
            <form method="get">
                <input type="hidden" name="actie" value="uitloggen">
                <button type="submit" class="knop-uitloggen">Uitloggen</button>
            </form>
        </div>
    </div>

    <div class="voortgang-spoor"><span></span></div>
    <small id="voortgang"></small>

    <h1><?= htmlspecialchars($formulier['naam']) ?></h1>

    <?php if (isset($_GET['opgeslagen'])): ?>
        <p class="melding-goed">Je antwoorden zijn tussentijds opgeslagen.
           Je kunt later verder gaan.</p>
    <?php endif; ?>

    <?php if ($fouten !== []): ?>
        <p class="melding-fout">Er zijn <?= count($fouten) ?> velden niet goed
           ingevuld. Ze zijn hieronder gemarkeerd.</p>
    <?php endif; ?>

    <form method="post" action="?actie=opslaan">
        <?= Beveiliging::veld() ?>

        <?php foreach ($structuur as $sectie): ?>

            <section data-sectie="<?= htmlspecialchars($sectie['code'], ENT_QUOTES) ?>">

                <?php if ($sectie['titel'] !== ''): ?>
                    <h2><?= htmlspecialchars($sectie['titel']) ?></h2>
                <?php endif; ?>

                <?php if ($sectie['intro'] !== null): ?>
                    <p><?= nl2br(htmlspecialchars($sectie['intro'])) ?></p>
                <?php endif; ?>

                <?php foreach ($sectie['vragen'] as $vraag): ?>
                    <?php
                    $code   = $vraag['code'];
                    $waarde = $antwoorden[$code] ?? ($vraag['type'] === 'checkbox' ? [] : '');
                    $fout   = $fouten[$code] ?? null;

                    $klassen = [];

                    if ($vraag['is_melding']) {
                        $klassen[] = 'melding';
                    }

                    if ($fout !== null) {
                        $klassen[] = 'fout';
                    }

                    $voorwaarde = '';

                    if ($vraag['toon_als_code'] !== null) {
                        $voorwaarde = ' data-toon-als="'
                            . htmlspecialchars($vraag['toon_als_code'], ENT_QUOTES)
                            . '" data-toon-als-waarde="'
                            . htmlspecialchars(implode('|', $vraag['toon_als_waarden']), ENT_QUOTES)
                            . '"';
                    }
                    ?>

                    <div data-vraag="<?= htmlspecialchars($code, ENT_QUOTES) ?>"<?= $voorwaarde ?>
                         <?= $vraag['verplicht'] ? 'data-verplicht="1"' : '' ?>
                         class="<?= implode(' ', $klassen) ?>">

                        <?php if ($vraag['is_melding']): ?>
                            <p><em><?= htmlspecialchars($vraag['label']) ?></em></p>
                        <?php else: ?>

                            <p>
                                <label for="veld_<?= htmlspecialchars($code, ENT_QUOTES) ?>">
                                    <?= htmlspecialchars($vraag['label']) ?>
                                    <?= $vraag['verplicht'] ? ' *' : '' ?>
                                </label>
                            </p>

                            <?php if ($vraag['help_tekst'] !== null): ?>
                                <p><small><?= htmlspecialchars($vraag['help_tekst']) ?></small></p>
                            <?php endif; ?>

                            <?php if ($fout !== null): ?>
                                <p><strong><?= htmlspecialchars($fout) ?></strong></p>
                            <?php endif; ?>

                            <?php if ($vraag['type'] === 'radio'): ?>

                                <?php foreach ($vraag['opties'] as $optie): ?>
                                    <label>
                                        <input type="radio"
                                               name="<?= htmlspecialchars($code, ENT_QUOTES) ?>"
                                               value="<?= htmlspecialchars($optie, ENT_QUOTES) ?>"
                                               <?= $waarde === $optie ? 'checked' : '' ?>>
                                        <span><?= htmlspecialchars($optie) ?></span>
                                    </label>
                                <?php endforeach; ?>

                            <?php elseif ($vraag['type'] === 'select'): ?>

                                <select id="veld_<?= htmlspecialchars($code, ENT_QUOTES) ?>"
                                        name="<?= htmlspecialchars($code, ENT_QUOTES) ?>">
                                    <option value="">Maak een keuze</option>
                                    <?php foreach ($vraag['opties'] as $optie): ?>
                                        <option value="<?= htmlspecialchars($optie, ENT_QUOTES) ?>"
                                            <?= $waarde === $optie ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($optie) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif ($vraag['type'] === 'checkbox'): ?>

                                <?php foreach ($vraag['opties'] as $optie): ?>
                                    <label>
                                        <input type="checkbox"
                                               name="<?= htmlspecialchars($code, ENT_QUOTES) ?>[]"
                                               value="<?= htmlspecialchars($optie, ENT_QUOTES) ?>"
                                               <?= in_array($optie, (array) $waarde, true) ? 'checked' : '' ?>>
                                        <span><?= htmlspecialchars($optie) ?></span>
                                    </label>
                                <?php endforeach; ?>

                            <?php elseif ($vraag['type'] === 'tekstvak'): ?>

                                <textarea id="veld_<?= htmlspecialchars($code, ENT_QUOTES) ?>"
                                          name="<?= htmlspecialchars($code, ENT_QUOTES) ?>"
                                          rows="4"><?= htmlspecialchars((string) $waarde) ?></textarea>

                            <?php else: ?>
                                <?php
                                $htmlType = match ($vraag['type']) {
                                    'email'    => 'email',
                                    'telefoon' => 'tel',
                                    'getal'    => 'number',
                                    'datum'    => 'date',
                                    default    => 'text',
                                };
                                ?>
                                <input type="<?= $htmlType ?>"
                                       id="veld_<?= htmlspecialchars($code, ENT_QUOTES) ?>"
                                       name="<?= htmlspecialchars($code, ENT_QUOTES) ?>"
                                       value="<?= htmlspecialchars((string) $waarde, ENT_QUOTES) ?>">

                            <?php endif; ?>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </section>

        <?php endforeach; ?>

        <div class="knoppen">
            <button type="submit" name="opslaan" value="1" formnovalidate>Tussentijds opslaan</button>
            <button type="submit" name="verstuur" value="1">Versturen</button>
        </div>
    </form>

</div>

<script src="js/voorwaarden.js"></script>
</body>
</html>
