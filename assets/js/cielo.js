/* ===========================================================================
   Tema e Cielo — la volta navigabile, e il quadro che decide da dove e quando.

   Due mestieri distinti in un file solo perche' stanno sulla stessa pagina e
   nessuno dei due ha senso senza l'altra:

   1. La volta si ingrandisce e si sposta agendo sul `viewBox` dell'SVG. Non si
      ridisegna niente e non si chiede niente al server: il disegno e'
      vettoriale, quindi ingrandirlo mostra davvero piu' dettaglio invece di
      sgranare. Le stelle deboli e i nomi ci sono gia' tutti.

   2. Il quadro di comando e' un modulo GET normale. La mappa e il completamento
      automatico riempiono latitudine e longitudine; il modulo li manda, e il
      server risponde con un altro cielo. Con JavaScript spento restano i campi,
      i pulsanti di scorrimento del tempo e il pulsante d'invio: si perde la
      mappa, non la funzione.
   =========================================================================== */
(function () {
  'use strict';

  var base = document.body.getAttribute('data-base') || '';

  /* =======================================================================
     1. La volta: ingrandimento e spostamento
     ======================================================================= */
  (function volta() {
    var riquadro = document.getElementById('volta-riquadro');
    var tela     = document.getElementById('volta-tela');
    if (!riquadro || !tela) { return; }

    var svg = tela.querySelector('svg.volta');
    if (!svg) { return; }

    var iniziale = (svg.getAttribute('viewBox') || '0 0 820 820').split(/\s+/).map(parseFloat);
    var X0 = iniziale[0], Y0 = iniziale[1], LARGO = iniziale[2], ALTO = iniziale[3];
    if (!(LARGO > 0) || !(ALTO > 0)) { return; }

    var SCALA_MIN = 1, SCALA_MAX = 12;
    var scala = 1, vx = X0, vy = Y0;

    function limita(v, min, max) { return v < min ? min : (v > max ? max : v); }

    function applica() {
      var w = LARGO / scala, h = ALTO / scala;
      // Il disegno non deve mai staccarsi dalla cornice: l'angolo si ferma
      // prima che compaia il vuoto.
      vx = limita(vx, X0, X0 + LARGO - w);
      vy = limita(vy, Y0, Y0 + ALTO - h);

      svg.setAttribute('viewBox', vx.toFixed(2) + ' ' + vy.toFixed(2) + ' ' + w.toFixed(2) + ' ' + h.toFixed(2));
      riquadro.classList.toggle('volta-zoomata', scala > 1.001);
      tela.setAttribute('aria-label', scala > 1.001
        ? 'Volta celeste ingrandita ' + scala.toFixed(1) + ' volte. Frecce per spostare, piu\' e meno per la scala, zero per tornare indietro.'
        : 'Volta celeste. Frecce per spostare, piu\' e meno per la scala.');
    }

    /** Dal punto sullo schermo al punto nel disegno. Esatto, qualunque sia la
        cornice: e' la matrice dell'SVG a dirlo, non un conto sui pixel. */
    function puntoSvg(clientX, clientY) {
      var m = svg.getScreenCTM();
      if (!m) { return null; }
      var p = svg.createSVGPoint();
      p.x = clientX; p.y = clientY;
      return p.matrixTransform(m.inverse());
    }

    /** Ingrandisce tenendo fermo il punto indicato. */
    function ingrandisci(nuova, ancora) {
      nuova = limita(nuova, SCALA_MIN, SCALA_MAX);
      if (Math.abs(nuova - scala) < 1e-6) { return; }

      var w = LARGO / scala, h = ALTO / scala;
      var wn = LARGO / nuova, hn = ALTO / nuova;

      if (ancora) {
        vx = ancora.x - (ancora.x - vx) * (wn / w);
        vy = ancora.y - (ancora.y - vy) * (hn / h);
      } else {
        vx += (w - wn) / 2;
        vy += (h - hn) / 2;
      }

      scala = nuova;
      applica();
    }

    function centro() {
      return { x: vx + LARGO / scala / 2, y: vy + ALTO / scala / 2 };
    }

    function reimposta() { scala = 1; vx = X0; vy = Y0; applica(); }

    /* --- rotella ---------------------------------------------------------- */
    tela.addEventListener('wheel', function (ev) {
      ev.preventDefault();
      var p = puntoSvg(ev.clientX, ev.clientY);
      // `deltaMode` 1 vuol dire righe, non pixel: senza normalizzarlo, su
      // Firefox un colpo di rotella salta da un capo all'altro della scala.
      var passo = ev.deltaY * (ev.deltaMode === 1 ? 16 : (ev.deltaMode === 2 ? 400 : 1));
      ingrandisci(scala * Math.pow(0.999, passo), p);
    }, { passive: false });

    /* --- trascinamento e pizzico ------------------------------------------ */
    var attivi = {};      // pointerId → ultima posizione sullo schermo
    var pizzico = null;   // { distanza, scala, ancora }

    function conta() { return Object.keys(attivi).length; }

    function distanzaFra(a, b) {
      return Math.hypot(a.x - b.x, a.y - b.y);
    }

    tela.addEventListener('pointerdown', function (ev) {
      if (ev.target.closest('.volta-attrezzi')) { return; }
      tela.setPointerCapture(ev.pointerId);
      attivi[ev.pointerId] = { x: ev.clientX, y: ev.clientY };

      if (conta() === 2) {
        var p = Object.keys(attivi).map(function (k) { return attivi[k]; });
        pizzico = {
          distanza: distanzaFra(p[0], p[1]),
          scala: scala,
          ancora: puntoSvg((p[0].x + p[1].x) / 2, (p[0].y + p[1].y) / 2)
        };
      }
      riquadro.classList.add('volta-in-mano');
    });

    tela.addEventListener('pointermove', function (ev) {
      var prec = attivi[ev.pointerId];
      if (!prec) { return; }
      ev.preventDefault();

      var ora = { x: ev.clientX, y: ev.clientY };

      if (conta() === 2 && pizzico) {
        attivi[ev.pointerId] = ora;
        var p = Object.keys(attivi).map(function (k) { return attivi[k]; });
        var d = distanzaFra(p[0], p[1]);
        if (pizzico.distanza > 8) {
          ingrandisci(pizzico.scala * (d / pizzico.distanza), pizzico.ancora);
        }
        return;
      }

      // Uno spostamento sullo schermo vale tanti passi nel disegno quanti ne
      // sta mostrando la cornice adesso: lo stesso gesto sposta di poco quando
      // si e' ingranditi, e di molto quando no.
      var r = svg.getBoundingClientRect();
      if (r.width > 0 && r.height > 0) {
        vx -= (ora.x - prec.x) * (LARGO / scala) / r.width;
        vy -= (ora.y - prec.y) * (ALTO / scala) / r.height;
        applica();
      }
      attivi[ev.pointerId] = ora;
    });

    function lascia(ev) {
      delete attivi[ev.pointerId];
      if (conta() < 2) { pizzico = null; }
      if (conta() === 0) { riquadro.classList.remove('volta-in-mano'); }
    }
    tela.addEventListener('pointerup', lascia);
    tela.addEventListener('pointercancel', lascia);
    tela.addEventListener('pointerleave', lascia);

    /* --- doppio tocco / doppio clic --------------------------------------- */
    tela.addEventListener('dblclick', function (ev) {
      ev.preventDefault();
      ingrandisci(scala > 1.001 ? 1 : 3, puntoSvg(ev.clientX, ev.clientY));
    });

    /* --- pulsanti --------------------------------------------------------- */
    riquadro.querySelectorAll('[data-zoom]').forEach(function (b) {
      b.addEventListener('click', function () {
        var che = b.getAttribute('data-zoom');
        if (che === 'in')        { ingrandisci(scala * 1.6, centro()); }
        else if (che === 'out')  { ingrandisci(scala / 1.6, centro()); }
        else                     { reimposta(); }
      });
    });

    /* --- tastiera --------------------------------------------------------- */
    // Un riquadro che si comanda dalla tastiera dev'essere raggiungibile con
    // il tabulatore. Il ruolo di immagine resta all'SVG che sta dentro: questo
    // e' il comando, non il disegno.
    tela.setAttribute('tabindex', '0');
    tela.addEventListener('keydown', function (ev) {
      var passo = (LARGO / scala) * 0.12;
      var fatto = true;

      switch (ev.key) {
        case 'ArrowLeft':  vx -= passo; applica(); break;
        case 'ArrowRight': vx += passo; applica(); break;
        case 'ArrowUp':    vy -= passo; applica(); break;
        case 'ArrowDown':  vy += passo; applica(); break;
        case '+': case '=': ingrandisci(scala * 1.6, centro()); break;
        case '-': case '_': ingrandisci(scala / 1.6, centro()); break;
        case '0':          reimposta(); break;
        default: fatto = false;
      }
      if (fatto) { ev.preventDefault(); }
    });

    var aiuto = document.getElementById('volta-aiuto');
    if (aiuto) { aiuto.hidden = false; }
    riquadro.classList.add('volta-viva');
    applica();
  }());

  /* =======================================================================
     2. Il quadro: da dove e quando
     ======================================================================= */
  (function quadro() {
    var modulo = document.getElementById('quadro-cielo');
    if (!modulo || !window.TEC || !window.TEC.autocompletaLuoghi) { return; }

    var $ = function (id) { return document.getElementById(id); };
    var campoCerca = $('cerca-cielo');
    var lista      = $('risultati-cielo');
    var campoLat   = $('cielo-lat');
    var campoLon   = $('cielo-lon');
    if (!campoCerca || !campoLat || !campoLon) { return; }

    /* --- mappa ----------------------------------------------------------- */
    var mappa = null, segnaposto = null, strati = {};

    function avviaMappa() {
      var nodo = $('mappa-cielo');
      if (!nodo || mappa || typeof L === 'undefined') { return; }

      var lat = parseFloat(campoLat.value), lon = parseFloat(campoLon.value);
      var haPunto = !isNaN(lat) && !isNaN(lon);

      mappa = L.map(nodo, { zoomControl: true, attributionControl: false })
               .setView(haPunto ? [lat, lon] : [42.5, 12.5], haPunto ? 9 : 4);

      strati.cartina = L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}',
        { maxZoom: 19 });
      strati.satellite = L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        { maxZoom: 19 });
      strati.cartina.addTo(mappa);

      if (haPunto) { posa(lat, lon, false); }
      mappa.on('click', function (ev) { posa(ev.latlng.lat, ev.latlng.lng, true); });

      modulo.querySelectorAll('.mappa-vest').forEach(function (b) {
        b.addEventListener('click', function () {
          Object.keys(strati).forEach(function (k) {
            if (mappa.hasLayer(strati[k])) { mappa.removeLayer(strati[k]); }
          });
          strati[b.getAttribute('data-strato')].addTo(mappa);
          modulo.querySelectorAll('.mappa-vest').forEach(function (x) {
            x.classList.toggle('attiva', x === b);
          });
        });
      });
    }

    function posa(lat, lon, interroga) {
      if (!mappa) { return; }
      if (segnaposto) {
        segnaposto.setLatLng([lat, lon]);
      } else {
        segnaposto = L.marker([lat, lon], { draggable: true }).addTo(mappa);
        segnaposto.on('dragend', function () {
          var p = segnaposto.getLatLng();
          posa(p.lat, p.lng, true);
        });
      }
      campoLat.value = lat.toFixed(6);
      campoLon.value = lon.toFixed(6);

      if (interroga) { chiediVicino(lat, lon); }
    }

    function chiediVicino(lat, lon) {
      fetch(base + '/api/luogo-vicino?lat=' + lat.toFixed(6) + '&lon=' + lon.toFixed(6),
            { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d) { return; }
          campoCerca.value = d.luogo
            ? d.luogo.nome + (d.luogo.contesto ? ', ' + d.luogo.contesto : '')
            : 'Punto a ' + lat.toFixed(4) + ', ' + lon.toFixed(4);
          ricerca.sincronizza();
        })
        .catch(function () { /* il punto resta valido comunque */ });
    }

    /* --- ricerca per nome ------------------------------------------------- */
    var ricerca = window.TEC.autocompletaLuoghi({
      campo: campoCerca,
      lista: lista,
      base: base,
      prefisso: 'cielo-luogo',
      onScelta: function (r) {
        campoCerca.value = r.nome + (r.contesto ? ', ' + r.contesto : '');
        campoLat.value = r.lat.toFixed(6);
        campoLon.value = r.lon.toFixed(6);
        ricerca.sincronizza();
        if (mappa) { mappa.setView([r.lat, r.lon], 10); posa(r.lat, r.lon, false); }
      }
    });

    /* La mappa dentro un pannello chiuso nasce con dimensione zero: Leaflet
       deve essere avvisato quando il pannello si apre, o resta un quadrato
       grigio con una tessera sola. Si aggancia qui, e non prima, perche'
       avviare la mappa puo' voler dire chiamare `ricerca`. */
    var piega = document.getElementById('mappa-piega');
    if (piega) {
      piega.addEventListener('toggle', function () {
        if (!piega.open) { return; }
        avviaMappa();
        if (mappa) { setTimeout(function () { mappa.invalidateSize(); }, 40); }
      });
      if (piega.open) { avviaMappa(); }
    }

    [campoLat, campoLon].forEach(function (c) {
      c.addEventListener('change', function () {
        var la = parseFloat(campoLat.value), lo = parseFloat(campoLon.value);
        if (isNaN(la) || isNaN(lo)) { return; }
        if (mappa) { mappa.setView([la, lo], Math.max(mappa.getZoom(), 8)); }
        posa(la, lo, true);
      });
    });
  }());
}());
