-- Tema e Cielo — F5, il corpus interpretativo.
--
-- Ogni voce esiste in DUE registri affiancati: tradizionale e moderno. Non
-- sono traduzioni l'uno dell'altro — dicono cose diverse, con categorie
-- diverse, e il lettore sceglie con quale voce leggere.
--
-- I testi stanno in tabella e non nel codice: l'admin li corregge dal pannello
-- senza toccare un file, e senza un rilascio.

CREATE TABLE testi (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Che genere di voce e'. Due famiglie:
    --   voci COMPLETE     pianeta_segno, pianeta_casa, aspetto, configurazione,
    --                     dignita, fase_luna, figura, segno, casa
    --   FRAMMENTI         pianeta, segno_modo, casa_campo, aspetto_relazione
    -- I frammenti servono a comporre le voci complete che nessuno ha ancora
    -- scritto: cosi' la copertura e' totale dal primo giorno, e la scrittura a
    -- mano sostituisce il composto dove conta di piu'.
    ambito     VARCHAR(32)  NOT NULL,

    -- L'identificativo dentro l'ambito: «sole.ariete», «sole.marte.quadrato»,
    -- «gran_trigono», «domicilio».
    chiave     VARCHAR(80)  NOT NULL,

    registro   ENUM('tradizionale','moderno') NOT NULL,
    titolo     VARCHAR(160) NOT NULL DEFAULT '',
    corpo      TEXT         NOT NULL,

    -- Quanto la voce pesa nel montaggio, prima delle correzioni che dipendono
    -- dalla carta (orbe stretto, casa angolare, dignita' estrema).
    peso       TINYINT UNSIGNED NOT NULL DEFAULT 5,

    -- Etichette per riconoscere le voci che dicono la stessa cosa e non
    -- ripeterle: «saturno», «limite», «esilio».
    etichette  VARCHAR(190) NOT NULL DEFAULT '',

    stato      ENUM('bozza','pubblicato') NOT NULL DEFAULT 'pubblicato',
    creato     DATETIME     NOT NULL,
    aggiornato DATETIME     NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY u_voce (ambito, chiave, registro),
    KEY i_ambito (ambito, registro, stato)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quante volte una voce e' stata effettivamente usata in una relazione.
-- Serve a sapere che cosa scrivere per primo: ha senso scrivere a mano le
-- voci che compaiono in mille carte, non quelle che compaiono in tre.
CREATE TABLE testi_uso (
    ambito  VARCHAR(32)  NOT NULL,
    chiave  VARCHAR(80)  NOT NULL,
    usi     INT UNSIGNED NOT NULL DEFAULT 0,
    ultimo  DATETIME     NOT NULL,
    PRIMARY KEY (ambito, chiave),
    KEY i_usi (usi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
