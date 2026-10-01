<?php
/**
 * Test D: de database na het draaien van schema.sql en vragenlijst.sql.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

echo "== Database ==\n";

$controle = bouwTestdatabase();
$pdo      = testPdo();

echo 'Controle-uitvoer van vragenlijst.sql: ' . toonWaarde($controle) . "\n\n";

$tel = fn (string $sql) => (int) $pdo->query($sql)->fetchColumn();

test('D1', 'Aantal secties', 13, $tel('SELECT COUNT(*) FROM sections'));
test('D2', 'Aantal rijen in questions (vragen + meldingen)', 83, $tel('SELECT COUNT(*) FROM questions'));
test('D3', 'Aantal meldingen', 7, $tel("SELECT COUNT(*) FROM questions WHERE type = 'melding'"));
test('D4', 'Aantal echte vragen (geen melding)', 76, $tel("SELECT COUNT(*) FROM questions WHERE type <> 'melding'"));
test('D5', 'Aantal rijen met een voorwaarde', 72, $tel('SELECT COUNT(*) FROM questions WHERE toon_als_question_id IS NOT NULL'));
test('D6', 'Voorwaarde zonder waarde of waarde zonder voorwaarde', 0, $tel(
    'SELECT COUNT(*) FROM questions
     WHERE (toon_als_question_id IS NULL) <> (toon_als_waarde IS NULL)'
));

// Alle foreign keys uit schema.sql
$verwachteSleutels = [
    'answers.fk_answers_question -> questions',
    'answers.fk_answers_submission -> form_submissions',
    'form_submissions.fk_submissions_form -> forms',
    'form_submissions.fk_submissions_user -> users',
    'questions.fk_questions_form -> forms',
    'questions.fk_questions_section -> sections',
    'questions.fk_questions_toon_als -> questions',
    'sections.fk_sections_form -> forms',
    'submission_events.fk_events_submission -> form_submissions',
    'user_profiles.fk_profiles_user -> users',
];

$statement = $pdo->prepare(
    "SELECT CONCAT(TABLE_NAME, '.', CONSTRAINT_NAME, ' -> ', REFERENCED_TABLE_NAME)
     FROM information_schema.REFERENTIAL_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = ?
     ORDER BY TABLE_NAME, CONSTRAINT_NAME"
);
$statement->execute([TEST_DB]);

test('D7', 'Alle 10 foreign keys aanwezig (information_schema)', $verwachteSleutels, $statement->fetchAll(PDO::FETCH_COLUMN));

// Elke waarde in toon_als_waarde moet een echte optie van de oudervraag zijn,
// anders kan de vraag nooit zichtbaar worden.
$fouten = [];

foreach ($pdo->query(
    'SELECT q.code, q.toon_als_waarde, ouder.code AS ouder, ouder.opties
     FROM questions q
     JOIN questions ouder ON ouder.id = q.toon_als_question_id'
) as $rij) {
    $opties = json_decode((string) $rij['opties'], true) ?? [];

    foreach (explode('|', $rij['toon_als_waarde']) as $waarde) {
        if (!in_array($waarde, $opties, true)) {
            $fouten[] = $rij['code'] . ' wacht op "' . $waarde . '" bij ' . $rij['ouder'];
        }
    }
}

test('D8', 'Elke voorwaarde verwijst naar een bestaande optie van de oudervraag', [], $fouten);

// Een vraag mag niet afhangen van een vraag die later in het formulier komt.
$statement = $pdo->query(
    'SELECT q.code
     FROM questions q
     JOIN sections s ON s.id = q.section_id
     JOIN questions ouder ON ouder.id = q.toon_als_question_id
     JOIN sections os ON os.id = ouder.section_id
     WHERE (os.volgorde, ouder.volgorde) >= (s.volgorde, q.volgorde)'
);

test('D9', 'Geen vraag hangt af van een vraag die later komt', [], $statement->fetchAll(PDO::FETCH_COLUMN));

// Een student kan maar een inzending per formulier hebben.
$pdo->exec("INSERT INTO users (studentnummer) VALUES ('9000001')");
$gebruiker = (int) $pdo->lastInsertId();
$formulier = (int) $pdo->query('SELECT id FROM forms WHERE is_active = 1')->fetchColumn();
$pdo->prepare('INSERT INTO form_submissions (form_id, user_id) VALUES (?, ?)')->execute([$formulier, $gebruiker]);

try {
    $pdo->prepare('INSERT INTO form_submissions (form_id, user_id) VALUES (?, ?)')->execute([$formulier, $gebruiker]);
    $uitkomst = 'tweede inzending toegestaan';
} catch (PDOException $e) {
    $uitkomst = 'geweigerd (' . $e->errorInfo[1] . ')';
}

test('D10', 'Tweede inzending voor dezelfde student wordt geweigerd', 'geweigerd (1062)', $uitkomst);

// Antwoord bij een vraag die niet bestaat moet de database weigeren.
try {
    $pdo->exec('INSERT INTO answers (submission_id, question_id, waarde) VALUES (999999, 999999, "x")');
    $uitkomst = 'opgeslagen';
} catch (PDOException $e) {
    $uitkomst = 'geweigerd (' . $e->errorInfo[1] . ')';
}

test('D11', 'Antwoord bij niet-bestaande inzending/vraag wordt geweigerd', 'geweigerd (1452)', $uitkomst);

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    samenvatting();
}
