/* ===========================================================================
   Tema e Cielo — il modulo di nascita.

   Tre modi di indicare il luogo, tutti e tre sincronizzati fra loro: cercarlo
   per nome, cliccare sulla mappa, digitare le coordinate. Toccandone uno, gli
   altri due si aggiornano.

   Senza JavaScript il modulo resta usabile: i campi di latitudine, longitudine
   e fuso sono normali campi di testo, e il calcolo parte lo stesso. Quello che
   si perde e' la comodita', non la funzione.
   =========================================================================== */
(function () {
  'use strict';

  var base = document.body.getAttribute('data-base') || '';
  var modulo = document.getElementById('modulo-nascita');
  if (!modulo) { return; }

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

    mappa.on('click', function (ev) {
      posaSegnaposto(ev.latlng.lat, ev.latlng.lng, true);
    });

    document.querySelectorAll('.mappa-vest').forEach(function (b) {
      b.addEventListener('click', function () {
        var quale = b.getAttribute('data-strato');
        Object.keys(strati).forEach(function (k) {
          if (mappa.hasLayer(strati[k])) { mappa.removeLayer(strati[k]); }
        });
        strati[quale].addTo(mappa);
        document.querySelectorAll('.mappa-vest').forEach(function (x) {
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
        var p = segnaposto.getLatLng();
        posaSegnaposto(p.lat, p.lng, true);
      });
    }

    campoLat.value = lat.toFixed(6);
    campoLon.value = lon.toFixed(6);

    if (interroga) { chiediLuogoVicino(lat, lon); }
  }

  /* --- ricerca per nome -------------------------------------------------- */

  var attesa = null, ultimaQuery = '', indiceAttivo = -1, risultatiCorrenti = [];

  function cerca(q) {
    if (q.length < 3) { chiudiLista(); return; }
    if (q === ultimaQuery) { return; }
    ultimaQuery = q;

    fetch(base + '/api/luoghi?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d || !d.risultati) { chiudiLista(); return; }
        mostraRisultati(d.risultati);
      })
      .catch(chiudiLista);
  }

  function mostraRisultati(risultati) {
    risultatiCorrenti = risultati;
    indiceAttivo = -1;
    lista.textContent = '';

    if (!risultati.length) {
      var vuoto = document.createElement('li');
      vuoto.className = 'risultato-vuoto';
      vuoto.textContent = 'Nessun luogo trovato. Prova con un nome diverso, o indica il punto sulla mappa.';
      lista.appendChild(vuoto);
      apriLista();
      return;
    }

    risultati.forEach(function (r, i) {
      var li = document.createElement('li');
      li.className = 'risultato';
      li.setAttribute('role', 'option');
      li.setAttribute('id', 'luogo-' + i);
      li.setAttribute('data-indice', String(i));

      var nome = document.createElement('span');
      nome.className = 'risultato-nome';
      nome.textContent = r.nome;
      li.appendChild(nome);

      // Se l'utente ha trovato il luogo digitando un altro nome — cerca
      // «Londra» e il luogo si chiama «London» — glielo si mostra, altrimenti
      // non capirebbe perche' quella riga e' comparsa.
      if (r.trovato_come) {
        var alias = document.createElement('span');
        alias.className = 'risultato-alias';
        alias.textContent = '«' + r.trovato_come + '»';
        li.appendChild(alias);
      }

      var ctx = document.createElement('span');
      ctx.className = 'risultato-contesto';
      ctx.textContent = r.contesto;
      li.appendChild(ctx);

      li.addEventListener('mousedown', function (ev) {
        ev.preventDefault();       // prima che il campo perda il fuoco
        scegli(i);
      });
      lista.appendChild(li);
    });

    apriLista();
  }

  function apriLista()  { lista.hidden = false; campoCerca.setAttribute('aria-expanded', 'true'); }
  function chiudiLista() { lista.hidden = true;  campoCerca.setAttribute('aria-expanded', 'false'); indiceAttivo = -1; }

  function evidenzia(i) {
    var voci = lista.querySelectorAll('.risultato');
    voci.forEach(function (v, k) { v.classList.toggle('attivo', k === i); });
    if (i >= 0 && voci[i]) {
      voci[i].scrollIntoView({ block: 'nearest' });
      campoCerca.setAttribute('aria-activedescendant', 'luogo-' + i);
    }
  }

  function scegli(i) {
    var r = risultatiCorrenti[i];
    if (!r) { return; }

    campoCerca.value = r.nome + (r.contesto ? ' — ' + r.contesto : '');
    campoNome.value  = r.nome + (r.contesto ? ', ' + r.contesto : '');
    campoId.value    = r.id;
    campoLat.value   = r.lat.toFixed(6);
    campoLon.value   = r.lon.toFixed(6);
    campoAlt.value   = r.altitudine;
    campoFuso.value  = r.fuso;

    chiudiLista();
    ultimaQuery = campoCerca.value;

    if (mappa) {
      mappa.setView([r.lat, r.lon], 12);
      posaSegnaposto(r.lat, r.lon, false);
    }
    aggiornaFuso();
  }

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
        ultimaQuery = campoCerca.value;
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
               + '&zona=' + encodeURIComponent(campoFuso.value),
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

  campoCerca.addEventListener('input', function () {
    clearTimeout(attesa);
    var q = campoCerca.value.trim();
    // Un quarto di secondo di attesa: si cerca quando si smette di digitare,
    // non a ogni tasto premuto.
    attesa = setTimeout(function () { cerca(q); }, 250);
  });

  campoCerca.addEventListener('keydown', function (ev) {
    if (lista.hidden) { return; }
    var n = risultatiCorrenti.length;

    if (ev.key === 'ArrowDown')      { ev.preventDefault(); indiceAttivo = (indiceAttivo + 1) % n; evidenzia(indiceAttivo); }
    else if (ev.key === 'ArrowUp')   { ev.preventDefault(); indiceAttivo = (indiceAttivo - 1 + n) % n; evidenzia(indiceAttivo); }
    else if (ev.key === 'Enter' && indiceAttivo >= 0) { ev.preventDefault(); scegli(indiceAttivo); }
    else if (ev.key === 'Escape')    { chiudiLista(); }
  });

  campoCerca.addEventListener('blur', function () { setTimeout(chiudiLista, 150); });

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
