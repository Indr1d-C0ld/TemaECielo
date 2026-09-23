-- 008 — la cache del motore deve potersi svuotare
--
-- `calcoli` fa due mestieri: tiene i permalink delle carte (riga con
-- `gettone`) e fa da cache del motore (riga senza `gettone`). I permalink sono
-- per sempre: sono l'unica identita' di una carta, e perderne uno vuol dire
-- perdere la carta. Le righe di cache invece sono ricalcolabili — si buttano e
-- si rifanno — e pesano trentun kilobyte l'una.
--
-- Finche' la volta mostrava solo «adesso» la crescita era lenta. Da quando si
-- puo' chiedere il cielo di una data e un luogo qualunque, un visitatore che
-- scorra le date riempie la tabella quanto vuole: trentamila richieste fanno
-- un gigabyte. Serve poterle togliere, e per toglierle senza scandire tutta la
-- tabella serve un indice sull'ultima volta che sono state usate.

ALTER TABLE calcoli ADD INDEX i_ultima_richiesta (ultima_richiesta);
