<?php
/**
 * Digikrachtig formulierensysteem
 * Model: inzendingen bekijken en resetten op de beheerpagina.
 *
 * Plaats dit bestand in: app/models/BeheerModel.php
 *
 * Het teruglezen van de antwoorden zelf gebeurt met
 * InzendingModel::antwoorden(), net als aan de kant van de student.
 */
class BeheerModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Aantal inzendingen: totaal, concept en ingediend.
     */
    public function telling(): array
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*)                   AS totaal,
                    SUM(status = 'concept')    AS concept,
                    SUM(status = 'ingediend')  AS ingediend
             FROM form_submissions"
        );
        $statement->execute();

        $rij = $statement->fetch();

        // SUM geeft NULL als de tabel leeg is, daarom omzetten naar int.
        return [
            'totaal'    => (int) $rij['totaal'],
            'concept'   => (int) $rij['concept'],
            'ingediend' => (int) $rij['ingediend'],
        ];
    }

    /**
     * Alle inzendingen, de laatst gestarte bovenaan.
     *
     * aantal_antwoorden telt vragen, geen rijen: een checkbox met drie
     * vinkjes heeft drie rijen in answers, maar is één beantwoorde vraag.
     */
    public function inzendingen(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.id,
                    u.studentnummer,
                    s.status,
                    s.gestart_op,
                    s.ingediend_op,
                    COUNT(DISTINCT a.question_id) AS aantal_antwoorden
             FROM form_submissions s
             JOIN users u
                 ON u.id = s.user_id
             LEFT JOIN answers a
                 ON a.submission_id = s.id
             GROUP BY s.id, u.studentnummer, s.status, s.gestart_op, s.ingediend_op
             ORDER BY s.gestart_op DESC, s.id DESC'
        );
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Eén inzending met het studentnummer en de naam van het formulier.
     * Geeft null terug als het id niet bestaat.
     */
    public function inzending(int $inzendingId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.id,
                    s.form_id,
                    s.status,
                    s.huidige_stap,
                    s.gestart_op,
                    s.ingediend_op,
                    u.studentnummer,
                    f.naam   AS formulier_naam,
                    f.versie AS formulier_versie
             FROM form_submissions s
             JOIN users u ON u.id = s.user_id
             JOIN forms f ON f.id = s.form_id
             WHERE s.id = :id'
        );
        $statement->execute(['id' => $inzendingId]);

        $rij = $statement->fetch();

        return $rij === false ? null : $rij;
    }

    /**
     * Het logboek van een inzending, oudste eerst.
     */
    public function logboek(int $inzendingId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT event_type, opmerking, created_at
             FROM submission_events
             WHERE submission_id = :id
             ORDER BY created_at, id'
        );
        $statement->execute(['id' => $inzendingId]);

        return $statement->fetchAll();
    }

    /**
     * Zet een inzending terug zodat de student opnieuw kan beginnen:
     *   - alle antwoorden weg
     *   - status terug op concept, ingediend_op leeg
     *   - huidige_stap leeg, zodat de student bij de eerste stap begint
     *   - een regel 'gereset' in het logboek
     *
     * Alles zit in één transactie. Lukt een stap niet, dan is er niets
     * veranderd. Geeft false terug als de inzending niet bestaat.
     */
    public function reset(int $inzendingId, string $doorBeheerder): bool
    {
        $this->pdo->beginTransaction();

        try {
            // FOR UPDATE: de rij blijft vast tot de commit, zodat de
            // student niet precies tussendoor kan opslaan.
            $zoek = $this->pdo->prepare(
                'SELECT id FROM form_submissions WHERE id = :id FOR UPDATE'
            );
            $zoek->execute(['id' => $inzendingId]);

            if ($zoek->fetch() === false) {
                $this->pdo->rollBack();

                return false;
            }

            $verwijder = $this->pdo->prepare(
                'DELETE FROM answers WHERE submission_id = :id'
            );
            $verwijder->execute(['id' => $inzendingId]);
            $aantal = $verwijder->rowCount();

            $terugzetten = $this->pdo->prepare(
                "UPDATE form_submissions
                 SET status       = 'concept',
                     ingediend_op = NULL,
                     huidige_stap = NULL
                 WHERE id = :id"
            );
            $terugzetten->execute(['id' => $inzendingId]);

            (new InzendingModel($this->pdo))->logGebeurtenis(
                $inzendingId,
                'gereset',
                'Door beheerder ' . $doorBeheerder . ', '
                    . $aantal . ' antwoordregels verwijderd'
            );

            $this->pdo->commit();
        } catch (Throwable $fout) {
            $this->pdo->rollBack();
            throw $fout;
        }

        return true;
    }
}
