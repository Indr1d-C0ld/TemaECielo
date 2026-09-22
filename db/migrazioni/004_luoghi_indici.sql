-- Tema e Cielo — F2, indici mancanti sul gazetteer.
--
-- Il riquadro di coordinate usato per «il luogo piu' vicino a un punto»
-- faceva una scansione completa di trecentomila righe: da 150 a 830
-- millisecondi per ogni click sulla mappa. Con l'indice composto la stessa
-- interrogazione diventa una scansione di intervallo.
--
-- L'ordine conta: prima la latitudine, che e' la colonna con l'intervallo piu'
-- stretto e selettivo nelle nostre ricerche.

ALTER TABLE luoghi ADD KEY i_coordinate (lat, lon);

-- La popolazione portata dentro la tabella degli alias evita di raggiungere
-- `luoghi` solo per ordinare i candidati.
ALTER TABLE luoghi_alias ADD COLUMN popolazione INT UNSIGNED NOT NULL DEFAULT 0 AFTER preferito;

UPDATE luoghi_alias a JOIN luoghi l ON l.id = a.luogo_id SET a.popolazione = l.popolazione;

ALTER TABLE luoghi_alias ADD KEY i_norm_pop (nome_norm(16), popolazione);
