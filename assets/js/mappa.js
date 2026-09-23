/* ===========================================================================
   Tema e Cielo — il modulo di nascita.

   Tre modi di indicare il luogo, tutti e tre sincronizzati fra loro: cercarlo
   per nome, cliccare sulla mappa, digitare le coordinate. Toccandone uno, gli
   altri due si aggiornano.

   Il completamento automatico sta in `luoghi.js`, che lo condivide con il
   quadro di comando della volta celeste: qui c'e' solo cosa fare del luogo
   scelto, che e' l'unica parte davvero diversa fra le due pagine.

   Senza JavaScript il modulo resta usabile: i campi di latitudine, longitudine
   e fuso sono normali campi di testo, e il calcolo parte lo stesso. Quello che
   si perde e' la comodita', non la funzione.
   =========================================================================== */
(function () {
  'use strict';

  var base = document.body.getAttribute('data-base') || '';
  var modulo = document.getElementById('modulo-nascita');
  if (!modulo) { return; }
  // `luoghi.js` arriva prima nell'ordine del documento e gli script differiti
  // rispettano quell'ordine: se manca, e' un guasto vero e tacere sarebbe
  // peggio che fermarsi qui.
  if (!window.TEC || !window.TEC.autocompletaLuoghi) { return; }

  var $ = function (id) { return document.getElementById(id); };
  var campoCerca = $('cerca-luogo');
  var lista      = $('risultati-luogo');
  var campoLat   = $('lat');
  var campoLon   = $('lon');
  var campoAlt   = $('altitudine');
  var campoFuso  = $('fuso');
  var campoId    = $('luogo_id');
  var campoNome  = $('luogo_nome');
  var campoData  = $('data');
  var campoOra   = $('ora');
  var esitoFuso  = $('esito-fuso');

  /* --- mappa ------------------------------------------------------------- */

  var mappa = null, segnaposto = null, strati = {};

  function avviaMappa() {
    var nodo = $('mappa');
    if (!nodo || typeof L === 'undefined') { return; }

    var lat = parseFloat(campoLat.value);
    var lon = parseFloat(campoLon.value);
    var haPunto = !isNaN(lat) && !isNaN(lon);

    mappa = L.map(nodo, { zoomControl: true, attributionControl: false })
             .setView(haPunto ? [lat, lon] : [42.5, 12.5], haPunto ? 11 : 5);

    // Le stesse tessere Esri gia' in uso altrove su questo server: nessuna
    // chiave, nessun account, nessun conteggio di chiamate.
    strati.cartina = L.tileLayer(
      'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}',
      { maxZoom: 19 });
    strati.satellite = L.tileLayer(
      'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
      { maxZoom: 19 });

    strati.cartina.addTo(mappa);

    if (haPunto) { posaSegnaposto(lat, lon, false); }

    // `wrap()`: oltre l'antimeridiano Leaflet restituisce longitudini fuori
    // scala, che il modulo rifiuta.
    mappa.on('click', function (ev) {
      var p = ev.latlng.wrap();
      posaSegnaposto(p.lat, p.lng, true);
    });

    modulo.querySelectorAll('.mappa-vest').forEach(function (b) {
      b.addEventListener('click', function () {
        var quale = b.getAttribute('data-strato');
        Object.keys(strati).forEach(function (k) {
          if (mappa.hasLayer(strati[k])) { mappa.removeLayer(strati[k]); }
        });
        strati[quale].addTo(mappa);
        modulo.querySelectorAll('.mappa-vest').forEach(function (x) {
          x.classList.toggle('attiva', x === b);
        });
      });
    });
  }

  function posaSegnaposto(lat, lon, interroga) {
    if (!mappa) { return; }

    if (segnaposto) {
      segnaposto.setLatLng([lat, lon]);
    } else {
      segnaposto = L.marker([lat, lon], { draggable: true }).addTo(mappa);
      segnaposto.on('dragend', function () {
        var p = segnaposto.getLatLng().wrap();
        posaSegnaposto(p.lat, p.lng, true);
      });
    }

    campoLat.value = lat.toFixed(6);
    campoLon.value = lon.toFixed(6);

    if (interroga) { chiediLuogoVicino(lat, lon); }
  }

  /* --- ricerca per nome -------------------------------------------------- */

  var ricerca = window.TEC.autocompletaLuoghi({
    campo: campoCerca,
    lista: lista,
    base: base,
    prefisso: 'luogo',
    onScelta: function (r) {
      campoCerca.value = r.nome + (r.contesto ? ' — ' + r.contesto : '');
      campoNome.value  = r.nome + (r.contesto ? ', ' + r.contesto : '');
      campoId.value    = r.id;
      campoLat.value   = r.lat.toFixed(6);
      campoLon.value   = r.lon.toFixed(6);
      campoAlt.value   = r.altitudine;
      campoFuso.value  = r.fuso;

      ricerca.sincronizza();

      if (mappa) {
        mappa.setView([r.lat, r.lon], 12);
        posaSegnaposto(r.lat, r.lon, false);
      }
      aggiornaFuso();
    }
  });

  /* --- click sulla mappa → luogo e fuso ---------------------------------- */

  function chiediLuogoVicino(lat, lon) {
    fetch(base + '/api/luogo-vicino?lat=' + lat.toFixed(6) + '&lon=' + lon.toFixed(6),
          { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d) { return; }

        if (d.fuso && d.fuso.fuso) { campoFuso.value = d.fuso.fuso; }

        if (d.luogo) {
          campoId.value   = d.luogo.id;
          campoNome.value = d.luogo.nome + (d.luogo.contesto ? ', ' + d.luogo.contesto : '');
          campoAlt.value  = d.luogo.altitudine;
          campoCerca.value = d.luogo.nome
            + (d.luogo.contesto ? ' — ' + d.luogo.contesto : '')
            + (d.luogo.distanza_km > 1 ? ' (a ' + d.luogo.distanza_km.toFixed(0) + ' km)' : '');
        } else {
          // Nessun luogo abitato nel raggio: il punto resta valido, il fuso e'
          // quello nautico. Va detto, non nascosto.
          campoId.value = '';
          campoNome.value = 'Punto a ' + lat.toFixed(4) + ', ' + lon.toFixed(4);
          campoCerca.value = campoNome.value;
        }
        ricerca.sincronizza();
        aggiornaFuso();
      })
      .catch(function () { /* la mappa resta usabile anche senza */ });
  }

  /* --- che scarto dal Tempo Universale verrà applicato? ------------------ */

  function aggiornaFuso() {
    if (!esitoFuso || !campoData || !campoOra || !campoFuso) { return; }

    var precisione = modulo.querySelector('input[name="precisione"]:checked');
    if (precisione && precisione.value === 'ignota') { esitoFuso.hidden = true; return; }
    if (!campoData.value || !campoOra.value || !campoFuso.value) { esitoFuso.hidden = true; return; }

    fetch(base + '/api/fuso?data=' + encodeURIComponent(campoData.value)
               + '&ora=' + encodeURIComponent(campoOra.value)
               + '&zona=' + encodeURIComponent(campoFuso.value)
               + (campoLon.value !== '' ? '&lon=' + encodeURIComponent(campoLon.value) : ''),
          { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d) { esitoFuso.hidden = true; return; }

        esitoFuso.hidden = false;
        esitoFuso.className = 'esito-fuso';

        if (d.stato === 'inesistente') {
          esitoFuso.classList.add('esito-male');
          esitoFuso.textContent = d.avviso || 'Quell’ora non è mai esistita in quel luogo.';
        } else if (d.stato === 'ambiguo') {
          esitoFuso.classList.add('esito-attento');
          esitoFuso.textContent = d.avviso || 'Quell’ora è esistita due volte quella notte.';
        } else if (d.ok && d.abbreviazione === 'LMT') {
          // Prima dei fusi: si dice che cosa si sta facendo, non solo lo scarto.
          esitoFuso.textContent = 'Ora locale media del luogo, ' + d.offset_testo
            + ' dalla longitudine: a quella data i fusi orari non erano ancora in uso.'
            + ' → ' + d.utc.replace('T', ' ').replace('Z', ' UT');
        } else if (d.ok) {
          esitoFuso.textContent = 'Scarto applicato: ' + d.offset_testo
            + (d.abbreviazione ? ' (' + d.abbreviazione + ')' : '')
            + (d.ora_legale ? ', ora legale in vigore' : '')
            + ' → ' + d.utc.replace('T', ' ').replace('Z', ' UT');
        } else {
          esitoFuso.hidden = true;
        }
      })
      .catch(function () { esitoFuso.hidden = true; });
  }

  /* --- collegamenti ------------------------------------------------------ */

  [campoLat, campoLon].forEach(function (c) {
    c.addEventListener('change', function () {
      var la = parseFloat(campoLat.value), lo = parseFloat(campoLon.value);
      if (isNaN(la) || isNaN(lo)) { return; }
      if (mappa) { mappa.setView([la, lo], Math.max(mappa.getZoom(), 10)); }
      posaSegnaposto(la, lo, true);
    });
  });

  [campoData, campoOra, campoFuso].forEach(function (c) {
    if (c) { c.addEventListener('change', aggiornaFuso); }
  });

  /* Il campo dell'ora sparisce quando si dichiara di non conoscerla. */
  function aggiornaVisibilitaOra() {
    var scelta = modulo.querySelector('input[name="precisione"]:checked');
    var campo = $('campo-ora');
    if (!campo || !scelta) { return; }
    var ignota = scelta.value === 'ignota';
    campo.hidden = ignota;
    if (campoOra) { campoOra.required = !ignota; }
    if (ignota && esitoFuso) { esitoFuso.hidden = true; } else { aggiornaFuso(); }
  }

  modulo.querySelectorAll('input[name="precisione"]').forEach(function (r) {
    r.addEventListener('change', aggiornaVisibilitaOra);
  });

  avviaMappa();
  aggiornaVisibilitaOra();
}());
