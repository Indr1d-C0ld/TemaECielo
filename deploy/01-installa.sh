#!/usr/bin/env bash
#
# Tema e Cielo — installazione / aggiornamento del codice.
# Da eseguire come utente normale (NON root):   bash deploy/01-installa.sh
#
# Copia il codice dalla cartella di lavoro alla radice web, applica le
# migrazioni e — solo alla prima installazione — chiede la password dell'admin.
# I percorsi si deducono; si forzano con TEC_WEBROOT, TEC_DIR, TEC_CONFIG_DIR.
# Idempotente: rilanciarlo aggiorna il codice senza toccare dati ne' password.
#
set -euo pipefail

export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# Stessi percorsi del bootstrap, dedotti allo stesso modo.
# La radice web va RISOLTA: su molti server /var/www/html e' un collegamento
# simbolico altrove, e prendere il percorso padre senza risolverlo porta la
# cartella dei segreti in un posto che non esiste.
RADICE_WEB="$(readlink -f "${TEC_WEBROOT:-/var/www/html}" 2>/dev/null || echo "${TEC_WEBROOT:-/var/www/html}")"
DST_DIR="${TEC_DIR:-${RADICE_WEB}/temaecielo}"
CONFIG_FILE="${TEC_CONFIG_DIR:-$(dirname "${RADICE_WEB}")/temaecielo-config}/config.php"

say()  { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
ok()   { printf '    \033[0;32m%s\033[0m\n' "$*"; }
die()  { printf '\n\033[1;31mERRORE: %s\033[0m\n' "$*" >&2; exit 1; }

[[ "${EUID}" -ne 0 ]] || die "Questo script NON va eseguito con sudo: scriverebbe file di root."
[[ -f "${CONFIG_FILE}" ]] || die "Manca ${CONFIG_FILE}. Esegui prima: sudo bash deploy/00-bootstrap.sh"
[[ -d "${DST_DIR}" ]]     || die "Manca ${DST_DIR}. Esegui prima: sudo bash deploy/00-bootstrap.sh"
[[ -w "${DST_DIR}" ]]     || die "${DST_DIR} non e' scrivibile da $(whoami)."

MANCANTI=()
for c in rsync php; do
  command -v "$c" >/dev/null 2>&1 || MANCANTI+=("$c")
done
if [[ ${#MANCANTI[@]} -gt 0 ]]; then
  die "Comandi non trovati: ${MANCANTI[*]}
     Installali con:  sudo apt-get install ${MANCANTI[*]}"
fi

say "1/3  Copia del codice in ${DST_DIR}"
rsync -a --delete \
  --exclude '.git/' --exclude 'fonti/' --exclude 'docs/' \
  --exclude 'storage/cache/' --exclude 'storage/log/' \
  --exclude 'config/config.php' \
  "${SRC_DIR}/" "${DST_DIR}/"
# rsync -a conserva il gruppo dell'origine, che e' quello dell'utente: senza
# questa correzione storage/ resta di gruppo utente e il web server non puo'
# scriverci. Il guaio e' che il primo sintomo e' l'ASSENZA di sintomi — gli
# errori non vengono registrati proprio perche' il registro non e' scrivibile.
# Solo le CARTELLE: i file dentro storage/ li ha scritti il web server ed
# appartengono a www-data, quindi un chgrp ricorsivo fallisce su di loro — e
# non serve, perche' il bit setgid sulle cartelle fa ereditare il gruppo
# giusto a tutto cio' che nasce li' dentro.
mkdir -p "${DST_DIR}/storage/cache/svg" "${DST_DIR}/storage/log"
find "${DST_DIR}/storage" -type d -exec chgrp www-data {} + 2>/dev/null || true
find "${DST_DIR}/storage" -type d -exec chmod 2775 {} + 2>/dev/null || true

if ! sudo -u www-data test -w "${DST_DIR}/storage/log" 2>/dev/null; then
  # Senza sudo non si puo' verificare davvero; si controlla almeno il gruppo.
  if [[ "$(stat -c '%G' "${DST_DIR}/storage/log")" != "www-data" ]]; then
    die "${DST_DIR}/storage/log non e' del gruppo www-data: il portale non potrebbe registrare nulla."
  fi
fi
ok "$(find "${DST_DIR}" -type f -name '*.php' | wc -l) file PHP installati"

say "2/3  Migrazioni"
php "${DST_DIR}/bin/console.php" migra

say "3/3  Amministratore"
if php "${DST_DIR}/bin/console.php" admin:esiste >/dev/null 2>&1; then
  ok "account amministratore gia' presente: non lo tocco."
  echo "    Per cambiare la password:  php ${DST_DIR}/bin/console.php admin:password"
else
  php "${DST_DIR}/bin/console.php" admin:password
fi

cat <<FINEMSG

  ────────────────────────────────────────────────────────────────
   Installato. Il portale risponde su:
     $(php -r '$c=require "'"${CONFIG_FILE}"'"; echo $c["app"]["url_pubblico"];')
  ────────────────────────────────────────────────────────────────

FINEMSG
