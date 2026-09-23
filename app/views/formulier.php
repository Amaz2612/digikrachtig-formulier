<?php
/**
 * Digikrachtig formulierensysteem
 * View: het formulier zelf.
 *
 * Plaats dit bestand in: app/views/formulier.php
 *
 * LET OP voor Rares: dit is een kale werkende versie zonder opmaak
 * en zonder JavaScript. Alle 84 regels staan nu tegelijk op het
 * scherm. Bij elke vraag met een voorwaarde staan twee attributen:
 *
 *   data-toon-als        code van de vraag waar dit van afhangt
 *   data-toon-als-waarde de antwoorden die dit veld tonen,
 *                        meerdere gescheiden door |
 *
 * Daarmee kun je het tonen en verbergen in JavaScript regelen
 * zonder de PHP aan te passen.
 */
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($formulier['naam']) ?></title>
</head>
<body>

<p>
    Ingelogd als <?= htmlspecialchars((string) Auth::studentnummer()) ?>
    - <a href="?actie=uitloggen">uitloggen</a>
</p>

<h1><?= htmlspecialchars($formulier['naam']) ?></h1>

<?php if (isset($_GET['opgeslagen'])): ?>
    <p><strong>Je antwoorden zijn tussentijds opgeslagen.</strong></p>
<?php endif; ?>

<?php if ($fouten !== []): ?>
    <p><strong>Er zijn <?= count($fouten) ?> velden niet goed ingevuld.
       Kijk hieronder waar het misgaat.</strong></p>
<?php endif; ?>

<form method="post" action="?actie=opslaan">
    <?= Beveiliging::veld() ?>

    <?php foreach ($structuur as $sectie): ?>

        <?php if ($sectie['titel'] !== ''): ?>
            <h2><?= htmlspecialchars($sectie['titel']) ?></h2>
        <?php endif; ?>

        <?php if ($sectie['intro'] !== null): ?>
            <p><?= nl2br(htmlspecialchars($sectie['intro'])) ?></p>
        <?php endif; ?>

        <?php foreach ($sectie['vragen'] as $vraag): ?>
            <?php
            $code    = $vraag['code'];
            $waarde  = $antwoorden[$code] ?? ($vraag['type'] === 'checkbox' ? [] : '');
            $fout    = $fouten[$code] ?? null;

            $voorwaarde = '';
            if ($vraag['toon_als_code'] !== null) {
                $voorwaarde = ' data-toon-als="'
                    . htmlspecialchars($vraag['toon_als_code'], ENT_QUOTES)
                    . '" data-toon-als-waarde="'
                    . htmlspecialchars(implode('|', $vraag['toon_als_waarden']), ENT_QUOTES)
                    . '"';
            }
            ?>

            <div data-vraag="<?= htmlspecialchars($code, ENT_QUOTES) ?>"<?= $voorwaarde ?>>

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

                        <?php foreach ($vraag['opties'] as $index => $optie): ?>
                            <label>
                                <input type="radio"
                                       name="<?= htmlspecialchars($code, ENT_QUOTES) ?>"
                                       value="<?= htmlspecialchars($optie, ENT_QUOTES) ?>"
                                       <?= $waarde === $optie ? 'checked' : '' ?>>
                                <?= htmlspecialchars($optie) ?>
                            </label><br>
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
                                <?= htmlspecialchars($optie) ?>
                            </label><br>
                        <?php endforeach; ?>

                    <?php elseif ($vraag['type'] === 'tekstvak'): ?>

                        <textarea id="veld_<?= htmlspecialchars($code, ENT_QUOTES) ?>"
                                  name="<?= htmlspecialchars($code, ENT_QUOTES) ?>"
                                  rows="4" cols="60"><?= htmlspecialchars((string) $waarde) ?></textarea>

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
                               value="<?= htmlspecialchars((string) $waarde, ENT_QUOTES) ?>"
                               size="50">

                    <?php endif; ?>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

        <hr>

    <?php endforeach; ?>

    <p>
        <button type="submit" name="opslaan" value="1">Tussentijds opslaan</button>
        <button type="submit" name="verstuur" value="1">Versturen</button>
    </p>
</form>

</body>
</html>
