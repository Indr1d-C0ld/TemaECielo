# Fonti di terzi

Questi dati **non stanno nel repository**: sono lavoro di altri, e l'AGPL copre il nostro
codice, non ci autorizza a ridistribuire il loro. Si scaricano a parte, in `fonti/`, che e'
escluso dal versionamento.

## Gazetteer dei luoghi — GeoNames

Licenza **CC BY 4.0**. Attribuzione dovuta, e presente nel modulo di nascita.

| file | cosa | peso |
|---|---|---|
| `cities500.txt` | luoghi del mondo con oltre 500 abitanti | ~40 MB |
| `IT.txt` | **tutti** i luoghi abitati italiani | ~15 MB |
| `admin1CodesASCII.txt` | nomi delle regioni | 148 KB |
| `admin2Codes.txt` | nomi delle province | 2,3 MB |
| `alt-it/IT.txt` | nomi alternativi italiani, con codice di lingua | ~10 MB |

`IT.txt` serve perche' **oltre 52.000 luoghi abitati italiani hanno meno di 500 abitanti** e in
`cities500` non ci sono affatto. Senza, meta' dei comuni italiani risulterebbe inesistente.

`alt-it/IT.txt` serve perche' GeoNames chiama Roma «Rome», Milano «Milan» e Firenze
«Florence». Da quel file si ricavano i nomi italiani veri, e — passando per il geonameid che
i file delle suddivisioni portano in quarta colonna — anche «Lombardia» invece di «Lombardy».

### Come si scaricano

```bash
mkdir -p fonti && cd fonti
for f in cities500.zip IT.zip; do
  curl -fLO "https://download.geonames.org/export/dump/$f" && unzip -oq "$f" && rm "$f"
done
for f in admin1CodesASCII.txt admin2Codes.txt readme.txt; do
  curl -fLO "https://download.geonames.org/export/dump/$f"
done
curl -fL -o alt-it.zip https://download.geonames.org/export/dump/alternatenames/IT.zip
unzip -oq alt-it.zip -d alt-it && rm alt-it.zip
```

Poi:

```bash
php bin/importa-luoghi.php fonti
```

L'importazione dura circa tre minuti e produce 287.000 luoghi e 889.000 nomi ricercabili,
per un totale di circa 190 MB di tabelle. E' idempotente: rilanciarla aggiorna senza duplicare.

## Effemeridi — Swiss Ephemeris

Pacchetti Debian `libswe2.0` e `swe-basic-data`, installati da `deploy/00-bootstrap.sh`.
Licenza **AGPL-3.0 o commerciale**: e' la ragione per cui anche questo progetto e' AGPL.
Vedi il README.

I tre file `.se1` del pacchetto base coprono dal 1800 al 2399, che e' il motivo per cui il
modulo di nascita rifiuta le date anteriori al 1800: fuori da quell'intervallo la libreria
ripiegherebbe **in silenzio** su un calcolo meno preciso, ed e' meglio dirlo che lasciarlo
accadere.

## Catalogo stellare — HYG

Licenza **CC BY-SA 4.0**.

| file | cosa | peso |
|---|---|---|
| `hyg_v40.csv` | 119.000 stelle con coordinate J2000, magnitudine e indice di colore | ~32 MB |
| `stellarium/modern.json` | linee delle 88 costellazioni (Stellarium, GPL-2.0) | ~200 KB |

```bash
cd fonti
curl -fL -o hyg_v40.csv.gz \
  https://raw.githubusercontent.com/astronexus/HYG-Database/main/hyg/CURRENT/hygdata_v40.csv.gz
gunzip hyg_v40.csv.gz
mkdir -p stellarium && curl -fL -o stellarium/modern.json \
  https://raw.githubusercontent.com/Stellarium/stellarium/master/skycultures/modern/index.json
cd .. && php bin/importa-stelle.php fonti
```

Si importano solo le stelle fino alla magnitudine 6,5 — il limite dell'occhio nudo in un
cielo davvero buio, circa 8.900 — piu' le poche piu' deboli che servono a chiudere i
segmenti delle costellazioni: un estremo mancante lascerebbe un buco in una figura che
tutti riconoscono. L'import dura cinque secondi.

