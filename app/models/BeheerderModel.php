<?php
/**
 * Digikrachtig formulierensysteem
 * Model: de beheerders (tabel admins).
 *
 * Plaats dit bestand in: app/models/BeheerderModel.php
 *
 * Hier komt alleen de hash van het wachtwoord langs, nooit het
 * wachtwoord zelf. Hashen en controleren gebeurt in BeheerAuth en in
 * tools/maak-admin.php.
 */
class BeheerderModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * De beheerder met deze gebruikersnaam, of null als die er niet is.
     */
    public function zoekOpGebruikersnaam(string $gebruikersnaam): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, gebruikersnaam, wachtwoord_hash
             FROM admins
             WHERE gebruikersnaam = :gebruikersnaam'
        );
        $statement->execute(['gebruikersnaam' => $gebruikersnaam]);

        $rij = $statement->fetch();

        return $rij === false ? null : $rij;
    }

    /**
     * Maakt een nieuwe beheerder aan en geeft het id terug.
     */
    public function maak(string $gebruikersnaam, string $wachtwoordHash): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO admins (gebruikersnaam, wachtwoord_hash)
             VALUES (:gebruikersnaam, :hash)'
        );
        $statement->execute([
            'gebruikersnaam' => $gebruikersnaam,
            'hash'           => $wachtwoordHash,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Zet een nieuwe hash. Voor een nieuw wachtwoord, of als PHP een
     * sterkere manier van hashen is gaan gebruiken.
     */
    public function zetWachtwoord(int $beheerderId, string $wachtwoordHash): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE admins SET wachtwoord_hash = :hash WHERE id = :id'
        );
        $statement->execute([
            'hash' => $wachtwoordHash,
            'id'   => $beheerderId,
        ]);
    }
}
