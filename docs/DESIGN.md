# Tema e Cielo — progetto

Portale astrologico e astronomico. Il visitatore lascia le proprie generalità, la data,
l'ora e il luogo di nascita; il portale restituisce il tema natale disegnato, la volta
celeste reale di quell'istante da quel punto della Terra, e la più ampia mole di dati
calcolati, tabulati e commentati che sia ragionevole produrre da quei quattro dati.

Documento di progetto, scritto prima di cominciare: qui c'è cosa si costruisce, in che ordine,
e quali trappole erano già note.

> **Stato: tutte le fasi da F0 a F7 sono realizzate.** Il documento è rimasto com'era, perché
> serve a capire *perché* il portale è fatto così, non a descrivere cosa c'è oggi — per quello
> c'è il README. Le poche cose decise diversamente strada facendo sono annotate dove capita.

---

## 1. Decisioni fondanti

Quattro scelte prese all'avvio, da cui discende tutto il resto.

| | |
|---|---|
| **Nome** | Tema e Cielo — percorso `/temaecielo`, DB `tec_temaecielo`, repo `TemaECielo` / `My_TemaECielo` |
| **Accesso** | Anonimo per tutti, login solo per l'admin. Nessun account utente, nessuna e-mail raccolta |
| **Interpretazione** | Doppio registro: ogni voce ha la lettura **tradizionale** e quella **moderna**, affiancate e commutabili |
| **Registro IP** | IP completo, geolocalizzazione e ASN offline, conservazione a tempo indeterminato |

E cinque decisioni tecniche che ne conseguono.

**Il motore di calcolo è la Swiss Ephemeris.** Debian trixie la pacchettizza
(`libswe2.0` + `swe-basic-data`): è lo standard di fatto dell'astrologia computazionale,
poggia sulle effemeridi JPL DE431 e copre dal 13201 a.C. al 17191 d.C. Non si riscrive
nulla a mano, non si approssima nulla. Vedi §4.2 per come la si chiama da PHP e §15 per la
questione di licenza, che è l'unico punto dove questo progetto si discosta dalla prassi.

**Niente account significa che il permalink è l'identità.** Ogni calcolo produce un
indirizzo con un token lungo e imprevedibile. Chi lo conserva ritrova il proprio tema, lo
rimanda a un amico, lo usa per la sinastria. Chi lo perde, lo perde: è il prezzo di non
chiedere un'e-mail, ed è il prezzo giusto.

**Il calcolo è deterministico, quindi si mette in cache per impronta.** Stessi dati di
nascita, stesse opzioni, stessa carta: si calcola una volta sola e si contano le richieste.
Un beneficio inatteso è statistico — il portale sa dire quante persone distinte hanno
chiesto lo stesso cielo.

**Il disegno esce dal server come SVG, non dal browser.** La ruota del tema e la volta
celeste sono generate in PHP. Funzionano senza JavaScript, si stampano nitide a qualsiasi
misura, si scaricano come file unico, entrano in un PDF. Il JavaScript si aggiunge sopra
come strato di comodità — evidenziazioni, zoom, tendine — mai come condizione per vedere il
risultato.

**L'ora sconosciuta è un caso di prima classe, non un errore.** Buona parte dei visitatori
non sa a che ora è nata. Il portale non rifiuta e non finge: calcola in modalità dichiarata,
marca come inattendibile tutto ciò che dipende dall'ora, e mostra quanto quei valori si
muoverebbero nelle ventiquattro ore. Vedi §5.4.

---

## 2. Estetica

**Riferimento: l'atlante celeste a incisione del Seicento.** Cellarius, Hevelius, Bayer.
Carte stellari su fondo notte con figure incise in oro e rame, linee di costruzione sottili
come su un astrolabio, cartigli, tipografia con le grazie. Il portale deve dare
l'impressione di una **tavola d'osservatorio**, non di una sfera di cristallo: niente neon
viola, niente glitter, niente lune sorridenti.

### 2.1 Due vesti

**Notte** (predefinita) — la volta celeste.

| ruolo | colore |
|---|---|
| fondo | `#0b1026` blu notte |
| pannelli | `#121a3a` |
| linee e cornici | `#c9a227` oro incisione |
| oro chiaro (titoli) | `#e8d18a` |
| testo | `#e6e9f5` |
| testo attenuato | `#9aa3c4` |

**Pergamena** — la carta d'atlante, per la stampa e per chi legge di giorno.

| ruolo | colore |
|---|---|
| fondo | `#efe6d2` |
| inchiostro | `#2b2418` |
| seppia | `#7a6340` |
| oro | `#9c7c26` |

### 2.2 I quattro elementi come codice cromatico unico

Fuoco `#c05a2e` · Terra `#6b7f4a` · Aria `#c9a227` · Acqua `#41739c`.

Questi quattro colori governano **tutto**: i settori della ruota, le righe delle tabelle,
le barre dei bilanci, i grafici delle statistiche pubbliche. Un lettore che ha imparato che
l'acqua è quel blu lo ritrova identico ovunque nel portale. È la disciplina che fa sembrare
un sito una cosa sola invece che sette pagine cucite insieme.

Gli aspetti hanno un codice proprio, trasversale agli elementi: armonici in verde acqua
`#3f8f7a` (trigono, sestile), di tensione in rosso ruggine `#b4432f` (quadrato,
opposizione), congiunzione in oro, minori in tratteggio sottile.

### 2.3 Tipografia e glifi

Titoli in **Cormorant Garamond**, testo in **EB Garamond** o **Spectral**, dati numerici e
tabelle in un sans neutro con cifre tabulari. Tutti font a licenza aperta, ospitati in
locale: nessuna chiamata a Google Fonts, che sarebbe anche un trasferimento di IP a terzi.

**I glifi astrologici non si prendono da Unicode.** I caratteri ☉☽☿♀♂ esistono nello
standard ma vengono resi in modo incoerente da sistema a sistema — su qualche dispositivo
diventano emoji colorate, su altri mancano del tutto, e i glifi dei segni minori e degli
asteroidi spesso non ci sono proprio. Si disegna uno **sprite SVG proprio**: un `<symbol>`
per ogni pianeta, segno, aspetto, punto e dignità, con tratto e peso decisi da noi,
colorabile via CSS, incorporabile nel disegno server-side. Costa una giornata di disegno e
risolve per sempre il problema della resa.

### 2.4 Trama e micro-interazioni

Grana di carta ottenuta in SVG con `feTurbulence` a bassissima opacità, vignettatura ai
bordi, cornici a filetto doppio. Passando sopra un pianeta nella ruota si accendono le sue
linee d'aspetto e si evidenzia la riga corrispondente in tabella; passando sulla riga della
tabella accade l'inverso. È l'unica animazione prevista, e serve a leggere.

