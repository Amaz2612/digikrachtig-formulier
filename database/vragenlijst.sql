-- Digikrachtig formulierensysteem
-- De echte vragenlijst: formulier, secties en vragen
-- Gemaakt door: Zakaria en Rares
--
-- Dit bestand hoort OOK op productie (anders is er geen formulier).
-- Draaien NA schema.sql, op een lege database.

USE digikrachtig;

-- ---------------------------------------------------------------
-- 1. Het formulier
-- ---------------------------------------------------------------
INSERT INTO forms (naam, versie, is_active)
VALUES ('Vragenlijst Digikrachtig Rivierenland', 1, 1);

SET @form_id = LAST_INSERT_ID();

-- ---------------------------------------------------------------
-- 2. De secties (de kopjes uit de vragenlijst)
-- ---------------------------------------------------------------
INSERT INTO sections (form_id, code, titel, intro, volgorde) VALUES
  (@form_id, 'gegevens', 'Gegevens', 'Digikrachtig Rivierenland is een initiatief van ROC Rivor waarbij studenten, docenten en het bedrijfsleven samenwerken om de digitale kennis en vaardigheden in de regio Rivierenland te vergroten. Daarom inventariseren studenten van ROC Rivor bij hun stagebedrijf met deze vragenlijst in hoeverre het bedrijf digitalisering gebruikt, wat de behoefte is m.b.t. digitale vaardigheden en welke hulpvragen er zijn. Docenten werken deze vragen samen met studenten uit en helpen bedrijven en werknemers om digitaal vaardiger te worden. Meer informatie vindt u op de site www.digikrachtig.nl.', 10),
  (@form_id, 'm365', 'Microsoft 365/Office', NULL, 20),
  (@form_id, 'word', 'Word', NULL, 30),
  (@form_id, 'excel', 'Excel', NULL, 40),
  (@form_id, 'powerpoint', 'PowerPoint', NULL, 50),
  (@form_id, 'outlook', 'Outlook', NULL, 60),
  (@form_id, 'teams', 'Teams', NULL, 70),
  (@form_id, 'onedrive', 'OneDrive', NULL, 80),
  (@form_id, 'website', 'Website', NULL, 90),
  (@form_id, 'social_media', 'Social media', NULL, 100),
  (@form_id, 'ai', 'AI', NULL, 110),
  (@form_id, 'kenniscafes', 'Kenniscafés, speeddates en bijeenkomsten', 'Vanuit Digikrachtig Rivierenland organiseren wij kenniscafés, speeddates en bijeenkomsten voor bedrijven en hun medewerkers.', 120),
  (@form_id, 'afsluiting', 'Afsluiting', 'Hulp nodig met digitale vraagstukken? Ga naar www.digikrachtig.nl en stel uw vraag bij ''Stel je vraag'' of mail naar digikrachtig@rocrivor.nl. Hartelijk dank voor het invullen van de vragenlijst!', 130);

