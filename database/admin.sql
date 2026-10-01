-- Digikrachtig formulierensysteem
-- Beheer: tabel voor de beheerders en een extra soort logregel.
--
-- Draaien NA schema.sql en vragenlijst.sql. Mag vaker gedraaid worden:
-- de tabel admins wordt alleen aangemaakt als hij nog niet bestaat, dus
-- bestaande beheerders blijven staan.
--
-- Hier staat bewust GEEN beheerder in. Een beheerder maak je aan met:
--   php tools/maak-admin.php

USE digikrachtig;

-- ---------------------------------------------------------------
-- admins
-- Beheerders van de beheerpagina (public/admin.php). Dit staat los
-- van de studenten in users: een student kan hier niet mee inloggen
-- en een beheerder heeft geen studentnummer.
--
-- wachtwoord_hash = uitkomst van password_hash(). Nooit het wachtwoord
-- zelf opslaan. 255 tekens, zodat er ruimte is als PHP later een
-- langer algoritme als standaard kiest.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  gebruikersnaam   VARCHAR(50)     NOT NULL,
  wachtwoord_hash  VARCHAR(255)    NOT NULL,
  created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admins_gebruikersnaam (gebruikersnaam)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
-- submission_events: 'gereset' erbij
-- Als een beheerder een inzending terugzet naar concept, komt daar
-- een regel van in het logboek.
-- ---------------------------------------------------------------
ALTER TABLE submission_events
  MODIFY event_type ENUM('aangemaakt','opgeslagen','validatie_mislukt',
                         'ingediend','gereset') NOT NULL;
