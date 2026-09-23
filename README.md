# Tema e Cielo

Portale astrologico e astronomico. Il visitatore lascia data, ora e luogo di nascita; il
portale restituisce il **tema natale disegnato**, la **volta celeste reale** di quell'istante
da quel punto della Terra, e tutto ciò che da quei tre dati si può calcolare — posizioni,
case, aspetti, dignità, bilanci, effemeridi del giorno — più una **lettura in due registri
affiancati**, quello tradizionale e quello moderno.

Le posizioni vengono dalla **Swiss Ephemeris**, che poggia sulle effemeridi JPL DE431 della
NASA. Niente è approssimato e niente è inventato.

> L'astrologia è una tradizione simbolica e culturale, non una scienza predittiva. Questo
> software calcola posizioni astronomiche reali — verificabili al secondo d'arco — e vi
> applica un linguaggio interpretativo storico. La metà astronomica, quella sì, è scienza.

---

## Che cosa fa

| | |
|---|---|
| **Tema natale** | 10 pianeti più Chirone, Lilith, Nodi, Vertex, Parte di Fortuna e i quattro asteroidi maggiori. Undici sistemi di case. Aspetti maggiori e minori, con applicativo/separativo. Dignità essenziali e accidentali col punteggio di Lilly. Paralleli di declinazione, antiscia, configurazioni. |
| **La ruota** | SVG generato dal server: funziona senza JavaScript, si stampa nitida, si scarica come file unico. Anti-collisione dei glifi con linea guida al grado vero. |
| **La volta celeste** | Il cielo *vero* di quell'istante: 8.900 stelle fino alla sesta magnitudine e mezzo, 88 costellazioni, eclittica, pianeti, la Luna nella fase e nell'inclinazione giuste, e il colore del cielo che segue l'altezza del Sole. |
| **Il cielo, da dove e quando vuoi** | La volta si ingrandisce fino a dodici volte e si sposta — rotella, trascinamento, pizzico, tastiera — agendo sul `viewBox` dell'SVG: nessuna richiesta al server, e ingrandire mostra davvero piu' dettaglio invece di sgranare. Luogo a scelta fra 287.039, dalla ricerca per nome o dalla mappa; data e ora dal 1800 al 2399, con salti di dieci minuti, un'ora, un giorno. Ogni cielo ha un indirizzo proprio, che si salva e si manda. |
| **La lettura** | Due registri affiancati — tradizionale (dignità, signorie, Lilly) e moderno (archetipi, Rudhyar) — montati per rilevanza, non concatenati. |
| **Sinastria** | Rapida segno-contro-segno, o completa carta-contro-carta: aspetti incrociati, sovrapposizione delle case, composita, quattro punteggi per area. |
| **Carte del tempo** | Transiti su data scelta, rivoluzione solare, progressioni secondarie, direzioni di arco solare, profezioni annuali. |
| **Comunità** | Guestbook con due voti distinti (gradimento e attinenza), moderazione, statistiche pubbliche aggregate. |
| **Regia** | Pannello per corpus, pagine redazionali, impostazioni, blocchi, registro accessi geolocalizzato offline. |

**330 prove di regressione**, che non confrontano i risultati con altri programmi di
astrologia — potrebbero sbagliare insieme — ma con fatti verificabili: agli equinozi il Sole
risulta a 0° entro un centesimo di grado, la Stella Polare sta a un'altezza pari alla
latitudine, a Longyearbyen il Sole non tramonta a giugno.

Funziona su telefono e su tablet quanto su un monitor: la barra delle sezioni si richiude,
le tabelle larghe scorrono dentro il proprio riquadro invece di sfondare la pagina, e dove
si tocca invece di puntare i bersagli si allargano senza che cambi il disegno.

---

## Licenza: AGPL-3.0, e non è un dettaglio

**Attenzione: è AGPL e non GPL.** Il motore di calcolo è la Swiss Ephemeris, distribuita in
doppia licenza AGPL-3.0 / commerciale. L'AGPL aggiunge alla GPL una clausola che riguarda
esattamente il caso di un portale web: **chi usa il programma attraverso la rete ha diritto di
riceverne il sorgente**, anche senza che il programma gli venga distribuito.

In pratica, se metti online questo portale:

1. devi rendere disponibile il sorgente completo, comprese le tue modifiche;
2. devi metterne il collegamento **in una pagina visibile del portale** — nel modello c'è già,
   nel piè di pagina.

