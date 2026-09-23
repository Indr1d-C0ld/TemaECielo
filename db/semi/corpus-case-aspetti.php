<?php

declare(strict_types=1);

/**
 * Tema e Cielo — frammenti delle case e degli aspetti.
 *
 * Stessa grammatica dei frammenti dei segni: il corpo continua la frase
 * «<Titolo del pianeta> ...». Negli aspetti, %s e' il posto del secondo corpo.
 */

return [

// ═══════════════════════════════════════════════════════════════════════════
// LE CASE — in che campo della vita
// ═══════════════════════════════════════════════════════════════════════════

['casa_campo', '1', 'tradizionale', 'in prima casa',
 'si trova in prima casa, che è la casa della vita e del corpo. Un pianeta qui si vede da '
 . 'fuori: colora l\'aspetto fisico, il temperamento e il modo di presentarsi.', 8, 'casa1 angolare corpo'],
['casa_campo', '1', 'moderno', 'in prima casa',
 'agisce sulla soglia, in prima casa: è la prima cosa che gli altri percepiscono di te, '
 . 'prima ancora che tu apra bocca. Qui non si nasconde niente.', 8, 'casa1 angolare identita'],

['casa_campo', '2', 'tradizionale', 'in seconda casa',
 'si trova in seconda casa, della sostanza e dei beni mobili. Riguarda il denaro guadagnato '
 . 'con le proprie mani e ciò che si possiede davvero.', 5, 'casa2 beni'],
['casa_campo', '2', 'moderno', 'in seconda casa',
 'agisce in seconda casa, nel campo di ciò che hai e di ciò che ti dai valore per avere: '
 . 'le risorse, il denaro, ma soprattutto il metro con cui giudichi quanto vali.', 5, 'casa2 risorse valore'],

['casa_campo', '3', 'tradizionale', 'in terza casa',
 'si trova in terza casa, dei fratelli, dei viaggi brevi e delle lettere. È casa cadente, di '
 . 'dea, e ciò che vi cade agisce più in sordina.', 4, 'casa3 cadente comunicazione'],
['casa_campo', '3', 'moderno', 'in terza casa',
 'agisce in terza casa, nel campo dello scambio quotidiano: come parli, come impari, i '
 . 'fratelli, il quartiere, le mille cose che non sembrano importanti finché non mancano.',
 4, 'casa3 comunicazione'],

['casa_campo', '4', 'tradizionale', 'in quarta casa',
 'si trova in quarta casa, angolare, del padre e delle radici. È il fondo della carta: ciò '
 . 'che vi sta lavora in profondità e si vede solo alla fine.', 7, 'casa4 angolare radici'],
['casa_campo', '4', 'moderno', 'in quarta casa',
 'agisce alla radice, in quarta casa: la famiglia da cui vieni, la casa, quello che si è '
 . 'depositato prima che potessi scegliere. Lavora sotto, e si vede tardi.', 7, 'casa4 angolare famiglia'],

['casa_campo', '5', 'tradizionale', 'in quinta casa',
 'si trova in quinta casa, dei figli, del piacere e della sorte. È casa succedente e di buona '
 . 'fortuna: ciò che vi sta tende a dare frutto.', 5, 'casa5 figli piacere'],
['casa_campo', '5', 'moderno', 'in quinta casa',
 'agisce in quinta casa, dove ti esprimi senza dover rendere conto: il gioco, la creatività, '
 . 'l\'innamoramento, i figli. Qui si mette in scena quello che si è.', 5, 'casa5 creativita'],

['casa_campo', '6', 'tradizionale', 'in sesta casa',
 'si trova in sesta casa, della malattia, della servitù e del lavoro umile. È casa cadente e '
 . 'infelice: ciò che vi cade fatica a farsi valere.', 4, 'casa6 cadente lavoro salute'],
['casa_campo', '6', 'moderno', 'in sesta casa',
 'agisce in sesta casa, nel campo del lavoro quotidiano e della salute: le routine, la cura '
 . 'del corpo, il mestiere fatto bene più che la carriera.', 4, 'casa6 lavoro salute'],

['casa_campo', '7', 'tradizionale', 'in settima casa',
 'si trova in settima casa, angolare, del matrimonio e dei nemici dichiarati. Ciò che vi sta '
 . 'si incontra sempre in forma di un altro.', 7, 'casa7 angolare relazione'],
['casa_campo', '7', 'moderno', 'in settima casa',
 'agisce in settima casa, di fronte a te: nelle relazioni a due, nei soci, negli avversari. '
 . 'Spesso è una parte di sé che si riconosce solo quando qualcun altro la incarna.',
 7, 'casa7 angolare relazione'],

['casa_campo', '8', 'tradizionale', 'in ottava casa',
 'si trova in ottava casa, della morte e dei beni altrui. È casa succedente ma oscura: '
 . 'riguarda eredità, debiti e ciò che si riceve o si perde per via d\'altri.',
 6, 'casa8 morte eredita'],
['casa_campo', '8', 'moderno', 'in ottava casa',
 'agisce in ottava casa, dove si mette in comune quello che di solito si tiene per sé: '
 . 'l\'intimità profonda, il denaro condiviso, le crisi che cambiano. Niente resta come prima.',
 6, 'casa8 crisi intimita'],

['casa_campo', '9', 'tradizionale', 'in nona casa',
 'si trova in nona casa, del dio, dei viaggi lunghi e della religione. È cadente, ma fra le '
 . 'cadenti la più fortunata: guarda lontano.', 5, 'casa9 viaggi fede'],
['casa_campo', '9', 'moderno', 'in nona casa',
 'agisce in nona casa, nel campo di ciò che allarga l\'orizzonte: gli studi superiori, i '
 . 'viaggi lontani, le convinzioni con cui leggi il mondo.', 5, 'casa9 studio viaggio'],

['casa_campo', '10', 'tradizionale', 'in decima casa',
 'si trova in decima casa, angolare, del regno e della madre. È il punto più alto della '
 . 'carta: ciò che vi sta si vede da lontano e fa la reputazione.', 8, 'casa10 angolare carriera'],
['casa_campo', '10', 'moderno', 'in decima casa',
 'agisce al culmine, in decima casa: la vocazione, il ruolo pubblico, quello per cui vieni '
 . 'riconosciuto. Il punto più visibile di tutta la carta.', 8, 'casa10 angolare vocazione'],

['casa_campo', '11', 'tradizionale', 'in undicesima casa',
 'si trova in undicesima casa, del buon demone, degli amici e delle speranze. È succedente e '
 . 'benevola: ciò che vi sta trova appoggio.', 5, 'casa11 amici speranze'],
['casa_campo', '11', 'moderno', 'in undicesima casa',
 'agisce in undicesima casa, nel campo del gruppo: gli amici, le reti, le cause condivise, e '
 . 'le speranze che si coltivano insieme ad altri.', 5, 'casa11 gruppo amicizia'],

['casa_campo', '12', 'tradizionale', 'in dodicesima casa',
 'si trova in dodicesima casa, del cattivo demone, delle prigioni e dei nemici occulti. È la '
 . 'più difficile: ciò che vi sta lavora di nascosto, anche da sé stessi.',
 6, 'casa12 cadente nascosto'],
['casa_campo', '12', 'moderno', 'in dodicesima casa',
 'agisce in dodicesima casa, dietro le quinte: quello che fai senza accorgertene, quello che '
 . 'ti porti dietro da prima, il bisogno di ritirarti. Difficile da vedere dall\'interno.',
 6, 'casa12 inconscio ritiro'],

// ═══════════════════════════════════════════════════════════════════════════
// GLI ASPETTI — che rapporto  (%s = il secondo corpo)
// ═══════════════════════════════════════════════════════════════════════════

['aspetto_relazione', 'congiunzione', 'tradizionale', 'congiunt{o|a} a',
 'è congiunt{o|a} a %s: i due si trovano nello stesso grado e non si distinguono più. Il più '
 . 'forte assorbe il più debole, e ciò che ne esce agisce come un corpo solo.',
 8, 'congiunzione fusione'],
['aspetto_relazione', 'congiunzione', 'moderno', 'unit{o|a} a',
 'è tutt\'uno con %s: le due funzioni non si distinguono, e quando si muove una si muove '
 . 'anche l\'altra. È una forza sola, e non si può usarne metà.', 8, 'congiunzione fusione'],

['aspetto_relazione', 'opposizione', 'tradizionale', 'oppost{o|a} a',
 'sta in opposizione a %s, a centottanta gradi: si guardano da due parti opposte del cielo. '
 . 'È aspetto di inimicizia, che divide, ma i due si vedono chiaramente.',
 8, 'opposizione tensione asse'],
['aspetto_relazione', 'opposizione', 'moderno', 'in tensione con',
 'sta di fronte a %s: due esigenze che tirano in direzioni opposte e che di solito si '
 . 'alternano invece di convivere. Spesso una delle due viene delegata a qualcun altro.',
 8, 'opposizione tensione proiezione'],

['aspetto_relazione', 'quadrato', 'tradizionale', 'in quadrato a',
 'è in quadrato a %s, a novanta gradi: aspetto di odio e di contesa. I due segni non hanno '
 . 'nulla in comune né per elemento né per natura, e l\'attrito non si compone da sé.',
 8, 'quadrato attrito'],
['aspetto_relazione', 'quadrato', 'moderno', 'in attrito con',
 'è in attrito con %s: due spinte che non si accordano da sole e chiedono di essere tenute '
 . 'insieme a forza. È scomodo, ed è anche il motore che fa muovere.', 8, 'quadrato attrito crescita'],

['aspetto_relazione', 'trigono', 'tradizionale', 'in trigono a',
 'è in trigono a %s, a centoventi gradi: aspetto di amicizia perfetta, fra segni del '
 . 'medesimo elemento. Ciò che ne viene riesce senza sforzo.', 7, 'trigono armonia facilita'],
['aspetto_relazione', 'trigono', 'moderno', 'in accordo con',
 'scorre insieme a %s: le due funzioni si sostengono senza che tu debba lavorarci. Il rischio '
 . 'è che, proprio perché facile, non venga mai messo alla prova.', 7, 'trigono armonia'],

['aspetto_relazione', 'sestile', 'tradizionale', 'in sestile a',
 'è in sestile a %s, a sessanta gradi: aspetto di amicizia moderata, fra segni di elementi '
 . 'conformi. Aiuta, ma meno del trigono e solo se sollecitato.', 6, 'sestile occasione'],
['aspetto_relazione', 'sestile', 'moderno', 'in appoggio a',
 'trova appoggio in %s: c\'è un\'occasione, ma va colta. A differenza del trigono, questo '
 . 'aspetto non lavora da solo.', 6, 'sestile occasione'],

['aspetto_relazione', 'quinconce', 'tradizionale', 'in quinconce a',
 'è in quinconce a %s, a centocinquanta gradi: i due segni non si vedono, non avendo in '
 . 'comune né elemento né modalità né genere. Aspetto di disagio sordo.',
 4, 'quinconce disagio'],
['aspetto_relazione', 'quinconce', 'moderno', 'in disagio con',
 'non riesce a vedere %s: due funzioni che non parlano la stessa lingua e che vanno aggiustate '
 . 'di continuo, senza che si arrivi mai a un assetto definitivo.', 4, 'quinconce disagio'],

];