---

## 3. Le sette facce del portale

1. **Calcola** — il modulo: generalità, data, ora (o dichiarazione di non saperla), luogo
   scelto sulla mappa. È la porta d'ingresso e coincide con la home.
2. **La carta** — il risultato: ruota disegnata, tabelle, bilanci, relazione discorsiva a
   doppio registro. Indirizzo permanente con token.
3. **Il cielo** — la volta celeste reale di quell'istante da quel luogo, più le effemeridi
   del giorno di nascita.
4. **Sinastria** — il rapporto fra due carte, o fra due soli segni per la versione rapida.
5. **Oggi** — il cielo corrente, le posizioni del momento, la fase lunare, i transiti
   notevoli. La pagina che dà un motivo per tornare.
6. **Statistiche** — cosa ha calcolato il portale finora, aggregato e anonimo, in grafici.
7. **Guestbook** — firma, commento, e i due voti sul calcolo ricevuto.

Più le pagine redazionali gestite dall'admin (chi siamo, come si legge una carta, metodo,
informativa) e il **pannello di regia**, che sta dietro la voce di accesso.

---

## 4. Architettura

### 4.1 Impianto, allineato agli altri progetti di questo server

```
<lavoro>/TemaECielo/              cartella di lavoro = repo PUBBLICO (factory-default)
  index.php                       unico punto d'ingresso
  src/
    Core/          router, richiesta, risposta, template, contenitore, log
    Astro/         IL MOTORE — vedi §5
    Grafica/       generatori SVG: RuotaTema, VoltaCeleste, Grafici
    Luogo/         gazetteer, geocodifica, fusi storici
    Corpus/        montaggio dei testi interpretativi
    Controllers/   una classe per faccia del portale
    Admin/         pannello di regia
    Support/       telemetria, limitatore, validazione, formati
  views/           layout, partials, admin/
  assets/          css, js, glifi.svg (sprite), font, leaflet/ (vendorizzato)
  bin/             console.php, effemeridi.php (worker CLI), importa-*.php
  db/              migrazioni numerate + semi del corpus
  deploy/          00-bootstrap.sh, 01-installa.sh, apache-temaecielo.conf
  docs/            questo file, METODO.md, FONTI.md
  storage/         cache SVG, log, lock         (negata dal web)
  fonti/           dati di terzi scaricati       (fuori dal repo, .gitignore)

<radice-web>/temaecielo/          copia in produzione, installata da deploy/
<padre-radice>/temaecielo-config/ config.php reale, 0640 <utente>:www-data, fuori dal webroot
<lavoro>/My_TemaECielo/           repo PRIVATO: backup integrale del deployment
```

Apache: conf in `conf-available`, `DirectoryMatch` che nega `src|db|bin|deploy|storage|views|docs|fonti`,
URL puliti via `RewriteRule ^ index.php [L]`.

**Nessuno di questi percorsi è scritto nel codice.** Si deducono dalla radice web — risolta dai
collegamenti simbolici, che su molti server puntano altrove — e si forzano con `TEC_WEBROOT`,
`TEC_DIR`, `TEC_CONFIG_DIR`. Un percorso fisso dentro il codice non è solo un dato di macchina
esposto: rende il progetto installabile soltanto dove è nato.

**CSP con nonce fin dal primo giorno.** La landing page di Balthasar ha già insegnato che la
CSP di questo vhost vieta lo stile inline: qui si parte direttamente senza `style="..."` e
senza `onclick=`, con un nonce per il `<style>` dinamico. Leaflet va configurato di
conseguenza — è il pezzo che tende a volere stili inline.

### 4.2 Come PHP parla alla Swiss Ephemeris

Il nodo tecnico centrale, e ha una soluzione pulita.

PHP 8.4 su questo server ha **FFI compilato ma con `ffi.enable=preload`**: sotto Apache
l'FFI è bloccato a meno di toccare `php.ini` da root, cosa che qui si preferisce evitare.
In **PHP CLI, invece, l'FFI è sempre abilitato**, senza eccezioni e senza configurazione.

Da qui il disegno:

```
   web (Apache, mod_php)                     CLI (php-cli, FFI attivo)
   ────────────────────────                  ──────────────────────────
   Astro\Motore                              bin/effemeridi.php
     costruisce la domanda in JSON  ──────▶    FFI::cdef su libswe.so.2
     proc_open, scrive su stdin               swe_set_ephe_path()
     legge JSON da stdout          ◀──────    swe_julday, swe_calc_ut,
     valida, mette in cache                   swe_houses_ex, swe_fixstar2_ut,
                                              swe_pheno_ut, swe_rise_trans,
                                              swe_azalt, swe_deltat
```

Una sola invocazione produce **tutto** il necessario per una carta: pianeti, punti, case,
stelle fisse, fenomeni, levate e tramonti, coordinate orizzontali. Il costo è un `fork` da
qualche millisecondo, pagato una volta per carta e poi mai più grazie alla cache per
impronta. Nessuna modifica a `php.ini`, nessun compilatore, nessun demone da tenere vivo,
nessun servizio da registrare in systemd — cosa che su questo server richiederebbe comunque
root.

Il worker CLI ha un contratto rigido: legge un solo oggetto JSON, non esegue mai codice che
venga dall'ingresso, e se libswe manca o i file di effemeridi non ci sono restituisce un
errore strutturato invece di calcolare male. Un test di regressione in `tests/` confronta le
sue uscite con carte di controllo note prima di ogni pubblicazione.

### 4.3 Le tre catene di cache

- **Effemeridi**: per `(giorno giuliano troncato, corpo, flag)`. Serve soprattutto alla
  pagina "Oggi" e ai transiti, che ricalcolano continuamente le stesse posizioni.
- **Carta**: per `impronta = sha256(data|ora|lat|lon|altitudine|sistema case|zodiaco|opzioni)`.
  Il risultato completo in JSON più l'SVG già reso. È la cache che conta.
- **Disegno**: l'SVG su disco in `storage/`, servito con `ETag` e `Cache-Control` lunghi,
  perché è immutabile per costruzione.

---

## 5. Il motore: tutto ciò che si ricava da quattro dati

L'utente chiede «più informazioni possibili». Ecco l'inventario, che è anche la specifica
del JSON restituito dal worker.

### 5.1 Corpi e punti calcolati

**I dieci** — Sole, Luna, Mercurio, Venere, Marte, Giove, Saturno, Urano, Nettuno, Plutone.