Se questo non ti va bene, l'unica alternativa è acquistare una licenza commerciale della Swiss
Ephemeris da Astrodienst.

---

## Installazione

### Che cosa serve

- PHP **8.4** con `pdo_mysql`, `intl`, `mbstring`, `gd`, `sodium`, `FFI`
- **MariaDB 10.6+** (sviluppato su 11.8)
- **Apache** con `mod_rewrite`, `mod_headers` e — consigliato — `mod_deflate`
- I pacchetti Debian `libswe2.0` e `swe-basic-data`
- Circa **1 GB** di spazio per il database, se importi tutti i dati di riferimento

Nessun Composer, nessun npm: niente dipendenze da scaricare, tutto il codice è qui.

### Due comandi

```bash
git clone https://github.com/Indr1d-C0ld/TemaECielo.git
cd TemaECielo

sudo bash deploy/00-bootstrap.sh    # pacchetti, cartelle, database, conf Apache
bash deploy/01-installa.sh          # codice, migrazioni, password dell'amministratore
```

Il primo chiede i permessi di root perché installa pacchetti, scrive in
`/etc/apache2/conf-available/` e crea il database. **Il secondo non va lanciato con sudo.**

I percorsi si deducono dalla radice web e si possono forzare:

```bash
TEC_WEBROOT=/srv/www TEC_DIR=/srv/www/astro TEC_CONFIG_DIR=/etc/temaecielo \
  sudo -E bash deploy/00-bootstrap.sh
```

La password dell'amministratore si sceglie in modo interattivo e **non viene scritta in nessun
file**: ne resta solo un hash Argon2id nel database.

```bash
php bin/console.php admin:password     # per cambiarla
```

### I dati di riferimento

Non sono nel repository: sono lavoro di altri, e l'AGPL copre il nostro codice, non ci
autorizza a ridistribuire il loro. Si scaricano a parte — tutto in `docs/FONTI.md`.

| cosa | da dove | peso | serve a |
|---|---|---|---|
| gazetteer | GeoNames (CC BY 4.0) | ~55 MB | cercare i luoghi di nascita |
| catalogo stellare | HYG (CC BY-SA 4.0) | ~32 MB | disegnare la volta celeste |
| costellazioni | Stellarium (GPL-2.0) | ~200 KB | le figure in cielo |
| geolocalizzazione IP | DB-IP Lite (CC BY 4.0) | ~89 MB | il registro accessi |

Il portale funziona anche senza: la ricerca dei luoghi resterà vuota e il cielo senza stelle,
ma calcoli e disegno della ruota non ne risentono.

---

## Console di servizio

```
php bin/console.php stato            configurazione, database, Swiss Ephemeris
php bin/console.php migra            applica le migrazioni pendenti
php bin/console.php migra:stato      elenca applicate e pendenti
php bin/console.php admin:password   crea o cambia la password dell'amministratore
php bin/console.php partizioni       aggiunge le partizioni mensili ad `accessi`
php bin/console.php cache:purga      butta la cache del motore    [giorni, 0 = tutta]
php bin/console.php astro            stato del motore astronomico
php bin/console.php astro:prova      calcola una carta di prova   [AAAA-MM-GG HH:MM lat lon]

php bin/importa-luoghi.php           gazetteer GeoNames
php bin/importa-stelle.php           catalogo stellare e costellazioni
php bin/importa-geoip.php            geolocalizzazione degli indirizzi
php bin/importa-corpus.php           testi interpretativi (--sostituisci per riallineare)
```

### La cache, e perché va tenuta d'occhio

La tabella `calcoli` fa due mestieri. I **permalink** sono l'unica copia di una carta — chi ne
perde l'indirizzo perde la carta — e non si buttano mai. Le righe di **cache** sono calcoli già
fatti, tenuti per non rifarli, e pesano una trentina di kilobyte l'una.

Finché la volta mostrava solo «adesso» la crescita aveva un tetto naturale. Da quando si può
chiedere il cielo di una data e di un luogo qualunque non ce l'ha più: trentamila richieste
fanno un gigabyte. Il portale se ne difende da solo — una scrittura su duecento butta ciò che
nessuno richiede da oltre trenta giorni — ma il pannello di manutenzione mostra i numeri, e
`cache:purga` fa pulizia subito. **In nessuno dei due casi i permalink vengono toccati.**

Le prove si lanciano una per una, e nessuna lascia traccia nel database — quelle che devono
scriverci lo fanno dentro una transazione che poi annullano:

