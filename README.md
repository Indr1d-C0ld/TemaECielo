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
| **Tema natale** | 10 pianeti più Chirone, Lilith, Nodi, Vertex, Parte di Fortuna e i quattro asteroidi maggiori. Undici sistemi di case. I cinque aspetti maggiori, con applicativo/separativo (il motore sa calcolare anche i minori, ma le pagine non li mostrano). Dignità essenziali e accidentali col punteggio di Lilly. Paralleli di declinazione, antiscia, configurazioni. |
| **La ruota** | SVG generato dal server: funziona senza JavaScript, si stampa nitida, si scarica come file unico. Anti-collisione dei glifi con linea guida al grado vero. |
| **La volta celeste** | Il cielo *vero* di quell'istante: le 3.229 stelle fino alla magnitudine 5,6 — tutte quelle che un occhio vede sotto un buon cielo — scelte fra le 8.872 del catalogo e disegnate se stanno sopra l'orizzonte, 88 costellazioni, eclittica, pianeti, la Luna nella fase e nell'inclinazione giuste, e il colore del cielo che segue l'altezza del Sole. |
| **Il cielo, da dove e quando vuoi** | La volta si ingrandisce fino a dodici volte e si sposta — rotella, trascinamento, pizzico, tastiera — agendo sul `viewBox` dell'SVG: nessuna richiesta al server, e ingrandire mostra davvero più dettaglio invece di sgranare. Luogo a scelta fra 287.039, dalla ricerca per nome o dalla mappa; data e ora dal 1800 al 2399, con salti di dieci minuti, un'ora, un giorno. Ogni cielo ha un indirizzo proprio, che si salva e si manda. |
| **La lettura** | Due registri affiancati — tradizionale (dignità, signorie, Lilly) e moderno (archetipi, Rudhyar) — montati per rilevanza, non concatenati. |
| **Sinastria** | Rapida segno-contro-segno, o completa carta-contro-carta: aspetti incrociati, sovrapposizione delle case, composita di punti medi, **carta di Davison** — il cielo vero dell'istante a metà fra le due nascite, visto dal punto a metà fra i due luoghi — e quattro punteggi per area. |
| **Carte del tempo** | Transiti su data scelta, rivoluzione solare, progressioni secondarie, direzioni di arco solare, profezioni annuali. |
| **Archivio** | **252 carte** di persone celebri, eventi storici e fondazioni di Stati — 158 persone, 70 eventi, 24 nazioni e istituzioni, dal 1801 al 2022 — calcolate e documentate dalla regia: ognuna con la fonte dei dati e la **classe Rodden** dell'ora, dalla AA dell'atto di nascita alla C dell'ora convenzionale. Si aprono come ogni altra carta. Vedi [L'archivio](#larchivio). |
| **Il mondo** | L'astrologia mondiale. Gli **ingressi** del Sole nei segni cardinali e le **lunazioni** di un anno qualunque, eretti per una capitale a scelta. Il catalogo delle **eclissi** con serie di Saros, punto di massimo, visibilità dal luogo e i gradi che cadono sulle carte dell'archivio. I **cicli dei pianeti lenti** dal 1800 al 2399, con le congiunzioni triple, le mutazioni di Giove e Saturno e l'**indice ciclico di Barbault** in un grafico affiancato agli eventi storici. Le carte di evento, di fondazione e del mondo hanno una **lettura mondiale**: il Sole è chi governa, la Luna il popolo, e i pianeti sugli angoli vengono per primi. Vedi [Il mondo](#il-mondo). |
| **Comunità** | Guestbook con due voti distinti (gradimento e attinenza), moderazione, statistiche pubbliche aggregate. |
| **Regia** | Pannello per corpus, pagine redazionali, impostazioni, blocchi, registro accessi geolocalizzato offline. Tutte le carte salvate consultabili, e da ognuna una scheda per l'archivio. |

**415 prove di regressione**, che non confrontano i risultati con altri programmi di
astrologia — potrebbero sbagliare insieme — ma con fatti verificabili: agli equinozi il Sole
risulta a 0° entro un centesimo di grado, la Stella Polare sta a un'altezza pari alla
latitudine, a Longyearbyen il Sole non tramonta a giugno, l'equinozio del 2026 cade alle 14:46
UT come pubblica l'Osservatorio Navale degli Stati Uniti, e l'eclissi totale del 12 agosto 2026
appartiene alla serie di Saros 126.

Funziona su telefono e su tablet quanto su un monitor: la barra delle sezioni si richiude,
le tabelle larghe scorrono dentro il proprio riquadro invece di sfondare la pagina, e dove
si tocca invece di puntare i bersagli si allargano senza che cambi il disegno.

E funziona **senza JavaScript**: i moduli di nascita, di sinastria e del cielo accettano il solo
nome del luogo, e il server fa da sé ciò che altrimenti farebbero il completamento automatico e
la mappa — cerca il luogo, ne ricava le coordinate e il fuso. Si perde la comodità, non la funzione.
Anche con JavaScript attivo vale il nome scritto: se lo si cambia dopo aver scelto un luogo
dall'elenco, il server se ne accorge (il modulo tiene una copia del nome scelto) e cerca quello
nuovo invece di usare le coordinate del luogo di prima.

Prima dei fusi orari conta l'**ora locale media del luogo**: per l'Italia prima del novembre
1893 un atto di nascita di Milano segnava l'ora di Milano, non quella di Roma che il database
dei fusi attribuisce a tutta la penisola — tredici minuti di differenza, qualche grado di
Ascendente. Per la Francia no: dal 1891 l'ora di Parigi era davvero l'ora legale di tutto il paese.

---

## L'archivio

`/archivio` raccoglie carte che la regia ha calcolato e documentato, divise in tre tipi:

| tipo | quante | categorie |
|---|---|---|
| **Persone** | 158 | scienza, arte, letteratura, musica, cinema, spettacolo, filosofia, politica, esplorazione, sport, religione, cronaca nera |
| **Eventi** | 70 | guerra, rivoluzione, politica, scienza, esplorazione, disastro, economia, cultura, cronaca nera |
| **Nazioni e istituzioni** | 24 | fondazione, costituzione, unione, moneta |

Un caso di cronaca ha di solito due carte, e l'archivio le tiene distinte: la **nascita** della
persona, sotto *Persone*, e il **fatto** — un delitto, un ritrovamento — sotto *Eventi*, entrambe
in «cronaca nera». Il fatto è un istante e non una persona, e come tale si legge: con la lettura
mondiale e con «l'ora dell'evento». Così per Elizabeth Short, la «Dalia Nera»: la carta solare
della nascita (1924, ora ignota) e quella del ritrovamento del corpo a Los Angeles (1947), che
si rimandano l'una all'altra.

La categoria raccoglie 27 carte: nascite di condannati (Cianciulli, Bilancia, Manson, Bundy,
Dahmer, Gacy, Hauptmann, Petiot, Kürten, Haarmann) ed eventi, dallo Squartatore di Whitechapel al
delitto di via Poma. Le regole con cui sono state scelte valgono anche per le voci future: casi
storici e documentati; i colpevoli solo se condannati, e con l'esito giudiziario preciso; nessun
nome di persone assolte o soltanto sospettate; i casi irrisolti solo come eventi; note neutre,
senza dettagli macabri.

Si filtra per tipo, categoria, secolo (l'Ottocento è il 1800-1899, come nell'uso italiano) e per
nome o luogo. Ogni voce ha un indirizzo leggibile e stabile — `/archivio/victor-hugo`,
`/archivio/breccia-di-porta-pia` — che non cambia nemmeno se la regia ne corregge il nome, e che
i motori di ricerca possono indicizzare: le carte dei visitatori no.

**Ogni scheda dice da dove vengono i dati** e quanto ci si può fidare dell'ora, con le classi
di Lois Rodden:

| classe | significa |
|---|---|
| **AA** | ora da un documento: atto di nascita, verbale, registrazione strumentale (sismografi, registri di lancio) |
| **A** | ora dalla persona stessa, dai familiari o da una cronaca affidabile dell'epoca |
| **B** | ora da una biografia o da una ricostruzione storica |
| **C** | ora incerta o convenzionale (la mezzanotte di un'entrata in vigore) |
| **DD** | fonti in contrasto, nessuna prevale |
| **X** | ora ignota: carta solare |

Sotto una carta di classe C o DD la pagina avverte che Ascendente, Medio Cielo e case vanno
presi con cautela; nell'elenco l'Ascendente compare solo per le classi AA, A e B.

**Da dove vengono le 252 voci.** Astro-Databank, la raccolta di riferimento, sta dietro un
controllo anti-robot che non si è voluto aggirare. I dati delle persone vengono da Astrotheme,
che pubblica ora, luogo, classe Rodden e collezionista (Rodden, Gauquelin, Scholfield, Bordoni e
altri): ogni carta è stata ricalcolata con il motore del portale e **confrontata con Ascendente e
Medio Cielo pubblicati**: tutte coincidono entro un quarto di grado. Gli eventi vengono da INGV, USGS,
NASA, la Commissione sull'11 settembre, i tribunali (Capaci), le cronache d'epoca, e da Nicholas
Campion, *The Book of World Horoscopes*, per le carte di fondazione più note.

**L'istante vero è quello della fonte.** Un atto del 1874 segna un'ora su un orologio che oggi non
esiste: il tempo medio di Roma, quello del luogo, l'ora delle ferrovie, l'ora di guerra. Ogni voce
porta l'orologio (`LMT`, `RMT`, `CET`, `EST`...) e l'istante in Tempo Universale ricavato dalla
fonte; l'importatore lo confronta con il fuso che il portale avrebbe applicato e segnala ogni
differenza. Sono 26 su 252, e tutte volute: Martin Luther King nato ad Atlanta nel 1929 quando la
città seguiva ancora l'ora del Centro, l'Ohio del 1930 senza ora legale, i lanci da Bajkonur
cronometrati sull'ora di Mosca, Pietrogrado nel 1917 a tempo medio locale.

**Le carte di evento e di fondazione si leggono diversamente.** Il corpus è scritto per una
persona: sotto la carta della Repubblica Italiana direbbe «il centro della coscienza prende la
forma dei Gemelli». Queste carte hanno invece una **lettura mondiale** (vedi sotto), e le frasi
sull'ora dicono «l'ora dell'evento», non «l'ora di nascita».

**Aggiungere una voce** si può in due modi:

- **dal portale**: da amministratore, il modulo di calcolo mostra un riquadro «Archivio (regia)»
  con tipo, categoria, classe Rodden e fonte. La carta nasce già nell'archivio e si completa poi
  la scheda (nota, collegamento alla fonte, pubblicata o bozza) da `/admin/carte/{gettone}`.
  Una bozza la vede solo la regia, anche se il gettone della carta è già in giro;
- **dai semi**, che è il modo che sopravvive a un ripristino: si aggiunge la voce a
  `db/semi/archivio.php` e si lancia l'importatore.

```bash
php bin/importa-archivio.php --prova        # controlla ogni voce e il fuso, non scrive
php bin/importa-archivio.php                # aggiunge le voci nuove, non tocca le altre
php bin/importa-archivio.php --sostituisci  # riallinea ai semi i testi delle schede
php bin/importa-archivio.php --ricalcola    # rifà le carte dai semi (lo slug pubblico resta)
```

Una carta d'archivio è una carta come le altre — stesso motore, stesso permalink, stesse pagine
di transiti, carte del tempo e sinastria — con accanto una scheda (tabella `archivio`) che la
indica. Non conta nelle statistiche dei visitatori e un visitatore non la può cancellare.

---

## Il mondo

`/mondo` è l'astrologia mondiale: il cielo che vale per tutti, eretto per un luogo — di solito
la capitale del paese che si vuole guardare. Si sceglie fra quindici capitali (Roma, Londra,
Parigi, Berlino, Madrid, Bruxelles, Mosca, Kyiv, Washington, Pechino, Tokyo, Nuova Delhi,
Gerusalemme, Il Cairo, Brasilia) o si scrive un luogo qualunque.

**L'anno** (`/mondo/anno`). I quattro istanti in cui il Sole entra in Ariete, Cancro, Bilancia e
Capricorno, e tutti i noviluni e pleniluni, per qualunque anno dal 1800 al 2399. La carta
dell'ingresso in Ariete è, per tradizione, la carta dell'anno per quel paese; le altre valgono
per la loro stagione. Le stagioni seguono l'emisfero del luogo: a Brasilia l'ingresso in Ariete
apre l'autunno. Le lunazioni che sono anche eclissi lo dicono.

**Le eclissi** (`/mondo/eclissi`). Il catalogo per periodi fino a trent'anni, con le funzioni di
eclissi della Swiss Ephemeris: genere (totale, anulare, ibrida, parziale, di penombra), grado
dello zodiaco, istante del massimo, magnitudine (di penombra per le eclissi di penombra, che
all'ombra non arrivano), serie di Saros e posto nella serie, punto della Terra dove l'eclissi di
Sole è massima, e **quanto se ne vede dal luogo scelto** — la parte di disco coperta, e se il
massimo cade sotto l'orizzonte. Per ogni eclissi, le carte dell'archivio su cui il suo grado cade
entro due gradi.

**I cicli** (`/mondo/cicli`). Tutte le congiunzioni fra Giove, Saturno, Urano, Nettuno e Plutone
dal 1800 al 2399, con le congiunzioni triple raccolte in un passaggio solo, per sette coppie:

| coppia | periodo | che cosa si legge |
|---|---|---|
| Giove e Saturno | 20 anni | la vita sociale e politica; le **mutazioni** d'elemento ogni due secoli circa |
| Saturno e Urano | 45 anni | l'ordine e la rottura |
| Saturno e Nettuno | 36 anni | le strutture e gli ideali (1917, 1953, 1989) |
| Saturno e Plutone | 33 anni | le crisi del potere (1914, 1947, 1982, 2020) |
| Urano e Nettuno | 171 anni | le idee collettive e le tecniche (1821, 1993) |
| Urano e Plutone | 127 anni | le rivoluzioni (1850, 1965-66) |
| Nettuno e Plutone | 492 anni | il ciclo di civiltà (1891-92) |

Le mutazioni di Giove e Saturno seguono la tradizione e non il semplice cambio d'elemento: una
**serie** è una sequenza di almeno due congiunzioni nello stesso elemento, e la mutazione è la
prima di una serie nuova. Le congiunzioni fuori serie sono **isolate** — un anticipo, come il
1980-81 in Bilancia prima dell'aria che comincia nel 2020, o un ritorno, come il 2000 in Toro.

Sopra le tabelle, **l'indice ciclico di Barbault**: la somma delle dieci distanze angolari fra i
cinque lenti, un valore al mese per sei secoli, in un grafico SVG con la media, i minimi e una
tacca per ogni evento e fondazione dell'archivio, che porta alla sua carta. Calcolati qui, i
minimi del Novecento cadono nel 1918, nel 1943 e nel 1983; poi c'è il lungo avvallamento del
2020-2023. Il grafico non dimostra niente: mette le due cose una accanto all'altra.

**La carta di un istante** (`/mondo/carta`). Ogni ingresso, lunazione, eclissi e congiunzione si
apre come carta eretta per il luogo scelto, con la ruota, le posizioni e la lettura mondiale.
Il titolo si compone solo da valori in elenco chiuso, e il tipo si crede solo se il cielo lo
conferma: un indirizzo che chiamasse «novilunio» un istante a metà mese mostra «Il cielo del…».

**La lettura mondiale** usa le corrispondenze dell'astrologia mondiale classica, raccolte da
Baigent, Campion e Harvey in *Mundane Astrology* (1984): il Sole è chi governa, la Luna il
popolo, Mercurio i commerci e la stampa, Marte le forze armate, Saturno le istituzioni; la casa X
è il vertice dello Stato, la VII le relazioni con gli altri paesi, la IV la terra e
l'opposizione. Vengono per primi i **pianeti angolari**, entro otto gradi da un angolo, che nella
tradizione «firmano» l'istante; poi gli altri, e gli aspetti stretti fra pianeti. I «colpi» sulle
carte dell'archivio cercano solo i sette pianeti tradizionali e gli angoli: Urano, Nettuno e
Plutone stanno anni nello stesso grado, e un'eclissi sul Plutone di una carta cadrebbe su quello
di tutte le carte di quegli anni.

**Il costo.** I calcoli del mondo non cambiano mai — il cielo del 1848 è quello — e stanno in
file JSON sotto `storage/cache/mondo`, scritti una volta sola: tutti i cicli dal 1800 al 2399
costano due secondi al primo accesso, poi niente. Le eclissi si chiedono al motore per decenni
interi; per le capitali il risultato si conserva, per un luogo scritto a mano si calcola e basta,
così nessuno può riempire il disco un luogo alla volta.

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

La radice web e la cartella della configurazione si deducono e si possono forzare:

```bash
TEC_WEBROOT=/srv/www TEC_CONFIG_DIR=/etc/temaecielo sudo -E bash deploy/00-bootstrap.sh
TEC_WEBROOT=/srv/www bash deploy/01-installa.sh
```

Il portale vive **sotto `/temaecielo`** della radice web: quel nome è scritto nella conf Apache
(`deploy/apache-temaecielo.conf`: la cartella, la `RewriteBase`, le regole che negano `src/`,
`storage/` e il resto) e nella configurazione (`app.base_path`). Installarlo sotto un altro nome
si può, ma vanno cambiati a mano tutti e due: con il solo `TEC_DIR` diverso le regole di
protezione della conf non si applicherebbero.

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
php bin/console.php privacy:purga    cancella accessi, eventi, sessioni oltre N giorni   [giorni]
php bin/console.php cache:purga      butta la cache del motore    [giorni, 0 = tutta]
php bin/console.php astro            stato del motore astronomico
php bin/console.php astro:prova      calcola una carta di prova   [AAAA-MM-GG HH:MM lat lon]

php bin/importa-luoghi.php           gazetteer GeoNames
php bin/importa-stelle.php           catalogo stellare e costellazioni
php bin/importa-geoip.php            geolocalizzazione degli indirizzi
php bin/importa-corpus.php           testi interpretativi (--sostituisci per riallineare)
php bin/importa-archivio.php         le voci dell'archivio  (--prova, --sostituisci, --ricalcola)
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

I calcoli del mondo — ingressi, lunazioni, eclissi, cicli — non sono carte e non vanno in
quella tabella: stanno in file JSON sotto `storage/cache/mondo`, uno per domanda, e non scadono
mai, perché il cielo di un anno passato non cambia. Cancellarli è sicuro: al primo accesso si
rifanno.

### La manutenzione che il portale fa da solo

Una richiesta su duecento, la telemetria fa un giro di manutenzione, protetto da un lucchetto del
database perché due processi non lo facciano insieme:

- **aggiunge le partizioni mensili** di `accessi` quando ne restano meno di tre mesi avanti — senza,
  dal gennaio dopo l'ultima partizione ogni riga finirebbe nella partizione di riserva;
- **purga il registro** di accessi, eventi e sessioni oltre `privacy.purga_accessi_giorni`, se
  l'impostazione è diversa da zero, cinquemila righe per tabella alla volta.

`partizioni` e `privacy:purga` fanno le stesse cose subito, da riga di comando. Il deploy rende
scrivibili dal web server i file che la riga di comando crea in `storage/` — il registro degli
errori del mese, la cache del mondo: nati con i permessi dell'utente, il web server non poteva
più scriverci, e gli errori si perdevano in silenzio.

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
  Archivio/          le schede dell'archivio pubblico
  Mondo/             astrologia mondiale: capitali, eclissi, cicli, colpi sull'archivio
  Support/           telemetria, manutenzione, impostazioni, Markdown, indirizzi di rete
views/               PHP puro, nessun templating
assets/              css, js, sprite dei glifi (disegnati, non Unicode), Leaflet
                     js/luoghi.js   completamento automatico, condiviso fra due pagine
                     js/cielo.js    volta navigabile + quadro «da dove e quando»
bin/                 console, worker delle effemeridi, importatori (luoghi, stelle,
                     GeoIP, corpus, archivio)
db/migrazioni/       SQL numerato, applicato una volta sola
db/semi/             il corpus interpretativo e le 252 voci dell'archivio
deploy/              bootstrap, installazione, conf Apache
docs/                DESIGN.md (il progetto per esteso), FONTI.md
tests/               415 prove di regressione in 14 file
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

### Il worker sa fare quattro cose in più

Oltre alla carta (`tema`), alle posizioni e ai ritorni, il worker delle effemeridi ha tre
operazioni per il mondo: `anno` (ingressi e lunazioni, per bisezione sullo scarto ridotto a
±180 gradi), `eclissi` (le funzioni `swe_sol_eclipse_when_glob`, `_where`, `_when_loc` e le
corrispondenti lunari, legate via FFI) e `cicli` (le posizioni dei cinque lenti ogni dieci
giorni per sei secoli, le congiunzioni affinate per bisezione, l'indice di Barbault).

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
sono le tessere della mappa Esri, e solo nelle pagine che hanno una mappa: il modulo di nascita,
quello della sinastria e il cielo.

### I glifi sono disegnati

I caratteri Unicode ☉☽☿ vengono resi in modo incoerente da sistema a sistema — su qualche
dispositivo diventano emoji colorate, su altri mancano, e per gli asteroidi spesso non
esistono affatto. Lo sprite è disegnato e incorporato in pagina.

### Il corpus è a frammenti

Scrivere a mano tutte le voci interpretative sono migliaia di schede. Qui **ottantasei
frammenti** — dieci pianeti e i due angoli, dodici segni, dodici case, cinque aspetti, per due
registri —
compongono le 465 voci per registro che servono a leggere una carta qualunque: copertura
totale dal primo giorno, nessuna carta esce muta. Le voci scritte a mano scavalcano sempre il
composto, e il pannello dice quali conviene scrivere per prime.

Comporre in italiano non è concatenare stringhe: `Corpus\Lingua` contrae le preposizioni
articolate («in» + «il bisogno» → «nel bisogno») e lascia stare i nomi propri («a Mercurio»,
non «al Mercurio»). I due angoli sono frammenti anch'essi, così «il Sole congiunto
all'Ascendente» — il più forte degli aspetti di una carta — ha la sua frase.

I testi usano le lettere accentate (è, più, perché), non l'apostrofo della macchina da scrivere:
corpus, pagine e messaggi del portale sono stati riallineati, e una prova lo controlla.

---

## Privacy

Il portale tratta insieme **data-ora-luogo di nascita** e **indirizzi IP**. Chi lo mette online
è il titolare del trattamento. Il software offre, già pronti:

- **informativa** come pagina redazionale, richiamata sotto il modulo e sotto il guestbook; il
  modello dice chi vede che cosa, che cosa resta dopo una cancellazione e come chiedere;
- **cancellazione autonoma** della propria carta dal permalink, senza account: si cancellano
  carta e dati di nascita, e l'indirizzo smette di funzionare per chiunque. I disegni scaricati
  non restano nelle cache condivise (`Cache-Control: private, no-store`);
- **un permalink per persona**: due persone con gli stessi dati di nascita condividono il
  calcolo, ma non l'indirizzo né il nome;
- **download** della ruota e della volta celeste come file SVG autonomi;
- **nessun dato di nascita nella telemetria**: gli eventi registrano che un calcolo, una ricerca
  di luogo o una sinastria sono avvenuti, non con quali dati; delle richieste alle API
  (`/api/fuso?data=…&ora=…`, `/api/luoghi?q=…`) il registro accessi tiene il percorso, non la
  domanda;
- **purga programmata** del registro accessi, degli eventi e delle sessioni
  (`privacy.purga_accessi_giorni`), fatta dal portale stesso o con `privacy:purga`;
- **anonimizzazione** dell'IP (`privacy.anonimizza_ip`) nel registro accessi e nelle sessioni.
  Non tocca l'indirizzo dei messaggi del guestbook né quello dei tentativi di accesso alla regia:
  servono alla moderazione e al blocco dei tentativi, e l'informativa lo dice.

Nel modello di configurazione purga e anonimizzazione sono **disattivate**: la scelta è di chi
installa.

I permalink portano `X-Robots-Tag: noindex`. Un tema natale con nome e cognome indicizzato dai
motori di ricerca sarebbe un problema serio, ed è escluso per costruzione. Fanno eccezione solo
le carte dell'archivio pubblicate, che sono fatte per essere trovate, e che un visitatore non
può cancellare.

**La regia può consultare tutte le carte salvate** — nome, data, ora e luogo — dal pannello
(`/admin/carte`, con ricerca e filtro fra carte dei visitatori e d'archivio). Il modello
dell'informativa e il modulo di calcolo lo dicono; chi installa il portale deve tenerlo scritto
nella propria. Le carte dei visitatori non finiscono mai nell'archivio da sole: ci va solo ciò
che la regia ha calcolato e documentato.

I dati della seconda persona in una sinastria **non vengono salvati come carta** e il suo nome
non viene registrato: il calcolo resta nella cache del motore, senza nome, e se nessuno lo
richiede si cancella dopo trenta giorni.

---

## Sicurezza

- **CSP con nonce** dal primo giorno: nessuno stile inline, nessun `onclick`, nessuno script
  incorporato.
- **Argon2id** per la password dell'amministratore, con blocco dopo cinque tentativi falliti
  per indirizzo (per IPv6, per rete /64). Il tentativo si conta *prima* di verificarlo, il nome
  utente deve coincidere byte per byte (non solo per la collation del database), e un nome che
  non esiste costa esattamente quanto uno che esiste: il cronometro non dice quali nomi sono validi.
- **Freni per indirizzo** sul motore di calcolo (40 processi al minuto) e sull'API dei luoghi
  (120 richieste al minuto), contati nel database e non nella sessione — che il cliente può
  semplicemente non mandare.
- Le risposte che una cache condivisa può conservare **non portano il cookie di sessione**, e gli
  SVG serviti da soli ricevono una CSP da immagine, senza script.
- **CSRF** su ogni modulo, **prepared statement** su ogni query.
- Il Markdown delle pagine redazionali **scappa il testo prima di qualunque trasformazione**:
  l'HTML non passa, i collegamenti `javascript:` e `//altrosito` vengono neutralizzati, e quelli
  interni ricevono la radice del portale.
- Il guestbook si difende con campo esca, cronometro minimo (un modulo mai aperto non passa),
  tetto orario per cliente (per IPv6, per rete /64), parole vietate e blocchi — **senza captcha
  di terzi**. Il voto di attinenza si aggancia solo alla carta che la sessione ha appena
  guardato. I blocchi accettano solo indirizzi e maschere possibili.
- Le **pagine del mondo** rifiutano istanti fuori dalle effemeridi, e un parametro deforme —
  anche una lista al posto di un testo — non arriva mai al codice: `Request::query()` restituisce
  solo testo. Un giro di prova passa valori deformi a tutte le 38 pagine senza un avviso.
- Le API rispondono sempre un JSON valido, anche a un testo che non è UTF-8.
- `src/`, `db/`, `bin/`, `config/`, `deploy/`, `views/`, `storage/`, `docs/` e `fonti/` sono
  negati dal web dalla conf Apache, e nella radice del portale l'unico `.php` eseguibile è
  `index.php`. Le regole valgono **solo** per il portale: una conf che porta il nome di
  un'applicazione non deve decidere per le altre ospitate sullo stesso server.

---

## Riconoscimenti

- **Swiss Ephemeris** — Astrodienst AG, AGPL-3.0 / commerciale
- **GeoNames** — CC BY 4.0
- **HYG Database** — David Nash / astronexus, CC BY-SA 4.0
- **Stellarium** — GPL-2.0, per le figure delle costellazioni
- **DB-IP Lite** — CC BY 4.0
- **Leaflet** — BSD-2-Clause · tessere **Esri**
- **Dati dell'archivio** — ogni scheda cita la propria fonte: per le persone le raccolte di
  dati di nascita pubblicate da Astrotheme (Rodden, Gauquelin e altri collezionisti), per eventi e
  fondazioni INGV, USGS, NASA, la Commissione sull'11 settembre, le cronache d'epoca, e Nicholas
  Campion (*The Book of World Horoscopes*) per le carte di fondazione più note
- **Astrologia mondiale** — le corrispondenze di Baigent, Campion e Harvey, *Mundane Astrology*
  (1984); l'indice ciclico e la lettura dei cicli di André Barbault, *Les astres et l'histoire*
  (1967)
