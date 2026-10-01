-- Digikrachtig formulierensysteem
-- Studentnummers voortaan met 'rv' ervoor (rv2100001 in plaats van 2100001).
--
-- Eenmalig draaien op een database die al studenten heeft. Zonder dit
-- bestand krijgt een student die opnieuw inlogt een nieuwe gebruiker
-- (rv2100001) en ziet hij zijn eerdere antwoorden niet meer.
--
-- Mag vaker gedraaid worden: nummers die al met rv beginnen blijven
-- zoals ze zijn.
--
-- Terugdraaien kan met:
--   UPDATE users SET studentnummer = SUBSTRING(studentnummer, 3)
--   WHERE studentnummer REGEXP '^rv[0-9]+$';

USE digikrachtig;

-- Controle vooraf: staat hetzelfde nummer er al met en zonder rv in,
-- dan kan dat niet samengevoegd worden (unique key). Deze query moet
-- leeg zijn; zo niet, los dat eerst met de hand op.
SELECT oud.id AS id_zonder_rv, nieuw.id AS id_met_rv, oud.studentnummer
FROM users oud
JOIN users nieuw ON nieuw.studentnummer = CONCAT('rv', oud.studentnummer)
WHERE oud.studentnummer REGEXP '^[0-9]+$';

-- Alleen nummers van alleen cijfers, en hoogstens 18 lang: met rv
-- erbij past het dan nog in de kolom van 20 tekens.
UPDATE users
SET studentnummer = CONCAT('rv', studentnummer)
WHERE studentnummer REGEXP '^[0-9]+$'
  AND CHAR_LENGTH(studentnummer) <= 18;
