-- Tema e Cielo — F2, gazetteer dei luoghi.
--
-- Dati di GeoNames (CC BY 4.0), importati con bin/importa-luoghi.php. Non
-- stanno nel repository: sono lavoro di altri, e l'AGPL copre il nostro codice,
-- non ci autorizza a ridistribuire il loro.
--
-- La ricerca e' OFFLINE, ed e' una scelta di sostanza: interrogare un servizio
-- esterno mentre qualcuno digita il proprio luogo di nascita vorrebbe dire
-- spedire a terzi, carattere per carattere, un dato personale.

CREATE TABLE luoghi (
    id            INT UNSIGNED NOT NULL,          -- geonameid
    nome          VARCHAR(190) NOT NULL,          -- nome da mostrare, in italiano quando esiste
    nome_geonames VARCHAR(190) NOT NULL,          -- come lo chiama GeoNames (spesso in inglese)
    nome_ascii    VARCHAR(190) NOT NULL DEFAULT '',
    paese         CHAR(2)      NOT NULL,
    paese_nome    VARCHAR(80)  NOT NULL DEFAULT '',
    admin1        VARCHAR(20)  NOT NULL DEFAULT '',
    admin1_nome   VARCHAR(120) NOT NULL DEFAULT '',
    admin2        VARCHAR(80)  NOT NULL DEFAULT '',
    admin2_nome   VARCHAR(120) NOT NULL DEFAULT '',
    lat           DECIMAL(9,6) NOT NULL,
    lon           DECIMAL(9,6) NOT NULL,
    altitudine    SMALLINT     NOT NULL DEFAULT 0,
    popolazione   INT UNSIGNED NOT NULL DEFAULT 0,
    fuso          VARCHAR(64)  NOT NULL DEFAULT '',
    codice        VARCHAR(10)  NOT NULL DEFAULT '',   -- PPL, PPLA, PPLC...
    PRIMARY KEY (id),
    KEY i_paese (paese, popolazione),
    KEY i_popolazione (popolazione)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un luogo ha molti nomi: quello ufficiale, gli esonimi, le sigle, le
-- traslitterazioni. Si indicizza `nome_norm` — minuscolo, senza accenti e
-- senza punteggiatura — cosi' che «citta' di castello», «Citta di Castello» e
-- «CITTÀ DI CASTELLO» trovino la stessa riga.
--
-- L'indice e' sui primi 24 caratteri: basta e avanza per un completamento
-- automatico, e tiene l'indice una frazione di quello che sarebbe sull'intera
-- colonna, con oltre un milione di righe.
CREATE TABLE luoghi_alias (
    id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    luogo_id  INT UNSIGNED NOT NULL,
    nome      VARCHAR(190) NOT NULL,
    nome_norm VARCHAR(190) NOT NULL,
    lingua    VARCHAR(7)   NOT NULL DEFAULT '',
    preferito TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY i_norm (nome_norm(24), luogo_id),
    KEY i_luogo (luogo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quando l'import e' andato a buon fine e con che cosa: serve a sapere se il
-- gazetteer e' aggiornato senza doverlo contare ogni volta.
CREATE TABLE luoghi_import (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quando    DATETIME     NOT NULL,
    sorgente  VARCHAR(120) NOT NULL,
    luoghi    INT UNSIGNED NOT NULL DEFAULT 0,
    alias     INT UNSIGNED NOT NULL DEFAULT 0,
    durata_s  INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