```bash
for t in tests/test_*.php; do php "$t"; done
```

---

## Impianto

```
index.php            front controller unico
src/
  Core/              configurazione, database, instradamento, viste, CSP, migrazioni
  Astro/             il motore: catalogo, effemeridi via FFI, aspetti, dignità,
                     bilanci, configurazioni, sinastria, carte derivate
  Cielo/             precessione, coordinate orizzontali, costellazioni
  Grafica/           generatori SVG: ruota (singola e doppia), volta celeste, griglia
  Luogo/             gazetteer offline, normalizzazione dei nomi, fusi orari storici
  Corpus/            frammenti, composizione, preposizioni articolate, montaggio
  Controllers/       una classe per faccia del portale
  Admin/             pannello di regia
  Support/           telemetria, impostazioni, Markdown, indirizzi di rete
views/               PHP puro, nessun templating
assets/              css, js, sprite dei glifi (disegnati, non Unicode), Leaflet
                     js/luoghi.js   completamento automatico, condiviso fra due pagine
                     js/cielo.js    volta navigabile + quadro «da dove e quando»
bin/                 console, worker delle effemeridi, importatori
db/migrazioni/       SQL numerato, applicato una volta sola
db/semi/             il corpus interpretativo
deploy/              bootstrap, installazione, conf Apache
docs/                DESIGN.md (il progetto per esteso), FONTI.md
tests/               330 prove di regressione
```

---

## Le decisioni che spiegano il resto

### La volta si sfoglia senza tornare al server

Ingrandire il cielo non chiede niente a nessuno: il disegno è un SVG, e spostare il suo
`viewBox` mostra le stelle deboli e i nomi che erano già lì, alla risoluzione dello schermo
invece che a quella di un'immagine. Rotella, trascinamento, pizzico e tastiera fanno tutti la
stessa cosa — cambiano quattro numeri.

Il punto sotto il dito resta fermo mentre si ingrandisce, e il riquadro non si stacca mai dal
bordo del disegno. Questo secondo vincolo è meno ovvio del primo: senza, dopo tre gesti ci si
ritrova a guardare il vuoto senza capire dove sia finito il cielo.

Il tocco è trattato in due modi a seconda dello stato. Finché la volta è intera non c'è niente
da spostare, quindi il dito scorre la pagina; appena si ingrandisce, il comando passa al
riquadro. Bloccarlo sempre aprirebbe in mezzo allo schermo di un telefono una zona morta alta
trecento pixel.

### Ogni cielo ha un indirizzo

Luogo, data e ora stanno nella stringa di ricerca, non nella sessione. Ne discendono tre cose
che non sarebbero venute gratis altrimenti: un cielo si può salvare e mandare a qualcuno; i
salti nel tempo sono collegamenti veri, quindi funzionano anche con JavaScript spento e si
aprono in una scheda nuova; e la cache per impronta lavora anche qui, perché due persone che
chiedono lo stesso istante dallo stesso posto fanno un calcolo solo.

L'ora si legge **nel fuso del luogo osservato**, non in quello di chi guarda: le 22:30 a Tokyo
sono le 22:30 a Tokyo anche se stai guardando da Milano. È l'unica lettura sensata per una
carta del cielo, e per ottenerla il fuso si ricava dalle coordinate con la stessa catena
storica che usa il modulo di nascita.

### Il calcolo passa dalla riga di comando

PHP sotto Apache ha `ffi.enable=preload`, che blocca l'FFI a meno di modificare `php.ini` da
root. In **PHP CLI l'FFI è sempre attivo**, senza configurazione. Perciò il livello web invoca
`bin/effemeridi.php` con `proc_open` e gli parla in JSON. Niente modifiche a `php.ini`, niente
compilatore, niente demoni da tenere vivi. Il costo è un fork da qualche millisecondo, pagato
una volta per carta e poi mai più grazie alla cache per impronta.

### Il disegno esce dal server

La ruota del tema e la volta celeste sono **SVG generati in PHP**. Funzionano senza
JavaScript, si stampano nitidi a qualsiasi misura, si scaricano come file unico, entrano in un
PDF. Il JavaScript aggiunge comodità — evidenziazioni, mappa, completamento automatico — mai
contenuto.

### Il fuso orario storico

