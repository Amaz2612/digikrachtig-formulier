<?php
/**
 * Digikrachtig formulierensysteem
 * Model: haalt het formulier met secties en vragen op.
 *
 * Plaats dit bestand in: app/models/FormulierModel.php
 *
 * Dit model leest alleen. Het opslaan van antwoorden komt in een
 * apart model (InzendingModel).
 */
class FormulierModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Het formulier dat op dit moment actief is.
     * Geeft null terug als er geen actief formulier is.
     */
    public function actiefFormulier(): ?array
    {
        $sql = 'SELECT id, naam, versie
                FROM forms
                WHERE is_active = 1
                ORDER BY versie DESC
                LIMIT 1';

        $rij = $this->pdo->query($sql)->fetch();

        return $rij === false ? null : $rij;
    }

    /**
     * Alle secties van een formulier, in de juiste volgorde.
     */
    public function secties(int $formulierId): array
    {
        $sql = 'SELECT id, code, titel, intro
                FROM sections
                WHERE form_id = :form_id
                ORDER BY volgorde';

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['form_id' => $formulierId]);

        return $statement->fetchAll();
    }

    /**
     * Alle vragen van een formulier, in de juiste volgorde.
     *
     * Per vraag wordt teruggegeven:
     *   opties          - array met keuzemogelijkheden (leeg bij open vragen)
     *   toon_als_code   - code van de vraag waar deze vraag van afhangt
     *   toon_als_waarden- array met antwoorden die deze vraag tonen
     *                     (in de database gescheiden door een |)
     *   is_melding      - true bij een tekstregel zonder invulveld
     */
    public function vragen(int $formulierId): array
    {
        $sql = 'SELECT
                    q.id,
                    q.section_id,
                    q.code,
                    q.label,
                    q.help_tekst,
                    q.type,
                    q.opties,
                    q.verplicht,
                    q.toon_als_waarde,
                    ouder.code AS toon_als_code
                FROM questions q
                JOIN sections s
                    ON s.id = q.section_id
                LEFT JOIN questions ouder
                    ON ouder.id = q.toon_als_question_id
                WHERE q.form_id = :form_id
                ORDER BY s.volgorde, q.volgorde';

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['form_id' => $formulierId]);

        $vragen = [];

        foreach ($statement->fetchAll() as $rij) {
            $rij['opties'] = $rij['opties'] === null
                ? []
                : (json_decode($rij['opties'], true) ?? []);

            $rij['toon_als_waarden'] = $rij['toon_als_waarde'] === null
                ? []
                : explode('|', $rij['toon_als_waarde']);

            $rij['verplicht']  = (bool) $rij['verplicht'];
            $rij['is_melding'] = $rij['type'] === 'melding';

            $vragen[] = $rij;
        }

        return $vragen;
    }

    /**
     * Het hele formulier in een keer: secties met hun vragen erin.
     * Dit is wat de view nodig heeft om het formulier te tekenen.
     */
    public function structuur(int $formulierId): array
    {
        $secties = $this->secties($formulierId);
        $vragen  = $this->vragen($formulierId);

        // Vragen alvast groeperen per sectie, zodat we niet voor
        // elke sectie de hele lijst hoeven door te lopen.
        $perSectie = [];
        foreach ($vragen as $vraag) {
            $perSectie[$vraag['section_id']][] = $vraag;
        }

        foreach ($secties as $index => $sectie) {
            $secties[$index]['vragen'] = $perSectie[$sectie['id']] ?? [];
        }

        return $secties;
    }

    /**
     * Alle vragen met hun code als sleutel.
     * Handig bij het opslaan en bij het controleren van voorwaarden.
     */
    public function vragenPerCode(int $formulierId): array
    {
        $resultaat = [];

        foreach ($this->vragen($formulierId) as $vraag) {
            $resultaat[$vraag['code']] = $vraag;
        }

        return $resultaat;
    }
}
