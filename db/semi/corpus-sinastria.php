<?php

declare(strict_types=1);

/**
 * Tema e Cielo — frammenti della sinastria rapida.
 *
 * Le coppie di segni sono settantotto. Scriverle tutte a mano nei due registri
 * sono centocinquantasei testi; con quarantasei frammenti si coprono tutte,
 * perche' quello che conta in una coppia di segni sono tre cose:
 *
 *   l'ELEMENTO dei due (fuoco con acqua non e' fuoco con aria),
 *   la DISTANZA fra i segni (l'opposizione non e' il trigono),
 *   la MODALITA' (due fissi si bloccano, due mobili si disperdono).
 *
 * ATTENZIONE ALLE CHIAVI: elementi e modalita' vanno scritti in ordine
 * ALFABETICO — «acqua.fuoco», non «fuoco.acqua» — perche' il compositore
 * ordina i due termini prima di cercare. Sbagliarlo non da' errore: fa
 * semplicemente sparire novanta coppie su centocinquantasei, in silenzio.
 *
 * GRAMMATICA — il montaggio produce:
 *   <elemento.corpo> <distanza.corpo> <modalita.corpo>
 * e ogni pezzo dev'essere una frase autonoma, cosi' l'ordine non rompe niente.
 */

return [

// ═══════════════════════════════════════════════════════════════════════════
// ELEMENTI — la materia di cui sono fatti
// ═══════════════════════════════════════════════════════════════════════════

['sinastria_elemento', 'fuoco.fuoco', 'moderno', 'Due fuochi',
 'Due segni di fuoco si riconoscono subito: stessa fretta, stessa voglia di cominciare, stessa '
 . 'insofferenza per chi tergiversa. Il rischio non e\' che si annoino, e\' che brucino tutto '
 . 'in fretta e non resti niente da tenere acceso.', 6, 'elemento fuoco'],
['sinastria_elemento', 'fuoco.fuoco', 'tradizionale', 'Due segni di fuoco',
 'Entrambi di natura calda e secca. La somiglianza di complessione li rende concordi, ma '
 . 'raddoppia il difetto: dove l\'uno eccede, l\'altro non lo tempera.', 6, 'elemento fuoco'],

['sinastria_elemento', 'fuoco.terra', 'moderno', 'Fuoco e terra',
 'Uno vuole partire, l\'altro vuole sapere dove si va a finire. E\' la coppia in cui le cose '
 . 'si fanno davvero — se il fuoco accetta di essere rallentato e la terra di essere smossa.',
 6, 'elemento fuoco terra'],
['sinastria_elemento', 'fuoco.terra', 'tradizionale', 'Fuoco e terra',
 'Caldo e secco contro freddo e secco: concordi nella siccita\', discordi nel calore. La terra '
 . 'soffoca il fuoco, il fuoco inaridisce la terra; ma insieme hanno la fermezza che manca '
 . 'alle altre combinazioni.', 6, 'elemento fuoco terra'],

['sinastria_elemento', 'aria.fuoco', 'moderno', 'Fuoco e aria',
 'Si accendono a vicenda: l\'aria porta le idee, il fuoco le trasforma in gesti. E\' la '
 . 'combinazione piu\' facile da vivere, e anche la piu\' facile da non prendere sul serio.',
 6, 'elemento fuoco aria'],
['sinastria_elemento', 'aria.fuoco', 'tradizionale', 'Fuoco e aria',
 'Caldo e secco con caldo e umido: concordi nel calore. Sono le due nature attive, e si '
 . 'alimentano — l\'aria attizza il fuoco, che senza di lei si spegne.', 6, 'elemento fuoco aria'],

['sinastria_elemento', 'acqua.fuoco', 'moderno', 'Fuoco e acqua',
 'Il fuoco non capisce perche\' l\'acqua ci metta tanto a dire quello che sente; l\'acqua non '
 . 'capisce come si faccia a dire tutto subito. Quando funziona e\' perche\' hanno smesso di '
 . 'pretendere che l\'altro cambi ritmo.', 6, 'elemento fuoco acqua'],
['sinastria_elemento', 'acqua.fuoco', 'tradizionale', 'Fuoco e acqua',
 'Caldo e secco contro freddo e umido: contrari in entrambe le qualita\', e per la tradizione '
 . 'e\' la discordia piu\' netta. L\'acqua spegne il fuoco, il fuoco prosciuga l\'acqua.',
 6, 'elemento fuoco acqua'],

['sinastria_elemento', 'terra.terra', 'moderno', 'Due terre',
 'Si capiscono senza parlare, e questo e\' insieme il pregio e il limite. Costruiscono bene e '
 . 'a lungo; il pericolo e\' che nessuno dei due proponga mai di cambiare qualcosa.',
 6, 'elemento terra'],
['sinastria_elemento', 'terra.terra', 'tradizionale', 'Due segni di terra',
 'Entrambi freddi e secchi. Concordia piena di complessione, ma natura passiva in tutti e '
 . 'due: l\'unione e\' solida e immobile.', 6, 'elemento terra'],

['sinastria_elemento', 'aria.terra', 'moderno', 'Terra e aria',
 'Uno pensa, l\'altro fa. Finche\' ciascuno rispetta il mestiere dell\'altro va benissimo; '
 . 'quando la terra chiede all\'aria di essere concreta, e l\'aria alla terra di essere '
 . 'flessibile, comincia il malinteso.', 6, 'elemento terra aria'],
['sinastria_elemento', 'aria.terra', 'tradizionale', 'Terra e aria',
 'Freddo e secco contro caldo e umido: contrari in entrambe le qualita\'. La tradizione li '
 . 'vede discordi, ma l\'aria e\' attiva dove la terra e\' passiva, e in questo si completano.',
 6, 'elemento terra aria'],

['sinastria_elemento', 'acqua.terra', 'moderno', 'Terra e acqua',
 'La terra da\' forma a quello che l\'acqua sente, l\'acqua ammorbidisce quello che la terra '
 . 'irrigidisce. E\' la combinazione piu\' fertile che ci sia, e anche quella che rischia di '
 . 'chiudersi al mondo.', 6, 'elemento terra acqua'],
['sinastria_elemento', 'acqua.terra', 'tradizionale', 'Terra e acqua',
 'Freddo e secco con freddo e umido: concordi nella freddezza. Entrambe nature passive, che '
 . 'ricevono e conservano. Unione feconda, ma senza slancio proprio.', 6, 'elemento terra acqua'],

['sinastria_elemento', 'aria.aria', 'moderno', 'Due arie',
 'Parlano la stessa lingua e non si annoiano mai. Manca chi porti il discorso a terra: tutto '
 . 'resta possibile, e per questo niente diventa necessario.', 6, 'elemento aria'],
['sinastria_elemento', 'aria.aria', 'tradizionale', 'Due segni d\'aria',
 'Entrambi caldi e umidi. Concordia piena e natura attiva in tutti e due: molto movimento, '
 . 'poca stabilita\'.', 6, 'elemento aria'],

['sinastria_elemento', 'acqua.aria', 'moderno', 'Aria e acqua',
 'L\'aria vuole capire quello che l\'acqua sente, e spiegandolo lo raffredda. L\'acqua vuole '
 . 'che l\'aria senta invece di spiegare. Si incontrano quando l\'uno smette di tradurre e '
 . 'l\'altro di pretendere.', 6, 'elemento aria acqua'],
['sinastria_elemento', 'acqua.aria', 'tradizionale', 'Aria e acqua',
 'Caldo e umido con freddo e umido: concordi nell\'umidita\'. Si mescolano facilmente, ma '
 . 'nessuno dei due da\' contorno all\'altro.', 6, 'elemento aria acqua'],

['sinastria_elemento', 'acqua.acqua', 'moderno', 'Due acque',
 'Si sentono prima di parlarsi, e non hanno bisogno di spiegarsi niente. Il rischio e\' che '
 . 'nessuno dei due riesca a dire quando qualcosa non va: si aspetta che l\'altro lo capisca '
 . 'da solo, e lui fa lo stesso.', 6, 'elemento acqua'],
['sinastria_elemento', 'acqua.acqua', 'tradizionale', 'Due segni d\'acqua',
 'Entrambi freddi e umidi. Concordia piena, ma doppia passivita\': nessuno dei due muove '
 . 'l\'altro, e l\'unione ristagna se non interviene un terzo.', 6, 'elemento acqua'],

// ═══════════════════════════════════════════════════════════════════════════
// DISTANZA — quanti segni li separano
// ═══════════════════════════════════════════════════════════════════════════

['sinastria_distanza', '0', 'moderno', 'Stesso segno',
 'Lo stesso segno: ci si riconosce come allo specchio, con tutto quello che uno specchio ha '
 . 'di comodo e di scomodo. I difetti che si vedono nell\'altro sono i propri, e questo puo\' '
 . 'rendere teneri o insopportabili.', 7, 'distanza congiunzione'],
['sinastria_distanza', '0', 'tradizionale', 'Nel medesimo segno',
 'I due segni coincidono: stessa natura, stesso signore, stessa complessione. Concordia '
 . 'perfetta secondo Tolomeo, ma senza il temperamento che viene dalla differenza.',
 7, 'distanza congiunzione'],

['sinastria_distanza', '1', 'moderno', 'Segni contigui',
 'Segni vicini di casa che non si parlano quasi mai: non hanno in comune ne\' elemento ne\' '
 . 'modalita\'. Funziona piu\' per abitudine che per affinita\', e spesso funziona benissimo.',
 5, 'distanza semisestile'],
['sinastria_distanza', '1', 'tradizionale', 'Segni contigui',
 'Segni che non si vedono: la tradizione chiama «inconiunti» quelli che non formano aspetto. '
 . 'Nessuna concordia di natura, ma nemmeno contrasto.', 5, 'distanza semisestile'],

['sinastria_distanza', '2', 'moderno', 'In sestile',
 'Sessanta gradi: elementi diversi ma compatibili, uno attivo e uno ricettivo. E\' la distanza '
 . 'dell\'amicizia facile — quella che regge senza che nessuno dei due debba sforzarsi.',
 6, 'distanza sestile'],
['sinastria_distanza', '2', 'tradizionale', 'In sestile',
 'Sessanta gradi: aspetto di amicizia moderata, fra elementi conformi. Giova, ma solo se '
 . 'qualcosa lo sollecita.', 6, 'distanza sestile'],

['sinastria_distanza', '3', 'moderno', 'In quadratura',
 'Novanta gradi: stessa modalita\', elementi incompatibili. Ci si urta di continuo sulle stesse '
 . 'cose, e proprio per questo ci si costringe a crescere. Poche coppie sono cosi\' vive, e '
 . 'poche cosi\' faticose.', 7, 'distanza quadrato'],
['sinastria_distanza', '3', 'tradizionale', 'In quadratura',
 'Novanta gradi: aspetto di contesa. I segni condividono la modalita\' ma nient\'altro, e '
 . 'l\'attrito non si compone da se\'.', 7, 'distanza quadrato'],

['sinastria_distanza', '4', 'moderno', 'In trigono',
 'Centoventi gradi, stesso elemento: ci si capisce senza spiegarsi. E\' la distanza piu\' '
 . 'comoda che esista, e per questo la piu\' facile da dare per scontata.', 7, 'distanza trigono'],
['sinastria_distanza', '4', 'tradizionale', 'In trigono',
 'Centoventi gradi, nel medesimo elemento: aspetto di amicizia perfetta. Quello che nasce fra '
 . 'loro riesce senza impedimento.', 7, 'distanza trigono'],

['sinastria_distanza', '5', 'moderno', 'In quinconce',
 'Centocinquanta gradi: nessun elemento, nessuna modalita\', nessun genere in comune. Non si '
 . 'vedono proprio, e il rapporto va riaggiustato di continuo senza arrivare mai a un assetto '
 . 'definitivo. Ci sono coppie che ci passano una vita, e non e\' detto che stiano male.',
 5, 'distanza quinconce'],
['sinastria_distanza', '5', 'tradizionale', 'In quinconce',
 'Centocinquanta gradi: i segni sono inconiunti e disgiunti in tutto. Aspetto di disagio, che '
 . 'la tradizione considera piu\' molesto della quadratura proprio perche\' non si vede.',
 5, 'distanza quinconce'],

['sinastria_distanza', '6', 'moderno', 'In opposizione',
 'Centottanta gradi: i due estremi dello stesso asse. Ciascuno ha esattamente quello che '
 . 'manca all\'altro, e lo riconosce al primo sguardo. E\' la distanza classica dell\'attrazione '
 . 'forte, e quella in cui si rischia di delegare all\'altro meta\' di se\'.', 7, 'distanza opposizione'],
['sinastria_distanza', '6', 'tradizionale', 'In opposizione',
 'Centottanta gradi: segni opposti, di elementi conformi ma di genere contrario. La tradizione '
 . 'lo dice aspetto di inimicizia, e insieme di perfetta complementarita\': si guardano da due '
 . 'estremi della stessa linea.', 7, 'distanza opposizione'],

// ═══════════════════════════════════════════════════════════════════════════
// MODALITA' — come si muovono
// ═══════════════════════════════════════════════════════════════════════════

['sinastria_modalita', 'cardinale.cardinale', 'moderno', 'Due cardinali',
 'Due che cominciano: pieni di iniziative, e nessuno dei due disposto a seguire quella '
 . 'dell\'altro.', 4, 'modalita cardinale'],
['sinastria_modalita', 'cardinale.cardinale', 'tradizionale', 'Due segni cardinali',
 'Entrambi mobili nel senso antico, cioe\' capaci di dare inizio. Molto impeto e poca '
 . 'sottomissione reciproca.', 4, 'modalita cardinale'],

['sinastria_modalita', 'cardinale.fisso', 'moderno', 'Cardinale e fisso',
 'Uno propone, l\'altro resiste. Se il fisso si convince, la cosa dura; se non si convince, '
 . 'non c\'e\' insistenza che tenga.', 4, 'modalita cardinale fisso'],
['sinastria_modalita', 'cardinale.fisso', 'tradizionale', 'Cardinale e fisso',
 'L\'uno da\' principio, l\'altro conserva. Combinazione feconda quando il primo accetta di '
 . 'non decidere anche per il secondo.', 4, 'modalita cardinale fisso'],

['sinastria_modalita', 'cardinale.mobile', 'moderno', 'Cardinale e mobile',
 'Uno decide, l\'altro si adatta — finche\' gli va. Il mobile non contrasta: cambia forma, e a '
 . 'volte scompare.', 4, 'modalita cardinale mobile'],
['sinastria_modalita', 'cardinale.mobile', 'tradizionale', 'Cardinale e mobile',
 'L\'uno comincia, l\'altro varia. Nessuno dei due conserva, e quello che fanno insieme ha '
 . 'bisogno di un terzo che lo tenga fermo.', 4, 'modalita cardinale mobile'],

['sinastria_modalita', 'fisso.fisso', 'moderno', 'Due fissi',
 'Nessuno dei due arretra. Quando vanno d\'accordo e\' per sempre; quando no, e\' una trincea.',
 5, 'modalita fisso'],
['sinastria_modalita', 'fisso.fisso', 'tradizionale', 'Due segni fissi',
 'Entrambi saldi. L\'unione e\' durevolissima e insieme inamovibile: cio\' che si stabilisce '
 . 'all\'inizio resta.', 5, 'modalita fisso'],

['sinastria_modalita', 'fisso.mobile', 'moderno', 'Fisso e mobile',
 'Uno tiene il punto, l\'altro gira intorno. Il fisso trova il mobile inaffidabile, il mobile '
 . 'trova il fisso ottuso, e hanno ragione tutti e due.', 4, 'modalita fisso mobile'],
['sinastria_modalita', 'fisso.mobile', 'tradizionale', 'Fisso e mobile',
 'L\'uno conserva, l\'altro muta. Si temperano a vicenda, ma nessuno dei due da\' inizio a '
 . 'niente.', 4, 'modalita fisso mobile'],

['sinastria_modalita', 'mobile.mobile', 'moderno', 'Due mobili',
 'Si adattano entrambi, e nessuno tiene il timone. Leggerissimi insieme, e capaci di lasciar '
 . 'sfumare qualunque cosa senza accorgersene.', 4, 'modalita mobile'],
['sinastria_modalita', 'mobile.mobile', 'tradizionale', 'Due segni mobili',
 'Entrambi variabili. Grande adattabilita\' e nessuna fermezza: l\'unione segue le circostanze '
 . 'piu\' che la volonta\'.', 4, 'modalita mobile'],

];
