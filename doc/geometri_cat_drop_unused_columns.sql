-- Migrazione per semplificare geometri_sessioni:
-- una sola data sessione, senza titolo sessione e senza data fine.

ALTER TABLE `geometri_sessioni`
  CHANGE COLUMN `data_inizio` `data` DATETIME NOT NULL,
  DROP COLUMN `data_fine`,
  DROP COLUMN `titolo`;
