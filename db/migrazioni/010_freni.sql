-- 010 — i freni: quante volte un cliente fa una cosa costosa, per minuto
--
-- Prima esisteva un solo limite, quello dell'API, ed era tenuto nella sessione:
-- bastava non mandare il cookie per ricevere una sessione nuova a ogni richiesta
-- e non incontrarlo mai. Il motore delle effemeridi non ne aveva nessuno, e ogni
-- cielo o transito non in cache lancia un processo PHP a riga di comando.
--
-- Il contatore sta qui, nel database, perche' e' l'unica cosa che tutte le
-- richieste di tutti i processi del server vedono insieme. Una riga per chiave
-- e per minuto; le righe vecchie le toglie `Freno` stesso, ogni tanto.

CREATE TABLE IF NOT EXISTS freni (
    chiave   VARCHAR(96)  NOT NULL,
    finestra INT UNSIGNED NOT NULL,
    n        INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (chiave, finestra),
    KEY i_finestra (finestra)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
