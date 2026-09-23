<?php
/**
 * Digikrachtig formulierensysteem
 * Model: inzendingen en antwoorden.
 *
 * Plaats dit bestand in: app/models/InzendingModel.php
 *
 * Een inzending heeft twee statussen:
 *   concept    - de student is nog bezig, hij kan later verder
 *   ingediend  - definitief verstuurd
 */
class InzendingModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * De inzending van deze student voor dit formulier.
     * Elke student heeft er maximaal een (unique key in de database).
     */
    public function zoek(int $formulierId, int $gebruikerId): ?array
    {
        $sql = 'SELECT id, form_id, user_id, status, gestart_op, ingediend_op
                FROM form_submissions
                WHERE form_id = :form_id
                  AND user_id = :user_id';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'form_id' => $formulierId,
            'user_id' => $gebruikerId,
        ]);

        $rij = $statement->fetch();

        return $rij === false ? null : $rij;
    }

    /**
     * Haalt de inzending op, of maakt hem aan als hij nog niet bestaat.
     */
    public function haalOfMaak(int $formulierId, int $gebruikerId): array
    {
        $bestaande = $this->zoek($formulierId, $gebruikerId);

        if ($bestaande !== null) {
            return $bestaande;
        }

        $sql = 'INSERT INTO form_submissions (form_id, user_id, status)
                VALUES (:form_id, :user_id, \'concept\')';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'form_id' => $formulierId,
            'user_id' => $gebruikerId,
        ]);

        $this->logGebeurtenis(
            (int) $this->pdo->lastInsertId(),
            'aangemaakt'
        );

        return $this->zoek($formulierId, $gebruikerId);
    }

    /**
     * De opgeslagen antwoorden van een inzending, met de code van de
     * vraag als sleutel. Bij een checkbox is de waarde een array.
     */
    public function antwoorden(int $inzendingId): array
    {
        $sql = 'SELECT q.code, q.type, a.waarde
                FROM answers a
                JOIN questions q ON q.id = a.question_id
                WHERE a.submission_id = :inzending_id
                ORDER BY a.id';

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['inzending_id' => $inzendingId]);

        $antwoorden = [];

        foreach ($statement->fetchAll() as $rij) {
            if ($rij['type'] === 'checkbox') {
                $antwoorden[$rij['code']][] = $rij['waarde'];
            } else {
                $antwoorden[$rij['code']] = $rij['waarde'];
            }
        }

        return $antwoorden;
    }

    /**
     * Slaat de antwoorden op.
     *
     * Werkwijze: eerst alle antwoorden van deze inzending weggooien,
     * daarna opnieuw invoegen. Dat is eenvoudiger dan per antwoord
     * kijken of hij al bestaat, en het werkt ook voor checkboxen
     * waar meerdere rijen bij horen.
     *
     * Er worden alleen antwoorden opgeslagen die:
     *   - bij een bestaande vraag horen
     *   - geen melding zijn (die hebben geen invulveld)
     *   - op dit moment zichtbaar zijn volgens de voorwaarden
     *
     * Alles zit in een transactie. Gaat er halverwege iets mis, dan
     * wordt niets opgeslagen en blijven de oude antwoorden staan.
     *
     * @param array $vragenPerCode  uit FormulierModel::vragenPerCode()
     * @param array $antwoorden     code => waarde (of array bij checkbox)
     * @return int                  aantal opgeslagen antwoordregels
     */
    public function slaAntwoordenOp(
        int $inzendingId,
        array $vragenPerCode,
        array $antwoorden
    ): int {
        $zichtbaar = Voorwaarden::zichtbareCodes($vragenPerCode, $antwoorden);
        $aantal    = 0;

        $this->pdo->beginTransaction();

        try {
            $verwijder = $this->pdo->prepare(
                'DELETE FROM answers WHERE submission_id = :inzending_id'
            );
            $verwijder->execute(['inzending_id' => $inzendingId]);

            $voegToe = $this->pdo->prepare(
                'INSERT INTO answers (submission_id, question_id, waarde)
                 VALUES (:inzending_id, :question_id, :waarde)'
            );

            foreach ($antwoorden as $code => $waarde) {
                // Onbekende vraag: overslaan.
                if (!isset($vragenPerCode[$code])) {
                    continue;
                }

                $vraag = $vragenPerCode[$code];

                // Meldingen hebben geen antwoord.
                if ($vraag['is_melding']) {
                    continue;
                }

                // Niet zichtbaar: niet opslaan. Dit voorkomt antwoorden
                // op vragen die de student nooit had mogen zien.
                if (!in_array((string) $code, $zichtbaar, true)) {
                    continue;
                }

                // Checkbox: een rij per aangevinkte waarde.
                $waarden = is_array($waarde) ? $waarde : [$waarde];

                foreach ($waarden as $enkeleWaarde) {
                    $tekst = trim((string) $enkeleWaarde);

                    // Lege velden slaan we niet op.
                    if ($tekst === '') {
                        continue;
                    }

                    $voegToe->execute([
                        'inzending_id' => $inzendingId,
                        'question_id'  => $vraag['id'],
                        'waarde'       => $tekst,
                    ]);

                    $aantal++;
                }
            }

            $this->logGebeurtenis(
                $inzendingId,
                'opgeslagen',
                $aantal . ' antwoorden'
            );

            $this->pdo->commit();
        } catch (Throwable $fout) {
            $this->pdo->rollBack();
            throw $fout;
        }

        return $aantal;
    }

    /**
     * Zet de inzending op definitief.
     */
    public function dienIn(int $inzendingId): void
    {
        $sql = 'UPDATE form_submissions
                SET status = \'ingediend\',
                    ingediend_op = NOW()
                WHERE id = :inzending_id';

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['inzending_id' => $inzendingId]);

        $this->logGebeurtenis($inzendingId, 'ingediend');
    }

    /**
     * Schrijft een regel in het logboek van de inzending.
     * Types: aangemaakt, opgeslagen, validatie_mislukt, ingediend.
     */
    public function logGebeurtenis(
        int $inzendingId,
        string $type,
        ?string $opmerking = null
    ): void {
        $sql = 'INSERT INTO submission_events
                    (submission_id, event_type, opmerking)
                VALUES (:inzending_id, :type, :opmerking)';

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'inzending_id' => $inzendingId,
            'type'         => $type,
            'opmerking'    => $opmerking,
        ]);
    }
}
