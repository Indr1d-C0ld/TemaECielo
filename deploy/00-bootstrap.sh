#!/usr/bin/env bash
#
# Tema e Cielo — bootstrap dell'ambiente.
# Da eseguire UNA VOLTA con sudo:   sudo bash deploy/00-bootstrap.sh
#
# Idempotente: si puo' rilanciare senza danni. Non tocca i vhost esistenti (usa
# conf-available/a2enconf), fa backup di cio' che sostituisce, e verifica la
# configurazione Apache con apache2ctl configtest PRIMA del reload.
#
# Cosa fa:
#   1. installa la Swiss Ephemeris (libswe2.0 + swe-basic-data) e ne trova la .so
#   2. prepara la cartella del progetto sotto la radice web, scrivibile dal
#      proprietario e di gruppo www-data
#   3. crea la cartella dei segreti FUORI dal DocumentRoot
#   4. crea database e utente MariaDB tec_temaecielo con password generata
#   5. scrive config.php se non esiste (password DB e chiave applicativa generate)
#   6. installa e abilita la conf Apache
#
# NON imposta la password dell'admin: quella si sceglie dopo, con
#   bash deploy/01-installa.sh   (o php bin/console.php admin:password)
# che la chiede in modo interattivo e ne conserva solo l'hash Argon2id.
#
set -euo pipefail

# sudo ripulisce l'ambiente e non tutte le configurazioni di sudoers mettono
# /sbin nel secure_path. Senza questa riga ldconfig, a2enmod, a2enconf e
# apache2ctl non si trovano, e lo script muore prima di creare qualunque cosa.
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

OWNER_USER="${TEC_OWNER:-$(logname 2>/dev/null || echo "${SUDO_USER:-root}")}"
OWNER_GROUP="www-data"
# I percorsi si deducono, non si scrivono: il DocumentRoot cambia da server a
# server, e un percorso fisso qui dentro renderebbe il progetto installabile
# solo sulla macchina di chi l'ha scritto.
#   TEC_DIR         dove installare il codice      (default: <radice web>/temaecielo)
#   TEC_CONFIG_DIR  dove tenere i segreti          (default: accanto alla radice web)
# La radice web va RISOLTA: su molti server /var/www/html e' un collegamento
# simbolico altrove, e prendere il percorso padre senza risolverlo porta la
# cartella dei segreti in un posto che non esiste.
RADICE_WEB="$(readlink -f "${TEC_WEBROOT:-/var/www/html}" 2>/dev/null || echo "${TEC_WEBROOT:-/var/www/html}")"
PROJECT_DIR="${TEC_DIR:-${RADICE_WEB}/temaecielo}"
CONFIG_DIR="${TEC_CONFIG_DIR:-$(dirname "${RADICE_WEB}")/temaecielo-config}"
CONFIG_FILE="${CONFIG_DIR}/config.php"
DB_NAME="tec_temaecielo"
DB_USER="tec_temaecielo"
SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APACHE_CONF_SRC="${SRC_DIR}/deploy/apache-temaecielo.conf"
APACHE_CONF_DST="/etc/apache2/conf-available/temaecielo.conf"
STAMP="$(date +%Y%m%d-%H%M%S)"

TEC_URL="${TEC_URL:-http://localhost/temaecielo}"

