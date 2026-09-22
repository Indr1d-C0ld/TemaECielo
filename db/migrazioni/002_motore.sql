-- Tema e Cielo — F1, tabelle del motore.
--
-- `calcoli` e' insieme archivio e cache: la chiave e' l'IMPRONTA dei dati di
-- nascita piu' le opzioni. Stessa domanda, stessa riga — si calcola una volta
-- sola e si conta quante volte e' stata chiesta.

CREATE TABLE soggetti (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome          VARCHAR(120) NOT NULL DEFAULT '',
    data_nascita  DATE         NOT NULL,
    ora_nascita   TIME         NULL,
    precisione_ora ENUM('esatta','approssimativa','ignota') NOT NULL DEFAULT 'esatta',
    luogo_nome    VARCHAR(190) NOT NULL DEFAULT '',
    luogo_paese   CHAR(2)      NULL,
    lat           DECIMAL(9,6) NOT NULL,
    lon           DECIMAL(9,6) NOT NULL,
    altitudine    SMALLINT     NOT NULL DEFAULT 0,
    fuso          VARCHAR(64)  NOT NULL DEFAULT 'UTC',
    offset_minuti SMALLINT     NOT NULL DEFAULT 0,
    ora_ut        DECIMAL(9,6) NOT NULL,
    creato        DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY i_data (data_nascita),
    KEY i_luogo (luogo_nome(32))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE calcoli (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    gettone          CHAR(32)     NULL,
    impronta         CHAR(64)     NOT NULL,
    tipo             ENUM('natale','sinastria','transiti','rivoluzione','progressioni') NOT NULL DEFAULT 'natale',
    opzioni          JSON         NULL,
    esito            LONGTEXT     NULL,
    errore           VARCHAR(500) NULL,
    svg              VARCHAR(190) NULL,
    richieste        INT UNSIGNED NOT NULL DEFAULT 1,
    durata_ms        INT UNSIGNED NOT NULL DEFAULT 0,
    creato           DATETIME     NOT NULL,
    ultima_richiesta DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY u_impronta (impronta),
    UNIQUE KEY u_gettone (gettone),
    KEY i_creato (creato),
    KEY i_tipo (tipo, creato)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Una sinastria ha due soggetti: il legame e' molti-a-molti.
CREATE TABLE calcoli_soggetti (
    calcolo_id  BIGINT UNSIGNED NOT NULL,
    soggetto_id INT UNSIGNED    NOT NULL,
    ruolo       ENUM('primo','secondo') NOT NULL DEFAULT 'primo',
    PRIMARY KEY (calcolo_id, soggetto_id),
    KEY i_soggetto (soggetto_id),
    CONSTRAINT fk_cs_calcolo  FOREIGN KEY (calcolo_id)  REFERENCES calcoli (id)  ON DELETE CASCADE,
    CONSTRAINT fk_cs_soggetto FOREIGN KEY (soggetto_id) REFERENCES soggetti (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
