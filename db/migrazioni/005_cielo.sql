-- Tema e Cielo — F4, il cielo astronomico.
--
-- Catalogo HYG (CC BY-SA 4.0) e linee delle costellazioni di Stellarium
-- (GPL-2.0). Entrambi materiale di terzi: non stanno nel repository, si
-- importano con bin/importa-stelle.php. Vedi docs/FONTI.md.

CREATE TABLE stelle (
    hip           INT UNSIGNED NOT NULL,
    nome          VARCHAR(60)  NULL,           -- nome proprio, quando ne ha uno
    bayer         VARCHAR(12)  NOT NULL DEFAULT '',
    flamsteed     SMALLINT     NULL,
    costellazione CHAR(3)      NOT NULL DEFAULT '',
    -- Ascensione retta e declinazione all'epoca J2000, in gradi. La
    -- precessione alla data si applica al momento del disegno: una stella si
    -- sposta di circa un grado ogni settant'anni.
    ar            DOUBLE       NOT NULL,
    decl          DOUBLE       NOT NULL,
    mag           FLOAT        NOT NULL,
    ci            FLOAT        NULL,           -- indice di colore B-V: da' la tinta
    spettro       VARCHAR(20)  NOT NULL DEFAULT '',
    distanza      FLOAT        NULL,           -- parsec
    PRIMARY KEY (hip),
    KEY i_mag (mag),
    KEY i_costellazione (costellazione)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE costellazioni (
    abbr      CHAR(3)     NOT NULL,
    nome_it   VARCHAR(60) NOT NULL,
    nome_lat  VARCHAR(60) NOT NULL,
    genitivo  VARCHAR(60) NOT NULL DEFAULT '',
    PRIMARY KEY (abbr)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ogni riga e' un segmento fra due stelle. Le polilinee di Stellarium vengono
-- spezzate in coppie: cosi' il disegno puo' saltare i segmenti che hanno un
-- estremo sotto l'orizzonte senza dover ricostruire la spezzata.
CREATE TABLE costellazioni_linee (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    costellazione CHAR(3)      NOT NULL,
    hip_a         INT UNSIGNED NOT NULL,
    hip_b         INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY i_costellazione (costellazione),
    KEY i_stelle (hip_a, hip_b)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