-- ---------------------------------------------------------------
-- 3. De vragen
-- Eerst in een tijdelijke hulptabel, zodat we de secties en de
-- voorwaarden op code kunnen koppelen in plaats van op id.
--
-- toon_als_code  = code van de vraag waar deze vraag van afhangt
-- toon_als_waarde = het antwoord dat deze vraag zichtbaar maakt.
--   Meerdere waarden scheiden met |
--   (bijv. 'ja, Microsoft 365|ja, Microsoft Office')
-- ---------------------------------------------------------------
DROP TEMPORARY TABLE IF EXISTS tmp_vragen;
CREATE TEMPORARY TABLE tmp_vragen (
  sectie_code      VARCHAR(60)  NOT NULL,
  code             VARCHAR(60)  NOT NULL,
  label            VARCHAR(500) NOT NULL,
  help_tekst       VARCHAR(255) NULL,
  type             VARCHAR(20)  NOT NULL,
  opties           TEXT         NULL,
  verplicht        TINYINT(1)   NOT NULL,
  volgorde         INT UNSIGNED NOT NULL,
  toon_als_code    VARCHAR(60)  NULL,
  toon_als_waarde  VARCHAR(100) NULL
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tmp_vragen
  (sectie_code, code, label, help_tekst, type, opties, verplicht, volgorde, toon_als_code, toon_als_waarde)
VALUES

  -- gegevens
  ('gegevens', 'naam_bedrijf', 'Naam bedrijf', NULL, 'tekst', NULL, 1, 10, NULL, NULL),
  ('gegevens', 'ingevuld_door', 'Ingevuld door', NULL, 'tekst', NULL, 1, 20, NULL, NULL),
  ('gegevens', 'functie', 'Functie', NULL, 'tekst', NULL, 1, 30, NULL, NULL),
  ('gegevens', 'telefoonnummer', 'Telefoonnummer', NULL, 'telefoon', NULL, 0, 40, NULL, NULL),
  ('gegevens', 'email', 'E-mail', NULL, 'email', NULL, 1, 50, NULL, NULL),
  ('gegevens', 'naam_student', 'Naam student', NULL, 'tekst', NULL, 1, 60, NULL, NULL),

  -- m365
  ('m365', 'm365_gebruik', 'Wordt er binnen uw bedrijf gewerkt met Microsoft 365/Office?', NULL, 'radio', '["ja, Microsoft 365","ja, Microsoft Office","nee"]', 1, 10, NULL, NULL),
  ('m365', 'm365_alternatief', 'Wat wordt er gebruikt i.p.v. Microsoft 365/Office?', NULL, 'tekstvak', NULL, 0, 20, 'm365_gebruik', 'nee'),

  -- word
  ('word', 'word_gebruik', 'Wordt er gewerkt met Word?', NULL, 'radio', '["ja","nee"]', 1, 10, 'm365_gebruik', 'ja, Microsoft 365|ja, Microsoft Office'),
  ('word', 'word_alternatief', 'Welk programma wordt gebruikt i.p.v. Word?', NULL, 'tekst', NULL, 0, 20, 'word_gebruik', 'nee'),
  ('word', 'word_alternatief_werkzaamheden', 'Voor welke werkzaamheden wordt dit programma gebruikt?', NULL, 'tekstvak', NULL, 0, 30, 'word_gebruik', 'nee'),
  ('word', 'word_werkzaamheden', 'Voor welke werkzaamheden wordt Word gebruikt?', NULL, 'tekstvak', NULL, 0, 40, 'word_gebruik', 'ja'),
  ('word', 'word_niveau', 'Wat is het kennis- en vaardigheidsniveau van de medewerkers die Word gebruiken in hun werk?', NULL, 'radio', '["onvoldoende","matig","voldoende","goed"]', 1, 50, 'word_gebruik', 'ja'),
  ('word', 'word_toelichting', 'Geef hier een korte toelichting op het niveau', NULL, 'tekstvak', NULL, 0, 60, 'word_gebruik', 'ja'),
  ('word', 'word_verbeteringen', 'Welke verbeteringen of ondersteuning zijn gewenst m.b.t. het gebruik van Word binnen uw bedrijf?', NULL, 'tekstvak', NULL, 0, 70, 'word_gebruik', 'ja'),

  -- excel
  ('excel', 'excel_gebruik', 'Wordt er gewerkt met Excel?', NULL, 'radio', '["ja","nee"]', 1, 10, 'm365_gebruik', 'ja, Microsoft 365|ja, Microsoft Office'),
  ('excel', 'excel_alternatief', 'Welk programma wordt gebruikt i.p.v. Excel?', NULL, 'tekst', NULL, 0, 20, 'excel_gebruik', 'nee'),
  ('excel', 'excel_alternatief_werkzaamheden', 'Voor welke werkzaamheden wordt dit programma gebruikt?', NULL, 'tekstvak', NULL, 0, 30, 'excel_gebruik', 'nee'),
  ('excel', 'excel_werkzaamheden', 'Voor welke werkzaamheden wordt Excel gebruikt?', NULL, 'tekstvak', NULL, 0, 40, 'excel_gebruik', 'ja'),
  ('excel', 'excel_niveau', 'Wat is het kennis- en vaardigheidsniveau van de medewerkers die Excel gebruiken in hun werk?', NULL, 'radio', '["onvoldoende","matig","voldoende","goed"]', 1, 50, 'excel_gebruik', 'ja'),
  ('excel', 'excel_toelichting', 'Geef hier een korte toelichting op het niveau', NULL, 'tekstvak', NULL, 0, 60, 'excel_gebruik', 'ja'),
  ('excel', 'excel_verbeteringen', 'Welke verbeteringen of ondersteuning zijn gewenst m.b.t. het gebruik van Excel binnen uw bedrijf?', NULL, 'tekstvak', NULL, 0, 70, 'excel_gebruik', 'ja'),

  -- powerpoint
  ('powerpoint', 'powerpoint_gebruik', 'Wordt er gewerkt met PowerPoint?', NULL, 'radio', '["ja","nee"]', 1, 10, 'm365_gebruik', 'ja, Microsoft 365|ja, Microsoft Office'),
  ('powerpoint', 'powerpoint_alternatief', 'Welk programma wordt gebruikt i.p.v. PowerPoint?', NULL, 'tekst', NULL, 0, 20, 'powerpoint_gebruik', 'nee'),
  ('powerpoint', 'powerpoint_alternatief_werkzaamheden', 'Voor welke werkzaamheden wordt dit programma gebruikt?', NULL, 'tekstvak', NULL, 0, 30, 'powerpoint_gebruik', 'nee'),
  ('powerpoint', 'powerpoint_werkzaamheden', 'Voor welke werkzaamheden wordt PowerPoint gebruikt?', NULL, 'tekstvak', NULL, 0, 40, 'powerpoint_gebruik', 'ja'),
  ('powerpoint', 'powerpoint_niveau', 'Wat is het kennis- en vaardigheidsniveau van de medewerkers die PowerPoint gebruiken in hun werk?', NULL, 'radio', '["onvoldoende","matig","voldoende","goed"]', 1, 50, 'powerpoint_gebruik', 'ja'),
  ('powerpoint', 'powerpoint_toelichting', 'Geef hier een korte toelichting op het niveau', NULL, 'tekstvak', NULL, 0, 60, 'powerpoint_gebruik', 'ja'),
  ('powerpoint', 'powerpoint_verbeteringen', 'Welke verbeteringen of ondersteuning zijn gewenst m.b.t. het gebruik van PowerPoint binnen uw bedrijf?', NULL, 'tekstvak', NULL, 0, 70, 'powerpoint_gebruik', 'ja'),

  -- outlook
  ('outlook', 'outlook_gebruik', 'Wordt er gewerkt met Outlook?', NULL, 'radio', '["ja","nee"]', 1, 10, 'm365_gebruik', 'ja, Microsoft 365|ja, Microsoft Office'),
  ('outlook', 'outlook_alternatief', 'Welk programma wordt gebruikt i.p.v. Outlook?', NULL, 'tekst', NULL, 0, 20, 'outlook_gebruik', 'nee'),
  ('outlook', 'outlook_alternatief_werkzaamheden', 'Voor welke werkzaamheden wordt dit programma gebruikt?', NULL, 'tekstvak', NULL, 0, 30, 'outlook_gebruik', 'nee'),
  ('outlook', 'outlook_werkzaamheden', 'Voor welke werkzaamheden wordt Outlook gebruikt?', NULL, 'tekstvak', NULL, 0, 40, 'outlook_gebruik', 'ja'),
  ('outlook', 'outlook_niveau', 'Wat is het kennis- en vaardigheidsniveau van de medewerkers die Outlook gebruiken in hun werk?', NULL, 'radio', '["onvoldoende","matig","voldoende","goed"]', 1, 50, 'outlook_gebruik', 'ja'),
  ('outlook', 'outlook_toelichting', 'Geef hier een korte toelichting op het niveau', NULL, 'tekstvak', NULL, 0, 60, 'outlook_gebruik', 'ja'),
  ('outlook', 'outlook_verbeteringen', 'Welke verbeteringen of ondersteuning zijn gewenst m.b.t. het gebruik van Outlook binnen uw bedrijf?', NULL, 'tekstvak', NULL, 0, 70, 'outlook_gebruik', 'ja'),

  -- teams
  ('teams', 'teams_gebruik', 'Wordt er gewerkt met Teams?', NULL, 'radio', '["ja","nee"]', 1, 10, 'm365_gebruik', 'ja, Microsoft 365|ja, Microsoft Office'),
  ('teams', 'teams_alternatief', 'Welk programma wordt gebruikt i.p.v. Teams?', NULL, 'tekst', NULL, 0, 20, 'teams_gebruik', 'nee'),
  ('teams', 'teams_alternatief_werkzaamheden', 'Voor welke werkzaamheden wordt dit programma gebruikt?', NULL, 'tekstvak', NULL, 0, 30, 'teams_gebruik', 'nee'),
  ('teams', 'teams_werkzaamheden', 'Voor welke werkzaamheden wordt Teams gebruikt?', NULL, 'tekstvak', NULL, 0, 40, 'teams_gebruik', 'ja'),
  ('teams', 'teams_niveau', 'Wat is het kennis- en vaardigheidsniveau van de medewerkers die Teams gebruiken in hun werk?', NULL, 'radio', '["onvoldoende","matig","voldoende","goed"]', 1, 50, 'teams_gebruik', 'ja'),
  ('teams', 'teams_toelichting', 'Geef hier een korte toelichting op het niveau', NULL, 'tekstvak', NULL, 0, 60, 'teams_gebruik', 'ja'),
  ('teams', 'teams_verbeteringen', 'Welke verbeteringen of ondersteuning zijn gewenst m.b.t. het gebruik van Teams binnen uw bedrijf?', NULL, 'tekstvak', NULL, 0, 70, 'teams_gebruik', 'ja'),

  -- onedrive
  ('onedrive', 'onedrive_gebruik', 'Wordt er gewerkt met OneDrive?', NULL, 'radio', '["ja","nee"]', 1, 10, 'm365_gebruik', 'ja, Microsoft 365|ja, Microsoft Office'),
  ('onedrive', 'onedrive_alternatief', 'Welk programma wordt gebruikt i.p.v. OneDrive?', NULL, 'tekst', NULL, 0, 20, 'onedrive_gebruik', 'nee'),
  ('onedrive', 'onedrive_alternatief_werkzaamheden', 'Voor welke werkzaamheden wordt dit programma gebruikt?', NULL, 'tekstvak', NULL, 0, 30, 'onedrive_gebruik', 'nee'),
  ('onedrive', 'onedrive_werkzaamheden', 'Voor welke werkzaamheden wordt OneDrive gebruikt?', NULL, 'tekstvak', NULL, 0, 40, 'onedrive_gebruik', 'ja'),
  ('onedrive', 'onedrive_niveau', 'Wat is het kennis- en vaardigheidsniveau van de medewerkers die OneDrive gebruiken in hun werk?', NULL, 'radio', '["onvoldoende","matig","voldoende","goed"]', 1, 50, 'onedrive_gebruik', 'ja'),
  ('onedrive', 'onedrive_toelichting', 'Geef hier een korte toelichting op het niveau', NULL, 'tekstvak', NULL, 0, 60, 'onedrive_gebruik', 'ja'),
  ('onedrive', 'onedrive_verbeteringen', 'Welke verbeteringen of ondersteuning zijn gewenst m.b.t. het gebruik van OneDrive binnen uw bedrijf?', NULL, 'tekstvak', NULL, 0, 70, 'onedrive_gebruik', 'ja'),

  -- website
  ('website', 'website_heeft', 'Heeft uw bedrijf een website?', NULL, 'radio', '["ja","nee"]', 1, 10, NULL, NULL),
  ('website', 'website_interesse', 'Heeft u interesse in een website voor uw bedrijf?', NULL, 'radio', '["ja","nee"]', 1, 20, 'website_heeft', 'nee'),
  ('website', 'website_hulp', 'Wilt u hierbij hulp en advies van studenten?', 'Bij ja nemen wij contact met u op om uw wensen en de mogelijkheden te bespreken.', 'radio', '["ja","nee"]', 1, 30, 'website_interesse', 'ja'),
  ('website', 'website_doel', 'Wat is het doel van de website (bijv. informatie geven, webshop)?', NULL, 'tekstvak', NULL, 0, 40, 'website_heeft', 'ja'),
  ('website', 'website_beheer', 'Onderhoudt u de website zelf of is dit uitbesteed?', NULL, 'radio', '["zelf","uitbesteed"]', 1, 50, 'website_heeft', 'ja'),
  ('website', 'website_tevreden', 'Bent u tevreden over uw website?', NULL, 'radio', '["ja","nee"]', 1, 60, 'website_heeft', 'ja'),
  ('website', 'website_verbeteringen', 'Welke verbeteringen of ondersteuning zijn gewenst m.b.t. uw website?', NULL, 'tekstvak', NULL, 0, 70, 'website_tevreden', 'nee'),
  ('website', 'website_scan', 'Heeft u interesse in een scan van uw website door studenten waarbij zij u een advies geven over mogelijke verbeteringen?', 'Bij ja nemen wij contact met u op om dit met u af te stemmen.', 'radio', '["ja","nee"]', 1, 80, 'website_heeft', 'ja'),

  -- social_media
  ('social_media', 'social_actief', 'Is uw bedrijf actief op social media?', NULL, 'radio', '["ja","nee"]', 1, 10, NULL, NULL),
  ('social_media', 'social_interesse', 'Heeft u interesse om social media in te zetten voor uw bedrijf?', NULL, 'radio', '["ja","nee"]', 1, 20, 'social_actief', 'nee'),
  ('social_media', 'social_hulp', 'Wilt u hierbij hulp en advies van studenten?', 'Bij ja nemen wij contact met u op om uw wensen en de mogelijkheden te bespreken.', 'radio', '["ja","nee"]', 1, 30, 'social_interesse', 'ja'),
  ('social_media', 'social_platforms', 'Welk(e) platform(s) gebruikt uw bedrijf?', NULL, 'tekst', NULL, 0, 40, 'social_actief', 'ja'),
  ('social_media', 'social_doel', 'Wat is het doel van de social media (bijv. informatie geven, reclame)?', NULL, 'tekstvak', NULL, 0, 50, 'social_actief', 'ja'),
  ('social_media', 'social_posts', 'Wat post uw bedrijf op social media?', NULL, 'tekstvak', NULL, 0, 60, 'social_actief', 'ja'),
  ('social_media', 'social_beheer', 'Onderhoudt u de social media zelf of is dit uitbesteed?', NULL, 'radio', '["zelf","uitbesteed"]', 1, 70, 'social_actief', 'ja'),
  ('social_media', 'social_tevreden', 'Bent u tevreden over het gebruik van social media voor uw bedrijf?', NULL, 'radio', '["ja","nee"]', 1, 80, 'social_actief', 'ja'),
  ('social_media', 'social_hulp_verbetering', 'Wilt u hulp en advies van studenten m.b.t. uw social media?', 'Bij ja nemen wij contact met u op om uw wensen en de mogelijkheden te bespreken.', 'radio', '["ja","nee"]', 1, 90, 'social_tevreden', 'nee'),

  -- ai
  ('ai', 'ai_gebruik', 'Wordt er binnen uw bedrijf gewerkt met AI?', NULL, 'radio', '["ja","nee"]', 1, 10, NULL, NULL),
  ('ai', 'ai_wil_weten', 'Wilt u weten hoe u AI in uw bedrijf kunt gebruiken?', NULL, 'radio', '["ja","nee"]', 1, 20, 'ai_gebruik', 'nee'),
  ('ai', 'ai_programma', 'Welk programma wordt er gebruikt?', NULL, 'tekst', NULL, 0, 30, 'ai_gebruik', 'ja'),
  ('ai', 'ai_werkzaamheden', 'Voor welke werkzaamheden wordt AI gebruikt?', NULL, 'tekstvak', NULL, 0, 40, 'ai_gebruik', 'ja'),
  ('ai', 'ai_niveau', 'Wat is het kennis- en vaardigheidsniveau van de medewerkers die AI gebruiken in hun werk?', NULL, 'radio', '["onvoldoende","matig","voldoende","goed"]', 1, 50, 'ai_gebruik', 'ja'),
  ('ai', 'ai_toelichting', 'Geef hier een korte toelichting op het niveau', NULL, 'tekstvak', NULL, 0, 60, 'ai_gebruik', 'ja'),
  ('ai', 'ai_verbeteren', 'Wat wilt u verbeteren m.b.t. het gebruik van AI binnen uw bedrijf?', NULL, 'tekstvak', NULL, 0, 70, 'ai_gebruik', 'ja'),

  -- kenniscafes
  ('kenniscafes', 'kenniscafe_interesse', 'Waar is vanuit uw bedrijf interesse in?', 'Meerdere antwoorden mogelijk.', 'checkbox', '["Bestanden en mappen aanmaken, kopiëren, verplaatsen en verwijderen","Effectief zoeken naar informatie op internet","Social media (content creëren en delen, blogs, contact onderhouden met anderen)","Basiskennis van beeld- en videobewerking","Bewust omgaan met het delen van persoonlijke informatie online (security)","AI in het algemeen","AI-chatbots als ChatGPT gebruiken","Anders"]', 0, 10, NULL, NULL),
  ('kenniscafes', 'kenniscafe_anders', 'Anders, namelijk:', NULL, 'tekst', NULL, 0, 20, 'kenniscafe_interesse', 'Anders'),

  -- afsluiting
  ('afsluiting', 'opmerkingen', 'Overige vragen of opmerkingen? Vul die hier in:', NULL, 'tekstvak', NULL, 0, 10, NULL, NULL);

-- Vragen overzetten naar de echte tabel, gekoppeld aan hun sectie
INSERT INTO questions
  (form_id, section_id, code, label, help_tekst, type, opties, verplicht, volgorde)
SELECT @form_id, s.id, v.code, v.label, v.help_tekst, v.type, v.opties, v.verplicht, v.volgorde
FROM tmp_vragen v
JOIN sections s ON s.form_id = @form_id AND s.code = v.sectie_code;

-- Voorwaarden invullen: de code omzetten naar het id van die vraag
UPDATE questions q
JOIN tmp_vragen v ON v.code = q.code
JOIN questions ouder ON ouder.form_id = q.form_id AND ouder.code = v.toon_als_code
SET q.toon_als_question_id = ouder.id,
    q.toon_als_waarde      = v.toon_als_waarde
WHERE q.form_id = @form_id;

DROP TEMPORARY TABLE tmp_vragen;

-- ---------------------------------------------------------------
-- 4. Controle: moet 13 secties en 77 vragen geven,
--    waarvan 65 met een voorwaarde
-- ---------------------------------------------------------------
SELECT
  (SELECT COUNT(*) FROM sections  WHERE form_id = @form_id) AS secties,
  (SELECT COUNT(*) FROM questions WHERE form_id = @form_id) AS vragen,
  (SELECT COUNT(*) FROM questions WHERE form_id = @form_id
     AND toon_als_question_id IS NOT NULL)                   AS met_voorwaarde;