Le linee di Stellarium sono polilinee di numeri Hipparcos; l'importatore le spezza in
coppie, cosi' il disegno puo' saltare i singoli segmenti che hanno un estremo sotto
l'orizzonte senza dover ricostruire la spezzata.

## Geolocalizzazione degli indirizzi — DB-IP Lite

Licenza **CC BY 4.0**. Attribuzione dovuta, e presente nell'informativa.

| file | cosa | peso |
|---|---|---|
| `dbip-city.csv.gz` | 7,7 milioni di intervalli con paese, regione, citta', coordinate | ~82 MB |
| `dbip-asn.csv.gz` | 473.000 intervalli con numero di sistema autonomo e operatore | ~7 MB |

```bash
cd fonti
M=$(date +%Y-%m)
curl -fL -o dbip-city.csv.gz "https://download.db-ip.com/free/dbip-city-lite-$M.csv.gz"
curl -fL -o dbip-asn.csv.gz  "https://download.db-ip.com/free/dbip-asn-lite-$M.csv.gz"
cd .. && php bin/importa-geoip.php fonti
```

L'import dura circa tre minuti e occupa **720 MB** di tabelle. E' offline, e non e' un
dettaglio: interrogare un servizio esterno per geolocalizzare i visitatori vorrebbe dire
spedire a terzi l'indirizzo di ognuno di loro — il colmo, per un portale costruito per non
far uscire nemmeno il luogo di nascita di chi compila il modulo.

I file sono datati per mese: per aggiornarli basta riscaricarli e rilanciare l'import, che
ricrea le tabelle da zero.

## Quello che NON si scarica, e perche'

**`alternateNamesV2.zip`** (195 MB) darebbe gli esonimi italiani di tutto il mondo con i
codici di lingua. Non serve: la colonna dei nomi alternativi gia' dentro `cities500.txt`
contiene «Londra», «Parigi» e «Monaco di Baviera», che e' quanto basta perche' la ricerca li
trovi. Si mostra il nome ufficiale e, accanto, il nome con cui l'utente l'ha trovato.

**I poligoni dei fusi orari** (`timezone-boundary-builder`, ~100 MB, ODbL). Il fuso si prende
da quello del luogo abitato piu' vicino. Vicino a un confine di fuso puo' sbagliare, ma per un
luogo di *nascita* e' quasi sempre giusto — si nasce dove c'e' gente — e cento megabyte per
coprire quei pochi casi non valgono il prezzo. Se un giorno servira', il punto in cui
innestarli e' `App\Luogo\Gazetteer::fusoDi()`.


## Il corpus interpretativo

Non e' materiale di terzi: e' scritto per questo portale e sta nel repository, in
`db/semi/corpus-*.php`. Si carica con:

```bash
php bin/importa-corpus.php                # non tocca i testi gia' corretti dal pannello
php bin/importa-corpus.php --sostituisci  # riallinea tutto ai semi
```

Senza `--sostituisci` l'import **non sovrascrive** quello che c'e': l'admin corregge i testi
dal pannello, e un import non deve cancellargli il lavoro.

### Come e' fatto

Ottantadue **frammenti** — dieci pianeti, dodici segni, dodici case, cinque aspetti, per due
registri — bastano a comporre le **465 voci per registro** che servono a leggere una carta
qualunque. La copertura e' totale dal primo giorno: nessuna carta esce muta.

Le voci **scritte a mano** scavalcano sempre il composto. Si aggiungono col tempo, e
`/admin/corpus/copertura` dice quali conviene scrivere per prime: quelle che il portale ha
effettivamente composto piu' volte.

### Grammatica del montaggio

Chi scrive deve rispettarla, o le frasi si rompono:

| campo | forma |
|---|---|
| `pianeta.titolo` | gruppo nominale con l'articolo, **minuscolo**: «il centro della coscienza» |
| `segno_modo.corpo` | continua «&lt;Il pianeta&gt; …»: comincia con un verbo |
| `casa_campo.corpo` | idem |
| `aspetto_relazione.corpo` | idem, con `%s` dove va il secondo corpo |
| `dignita.corpo` | `%s` dove va il nome del pianeta |

Le preposizioni articolate le fa `Corpus\Lingua`: «in» + «il bisogno» diventa «nel bisogno»,
e «a» + «Mercurio» resta «a Mercurio». Il pannello rifiuta il salvataggio se si toglie un
`%s` da un testo che ne ha bisogno.
