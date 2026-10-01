<?php
/**
 * Test O: opslaan en teruglezen van antwoorden (app/models/InzendingModel.php).
 *
 * Verwacht dat database_test.php eerst gedraaid is.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

echo "\n== Opslaan ==\n";

$pdo       = testPdo();
$formModel = new FormulierModel($pdo);
$model     = new InzendingModel($pdo);

[$formulierId, , $inzending] = nieuweInzending('9100001');
$vragen = $formModel->vragenPerCode($formulierId);

$basis = [
    'naam_bedrijf'  => 'Testbedrijf BV',
    'ingevuld_door' => 'Jan Jansen',
    'functie'       => 'Directeur',
    'email'         => 'jan@testbedrijf.nl',
    'm365_gebruik'  => 'nee',
    'website_heeft' => 'ja',
];

// O1: antwoorden op verborgen vragen worden niet opgeslagen
$model->slaAntwoordenOp($inzending, $vragen, $basis + [
    'website_interesse' => 'ja',
    'website_hulp'      => 'ja',
    'word_gebruik'      => 'ja',   // verborgen, want m365 = nee
    'word_niveau'       => 'goed',
]);
$opgeslagen = $model->antwoorden($inzending);

test('O1', 'Verborgen vragen (website_interesse, website_hulp, word_gebruik, word_niveau) niet opgeslagen',
    [false, false, false, false],
    [
        isset($opgeslagen['website_interesse']),
        isset($opgeslagen['website_hulp']),
        isset($opgeslagen['word_gebruik']),
        isset($opgeslagen['word_niveau']),
    ]);

// O2: eerst zichtbaar en opgeslagen, daarna verborgen geraakt
$model->slaAntwoordenOp($inzending, $vragen, array_merge($basis, [
    'website_heeft' => 'nee', 'website_interesse' => 'ja', 'website_hulp' => 'ja',
]));
$voor = $model->antwoorden($inzending);

$model->slaAntwoordenOp($inzending, $vragen, array_merge($basis, [
    'website_heeft' => 'ja', 'website_interesse' => 'ja', 'website_hulp' => 'ja',
]));
$na = $model->antwoorden($inzending);

test('O2', 'Keten: website_hulp eerst opgeslagen, na website_heeft=ja verdwenen uit de database',
    ['voor' => ['ja', 'ja'], 'na' => [null, null]],
    [
        'voor' => [$voor['website_interesse'] ?? null, $voor['website_hulp'] ?? null],
        'na'   => [$na['website_interesse'] ?? null, $na['website_hulp'] ?? null],
    ]);

// O3 en O4: meldingen en verzonnen velden
$model->slaAntwoordenOp($inzending, $vragen, $basis + [
    'website_melding_heeft' => 'hack',
    'bestaat_niet'          => 'hack',
]);
$opgeslagen = $model->antwoorden($inzending);

test('O3', 'Antwoord bij een melding (website_melding_heeft) niet opgeslagen', false, isset($opgeslagen['website_melding_heeft']));
test('O4', 'Veld dat niet bij een vraag hoort (bestaat_niet) niet opgeslagen', false, isset($opgeslagen['bestaat_niet']));

// O5: checkbox met meerdere antwoorden
$vinkjes = [
    'Bestanden en mappen aanmaken, kopiëren, verplaatsen en verwijderen.',
    'AI in het algemeen',
    'Anders, namelijk:',
];
$model->slaAntwoordenOp($inzending, $vragen, $basis + [
    'kenniscafe_interesse' => $vinkjes,
    'kenniscafe_anders'    => 'Cursus 3D-printen',
]);

$rijen = $pdo->prepare(
    "SELECT COUNT(*) FROM answers a JOIN questions q ON q.id = a.question_id
     WHERE a.submission_id = ? AND q.code = 'kenniscafe_interesse'"
);
$rijen->execute([$inzending]);
$opgeslagen = $model->antwoorden($inzending);

test('O5', 'Checkbox met 3 vinkjes geeft 3 rijen in answers', 3, (int) $rijen->fetchColumn());
test('O6', 'Checkbox teruggelezen als dezelfde lijst (ook ë goed bewaard)', $vinkjes, $opgeslagen['kenniscafe_interesse'] ?? null);
test('O7', 'Vervolgvraag op de checkbox (kenniscafe_anders) opgeslagen', 'Cursus 3D-printen', $opgeslagen['kenniscafe_anders'] ?? null);

// O8: een vinkje wordt teruggelezen als lijst, niet als losse tekst
$model->slaAntwoordenOp($inzending, $vragen, $basis + ['kenniscafe_interesse' => ['AI in het algemeen']]);
test('O8', 'Checkbox met 1 vinkje teruggelezen als lijst met 1 waarde', ['AI in het algemeen'], $model->antwoorden($inzending)['kenniscafe_interesse'] ?? null);

// O9: tweemaal hetzelfde vinkje meesturen (kan alleen met een aangepast verzoek)
$model->slaAntwoordenOp($inzending, $vragen, $basis + ['kenniscafe_interesse' => ['AI in het algemeen', 'AI in het algemeen']]);
test('O9', 'Hetzelfde vinkje twee keer meegestuurd: maar een keer opgeslagen', ['AI in het algemeen'], $model->antwoorden($inzending)['kenniscafe_interesse'] ?? null);

// O10: spaties
$model->slaAntwoordenOp($inzending, $vragen, array_merge($basis, ['functie' => '   ', 'naam_bedrijf' => '  Testbedrijf BV  ']));
$opgeslagen = $model->antwoorden($inzending);
test('O10', 'Alleen spaties wordt niet opgeslagen, spaties rondom worden weggehaald',
    [false, 'Testbedrijf BV'],
    [isset($opgeslagen['functie']), $opgeslagen['naam_bedrijf'] ?? null]);

// O11: opnieuw opslaan vervangt, er komen geen dubbele rijen bij
$model->slaAntwoordenOp($inzending, $vragen, $basis);
$model->slaAntwoordenOp($inzending, $vragen, $basis);
$telling = $pdo->prepare('SELECT COUNT(*) FROM answers WHERE submission_id = ?');
$telling->execute([$inzending]);
test('O11', 'Twee keer hetzelfde opslaan geeft 6 rijen, niet 12', 6, (int) $telling->fetchColumn());

// O12: gaat er halverwege iets mis, dan blijven de oude antwoorden staan
$kapot = $vragen;
$kapot['functie']['id'] = 99999999; // bestaat niet, foreign key faalt

try {
    $model->slaAntwoordenOp($inzending, $kapot, array_merge($basis, ['naam_bedrijf' => 'Nieuwe naam']));
    $fout = 'geen fout';
} catch (PDOException $e) {
    $fout = 'fout gegooid';
}

test('O12', 'Fout tijdens opslaan: transactie teruggedraaid, oude antwoorden staan er nog',
    ['fout gegooid', 'Testbedrijf BV', 6],
    [$fout, $model->antwoorden($inzending)['naam_bedrijf'] ?? null, (function () use ($telling, $inzending) {
        $telling->execute([$inzending]);
        return (int) $telling->fetchColumn();
    })()]);

// O13: logboek
$events = $pdo->prepare('SELECT COUNT(*) FROM submission_events WHERE submission_id = ? AND event_type = ?');
$events->execute([$inzending, 'opgeslagen']);
$voor = (int) $events->fetchColumn();
$model->slaAntwoordenOp($inzending, $vragen, $basis, false);
$events->execute([$inzending, 'opgeslagen']);
$naZonder = (int) $events->fetchColumn();
$model->slaAntwoordenOp($inzending, $vragen, $basis, true);
$events->execute([$inzending, 'opgeslagen']);
$naMet = (int) $events->fetchColumn();

test('O13', 'Opslaan met log=false schrijft niets in het logboek, met log=true wel een regel',
    [0, 1], [$naZonder - $voor, $naMet - $naZonder]);

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    samenvatting();
}
