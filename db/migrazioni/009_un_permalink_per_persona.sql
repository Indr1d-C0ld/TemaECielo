-- 009 — un permalink per persona
--
-- Fino a qui, due persone che inserivano gli stessi dati di nascita finivano
-- sulla stessa riga di `calcoli`, e quindi sullo stesso permalink: la seconda
-- vedeva il nome della prima, e la carta della prima poteva cominciare a
-- mostrare il nome della seconda. Il codice ora da' a ciascuno una riga sua;
-- questa migrazione separa i casi gia' esistenti.
--
-- Per ogni calcolo con piu' di un soggetto «primo», il soggetto piu' vecchio
-- resta dov'e' — il suo indirizzo e' quello gia' consegnato, e non deve
-- cambiare — e ogni altro riceve una copia del calcolo con un gettone nuovo.
-- L'impronta della copia e' derivata da quella vera e dal gettone, come fa il
-- codice: resta unica, e il motore non la confonde con la propria cache.
--
-- Chi aveva ricevuto l'indirizzo condiviso come «secondo» lo perde: da quel
-- link ora vede la carta del primo col nome giusto, cioe' quello del primo.
-- Il suo nuovo indirizzo e' nel pannello di regia. Non c'e' modo migliore:
-- l'indirizzo e' l'unica identita', e ne esisteva uno solo per due persone.

CREATE TEMPORARY TABLE _da_separare AS
SELECT cs.calcolo_id, cs.soggetto_id, CONVERT(LOWER(HEX(RANDOM_BYTES(16))) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS nuovo
  FROM calcoli_soggetti cs
  JOIN (SELECT calcolo_id, MIN(soggetto_id) AS resta
          FROM calcoli_soggetti
         WHERE ruolo = 'primo'
         GROUP BY calcolo_id
        HAVING COUNT(*) > 1) d
    ON d.calcolo_id = cs.calcolo_id
 WHERE cs.ruolo = 'primo'
   AND cs.soggetto_id <> d.resta;

INSERT INTO calcoli (gettone, impronta, tipo, opzioni, esito, errore, svg, richieste, durata_ms, creato, ultima_richiesta)
SELECT t.nuovo, SHA2(CONCAT(c.impronta, ':', t.nuovo), 256), c.tipo, c.opzioni, c.esito, c.errore,
       NULL, 1, c.durata_ms, s.creato, NOW()
  FROM _da_separare t
  JOIN calcoli c  ON c.id = t.calcolo_id
  JOIN soggetti s ON s.id = t.soggetto_id;

UPDATE calcoli_soggetti cs
  JOIN _da_separare t ON t.calcolo_id = cs.calcolo_id AND t.soggetto_id = cs.soggetto_id
  JOIN calcoli n      ON n.gettone = t.nuovo
   SET cs.calcolo_id = n.id;

DROP TEMPORARY TABLE _da_separare;