say()  { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
ok()   { printf '    \033[0;32m%s\033[0m\n' "$*"; }
warn() { printf '    \033[0;33m%s\033[0m\n' "$*"; }
die()  { printf '\n\033[1;31mERRORE: %s\033[0m\n' "$*" >&2; exit 1; }

[[ "${EUID}" -eq 0 ]] || die "Questo script va eseguito con sudo."
[[ -f "${APACHE_CONF_SRC}" ]] || die "Manca ${APACHE_CONF_SRC}."

# Meglio accorgersi adesso che manca un attrezzo, invece di a meta' strada con
# le cartelle create e il database no.
MANCANTI=()
for c in apt-get dpkg mariadb php openssl a2enconf a2enmod apache2ctl systemctl install; do
  command -v "$c" >/dev/null 2>&1 || MANCANTI+=("$c")
done
if [[ ${#MANCANTI[@]} -gt 0 ]]; then
  die "Comandi non trovati nel PATH: ${MANCANTI[*]}
     PATH attuale: ${PATH}"
fi

# --- 1. Swiss Ephemeris ------------------------------------------------------
say "1/6  Swiss Ephemeris"
NEEDED=()
dpkg -s libswe2.0     >/dev/null 2>&1 || NEEDED+=(libswe2.0)
dpkg -s swe-basic-data >/dev/null 2>&1 || NEEDED+=(swe-basic-data)
if [[ ${#NEEDED[@]} -gt 0 ]]; then
  DEBIAN_FRONTEND=noninteractive apt-get install -y "${NEEDED[@]}"
else
  ok "gia' installata"
fi

# Si chiede al pacchetto dove ha messo la libreria: dpkg -L dice sempre la
# verita' ed e' indipendente dalla cache di ldconfig e dal PATH. Le altre due
# vie restano come rete di sicurezza per installazioni fuori da dpkg.
LIBSWE="$(dpkg -L libswe2.0 2>/dev/null | grep -m1 -E '/libswe\.so\.[0-9]+$' || true)"
[[ -n "${LIBSWE}" ]] || LIBSWE="$(ldconfig -p 2>/dev/null | awk '/libswe\.so/{print $NF; exit}' || true)"
[[ -n "${LIBSWE}" ]] || LIBSWE="$(find /usr/lib /usr/local/lib -name 'libswe.so.*' -type f 2>/dev/null | head -1)"

if [[ -z "${LIBSWE}" || ! -f "${LIBSWE}" ]]; then
  die "libswe.so non trovata dopo l'installazione.
     Cercata con:  dpkg -L libswe2.0 | grep libswe.so
                   ldconfig -p | grep libswe
                   find /usr/lib -name 'libswe.so.*'
     Se il pacchetto risulta installato ma il file manca, prova:
       sudo apt-get install --reinstall libswe2.0"
fi
ok "libreria  ${LIBSWE}"

EPHE=""
FOUND="$(dpkg -L swe-basic-data 2>/dev/null | grep -m1 '\.se1$' || true)"
if [[ -n "${FOUND}" ]]; then
  EPHE="$(dirname "${FOUND}")"
else
  for d in /usr/share/libswe/ephe /usr/share/swisseph /usr/share/libswe; do
    if compgen -G "${d}/*.se1" >/dev/null 2>&1; then EPHE="${d}"; break; fi
  done
fi
if [[ -n "${EPHE}" ]]; then
  ok "effemeridi  ${EPHE}  ($(ls -1 "${EPHE}"/*.se1 2>/dev/null | wc -l) file)"
else
  warn "file .se1 non trovati: il motore ripieghera' sull'effemeride analitica Moshier."
  EPHE="/usr/share/libswe/ephe"
fi

# --- 2. Directory di progetto ------------------------------------------------
say "2/6  Directory di progetto ${PROJECT_DIR}"
mkdir -p "${PROJECT_DIR}"
chown -R "${OWNER_USER}:${OWNER_GROUP}" "${PROJECT_DIR}"
chmod 2775 "${PROJECT_DIR}"
ok "$(stat -c '%U:%G %a' "${PROJECT_DIR}")  ${PROJECT_DIR}"

# --- 3. Directory dei segreti ------------------------------------------------
say "3/6  Configurazione ${CONFIG_DIR}"
mkdir -p "${CONFIG_DIR}"
chown "${OWNER_USER}:${OWNER_GROUP}" "${CONFIG_DIR}"
chmod 2750 "${CONFIG_DIR}"
ok "$(stat -c '%U:%G %a' "${CONFIG_DIR}")  ${CONFIG_DIR}"

# --- 4. Database -------------------------------------------------------------
say "4/6  Database MariaDB ${DB_NAME}"
if [[ -f "${CONFIG_FILE}" ]]; then
  DB_PASS="$(php -r '$c=require "'"${CONFIG_FILE}"'"; echo $c["db"]["pass"] ?? "";')"
  [[ -n "${DB_PASS}" ]] || die "config.php presente ma senza password DB leggibile."
  APP_KEY="$(php -r '$c=require "'"${CONFIG_FILE}"'"; echo $c["sicurezza"]["chiave"] ?? "";')"
  warn "config.php gia' presente: riuso password e chiave esistenti."
else
  DB_PASS="$(openssl rand -base64 30 | tr -dc 'A-Za-z0-9' | head -c 28)"
  APP_KEY="$(openssl rand -hex 32)"
fi

mariadb -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mariadb -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mariadb -e "ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mariadb -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
mariadb -e "FLUSH PRIVILEGES;"
ok "database e utente pronti"

# --- 5. config.php -----------------------------------------------------------
say "5/6  ${CONFIG_FILE}"
# Se la configurazione esiste gia', NON si riscrive.
#
# Prima si riscriveva sempre, riusando soltanto password e chiave di sessione:
# tutto il resto tornava ai valori predefiniti. Rilanciare il bootstrap — che
# serve anche solo per aggiornare la conf Apache — rimetteva l'indirizzo
# pubblico a «http://localhost», e con lui le scelte sulla privacy
# (conservazione degli accessi, anonimizzazione degli indirizzi) fatte a mano.
# Il codice legge ogni chiave con un valore predefinito, quindi un file scritto
# da una versione precedente resta valido cosi' com'e'.
if [[ -f "${CONFIG_FILE}" ]]; then
  ok "gia' presente: la lascio com'e' (per rigenerarla, spostala altrove e rilancia)"
else
cat > "${CONFIG_FILE}" <<PHPEOF
<?php

declare(strict_types=1);

/**
 * Tema e Cielo — configurazione reale.
 * Generata da deploy/00-bootstrap.sh il ${STAMP}.
 * Fuori dal DocumentRoot. Non finisce in nessun repository.
 */

return [
    'app' => [
        'nome'        => 'Tema e Cielo',
        'env'         => 'production',
        'debug'       => false,
        'timezone'    => 'Europe/Rome',
        'base_path'   => '/temaecielo',
        'url_pubblico'=> '${TEC_URL}',
    ],
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => '${DB_NAME}',
        'user'    => '${DB_USER}',
        'pass'    => '${DB_PASS}',
        'charset' => 'utf8mb4',
    ],
    'sicurezza' => [
        'nome_sessione'    => 'temaecielo_sess',
        'durata_sessione'  => 7200,
        'chiave'           => '${APP_KEY}',
    ],
    'astro' => [
        'libswe'     => '${LIBSWE}',
        'effemeridi' => '${EPHE}',
        'php_cli'    => '$(command -v php)',
        'timeout'    => 20,
    ],
    'privacy' => [
        // Conservazione illimitata degli accessi: scelta esplicita.
        // Per attivare la purga, mettere qui il numero di giorni (es. 90).
        'purga_accessi_giorni' => 0,
        'anonimizza_ip'        => false,
    ],
];
PHPEOF
chown "${OWNER_USER}:${OWNER_GROUP}" "${CONFIG_FILE}"
chmod 0640 "${CONFIG_FILE}"
ok "$(stat -c '%U:%G %a' "${CONFIG_FILE}")  scritto"
fi

# --- 6. Apache ---------------------------------------------------------------
say "6/6  Apache"
if [[ -f "${APACHE_CONF_DST}" ]]; then
  cp -a "${APACHE_CONF_DST}" "${APACHE_CONF_DST}.bak-${STAMP}"
  ok "backup  ${APACHE_CONF_DST}.bak-${STAMP}"
fi
install -m 0644 "${APACHE_CONF_SRC}" "${APACHE_CONF_DST}"
a2enmod rewrite headers >/dev/null 2>&1 || true
a2enconf temaecielo >/dev/null
if apache2ctl configtest 2>&1 | grep -qi 'Syntax OK'; then
  systemctl reload apache2
  ok "conf installata, configtest OK, Apache ricaricato"
else
  a2disconf temaecielo >/dev/null 2>&1 || true
  apache2ctl configtest || true
  die "configtest fallito: conf disabilitata, Apache NON ricaricato."
fi

cat <<FINEMSG

  ────────────────────────────────────────────────────────────────
   Bootstrap completato.

   Ora, come utente normale (non root):

     cd ${SRC_DIR}
     bash deploy/01-installa.sh

   che copia il codice in ${PROJECT_DIR}, applica le migrazioni e
   chiede la password dell'amministratore.
  ────────────────────────────────────────────────────────────────

FINEMSG
