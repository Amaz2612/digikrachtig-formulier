-- Digikrachtig formulierensysteem
-- Database schema
-- Gemaakt door: Zakaria en Rares
-- Versie 2 - aangepast op de echte vragenlijst
-- Werkt op MySQL 8 en op MariaDB (XAMPP)

CREATE DATABASE IF NOT EXISTS digikrachtig
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE digikrachtig;

-- Volgorde van droppen is omgekeerd aan aanmaken vanwege de foreign keys
DROP TABLE IF EXISTS submission_events;
DROP TABLE IF EXISTS answers;
DROP TABLE IF EXISTS form_submissions;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS sections;
DROP TABLE IF EXISTS forms;
DROP TABLE IF EXISTS user_profiles;
DROP TABLE IF EXISTS users;


-- ---------------------------------------------------------------
-- users
-- Wordt in productie gevuld door de login van Padgin.
-- Lokaal vullen wij deze tabel zelf met testgebruikers (seed.sql).
-- ---------------------------------------------------------------
CREATE TABLE users (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  studentnummer  VARCHAR(20)     NOT NULL,
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_studentnummer (studentnummer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
-- user_profiles
-- De gebruikersnaam die de student bij de eerste keer inloggen invult.
-- Versleuteld opgeslagen, daarom VARBINARY en een losse IV per rij.
-- ---------------------------------------------------------------
CREATE TABLE user_profiles (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       BIGINT UNSIGNED NOT NULL,
  username_enc  VARBINARY(255)  NOT NULL,
  username_iv   VARBINARY(32)   NOT NULL,
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_profiles_user (user_id),
  CONSTRAINT fk_profiles_user
    FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
-- forms
-- Een formulier met een versienummer. Komt er later een v2, dan
-- zetten we de oude op is_active = 0 in plaats van hem weg te gooien.
-- ---------------------------------------------------------------
CREATE TABLE forms (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  naam        VARCHAR(150)    NOT NULL,
  versie      INT UNSIGNED    NOT NULL DEFAULT 1,
  is_active   TINYINT(1)      NOT NULL DEFAULT 0,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_forms_naam_versie (naam, versie),
  KEY idx_forms_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
-- sections
-- De kopjes uit de vragenlijst: Bedrijfsgegevens, Microsoft 365,
-- Word, Excel, PowerPoint, Outlook, Teams, OneDrive, Website,
-- Social media, AI en Kenniscafes.
--
-- intro = het uitlegblokje boven een sectie (staat bijvoorbeeld
--         bij Kenniscafes). Leeg laten als er geen uitleg is.
-- ---------------------------------------------------------------
CREATE TABLE sections (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  form_id   BIGINT UNSIGNED NOT NULL,
  code      VARCHAR(60)     NOT NULL,
  titel     VARCHAR(150)    NOT NULL,
  intro     TEXT                NULL,
  volgorde  INT UNSIGNED    NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sections_form_code (form_id, code),
  KEY idx_sections_volgorde (form_id, volgorde),
  CONSTRAINT fk_sections_form
    FOREIGN KEY (form_id) REFERENCES forms (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
-- questions
-- De vragen staan hier en niet in de PHP-code. Komt er een vraag bij,
-- dan is dat een INSERT en hoeft er niets aan de code te veranderen.
--
-- code   = vaste tekstcode, bijvoorbeeld 'word_niveau'. Hiermee kan
--          de analysegroep vragen herkennen ook als de id's wijzigen.
-- opties = alleen invullen bij keuzevragen (radio, select, checkbox),
--          bijvoorbeeld: ["onvoldoende","matig","voldoende","goed"]
--
-- Voorwaardelijke vragen ("Bij ja" / "Bij nee" in de vragenlijst):
--   toon_als_question_id = de vraag waar deze vraag van afhangt
--   toon_als_waarde      = het antwoord dat deze vraag zichtbaar maakt
-- Allebei leeg betekent: de vraag is altijd zichtbaar.
-- Voorbeeld: 'word_werkzaamheden' hangt af van 'word_gebruik' = 'ja'
-- ---------------------------------------------------------------
CREATE TABLE questions (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  form_id               BIGINT UNSIGNED NOT NULL,
  section_id            BIGINT UNSIGNED NOT NULL,
  code                  VARCHAR(60)     NOT NULL,
  label                 VARCHAR(500)    NOT NULL,
  help_tekst            VARCHAR(255)        NULL,
  type                  ENUM('tekst','tekstvak','getal','datum','email',
                             'telefoon','radio','select','checkbox')
                        NOT NULL DEFAULT 'tekst',
  opties                JSON                NULL,
  verplicht             TINYINT(1)      NOT NULL DEFAULT 0,
  volgorde              INT UNSIGNED    NOT NULL DEFAULT 0,
  toon_als_question_id  BIGINT UNSIGNED     NULL,
  toon_als_waarde       VARCHAR(100)        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_questions_form_code (form_id, code),
  KEY idx_questions_volgorde (section_id, volgorde),
  KEY idx_questions_toon_als (toon_als_question_id),
  CONSTRAINT fk_questions_form
    FOREIGN KEY (form_id) REFERENCES forms (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_questions_section
    FOREIGN KEY (section_id) REFERENCES sections (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_questions_toon_als
    FOREIGN KEY (toon_als_question_id) REFERENCES questions (id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
-- form_submissions
-- Een inzending van een student. Status 'concept' betekent bezig,
-- 'ingediend' betekent definitief verstuurd.
--
-- De unique key zorgt dat een student maar een keer kan inleveren
-- per formulier. Wil je meerdere inzendingen toestaan, haal dan
-- uq_submission_user_form weg.
-- ---------------------------------------------------------------
CREATE TABLE form_submissions (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  form_id       BIGINT UNSIGNED NOT NULL,
  user_id       BIGINT UNSIGNED NOT NULL,
  status        ENUM('concept','ingediend') NOT NULL DEFAULT 'concept',
  gestart_op    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ingediend_op  DATETIME            NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_submission_user_form (form_id, user_id),
  KEY idx_submissions_status (status),
  CONSTRAINT fk_submissions_form
    FOREIGN KEY (form_id) REFERENCES forms (id),
  CONSTRAINT fk_submissions_user
    FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
-- answers
-- Een rij per gegeven antwoord.
--
-- Let op: er staat bewust GEEN unique key op (submission_id,
-- question_id). Bij de vraag over kenniscafes mogen meerdere
-- vakjes aangevinkt worden, en dan krijgt elk aangevinkt vakje
-- een eigen rij.
--
-- Bij opslaan dus: eerst de antwoorden van die inzending
-- verwijderen, daarna opnieuw invoegen.
-- ---------------------------------------------------------------
CREATE TABLE answers (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  submission_id  BIGINT UNSIGNED NOT NULL,
  question_id    BIGINT UNSIGNED NOT NULL,
  waarde         TEXT                NULL,
  PRIMARY KEY (id),
  KEY idx_answers_submission (submission_id),
  KEY idx_answers_question (question_id),
  CONSTRAINT fk_answers_submission
    FOREIGN KEY (submission_id) REFERENCES form_submissions (id)
    ON DELETE CASCADE,
  CONSTRAINT fk_answers_question
    FOREIGN KEY (question_id) REFERENCES questions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------
-- submission_events
-- Logboekje per inzending. Handig voor het testrapport en om te
-- kunnen zien waar iets misging.
-- ---------------------------------------------------------------
CREATE TABLE submission_events (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  submission_id  BIGINT UNSIGNED NOT NULL,
  event_type     ENUM('aangemaakt','opgeslagen','validatie_mislukt',
                      'ingediend') NOT NULL,
  opmerking      VARCHAR(255)        NULL,
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_events_submission (submission_id, created_at),
  CONSTRAINT fk_events_submission
    FOREIGN KEY (submission_id) REFERENCES form_submissions (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
