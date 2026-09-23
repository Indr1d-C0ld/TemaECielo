/* ===========================================================================
   Tema e Cielo — il completamento automatico dei luoghi.

   Lo usano in due: il modulo di nascita e il quadro di comando della volta
   celeste. Chiedono la stessa cosa al server — `/api/luoghi` — e si comportano
   allo stesso modo; quel che cambia e' cosa fanno del luogo scelto, e quello
   arriva dall'esterno come funzione.

   Senza JavaScript non succede nulla di male: i campi di latitudine e
   longitudine restano campi di testo normali e la pagina funziona lo stesso.
   Si perde la comodita', non la funzione.
   =========================================================================== */
(function () {
  'use strict';

  window.TEC = window.TEC || {};

  /**
   * @param opzioni.campo     input di testo in cui si digita
   * @param opzioni.lista     <ul> in cui comparire i risultati
   * @param opzioni.base      prefisso degli indirizzi del portale
   * @param opzioni.prefisso  prefisso degli id delle righe (dev'essere unico in pagina)
   * @param opzioni.onScelta  funzione chiamata col luogo scelto
   */
  window.TEC.autocompletaLuoghi = function (opzioni) {
    var campo = opzioni.campo;
    var lista = opzioni.lista;
    if (!campo || !lista) { return null; }

    var base     = opzioni.base || '';
    var prefisso = opzioni.prefisso || 'luogo';
    var onScelta = opzioni.onScelta || function () {};

    var attesa = null, ultimaQuery = '', indiceAttivo = -1, correnti = [];
    // Ogni ricerca ha un numero; conta solo la risposta all'ultima. Senza, una
    // risposta lenta arrivava dopo una piu' recente e la sovrascriveva, oppure
    // riapriva la lista quando il luogo era gia' stato scelto.
    var sequenza = 0;
    // Il testo del campo al momento dell'ultima scelta: se non e' cambiato,
    // Invio puo' spedire il modulo.
    var ultimaScelta = campo.value.trim();

    function apri()   { lista.hidden = false; campo.setAttribute('aria-expanded', 'true'); }
    function chiudi() {
      lista.hidden = true;
      campo.setAttribute('aria-expanded', 'false');
      campo.removeAttribute('aria-activedescendant');
      indiceAttivo = -1;
    }

    function cerca(q) {
      // Sotto le tre lettere si chiude, e si dimentica l'ultima ricerca: prima
      // restava memorizzata, e cancellando «Mil» fino a «Mi» per poi riscrivere
      // «Mil» la lista non si riapriva piu'.
      if (q.length < 3) { ultimaQuery = ''; sequenza++; chiudi(); return; }
      if (q === ultimaQuery) { return; }
      ultimaQuery = q;
      var numero = ++sequenza;

      fetch(base + '/api/luoghi?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (numero !== sequenza) { return; }   // e' arrivata dopo una piu' recente
          if (!d || !d.risultati) { chiudi(); return; }
          mostra(d.risultati);
        })
        .catch(function () { if (numero === sequenza) { chiudi(); } });
    }

    function mostra(risultati) {
      correnti = risultati;
      indiceAttivo = -1;
      lista.textContent = '';

      if (!risultati.length) {
        var vuoto = document.createElement('li');
        vuoto.className = 'risultato-vuoto';
        vuoto.textContent = 'Nessun luogo trovato. Prova con un nome diverso, o indica il punto sulla mappa.';
        lista.appendChild(vuoto);
        apri();
        return;
      }

      risultati.forEach(function (r, i) {
        var li = document.createElement('li');
        li.className = 'risultato';
        li.setAttribute('role', 'option');
        li.setAttribute('id', prefisso + '-' + i);
        li.setAttribute('aria-selected', 'false');

        var nome = document.createElement('span');
        nome.className = 'risultato-nome';
        nome.textContent = r.nome;
        li.appendChild(nome);

        // Se il luogo e' stato trovato digitando un altro nome — si cerca
        // «Londra» e il luogo si chiama «London» — glielo si mostra, altrimenti
        // non si capisce perche' quella riga sia comparsa.
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

        // `mousedown` e non `click`: il click arriva dopo che il campo ha gia'
        // perso il fuoco, e la lista a quel punto si e' gia' chiusa.
        li.addEventListener('mousedown', function (ev) { ev.preventDefault(); scegli(i); });
        lista.appendChild(li);
      });

      apri();
    }

    function evidenzia(i) {
      var voci = lista.querySelectorAll('.risultato');
      voci.forEach(function (v, k) {
        v.classList.toggle('attivo', k === i);
        v.setAttribute('aria-selected', k === i ? 'true' : 'false');
      });
      if (i >= 0 && voci[i]) {
        voci[i].scrollIntoView({ block: 'nearest' });
        campo.setAttribute('aria-activedescendant', prefisso + '-' + i);
      }
    }

    function scegli(i) {
      var r = correnti[i];
      if (!r) { return; }
      sequenza++;   // qualunque risposta ancora in viaggio non riapre piu' la lista
      chiudi();
      ultimaQuery = campo.value;
      onScelta(r);
    }

    campo.addEventListener('input', function () {
      clearTimeout(attesa);
      var q = campo.value.trim();
      // Un quarto di secondo: si cerca quando si smette di digitare, non a
      // ogni tasto premuto.
      attesa = setTimeout(function () { cerca(q); }, 250);
    });

    campo.addEventListener('keydown', function (ev) {
      if (lista.hidden) {
        // Invio con la lista chiusa, ma con un testo che non e' ancora un luogo
        // scelto: il modulo partirebbe con le coordinate di prima. Si riapre la
        // ricerca invece di spedire.
        if (ev.key === 'Enter' && campo.value.trim() !== ultimaScelta) {
          ev.preventDefault();
          ultimaQuery = '';
          cerca(campo.value.trim());
        }
        return;
      }
      var n = correnti.length;
      if (!n) { if (ev.key === 'Escape') { chiudi(); } return; }

      if (ev.key === 'ArrowDown')    { ev.preventDefault(); indiceAttivo = (indiceAttivo + 1) % n; evidenzia(indiceAttivo); }
      else if (ev.key === 'ArrowUp') { ev.preventDefault(); indiceAttivo = (indiceAttivo - 1 + n) % n; evidenzia(indiceAttivo); }
      // Invio senza una voce evidenziata sceglie la prima, come fa ogni ricerca:
      // altrimenti partirebbe il modulo con le coordinate del luogo di prima.
      else if (ev.key === 'Enter') { ev.preventDefault(); scegli(indiceAttivo >= 0 ? indiceAttivo : 0); }
      else if (ev.key === 'Escape')  { chiudi(); }
    });

    campo.addEventListener('blur', function () { setTimeout(chiudi, 150); });

    return {
      /** Da chiamare quando il testo del campo e' cambiato da fuori. */
      sincronizza: function () { ultimaQuery = campo.value; ultimaScelta = campo.value.trim(); },
      chiudi: chiudi
    };
  };
}());
