-- 011 — l'archivio: persone, eventi e nazioni
--
-- Una scheda che si aggancia a una carta gia' esistente. La carta resta quello
-- che e' — una riga di `calcoli` con il suo permalink e il suo soggetto — e la
-- scheda le aggiunge cio' che serve a chi la trova in un archivio pubblico:
-- chi o che cosa sia, di che categoria, da quale fonte vengano i dati e quanto
-- ci si possa fidare dell'ora.
--
-- Cosi' una persona celebre, un evento storico e la fondazione di uno Stato
-- hanno gratis tutto quello che il portale sa fare con una carta: la ruota, la
-- volta, le letture, i transiti, le progressioni, le rivoluzioni, i confronti.
--
-- La classe di affidabilita' e' quella di Lois Rodden, lo standard del mestiere:
--   AA  ora da un documento ufficiale (atto di nascita, verbale, registrazione)
--   A   ora dalla persona stessa o da chi c'era
--   B   ora da una biografia
--   C   ora approssimativa o convenzionale
--   DD  fonti in contrasto
--   X   ora ignota (carta solare)

CREATE TABLE IF NOT EXISTS archivio (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    calcolo_id  BIGINT UNSIGNED  NOT NULL,
    slug        VARCHAR(120)     NOT NULL,
    tipo        ENUM('persona','evento','nazione') NOT NULL DEFAULT 'persona',
    nome        VARCHAR(160)     NOT NULL,
    categoria   VARCHAR(40)      NOT NULL DEFAULT '',
    nota        TEXT             NULL,
    fonte       VARCHAR(500)     NOT NULL DEFAULT '',
    url_fonte   VARCHAR(500)     NOT NULL DEFAULT '',
    rodden      ENUM('AA','A','B','C','DD','X') NOT NULL DEFAULT 'C',
    pubblicata  TINYINT(1)       NOT NULL DEFAULT 1,
    creato      DATETIME         NOT NULL,
    aggiornato  DATETIME         NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY u_slug (slug),
    UNIQUE KEY u_calcolo (calcolo_id),
    KEY i_tipo (tipo, pubblicata),
    KEY i_categoria (categoria),
    CONSTRAINT fk_archivio_calcolo FOREIGN KEY (calcolo_id) REFERENCES calcoli (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
