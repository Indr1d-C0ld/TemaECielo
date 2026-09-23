/* ===========================================================================
   Tema e Cielo — la veste, applicata prima che la pagina si disegni.

   Sta nel <head> e NON e' differito, di proposito: e' l'unico script che deve
   girare prima del primo disegno. Quando la scelta veniva applicata da
   portale.js, che e' differito, chi aveva scelto la pergamena vedeva a ogni
   pagina un lampo di cielo notturno prima che la veste cambiasse.

   Sono cinque righe, e il browser le tiene in cache: il costo e' niente.
   =========================================================================== */
(function () {
  'use strict';
  try {
    var v = localStorage.getItem('temaecielo-veste');
    if (v === 'notte' || v === 'pergamena') {
      document.documentElement.setAttribute('data-veste', v);
    }
  } catch (e) { /* navigazione privata: resta la veste predefinita */ }
}());
