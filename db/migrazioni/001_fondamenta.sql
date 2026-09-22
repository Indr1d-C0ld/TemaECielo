-- Tema e Cielo — F0, fondamenta.
-- Amministrazione, pagine redazionali, impostazioni e telemetria.
-- Le tabelle del motore astrologico arrivano con le migrazioni successive.

-- ---------------------------------------------------------------------------
-- Amministrazione
-- ---------------------------------------------------------------------------

CREATE TABLE amministratori (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    utente         VARCHAR(64)  NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    attivo         TINYINT(1)   NOT NULL DEFAULT 1,
    creato         DATETIME     NOT NULL,
    ultimo_accesso DATETIME     NULL,
    ultimo_ip      VARCHAR(45)  NULL,
    PRIMARY KEY (id),
    UNIQUE KEY u_utente (utente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tentativi di accesso: servono al blocco progressivo dopo cinque fallimenti.
CREATE TABLE accessi_admin (
    id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quando    DATETIME    NOT NULL,
    ip        VARCHAR(45) NOT NULL,
    utente    VARCHAR(64) NOT NULL,
    riuscito  TINYINT(1)  NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY i_ip_quando (ip, quando),
    KEY i_quando (quando)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ogni azione dell'admin lascia traccia. E' la ragione per cui questo portale
-- ha credenziali proprie invece di un .htpasswd condiviso.
CREATE TABLE admin_registro (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quando       DATETIME     NOT NULL,
    admin_id     INT UNSIGNED NULL,
    admin_utente VARCHAR(64)  NOT NULL,
    azione       VARCHAR(64)  NOT NULL,
    oggetto      VARCHAR(128) NOT NULL DEFAULT '',
    dettaglio    VARCHAR(1000) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY i_quando (quando),
    KEY i_azione (azione, quando)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Regia del portale
-- ---------------------------------------------------------------------------

CREATE TABLE impostazioni (
    chiave     VARCHAR(64) NOT NULL,
    valore     TEXT        NOT NULL,
    tipo       ENUM('testo','intero','booleano','json') NOT NULL DEFAULT 'testo',
    descrizione VARCHAR(255) NOT NULL DEFAULT '',
    aggiornata DATETIME    NOT NULL,
    PRIMARY KEY (chiave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pagine (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(80)  NOT NULL,
    titolo      VARCHAR(160) NOT NULL,
    sottotitolo VARCHAR(255) NOT NULL DEFAULT '',
    corpo       MEDIUMTEXT   NOT NULL,
    stato       ENUM('bozza','pubblicata') NOT NULL DEFAULT 'bozza',
    in_menu     TINYINT(1)   NOT NULL DEFAULT 0,
    ordine      SMALLINT     NOT NULL DEFAULT 0,
    creata      DATETIME     NOT NULL,
    aggiornata  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY u_slug (slug),
    KEY i_menu (stato, in_menu, ordine)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Telemetria
--
-- `accessi` conserva l'IP completo senza scadenza: e' una scelta esplicita.
-- Per reggerla la tabella e' PARTIZIONATA PER MESE — senza partizioni, a
-- milioni di righe anche un semplice «ultime 50 visite» diventa una scansione
-- completa. Le nuove partizioni le aggiunge `php bin/console.php partizioni`.
-- ---------------------------------------------------------------------------

CREATE TABLE sessioni (
    id           CHAR(32)     NOT NULL,
    prima_vista  DATETIME     NOT NULL,
    ultima_vista DATETIME     NOT NULL,
    pagine       INT UNSIGNED NOT NULL DEFAULT 0,
    calcoli      INT UNSIGNED NOT NULL DEFAULT 0,
    ip           VARCHAR(45)  NOT NULL,
    paese        CHAR(2)      NULL,
    ua_famiglia  VARCHAR(40)  NULL,
    ua_so        VARCHAR(40)  NULL,
    dispositivo  ENUM('desktop','mobile','tablet','bot','ignoto') NOT NULL DEFAULT 'ignoto',
    bot          TINYINT(1)   NOT NULL DEFAULT 0,
    ingresso     VARCHAR(255) NOT NULL DEFAULT '',
    referente    VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY i_ultima (ultima_vista),
    KEY i_ip (ip),
    KEY i_bot (bot, prima_vista)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE accessi (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quando         DATETIME     NOT NULL,
    sessione       CHAR(32)     NULL,
    ip             VARCHAR(45)  NOT NULL,
    ip_binario     VARBINARY(16) NULL,
    paese          CHAR(2)      NULL,
    regione        VARCHAR(80)  NULL,
    citta          VARCHAR(120) NULL,
    lat            DECIMAL(9,6) NULL,
    lon            DECIMAL(9,6) NULL,
    asn            INT UNSIGNED NULL,
    operatore      VARCHAR(160) NULL,
    metodo         VARCHAR(8)   NOT NULL,
    percorso       VARCHAR(255) NOT NULL,
    parametri      VARCHAR(255) NOT NULL DEFAULT '',
    stato          SMALLINT UNSIGNED NOT NULL,
    byte_inviati   INT UNSIGNED NOT NULL DEFAULT 0,
    durata_ms      INT UNSIGNED NOT NULL DEFAULT 0,
    referente      VARCHAR(255) NOT NULL DEFAULT '',
    lingua         VARCHAR(100) NOT NULL DEFAULT '',
    ua             VARCHAR(500) NOT NULL DEFAULT '',
    ua_famiglia    VARCHAR(40)  NULL,
    ua_so          VARCHAR(40)  NULL,
    dispositivo    ENUM('desktop','mobile','tablet','bot','ignoto') NOT NULL DEFAULT 'ignoto',
    bot            TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (id, quando),
    KEY i_quando (quando),
    KEY i_ip (ip, quando),
    KEY i_sessione (sessione),
    KEY i_percorso (percorso(64), quando),
    KEY i_bot (bot, quando)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
PARTITION BY RANGE COLUMNS (quando) (
    PARTITION p2026_09 VALUES LESS THAN ('2026-10-01'),
    PARTITION p2026_10 VALUES LESS THAN ('2026-11-01'),
    PARTITION p2026_11 VALUES LESS THAN ('2026-12-01'),
    PARTITION p2026_12 VALUES LESS THAN ('2027-01-01'),
    PARTITION p2027_01 VALUES LESS THAN ('2027-02-01'),
    PARTITION p2027_02 VALUES LESS THAN ('2027-03-01'),
    PARTITION p2027_03 VALUES LESS THAN ('2027-04-01'),
    PARTITION p2027_04 VALUES LESS THAN ('2027-05-01'),
    PARTITION p2027_05 VALUES LESS THAN ('2027-06-01'),
    PARTITION p2027_06 VALUES LESS THAN ('2027-07-01'),
    PARTITION p2027_07 VALUES LESS THAN ('2027-08-01'),
    PARTITION p2027_08 VALUES LESS THAN ('2027-09-01'),
    PARTITION p2027_09 VALUES LESS THAN ('2027-10-01'),
    PARTITION p2027_10 VALUES LESS THAN ('2027-11-01'),
    PARTITION p2027_11 VALUES LESS THAN ('2027-12-01'),
    PARTITION p2027_12 VALUES LESS THAN ('2028-01-01'),
    PARTITION pMAX     VALUES LESS THAN (MAXVALUE)
);

-- Eventi applicativi: l'imbuto del modulo di nascita, le sezioni usate, gli
-- errori del motore. Piu' fine degli accessi e senza dati personali.
CREATE TABLE eventi (
    id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quando    DATETIME     NOT NULL,
    sessione  CHAR(32)     NULL,
    tipo      VARCHAR(48)  NOT NULL,
    oggetto   VARCHAR(128) NOT NULL DEFAULT '',
    valore    VARCHAR(255) NOT NULL DEFAULT '',
    durata_ms INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY i_tipo (tipo, quando),
    KEY i_quando (quando),
    KEY i_sessione (sessione)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aggregati giornalieri precalcolati: le pagine pubbliche di statistica non
-- devono mai interrogare `accessi`, che e' la tabella grande.
CREATE TABLE statistiche_giorno (
    giorno        DATE         NOT NULL,
    metrica       VARCHAR(64)  NOT NULL,
    chiave        VARCHAR(64)  NOT NULL DEFAULT '',
    valore        BIGINT       NOT NULL DEFAULT 0,
    valore_reale  DOUBLE       NULL,
    PRIMARY KEY (giorno, metrica, chiave),
    KEY i_metrica (metrica, giorno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blocchi (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cidr     VARCHAR(45)  NOT NULL,
    motivo   VARCHAR(255) NOT NULL DEFAULT '',
    creato   DATETIME     NOT NULL,
    scade    DATETIME     NULL,
    creato_da VARCHAR(64) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY u_cidr (cidr),
    KEY i_scade (scade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