**Estensioni** — Chirone; Lilith media e Lilith vera (osculante); Nodo lunare medio e vero,
con il Nodo Sud sempre esplicitato; Vertex e Antivertex; i quattro asteroidi maggiori Cerere,
Pallade, Giunone, Vesta; Terra eliocentrica per la carta eliocentrica opzionale.

**Punti calcolati** — Ascendente, Medio Cielo, Discendente, Fondo Cielo; Parte di Fortuna
(con la formula corretta, **invertita fra carta diurna e notturna**, che è la distinzione
che metà dei siti gratuiti sbaglia); Parte di Spirito; Sizigia prenatale, cioè l'ultimo
novilunio o plenilunio prima della nascita; Est Point.

**Per ciascuno, sempre**: longitudine eclittica in segno-grado-primo-secondo, longitudine
decimale assoluta, latitudine eclittica, ascensione retta, declinazione, distanza,
velocità in longitudine con segno, stato di retrogradazione o stazionarietà, casa di
appartenenza, decano, termine egizio, azimut e altezza sull'orizzonte del luogo di nascita.

### 5.2 Case

Undici sistemi selezionabili: **Placido** (predefinito), Koch, Regiomontano, Campano,
Porfirio, Equale, Equale dal MC, **Segni Interi**, Alcabizio, Topocentrico, Morinus.

