-- Tema e Cielo — F7, comunita' e regia.

-- ---------------------------------------------------------------------------
-- Guestbook
--
-- Due voti DISTINTI, e tenerli separati e' la scelta giusta: «mi e' piaciuto»
-- e «mi ci sono riconosciuto» sono due giudizi diversi, e mescolarli
-- renderebbe inutili tutti e due. L'attinenza, aggregata per segno solare e
-- per ascendente, e' il grafico piu' interessante delle statistiche pubbliche.
-- ---------------------------------------------------------------------------

CREATE TABLE guestbook (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- Il messaggio puo' essere agganciato alla carta che l'ha generato: solo
    -- cosi' il voto di attinenza acquista un riferimento verificabile.
    calcolo_id      BIGINT UNSIGNED NULL,
    nome            VARCHAR(80)  NOT NULL DEFAULT '',
    messaggio       TEXT         NOT NULL,
    voto_gradimento TINYINT UNSIGNED NULL,
    voto_attinenza  TINYINT UNSIGNED NULL,
    stato           ENUM('coda','approvato','rifiutato','cestino') NOT NULL DEFAULT 'coda',
    ip              VARCHAR(45)  NOT NULL DEFAULT '',
    sessione        CHAR(32)     NULL,
    ua              VARCHAR(500) NOT NULL DEFAULT '',
    paese           CHAR(2)      NULL,
    creato          DATETIME     NOT NULL,
    moderato_il     DATETIME     NULL,
    moderato_da     VARCHAR(64)  NOT NULL DEFAULT '',
    nota_admin      VARCHAR(500) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY i_stato (stato, creato),
    KEY i_creato (creato),
    KEY i_ip (ip),
    KEY i_calcolo (calcolo_id),
    CONSTRAINT fk_gb_calcolo FOREIGN KEY (calcolo_id) REFERENCES calcoli (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE guestbook_risposte (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    messaggio_id BIGINT UNSIGNED NOT NULL,
    corpo       TEXT         NOT NULL,
    autore      VARCHAR(64)  NOT NULL DEFAULT '',
    creato      DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY i_messaggio (messaggio_id),
    CONSTRAINT fk_gbr_messaggio FOREIGN KEY (messaggio_id) REFERENCES guestbook (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Geolocalizzazione degli indirizzi — DB-IP Lite (CC BY 4.0)
--
-- OFFLINE, e non e' un dettaglio: interrogare un servizio esterno per
-- geolocalizzare i visitatori vorrebbe dire spedire a terzi l'indirizzo di
-- ognuno di loro. Sarebbe il colmo, per un portale che si preoccupa di non
-- far uscire nemmeno il luogo di nascita.
--
-- Gli indirizzi si conservano SEMPRE su sedici byte, anche gli IPv4, nella
-- forma mappata ::ffff:a.b.c.d. Mescolare chiavi da quattro e da sedici byte
-- nella stessa colonna romperebbe ogni confronto d'intervallo.
-- ---------------------------------------------------------------------------

CREATE TABLE geoip_reti (
    ip_da    VARBINARY(16) NOT NULL,
    ip_a     VARBINARY(16) NOT NULL,
    paese    CHAR(2)      NOT NULL DEFAULT '',
    regione  VARCHAR(80)  NOT NULL DEFAULT '',
    citta    VARCHAR(120) NOT NULL DEFAULT '',
    lat      DECIMAL(9,6) NULL,
    lon      DECIMAL(9,6) NULL,
    PRIMARY KEY (ip_da)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE geoip_asn (
    ip_da          VARBINARY(16) NOT NULL,
    ip_a           VARBINARY(16) NOT NULL,
    asn            INT UNSIGNED NOT NULL DEFAULT 0,
    organizzazione VARCHAR(160) NOT NULL DEFAULT '',
    PRIMARY KEY (ip_da)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Impostazioni di partenza
-- ---------------------------------------------------------------------------

INSERT INTO impostazioni (chiave, valore, tipo, descrizione, aggiornata) VALUES
 ('guestbook_attivo',       '1', 'booleano', 'Il guestbook accetta nuovi messaggi', NOW()),
 ('guestbook_moderazione',  '1', 'booleano', 'I messaggi passano dalla coda prima di comparire', NOW()),
 ('guestbook_tetto_ora',    '3', 'intero',   'Messaggi al massimo per indirizzo ogni ora', NOW()),
 ('guestbook_parole',       '', 'testo',     'Parole vietate, separate da virgola', NOW()),
 ('statistiche_pubbliche',  '1', 'booleano', 'Le statistiche sono visibili a tutti', NOW()),
 ('manutenzione',           '0', 'booleano', 'Modalita'' manutenzione: solo l''admin puo'' entrare', NOW()),
 ('sinastria_attiva',       '1', 'booleano', 'La sinastria e'' accessibile', NOW());