È la prima causa di temi natali sbagliati al mondo. Una nascita del 15 giugno 1943 a Milano
non è «UTC+1»: fra il 1916 e il 1920 e fra il 1940 e il 1948 l'ora legale italiana cambiava
regole quasi ogni anno, **dal 1949 al 1965 non esisteva affatto**, e prima del 1893 si andava
a ora locale media. Il portale non calcola niente a mano: interroga il `tzdata` di sistema, che
sa tutto questo, e riconosce i due casi all'anno in cui un'ora locale **non identifica un
istante** — quella ripetuta al ritorno dall'ora legale e quella saltata all'andata.

### L'ora ignota è un caso di prima classe

Buona parte dei visitatori non sa a che ora è nata. Il portale non rifiuta e non finge:
calcola una **carta solare** dichiarata, attenua visivamente tutto ciò che dipende dall'ora, e
mostra **l'arco percorso nelle ventiquattro ore** — «quel giorno la Luna è restata in Gemelli
comunque» è un'informazione vera e utile.

### Niente esce da questo server

La ricerca dei luoghi è offline su un gazetteer locale: mentre qualcuno digita il proprio
luogo di nascita non esce un carattere. La geolocalizzazione degli indirizzi è offline. Non ci
sono captcha di terzi, font remoti o strumenti di statistica esterni. L'unica origine remota
sono le tessere della mappa Esri, e solo nella pagina del modulo.

### I glifi sono disegnati

I caratteri Unicode ☉☽☿ vengono resi in modo incoerente da sistema a sistema — su qualche
dispositivo diventano emoji colorate, su altri mancano, e per gli asteroidi spesso non
esistono affatto. Lo sprite è disegnato e incorporato in pagina.

### Il corpus è a frammenti

Scrivere a mano tutte le voci interpretative sono migliaia di schede. Qui **ottantadue
frammenti** — dieci pianeti, dodici segni, dodici case, cinque aspetti, per due registri —
compongono le 465 voci per registro che servono a leggere una carta qualunque: copertura
totale dal primo giorno, nessuna carta esce muta. Le voci scritte a mano scavalcano sempre il
composto, e il pannello dice quali conviene scrivere per prime.

Comporre in italiano non è concatenare stringhe: `Corpus\Lingua` contrae le preposizioni
articolate («in» + «il bisogno» → «nel bisogno») e lascia stare i nomi propri («a Mercurio»,
non «al Mercurio»).

---

## Privacy

Il portale tratta insieme **data-ora-luogo di nascita** e **indirizzi IP**. Chi lo mette online
è il titolare del trattamento. Il software offre, già pronti:

- **informativa** come pagina redazionale, richiamata sotto il modulo e sotto il guestbook;
- **cancellazione autonoma** del proprio calcolo tramite il permalink, senza account;
- **esportazione** del proprio calcolo;
- **purga programmata** del registro accessi (`privacy.purga_accessi_giorni`);
- **anonimizzazione** dell'IP (`privacy.anonimizza_ip`).

Nel modello di configurazione le ultime due sono **disattivate**: la scelta è di chi installa.

I permalink portano `X-Robots-Tag: noindex`. Un tema natale con nome e cognome indicizzato dai
motori di ricerca sarebbe un problema serio, ed è escluso per costruzione.

I dati della seconda persona in una sinastria **non vengono archiviati**: chi partecipa a un
confronto non ha scelto di essere nel portale.

---

## Sicurezza

- **CSP con nonce** dal primo giorno: nessuno stile inline, nessun `onclick`, nessuno script
  incorporato.
- **Argon2id** per la password dell'amministratore, con blocco dopo cinque tentativi falliti.
- **CSRF** su ogni modulo, **prepared statement** su ogni query.
- Il Markdown delle pagine redazionali **scappa il testo prima di qualunque trasformazione**:
  l'HTML non passa, e i collegamenti `javascript:` vengono neutralizzati.
- Il guestbook si difende con campo esca, cronometro minimo, tetto orario per indirizzo,
  parole vietate e blocchi — **senza captcha di terzi**.
- `src/`, `db/`, `bin/`, `config/`, `deploy/`, `views/`, `storage/`, `docs/` e `fonti/` sono
  negati dal web dalla conf Apache.

---

## Riconoscimenti

- **Swiss Ephemeris** — Astrodienst AG, AGPL-3.0 / commerciale
- **GeoNames** — CC BY 4.0
- **HYG Database** — David Nash / astronexus, CC BY-SA 4.0
- **Stellarium** — GPL-2.0, per le figure delle costellazioni
- **DB-IP Lite** — CC BY 4.0
- **Leaflet** — BSD-2-Clause · tessere **Esri**
