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

  var salvata = null;
  try { salvata = localStorage.getItem(CHIAVE); } catch (e) { /* idem */ }
  if (salvata === 'notte' || salvata === 'pergamena') {
    radice.setAttribute('data-veste', salvata);
  }

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
}());
