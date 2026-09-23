/* ===========================================================================
   Tema e Cielo — JavaScript di comodo.

   Regola di fondo: il portale funziona senza. Il disegno della carta e della
   volta celeste esce gia' fatto dal server, le tabelle sono HTML, i moduli
   sono moduli. Quello che c'e' qui aggiunge comodita', mai contenuto.
   =========================================================================== */
(function () {
  'use strict';

  var CHIAVE = 'temaecielo-veste';
  var radice = document.documentElement;

  /* --- veste notte / pergamena ------------------------------------------- */

  function applica(veste) {
    radice.setAttribute('data-veste', veste);
    try { localStorage.setItem(CHIAVE, veste); } catch (e) { /* navigazione privata */ }
  }

  // La veste salvata la applica gia' veste.js, nel <head>, prima del disegno.

  var tasto = document.getElementById('cambia-veste');
  if (tasto) {
    tasto.addEventListener('click', function () {
      applica(radice.getAttribute('data-veste') === 'pergamena' ? 'notte' : 'pergamena');
    });
  }

  /* --- evidenziazione incrociata carta <-> tabella ------------------------
     Passando su un pianeta nella ruota si accendono i suoi aspetti e si
     evidenzia la riga in tabella; passando sulla riga accade l'inverso.
     E' l'unica animazione prevista, e serve a leggere. Gli agganci esistono
     gia' ora perche' la ruota di F3 li trovi pronti.                        */

  function evidenzia(corpo, acceso) {
    document.querySelectorAll('[data-corpo="' + corpo + '"]').forEach(function (n) {
      n.classList.toggle('acceso', acceso);
      // La riga di tabella intera, non solo la cella che porta l'attributo.
      var tr = n.closest ? n.closest('tr') : null;
      if (tr) { tr.classList.toggle('acceso', acceso); }
    });
    document.querySelectorAll('[data-corpi~="' + corpo + '"]').forEach(function (n) {
      n.classList.toggle('acceso', acceso);
      var tr = n.closest ? n.closest('tr') : null;
      if (tr) { tr.classList.toggle('acceso', acceso); }
    });
    // Spegnendo il resto della tela, le corde accese si leggono davvero.
    document.querySelectorAll('svg.ruota').forEach(function (r) {
      r.classList.toggle('evidenziando', acceso);
    });
  }

  document.addEventListener('mouseover', function (ev) {
    var n = ev.target.closest('[data-corpo]');
    if (n) { evidenzia(n.getAttribute('data-corpo'), true); }
  });
  document.addEventListener('mouseout', function (ev) {
    var n = ev.target.closest('[data-corpo]');
    if (n) { evidenzia(n.getAttribute('data-corpo'), false); }
  });

  /* --- il menu che si richiude sugli schermi stretti ----------------------
     Sette voci in maiuscoletto spaziato occupano tre righe su un telefono: la
     testata arrivava a mangiarsi un terzo dello schermo prima che cominciasse
     il contenuto.

     Il pulsante esiste solo quando c'e' JavaScript, e per questo nel documento
     nasce `hidden`: se lo script non gira, nascondere il menu senza dare modo
     di riaprirlo chiuderebbe fuori il visitatore. Chiudere il menu e' una
     comodita'; poterci entrare non lo e'.                                    */

  var menu = document.getElementById('menu-tasto');
  var navigazione = document.getElementById('navigazione');

  if (menu && navigazione) {
    menu.hidden = false;
    navigazione.classList.add('richiudibile');

    function mostraMenu(aperto) {
      navigazione.classList.toggle('aperta', aperto);
      menu.setAttribute('aria-expanded', aperto ? 'true' : 'false');
    }

    // Si parte chiusi, ma solo dove il pulsante si vede davvero: su un monitor
    // la barra e' sempre distesa e la classe non deve toglierla.
    function stretto() { return getComputedStyle(menu).display !== 'none'; }
    mostraMenu(!stretto());

    menu.addEventListener('click', function () {
      mostraMenu(menu.getAttribute('aria-expanded') !== 'true');
    });

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && menu.getAttribute('aria-expanded') === 'true' && stretto()) {
        mostraMenu(false);
        menu.focus();
      }
    });

    // Girando il telefono si passa da stretto a largo: il menu deve tornare
    // disteso, o resta chiuso su una barra che avrebbe spazio da vendere.
    var ultimoStretto = stretto();
    window.addEventListener('resize', function () {
      var ora = stretto();
      if (ora !== ultimoStretto) { ultimoStretto = ora; mostraMenu(!ora); }
    });
  }
}());