Per ciascuna casa: cuspide, segno, signore secondo la tradizione (Marte all'Ariete, Saturno
all'Acquario…) e signore moderno (Plutone, Urano, Nettuno), pianeti contenuti, casa
intercettata sì/no, segni intercettati e segni duplicati sulle cuspidi.

Alle latitudini estreme Placido e Koch degenerano — oltre il circolo polare le cuspidi non
esistono. Il motore lo rileva, lo dice a chiare lettere e propone Segni Interi o Porfirio
invece di produrre numeri privi di senso.

### 5.3 Aspetti

**Maggiori**: congiunzione, opposizione, trigono, quadrato, sestile.
**Minori**: quinconce, semisestile, semiquadrato, sesquiquadrato, quintile, biquintile,
settile, noventile, decile.

Per ciascun aspetto: i due corpi, l'angolo esatto, lo scarto dall'aspetto perfetto, l'orbe
ammesso (tabella configurabile per corpo e per aspetto, con orbe maggiore a Sole e Luna),
la **forza** normalizzata da 0 a 1, e soprattutto se è **applicativo o separativo** — che si
determina dalle velocità relative ed è quello che distingue un aspetto che sta per compiersi
da uno che si sta sciogliendo.

**Paralleli e contro-paralleli di declinazione**, che quasi nessun portale gratuito calcola
e che sono aspetti a pieno titolo. **Antiscia e contrantiscia** (riflessioni sull'asse
Cancro-Capricorno).

**Configurazioni riconosciute automaticamente**: stellium, gran trigono, gran croce,
T-quadrata, yod, rettangolo mistico, aquilone, trapezio, semi-gran trigono. Per ognuna i
pianeti coinvolti, l'elemento o la modalità dominante, e il punto focale.

### 5.4 Quando l'ora non si sa

Tre modalità dichiarate, mai silenziose.

- **Ora esatta** — tutto è attendibile.
- **Ora approssimativa** (l'utente sa «verso sera») — si calcola sul centro
  dell'intervallo e si mostra una banda di incertezza su ASC e MC.
- **Ora ignota** — si calcola la **carta solare**: il Sole in cuspide di prima casa, case in
  Segni Interi. Sono marcati come non attendibili e visivamente attenuati: tutte le cuspidi,
  ASC/MC/DSC/IC, Parte di Fortuna, Vertex, la casa di ogni pianeta, e gli aspetti della Luna
  che entrano o escono dall'orbe nell'arco della giornata.

In modalità ignota il portale mostra comunque un dato prezioso: **l'arco percorso nelle 24
ore**. «Quel giorno la Luna è passata da 4° Gemelli a 17° Gemelli: resta in Gemelli comunque»
è un'informazione utile e onesta. «L'Ascendente ha percorso l'intero zodiaco» lo è
altrettanto.

### 5.5 Bilanci e sintesi

Elementi (fuoco/terra/aria/acqua) e modalità (cardinale/fisso/mobile) con conteggio grezzo e
conteggio **pesato** — il Sole e la Luna valgono più di Plutone, l'Ascendente entra nel
conto. Polarità diurno/notturno. Emisferi nord/sud ed est/ovest. Quadranti. Dominanti
planetarie, per segno e per casa.

**Figura planetaria secondo Jones**: fascio, ciotola, locomotiva, altalena, secchio, spruzzo,
fionda. Si ricava dalla distribuzione angolare dei dieci e dice in una parola com'è
distribuita l'energia della carta.

**Albero dei dispositori** con individuazione dei cicli e del dispositore finale, quando
esiste. **Almuten figuris**.

### 5.6 Dignità

**Essenziali**, sistema completo: domicilio, esaltazione, triplicità (con la distinzione
diurno/notturno e il signore partecipante), **termini egizi**, decani o facce, detrimento,
caduta, peregrinità. Con il punteggio numerico alla maniera di Lilly.

**Accidentali**: angolarità (casa angolare, succedente, cadente), velocità rispetto alla
media, direzione del moto, **combustione, cazimi e sotto i raggi** con le soglie canoniche,
orientalità e occidentalità rispetto al Sole, aspetti ai benefici e ai malefici, assedio.

### 5.7 Stelle fisse

Congiunzioni entro 1° con le stelle di prima e seconda grandezza tradizionalmente usate —
Regolo, Spica, Aldebaran, Antares, Algol, Sirio, Fomalhaut, Vega, Altair, Arturo, le Pleiadi
e le altre del canone. Con nome, magnitudine, natura planetaria secondo Tolomeo, e posizione
**precessata alla data di nascita**, non alla J2000: una stella si sposta di circa un grado
ogni settant'anni, e usare la posizione sbagliata falsa la congiunzione.

### 5.8 Contorno astronomico

Giorno giuliano, ΔT applicato, tempo siderale locale e di Greenwich, obliquità
dell'eclittica vera e media, equazione del tempo, nutazione.

Del giorno di nascita nel luogo di nascita: alba e tramonto del Sole, i tre crepuscoli
(civile, nautico, astronomico), durata del giorno, sorgere/culminare/tramontare di **ogni**
pianeta, fase e illuminazione della Luna con l'età in giorni, distanza della Luna con
perigeo/apogeo, eclissi solari e lunari più vicine prima e dopo la nascita.

E il dato che chiude il cerchio con la seconda metà del portale: **azimut e altezza di ogni
corpo all'istante esatto**, cioè dove stava fisicamente in cielo chi guardava in alto da lì
in quel momento.

### 5.9 Carte derivate

Transiti sulla natale a una data qualsiasi, con il giorno corrente come predefinito.
Rivoluzione solare dell'anno scelto, con le opzioni di domificazione sul luogo di nascita o
sul luogo attuale. Rivoluzione lunare. Progressioni secondarie (un giorno = un anno).
Direzioni di arco solare. Profezioni annuali. Armoniche 5ª, 7ª e 9ª. Carta draconica.
Zodiaco siderale con le principali ayanamsa, per chi vuole il confronto con la tradizione
indiana.

---

## 6. La ruota del tema

Generata in PHP come SVG puro, in una misura, scalabile a qualsiasi altra.

**Gli anelli, dall'esterno verso il centro**: cornice incisa con i nomi delle costellazioni;
anello zodiacale a dodici settori colorati per elemento, con glifi e tacche di grado (fitte
ogni 1°, marcate ogni 5°, numerate ogni 10°); corona dei pianeti con glifo, grado, minuto e
segnale di retrogradazione; anello delle case con numeri romani e cuspidi; al centro la
**tela degli aspetti**, le corde che uniscono i pianeti, colorate per tipo e con spessore
proporzionale alla forza.

Gli assi ASC–DSC e MC–IC sono tracciati più marcati e sbordano dalla ruota con
un'etichetta, come si fa sulle carte a stampa.

**Il problema vero è l'anti-collisione dei glifi.** Quando tre pianeti stanno in due gradi,
i simboli si sovrappongono e la carta diventa illeggibile — è esattamente il dettaglio che
distingue una carta fatta bene da una fatta male. Si risolve con un rilassamento a molle
sull'angolo: ogni glifo ha una posizione desiderata (il grado reale) e un ingombro minimo;
si itera spingendo via i vicini troppo stretti finché nessuno si tocca, e si traccia una
**linea guida sottile** dal glifo spostato alla sua tacca di grado esatta, così la verità
resta visibile. Una decina di iterazioni bastano sempre.

**Doppia ruota** per transiti e sinastria: la carta radice dentro, quella sovrapposta
nell'anello esterno, e gli aspetti incrociati che attraversano. **Tripla ruota** per
natale + progressioni + transiti.

Sotto la ruota, e in pagina stampabile a parte: tabella delle posizioni, **griglia
triangolare degli aspetti** (la tradizionale matrice a scaletta), tabella delle dignità con
punteggi, barre dei bilanci, elenco delle configurazioni.

---

## 7. Il riquadro del cielo astronomico

La seconda metà del portale, e ciò che lo distingue da qualunque calcolatore di temi natali.
Non lo zodiaco: **il cielo vero**, quello che si vedeva alzando gli occhi.

**Vista principale** — proiezione stereografica azimutale centrata sullo zenit, con
l'orizzonte come cerchio esterno e i punti cardinali sul bordo. Sopra ci stanno:

- le stelle fino alla sesta magnitudine, circa novemila, con raggio proporzionale alla
  magnitudine e tinta derivata dall'indice di colore B−V, così Betelgeuse è rossa e Rigel
  azzurra come in cielo;
- le linee delle costellazioni e, a scelta, i confini IAU;
- l'eclittica, l'equatore celeste e il meridiano locale, tratteggiati e etichettati;
- i pianeti visibili, con glifo, nome e magnitudine;
- la Luna **nella fase reale**, col terminatore disegnato come ellisse e l'inclinazione
  corretta per quella latitudine e quell'ora;
- il colore del fondo che segue l'altezza del Sole: giorno pieno, crepuscolo civile, nautico,
  astronomico, notte profonda. Chi è nato alle tre del pomeriggio vede un cielo azzurro con
  il Sole alto e nessuna stella, ed è giusto così.

**Viste secondarie** — planisfero rettangolare di tutto il cielo; dettaglio dell'orizzonte
orientale con il grado dell'Ascendente che sorge, che è il ponte visivo fra le due metà del
portale.

I dati stellari vengono dal catalogo HYG, importato una volta in MariaDB e precessato alla
data. Le linee delle costellazioni dal file `constellationship` di Stellarium. Entrambi sono
materiale di terzi: restano fuori dal repo pubblico, con uno script che li scarica al primo
avvio e un `FONTI.md` che dice da dove e con quale licenza.

---

## 8. Il riquadro mappa

Leaflet vendorizzato in locale — la copia è già in casa, OrbitalEye la usa — con i due strati
di tile Esri già collaudati su questo server:

- `World_Street_Map` per la vista cartina;
- `World_Imagery` per la vista satellitare.

Un interruttore le scambia; nessuna chiave API, nessun account.

**La ricerca località è offline.** Si importa il gazetteer GeoNames in MariaDB — `cities500`
più gli alternate names italiani — con indice FULLTEXT sui nomi. Digitando «Reggio» il
portale propone Reggio Calabria e Reggio Emilia con paese, regione, popolazione e coordinate,
istantaneamente, senza interrogare nessun servizio esterno, senza quote e senza spedire a
terzi il luogo di nascita di chi sta compilando il modulo.

Tre modi di indicare il luogo, tutti e tre sincronizzati fra loro: cercarlo per nome,
cliccare sulla mappa, digitare le coordinate a mano in gradi decimali o in
gradi-primi-secondi. Più l'altitudine, presa dal gazetteer e correggibile, che serve alla
parallasse della Luna e agli orari di alba e tramonto.

### 8.1 Il fuso orario storico, ovvero l'errore numero uno

**È la prima causa di temi natali sbagliati al mondo**, e merita il suo paragrafo.

Una nascita del 15 giugno 1943 a Milano non è «UTC+1». L'Italia in quegli anni applicava
un'ora legale con date proprie, diverse da oggi; fra il 1916 e il 1920 e fra il 1940 e il
1948 le regole cambiavano quasi ogni anno; prima del 1893 Milano andava a ora locale media,
legata alla longitudine, non a un fuso. Chi applica l'offset di oggi a una nascita di
ottant'anni fa sbaglia l'Ascendente di un intero segno.

La catena corretta, che il portale implementa per intero:

```
coordinate ──▶ fuso IANA (dal gazetteer; per i click fuori città, dai poligoni
               timezone-boundary-builder caricati in MariaDB con indice SPATIAL)
          ──▶ DateTimeZone + data e ora locali
          ──▶ offset storico reale, ora legale compresa, dal tzdata di sistema
          ──▶ UT ──▶ giorno giuliano ──▶ effemeridi
```

PHP su questo server usa il tzdata di sistema, che contiene tutte le transizioni storiche
fino al 1835 e oltre: la correttezza c'è già, basta non aggirarla con un offset fisso.

Due casi limite vanno gestiti a mano e mostrati all'utente:

- **L'ora ripetuta.** Al ritorno dall'ora legale le 02:30 esistono due volte. Il portale
  rileva l'ambiguità, la spiega e chiede quale delle due, invece di sceglierne una in
  silenzio.
- **L'ora inesistente.** All'entrata in ora legale le 02:30 non esistono. Stesso trattamento.

E in ogni caso il portale **mostra sempre l'offset che ha applicato**, accanto all'ora
inserita. Se sbaglia, l'utente può accorgersene.

---

## 9. Sinastria

L'utente l'ha chiesta in due forme, e sono due funzioni diverse. Ci sono entrambe.

**Sinastria rapida, segno contro segno.** Nessuna data richiesta: si scelgono due segni e si
ottiene il ritratto del rapporto. Dietro c'è una matrice 12×12 di testi — 78 combinazioni
distinte, ciascuna nei due registri — più i punteggi derivati da elemento, modalità, distanza
angolare fra i segni e rapporto fra i loro signori. È la funzione leggera, quella che si
condivide, quella che porta traffico.

**Sinastria completa, carta contro carta.** Due temi natali, e allora:

- **aspetti incrociati** fra tutti i corpi delle due carte, con orbi più stretti del solito
  perché altrimenti tutto aspetta tutto;
- **sovrapposizione delle case**: dove cadono i pianeti di lei nelle case di lui e viceversa,
  che nella pratica dice più degli aspetti;
- **carta composita di punti medi** e **carta di Davison**, che sono due cose diverse e
  vengono presentate come tali;
- **punteggi per area** — attrazione, affinità mentale, tenuta nel tempo, attrito — ciascuno
  con il dettaglio di quali contatti lo compongono, perché un punteggio senza il suo perché
  è un oroscopo da rivista;
- la **doppia ruota** con le due carte concentriche.

Funziona fra due persone come fra una persona e un'altra data qualsiasi. E accetta il caso
misto: se di uno dei due manca l'ora, si dichiara e si escludono dal conto i contatti che
dipendono dalle case.

---

## 10. Il corpus a doppio registro

È il lavoro più lungo del progetto e ciò che lo rende diverso dagli altri.

Ogni voce interpretativa esiste in **due versioni affiancate**, commutabili con una linguetta
in cima alla pagina e ricordate nelle preferenze del visitatore:

- **Tradizionale** — dignità, signorie, sette pianeti visibili in primo piano, linguaggio e
  categorie di Tolomeo, Lilly, Morin. Le tre pianete moderne compaiono come chiose.
- **Moderna** — archetipi, simbolismo psicologico, processo di crescita, linguaggio di
  Rudhyar, Hand, Greene.

Gli ambiti coperti: pianeta in segno (10 × 12), pianeta in casa (10 × 12), signore di casa in
casa (12 × 12), aspetto fra due pianeti (45 coppie × 5 aspetti maggiori), segni, case,
configurazioni, dignità, fasi lunari, stelle fisse, sinastria segno-segno, aspetti di
sinastria. Circa **millecinquecento schede brevi per registro**: si costruisce per strati,
partendo dalle voci che compaiono in ogni carta.

**Il montaggio della relazione discorsiva non è una concatenazione.** Il motore ordina le
voci per rilevanza — un Sole congiunto all'Ascendente conta più di un Nettuno in undicesima —
scarta le ripetizioni quando due voci dicono la stessa cosa, e le cuce con connettivi in modo
che il testo finale si legga come una relazione e non come un elenco. Ogni frase resta
tracciabile alla scheda che l'ha prodotta, e il lettore può aprire il dettaglio.

I testi stanno in tabella, non nel codice: l'admin li corregge dal pannello senza toccare un
file.

---

## 11. Guestbook e voti

Il visitatore firma, lascia un saluto e un commento sul calcolo ricevuto, e dà **due voti
distinti da 1 a 5**:

- **gradimento** — quanto gli è piaciuto il portale;
- **attinenza** — quanto il risultato gli è parso corrispondente.

Tenerli separati è la scelta giusta: sono due giudizi diversi e mescolarli renderebbe
entrambi inutili. L'attinenza, aggregata per segno solare, ascendente e configurazione,
diventa il grafico più interessante delle statistiche pubbliche.

Il messaggio può essere **agganciato al calcolo** appena fatto: l'admin vede allora quale
carta ha generato quel commento, e il voto di attinenza acquista un riferimento.

**Contro gli abusi, senza captcha di terzi** — che sarebbero un'altra fuga di dati verso
l'esterno: campo esca invisibile, tempo minimo di compilazione, limite per IP e per
sessione, elenco di parole vietate, e una piccola prova di lavoro in JavaScript per i bot
più insistenti. Se serve, coda di moderazione preventiva attivabile con un interruttore.

**Moderazione**: coda con approva / rifiuta / cestina, modifica del testo con nota, blocco
dell'IP o dell'intera rete, risposta dell'admin mostrata sotto il messaggio, ricerca e
filtri. Ogni azione finisce nel registro dell'admin.

---

## 12. Statistiche pubbliche

Visibili a chiunque, aggregate e prive di dati personali. Sono una delle attrattive del
portale: un archivio che cresce e si racconta.

**Sul cielo** — distribuzione dei segni solari, lunari e degli ascendenti fra tutti i temi
calcolati, confrontata con la distribuzione attesa (che non è uniforme: gli ascendenti non
lo sono mai, per ragioni geometriche, e mostrarlo è didattico); bilancio di elementi e
modalità; percentuale di pianeti retrogradi; aspetti più e meno frequenti; configurazioni
più rare incontrate; fasi lunari.

**Sulle persone** — decenni di nascita, mesi, ore del giorno (con il picco notturno reale
delle nascite spontanee), mappa mondiale dei luoghi di nascita a punti aggregati per
regione, paesi rappresentati.

**Sul portale** — temi calcolati in totale e nel tempo, sinastrie, sistemi di case più
scelti, registro interpretativo preferito, quante carte senza ora, voti medi di gradimento e
attinenza con il loro andamento, le pagine più lette.

**Il cielo di oggi** — posizioni correnti, fase lunare, pianeti retrogradi in questo momento,
prossimi ingressi di segno, prossime lunazioni ed eclissi. È il riquadro che dà un motivo per
tornare domani.

Ogni grafico si scarica in CSV. Sono tutti disegnati con lo stesso codice cromatico degli
elementi, e sono SVG server-side come tutto il resto.

---

## 13. Il pannello di regia

Si entra dalla voce di accesso in fondo alla pagina, che sblocca l'area riservata. Sessione
separata, riautenticazione per le azioni distruttive, e ogni azione registrata.

**Regia del portale** — pagine redazionali con editor Markdown, anteprima e stato
bozza/pubblicata; voci di menu; testi di testata e piè di pagina; interruttori delle
funzioni (sinastria, guestbook, statistiche pubbliche, modalità manutenzione); valori
predefiniti del motore (sistema di case, orbi, zodiaco, registro interpretativo iniziale).

**Corpus** — ricerca, modifica e revisione delle schede interpretative nei due registri, con
indicazione di quali voci non sono ancora state scritte e con che frequenza compaiono nelle
carte: così il lavoro di scrittura parte sempre da ciò che serve di più.

**Guestbook** — la coda di moderazione di §11.

**Registro dei calcoli** — ogni carta richiesta, con dati di nascita, impronta, quante volte
è stata richiesta, tempo di calcolo, errori, permalink. Ricerca per nome, per data, per
luogo. Esportazione. Cancellazione singola su richiesta dell'interessato.

**Registro accessi** — come scelto, con **IP completo e conservazione illimitata**. Per ogni
accesso: data e ora, indirizzo IPv4 o IPv6, paese, regione, città, coordinate, ASN e
operatore, user agent con famiglia di browser, sistema e tipo di dispositivo, se è un bot,
referente, lingua accettata, percorso richiesto, metodo, stato HTTP, byte serviti, durata di
elaborazione, identificativo di sessione. La geolocalizzazione usa un database **offline**
(DB-IP Lite o equivalente) importato in MariaDB: nessuna interrogazione a servizi esterni,
che sarebbe il colmo per un portale che si preoccupa della privacy dei suoi visitatori.

Viste derivate: sessioni ricostruite con il percorso di navigazione, visitatori ricorrenti,
classifica per IP e per rete, provenienza geografica su mappa, umani contro bot, ore di
punta, imbuto del modulo di nascita (quanti aprono, quanti compilano, quanti arrivano al
risultato, dove si fermano), errori, tempi di risposta, elenco dei blocchi.

**Manutenzione** — svuotamento selettivo delle cache, ricalcolo forzato di una carta, stato
di libswe e dei file di effemeridi, verifica dei test di regressione, esecuzione degli
import (gazetteer, stelle, GeoIP), backup del database, esportazione completa.

---

## 14. Schema dati

MariaDB 11.8, InnoDB, `utf8mb4_unicode_ci`, migrazioni numerate in `db/`.

**Riferimento** (importato, non modificato a mano)
`luoghi`, `luoghi_alias`, `fusi_poligoni` (SPATIAL), `stelle`, `costellazioni`,
`costellazioni_linee`, `geoip_reti`, `geoip_asn`.

**Calcoli**
`soggetti` — generalità, data, ora, precisione dell'ora, luogo, coordinate, altitudine,
fuso applicato, offset applicato.
`calcoli` — token pubblico, tipo, impronta, opzioni, esito JSON, riferimento all'SVG, numero
di richieste, tempi, esito o errore.
`calcoli_soggetti` — il ponte molti-a-molti, perché una sinastria ha due soggetti.

**Corpus**
`testi` — ambito, chiave, registro, titolo, corpo, peso, stato, revisione.

**Comunità**
`guestbook`, `guestbook_risposte`.

**Regia**
`pagine`, `impostazioni`, `admin_registro`, `blocchi`.

**Telemetria**
`accessi` (partizionata per mese, perché a conservazione illimitata cresce senza fine),
`sessioni`, `eventi`, `statistiche_giorno` (aggregati precalcolati, così le pagine
pubbliche non interrogano mai la tabella grande).

La partizione mensile di `accessi` è la decisione che rende sostenibile la conservazione
illimitata: le interrogazioni recenti restano veloci anche con milioni di righe, e un mese
vecchio si archivia o si stacca senza toccare il resto.

---

## 15. Licenze, privacy, avvertenze

### 15.1 La Swiss Ephemeris impone AGPL, e questo cambia la prassi

Punto da decidere consapevolmente, perché **si discosta dalla prassi consolidata** dei repo
pubblici in GPL-3.0.

La Swiss Ephemeris è distribuita in doppia licenza: **AGPL-3.0** oppure licenza commerciale a
pagamento. L'AGPL aggiunge alla GPL una clausola che riguarda esattamente il caso di un
portale web: **chi usa il programma attraverso la rete ha diritto di ricevere il sorgente**,
anche senza che il programma gli venga distribuito.

Conseguenze concrete per Tema e Cielo:

1. Il repo pubblico va licenziato **AGPL-3.0**, non GPL-3.0.
2. Nel piè di pagina del portale ci va un collegamento visibile al sorgente completo, cioè al
   repo pubblico. Non è un vezzo: è la condizione di conformità.
3. Il repo privato `My_TemaECielo` resta un backup e non è una distribuzione, quindi non
   cambia nulla per lui.

Le alternative esistono ma sono peggiori: una licenza commerciale Swiss Ephemeris costa; un
motore scritto da zero sarebbe mesi di lavoro per una precisione inferiore. L'AGPL qui non
toglie niente a un progetto che sarebbe comunque stato pubblicato in GPL — aggiunge solo
l'obbligo del collegamento in pagina.

### 15.2 Materiale di terzi, fuori dal repo pubblico

Come per gli altri progetti: quello che non è nostro non si ridistribuisce. Restano in
`fonti/`, esclusi dal versionamento, con uno script che li scarica e un `FONTI.md` che
dichiara origine e licenza di ciascuno:

| materiale | origine | licenza |
|---|---|---|
| file di effemeridi | `swe-basic-data` (Debian) | AGPL-3.0 / commerciale |
| gazetteer | GeoNames | CC BY 4.0 |
| poligoni dei fusi | timezone-boundary-builder | ODbL |
| catalogo stellare | HYG | CC BY-SA 4.0 |
| linee costellazioni | Stellarium | GPL-2.0 |
| geolocalizzazione IP | DB-IP Lite | CC BY 4.0 |

### 15.3 Dati personali

Il portale tratta, insieme, **data-ora-luogo di nascita** (che identificano una persona con
precisione notevole) e **indirizzi IP completi conservati senza scadenza**. Titolare del
trattamento è chi gestisce il server.

Quello che il progetto mette a disposizione, già costruito:

- **informativa** come pagina redazionale, richiamata sotto il modulo di calcolo e sotto il
  guestbook, che dice cosa si raccoglie, perché, per quanto e come farlo cancellare;
- **cancellazione autonoma**: chi possiede il permalink del proprio calcolo può eliminarlo
  dal portale con un pulsante, senza chiedere niente a nessuno;
- **esportazione**: dalla stessa pagina, il proprio calcolo in JSON;
- **purga programmata degli accessi** già implementata e **disattivata per impostazione**,
  con il numero di giorni configurabile: basta un interruttore nel pannello se un domani si
  cambia idea;
- **anonimizzazione dell'IP** disponibile come modalità alternativa, anch'essa spenta.

Il `robots.txt` e un `X-Robots-Tag: noindex` sui permalink tengono le carte fuori dai motori
di ricerca: un tema natale con nome e cognome indicizzato su Google sarebbe un problema serio
e va escluso per costruzione, non per buona volontà.

### 15.4 Avvertenza in piè di pagina

Una riga sobria, sempre presente: l'astrologia è una tradizione simbolica e culturale, non
una scienza predittiva; il portale calcola posizioni astronomiche reali e vi applica un
linguaggio interpretativo storico. Detta così non toglie nulla al fascino della cosa, e
mette il portale in una posizione onesta. La parte astronomica, per inciso, è verificabile e
corretta al secondo d'arco: quella è scienza per davvero.

---

## 16. Percorso di sviluppo

Otto fasi, ciascuna con un risultato visibile e collaudabile.

**F0 — Fondamenta.** Impianto PHP 8.4, autoload, router, configurazione fuori dal webroot,
schema iniziale, conf Apache con CSP a nonce, script di bootstrap e installazione, veste
grafica con le due palette, sprite dei glifi, pagina statica. *Risultato: il portale risponde
e ha già la sua faccia.*

**F1 — Il motore.** `libswe2.0` e `swe-basic-data` installati, worker CLI con FFI, protocollo
JSON, pianeti, punti, case negli undici sistemi, aspetti con applicativo/separativo, dignità,
bilanci, configurazioni. Test di regressione contro carte di controllo. *Risultato: da riga
di comando esce un tema natale completo e verificato.*

**F2 — Il luogo e il tempo.** Import GeoNames, ricerca offline, mappa Leaflet con i due
strati Esri, coordinate nei tre modi, poligoni dei fusi, catena del fuso storico con i casi
di ora ambigua e inesistente, le tre modalità di precisione dell'ora. *Risultato: il modulo
di calcolo funziona ed è quello definitivo.*

**F3 — La carta.** Generatore SVG della ruota singola con anti-collisione, tabelle di
posizioni, griglia degli aspetti, dignità, bilanci, permalink, stampa, scarico dell'SVG.
*Risultato: il portale fa il suo mestiere principale.*

**F4 — Il cielo.** Import del catalogo stellare e delle costellazioni, proiezione
stereografica, volta celeste in SVG con fase lunare e colore del cielo, effemeridi del giorno
di nascita, pagina "Oggi". *Risultato: la seconda metà del portale esiste.*

**F5 — Le parole.** Tabella dei testi, editor nel pannello, primo strato del corpus nei due
registri, motore di montaggio della relazione con ordinamento per rilevanza e soppressione
delle ripetizioni. *Risultato: la carta si legge, non solo si guarda.*

**F6 — Relazioni e derivati.** Sinastria rapida segno-segno, sinastria completa con doppia
ruota, composita e Davison, punteggi per area; transiti, rivoluzione solare e lunare,
progressioni, direzioni, profezioni. *Risultato: c'è un motivo per tornare più volte.*

**F7 — Comunità e regia.** Guestbook con i due voti e le difese anti-abuso, moderazione,
registro accessi con GeoIP offline, statistiche pubbliche, pannello di regia completo,
gestione pagine, esportazioni, manutenzione. *Risultato: il portale è finito e si governa da
solo.*

**Dopo** — armoniche e zodiaco siderale, relazione in PDF, carta eliocentrica, glossario
navigabile, condivisione come immagine.

---

## 17. Trappole già note

| trappola | dove | come si evita |
|---|---|---|
| **Fuso storico e ora legale** | F2 | Catena completa di §8.1, offset sempre mostrato, ore ambigue chieste |
| **Ora di nascita ignota** | F1/F3 | Modalità dichiarate, valori inattendibili attenuati, arco delle 24 ore |
| **Parte di Fortuna** | F1 | Formula invertita fra carta diurna e notturna: è l'errore classico |
| **Stelle fisse non precessate** | F1 | Posizione calcolata alla data, non alla J2000 |
| **Glifi Unicode incoerenti** | F0 | Sprite SVG proprio, nessuna dipendenza dai font di sistema |
| **Glifi sovrapposti nella ruota** | F3 | Rilassamento a molle + linea guida al grado vero |
| **Case degeneri alle alte latitudini** | F1 | Rilevamento e proposta di un sistema alternativo |
| **Licenza AGPL della Swiss Ephemeris** | F1 | §15.1: repo pubblico AGPL, sorgente linkato in pagina |
| **`accessi` che cresce senza limite** | F7 | Partizione mensile + aggregati precalcolati |
| **CSP e stili inline di Leaflet** | F2 | Nonce fin da F0, Leaflet configurato di conseguenza |
| **FFI bloccato sotto Apache** | F1 | Il calcolo passa dal CLI, dove l'FFI è sempre attivo |
| **Permalink indicizzati dai motori** | F3 | `noindex` sui permalink, `robots.txt` |
| **Bot che falsano le statistiche** | F7 | Riconoscimento dei bot e conteggi separati |

---

## 18. Pubblicazione

Secondo la prassi consolidata, con una sola variante.

- **Pubblico**: `Indr1d-C0ld/TemaECielo`, **AGPL-3.0** anziché GPL-3.0 per §15.1. La cartella
  di lavoro `<lavoro>/TemaECielo/` *è* già il repo pubblico, tenuta factory-default: host,
  domini, percorsi e credenziali sono segnaposto, `config/` e `fonti/` sono esclusi.
- **Privato**: `Indr1d-C0ld/My_TemaECielo`, backup integrale del deployment vivo.
- **Identità dei commit**: `TemaECielo <noreply@example.com>`, impostata *prima* del primo
  commit in entrambi i repo.
- **`sync_temaecielo.sh`** accanto alle cartelle di lavoro, con le guardie di rito: una che cerca
  tracce personali residue (indirizzo, dominio, nome macchina, utente di sistema, password di
  prova) e una che cerca rimandi rotti nei documenti. Più una terza, propria di questo
  progetto: **nessun file di `fonti/` deve finire nel pubblico**, perché è materiale di terzi.
- Il README pubblico spiega che i dati di riferimento non sono inclusi, come scaricarli, e
  quali licenze portano con sé.
- Voce nuova in `services.php` della landing page di Balthasar, gruppo pubblico.

---

## 19. Da decidere prima di F0

Cose che non bloccano il disegno ma vanno fissate prima di scrivere codice.

1. **Sotto-percorso o sottodominio.** `/temaecielo` è coerente con gli altri servizi; un
   sottodominio starebbe meglio a un portale con un nome proprio. Ricordare l'HSTS del vhost,
   che ha già dato problemi ai servizi esposti per porta.
2. **Accesso admin.** Riuso del `.htpasswd` di Ezine come la landing page — una password sola
   per tutte le aree riservate — oppure credenziali proprie in tabella con sessione dedicata.
   La seconda è più pulita per un pannello che ha un registro di azioni.
3. **Lingua.** Tutto in italiano, oppure struttura predisposta per l'inglese fin da F0. Il
   corpus è la parte che costa: aggiungere una lingua dopo significa riscriverlo.
4. **Ampiezza del corpus al varo.** Con quante schede si pubblica: il minimo che copre ogni
   carta (pianeta in segno, pianeta in casa, aspetti maggiori) è già circa seicento voci per
   registro.
5. **Nomi delle persone.** Se conservarli nel registro dei calcoli o sostituirli con le sole
   iniziali: incide su §15.3 e sulla serenità con cui si tiene il registro.

---

## 20. Poscritto: quello che si è scoperto dopo

Il documento sopra è stato scritto prima di cominciare, e si è scelto di non riscriverlo: serve
a capire *perché* il portale è fatto così. Qui sotto stanno invece le cose che si sono viste
solo costruendolo e usandolo, e che cambiano il disegno.

### 20.1 La volta doveva essere navigabile fin dall'inizio

Nel progetto la volta celeste era un'illustrazione: il cielo dell'istante di nascita, da
guardare. Alla prova dei fatti la prima cosa che si vuole fare davanti a una carta del cielo è
**spostarsi** — di un'ora, di un giorno, di un luogo — e la seconda è **avvicinarsi**, perché
le stelle deboli e i nomi ci sono già tutti nel disegno e si vedono solo ingrandendo.

Nessuna delle due richiede il server: lo zoom agisce sul `viewBox` dell'SVG, e lo spostamento
nel tempo è un collegamento con parametri diversi. La lezione generale è che un disegno
vettoriale generato dal server non è meno interattivo di uno costruito nel browser — è
interattivo in un altro punto, e quel punto costa molto meno.

### 20.2 Una cache senza scadenza è un difetto, non una scelta

`calcoli` tiene insieme due cose che sembravano affini e non lo sono: i permalink, che sono
dati veri e irripetibili, e la cache del motore, che è ricalcolabile. Finché la volta mostrava
solo «adesso», la cache cresceva piano e nessuno se n'è accorto. Bastata rendere scegliibili
data e luogo perché diventasse illimitata.

La cura non è un compito periodico — chi installa il portale altrove non sa di doverlo
installare — ma una valvola dentro la scrittura, che una volta su duecento butta ciò che
nessuno richiede da un mese. La riga che conta è `gettone IS NULL`.

### 20.3 Un indirizzo IP *assente* costa quanto tutta la tabella

È il difetto peggiore trovato, e valeva quasi cinque secondi su ogni pagina.

La ricerca geografica di un indirizzo si fa su una tabella di sette milioni e settecentomila
intervalli. La domanda ingenua — «dammi l'intervallo con `ip_da <= X AND ip_a >= X`» — si
comporta benissimo quando l'indirizzo c'è: il database salta sull'indice e trova subito.
Quando l'indirizzo **non** c'è, torna indietro riga per riga cercando un intervallo che
arrivi abbastanza avanti, e non lo trova mai: scandisce tutto.

La forma giusta è un salto solo — «l'ultimo intervallo che comincia prima di X» — e poi il
confronto sul suo estremo destro, fatto in PHP. Gli intervalli non si sovrappongono, quindi
quel candidato è l'unico possibile.

Il motivo per cui è sfuggito così a lungo è istruttivo: in prova si usano indirizzi veri, e
gli indirizzi veri ci sono. A non esserci sono gli indirizzi di rete locale — cioè quelli da
cui si guarda il proprio portale da casa. **Il caso lento era esattamente quello dello
sviluppatore, e nessuna prova lo toccava.**

### 20.4 `fastcgi_finish_request` non esiste sotto mod_php

Il front controller manda la risposta, poi chiude la connessione con
`fastcgi_finish_request()` e solo dopo registra la visita. Con PHP-FPM funziona. Con mod_php
quella funzione **non esiste**, il blocco non fa nulla e la telemetria resta dentro il tempo
di risposta.

Non è un guaio di per sé — sono pochi millisecondi — ma è una falsa sicurezza: tutto ciò che
sta dopo la risposta dev'essere veloce *di suo*, e non perché si crede che nessuno lo stia
aspettando. Era proprio lì che si nascondevano i cinque secondi di §20.3.

### 20.5 HEAD non è un metodo esotico

Nessuna rotta è dichiarata come HEAD, e non avrebbe senso dichiararle tutte due volte: deve
pensarci lo smistatore, traducendolo in GET. Non facendolo, ogni richiesta HEAD riceveva un
405 — e con esso una pagina d'errore che, non stando sotto `/carta/`, **non portava
l'intestazione `noindex`**. Un HEAD su un permalink era l'unico modo di toccare una carta
senza ricevere il divieto di indicizzarla.

### 20.6 La barra delle sezioni non stava su un telefono

Sette voci in maiuscoletto spaziato occupano settecento pixel distese. Su uno schermo da
trecentosettantacinque diventavano tre righe, e la testata si mangiava un terzo dello schermo
prima che cominciasse il contenuto — su ogni pagina.

Il pulsante che la richiude nasce `hidden` nel documento ed è il JavaScript a scoprirlo:
nascondere il menu senza dare modo di riaprirlo chiuderebbe fuori chi non ha JavaScript.
Chiudere il menu è una comodità; poterci entrare non lo è.

### 20.7 Una media query a metà foglio viene scavalcata in silenzio

Le regole adattive erano state messe accanto alle altre, a metà del foglio di stile. Un foglio
di stile si legge dall'alto in basso e a parità di peso vince l'ultima regola scritta: tutto
ciò che veniva definito più sotto le annullava. Non c'è nessun errore, nessun avviso, e la
pagina sembra semplicemente non adattarsi. Ora stanno in fondo, e c'è scritto perché.
