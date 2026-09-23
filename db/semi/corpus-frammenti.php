<?php

declare(strict_types=1);

/**
 * Tema e Cielo — i frammenti del corpus.
 *
 * Da questi ottantadue pezzi il montatore compone le quattrocentosessantacinque
 * voci per registro che servono a leggere una carta qualunque: pianeta in
 * segno, pianeta in casa, aspetto fra due pianeti.
 *
 * Una voce composta non vale una scritta a mano, e il portale lo dice. Ma vale
 * molto piu' di una casella vuota, e garantisce che nessuna carta esca muta.
 * Le voci scritte a mano, quando ci sono, scavalcano sempre il composto.
 *
 * GRAMMATICA DEL MONTAGGIO — da rispettare scrivendo, o le frasi si rompono:
 *
 *   pianeta.titolo          gruppo nominale con l'articolo, minuscolo
 *                           «il centro della coscienza»
 *   pianeta.corpo           frase autonoma sul pianeta in se'
 *   segno_modo.corpo        continua «<Titolo del pianeta> ...»
 *                           «prende la forma dell'Ariete: ...»
 *   casa_campo.corpo        idem, per la casa
 *   aspetto_relazione.corpo idem, con %s dove va il secondo pianeta
 */

return [

// ═══════════════════════════════════════════════════════════════════════════
// I PIANETI — che facolta' sono
// ═══════════════════════════════════════════════════════════════════════════

['pianeta', 'sole', 'tradizionale', 'il Sole',
 'Il Sole è il luminare del giorno, signore del Leone ed esaltato in Ariete. Nella carta '
 . 'indica il padre, l\'autorità, la dignità e la salute; il luogo in cui si è visibili e '
 . 'da cui non ci si può nascondere.', 10, 'sole luminare identita'],

['pianeta', 'sole', 'moderno', 'il centro della coscienza',
 'Il Sole è ciò che sei quando nessuno guarda, e insieme ciò verso cui tendi. Non è il '
 . 'carattere ma il centro: il punto attorno a cui tutto il resto della carta si dispone.',
 10, 'sole identita centro'],

['pianeta', 'luna', 'tradizionale', 'la Luna',
 'La Luna è il luminare della notte, signora del Cancro ed esaltata in Toro. Governa la '
 . 'madre, il corpo, le abitudini e gli umori; muta più in fretta di ogni altro corpo, e con '
 . 'lei mutano gli stati d\'animo.', 10, 'luna luminare corpo abitudine'],

['pianeta', 'luna', 'moderno', 'la vita emotiva',
 'La Luna è la parte che reagisce prima di pensare: il bisogno di sentirsi al sicuro, la '
 . 'memoria del corpo, ciò che si cerca istintivamente quando si è stanchi.',
 10, 'luna emozione bisogno sicurezza'],

['pianeta', 'mercurio', 'tradizionale', 'Mercurio',
 'Mercurio governa Gemelli e Vergine, ed è esaltato in Vergine. Presiede alla parola, al '
 . 'calcolo, ai viaggi brevi, ai fratelli e a ogni commercio. È convertibile: prende la '
 . 'natura del pianeta cui si accosta.', 7, 'mercurio parola mente'],

['pianeta', 'mercurio', 'moderno', 'la facoltà di mettere in relazione',
 'Mercurio è il modo in cui raccogli le informazioni e le colleghi: come impari, come '
 . 'spieghi, che cosa noti e che cosa lasci cadere.', 7, 'mercurio mente linguaggio'],

['pianeta', 'venere', 'tradizionale', 'Venere',
 'Venere è il benefico minore, signora di Toro e Bilancia, esaltata in Pesci. Governa '
 . 'l\'amore, l\'ornamento, la musica, i patti e tutto ciò che concilia.', 7, 'venere amore misura'],

['pianeta', 'venere', 'moderno', 'la facoltà di dare valore',
 'Venere è ciò che ti attira e ciò che ti sembra bello: il metro con cui stabilisci che '
 . 'una cosa vale la pena, e il modo in cui ti avvicini a chi ti piace.', 7, 'venere valore desiderio'],

['pianeta', 'marte', 'tradizionale', 'Marte',
 'Marte è il malefico minore, signore di Ariete e Scorpione, esaltato in Capricorno. '
 . 'Governa il ferro, il fuoco, la guerra, la chirurgia e l\'ira; dà coraggio a chi lo tiene '
 . 'bene e rovina chi lo lascia sciolto.', 8, 'marte forza conflitto'],

['pianeta', 'marte', 'moderno', 'la facoltà di volere',
 'Marte è come ti muovi verso quello che vuoi, e come reagisci quando qualcuno si mette in '
 . 'mezzo. È anche il modo in cui dici di no.', 8, 'marte volonta conflitto'],

['pianeta', 'giove', 'tradizionale', 'Giove',
 'Giove è il benefico maggiore, signore di Sagittario e Pesci, esaltato in Cancro. Governa '
 . 'la legge, la religione, i viaggi lunghi, la fortuna e l\'abbondanza. Dove si trova, allarga.',
 7, 'giove espansione fortuna'],

['pianeta', 'giove', 'moderno', 'il bisogno di senso',
 'Giove è la spinta ad allargare: capire più di quanto serva, andare più in là di dove '
 . 'basterebbe, credere che valga la pena. Dove sta, le cose crescono — comprese quelle che '
 . 'sarebbe meglio contenere.', 7, 'giove senso crescita'],

['pianeta', 'saturno', 'tradizionale', 'Saturno',
 'Saturno è il malefico maggiore, signore di Capricorno e Acquario, esaltato in Bilancia. '
 . 'Governa il tempo, il limite, la vecchiaia, la terra e tutto ciò che resiste. Nega, ma '
 . 'ciò che concede dura.', 9, 'saturno limite tempo prova'],

['pianeta', 'saturno', 'moderno', 'la struttura',
 'Saturno è dove hai dovuto imparare a fare da solo. Segna il punto in cui la realtà non '
 . 'concede sconti — e, se ci si lavora abbastanza a lungo, quello in cui si diventa capaci '
 . 'sul serio.', 9, 'saturno limite responsabilita'],

['pianeta', 'urano', 'tradizionale', 'Urano',
 'Urano non appartiene alla tradizione: è stato visto nel 1781, dopo Tolomeo e dopo Lilly. '
 . 'Gli autori moderni gli danno la signoria dell\'Acquario e lo leggono come rottura '
 . 'improvvisa dell\'ordine stabilito.', 5, 'urano rottura novita'],

['pianeta', 'urano', 'moderno', 'la spinta a liberarsi',
 'Urano è il punto in cui non sopporti che ti si dica come si fa. Dove sta, prima o poi '
 . 'qualcosa si rompe — di solito nel momento in cui sembrava tutto sistemato.',
 5, 'urano rottura liberta'],

['pianeta', 'nettuno', 'tradizionale', 'Nettuno',
 'Nettuno, scoperto nel 1846, è estraneo alla tradizione antica. Gli si attribuisce la '
 . 'signoria dei Pesci e si legge come dissoluzione dei confini: il mare, il sogno, '
 . 'l\'inganno, la devozione.', 5, 'nettuno dissoluzione'],

['pianeta', 'nettuno', 'moderno', 'la porosità dei confini',
 'Nettuno è dove il confine fra te e il resto si assottiglia: l\'empatia, l\'ispirazione, e '
 . 'anche l\'illusione. Dove sta, è difficile vedere le cose come sono — in bene e in male.',
 5, 'nettuno confine illusione'],

['pianeta', 'plutone', 'tradizionale', 'Plutone',
 'Plutone è del 1930 e alla tradizione non appartiene affatto. Gli autori moderni gli danno '
 . 'lo Scorpione e lo leggono come ciò che sta sotto e che prima o poi viene a galla: il '
 . 'potere, la morte, la rigenerazione.', 5, 'plutone potere trasformazione'],

['pianeta', 'plutone', 'moderno', 'la capacità di trasformazione',
 'Plutone è dove non esistono mezze misure: quello che tocca, o lo cambi o ti cambia. È '
 . 'anche il punto in cui si gioca la partita del potere — su di sé prima che sugli altri.',
 5, 'plutone potere crisi'],

// Gli angoli, come secondo termine di un aspetto: «Il Sole congiunto
// all'Ascendente». Senza questi due, i centoventi aspetti dei pianeti agli
// assi — i piu' forti di una carta — restavano senza una parola.

['pianeta', 'asc', 'tradizionale', 'l\'Ascendente',
 'L\'Ascendente è il grado che sorgeva all\'orizzonte orientale: la prima casa, il corpo, il '
 . 'temperamento, il modo in cui la vita comincia.', 9, 'asc angolo corpo'],

['pianeta', 'asc', 'moderno', 'il modo di presentarsi al mondo',
 'L\'Ascendente è la soglia fra te e gli altri: lo stile con cui entri in una stanza, la '
 . 'maschera che non è una finzione ma il tuo modo di cominciare.', 9, 'asc angolo identita'],

['pianeta', 'mc', 'tradizionale', 'il Medio Cielo',
 'Il Medio Cielo è il punto più alto dell\'eclittica sopra il luogo: la decima casa, la '
 . 'professione, gli onori, la reputazione.', 9, 'mc angolo professione'],

['pianeta', 'mc', 'moderno', 'la vocazione pubblica',
 'Il Medio Cielo è la vocazione e il ruolo: ciò verso cui ti orienti davanti al mondo, e il '
 . 'modo in cui il mondo finisce per riconoscerti.', 9, 'mc angolo vocazione'],

// ═══════════════════════════════════════════════════════════════════════════
// I SEGNI — come colorano  («<Il pianeta> ...»)
// ═══════════════════════════════════════════════════════════════════════════

['segno_modo', 'ariete', 'tradizionale',
 'in Ariete, domicilio di Marte',
 'si trova in Ariete, segno cardinale di fuoco e domicilio di Marte, dove prende natura calda '
 . 'e secca: agisce d\'impeto, comincia volentieri e non porta a termine.', 5, 'ariete fuoco cardinale'],

['segno_modo', 'ariete', 'moderno', 'in Ariete',
 'prende la forma dell\'Ariete: si muove prima di spiegarsi, e il dubbio arriva dopo il gesto. '
 . 'Comincia bene; finire è un\'altra faccenda.', 5, 'ariete fuoco cardinale'],

['segno_modo', 'toro', 'tradizionale', 'in Toro, domicilio di Venere',
 'si trova in Toro, segno fisso di terra e domicilio di Venere: natura fredda e secca, che '
 . 'tiene e non lascia. Lent{o|a} a muoversi, lentissim{o|a} a cambiare.', 5, 'toro terra fisso'],

['segno_modo', 'toro', 'moderno', 'in Toro',
 'prende la forma del Toro: ha bisogno di toccare per credere, e una volta che ha deciso non '
 . 'si sposta. La pazienza è la sua qualità; l\'inerzia il suo rischio.', 5, 'toro terra fisso'],

['segno_modo', 'gemelli', 'tradizionale', 'in Gemelli, domicilio di Mercurio',
 'si trova in Gemelli, segno mobile d\'aria e domicilio di Mercurio: natura calda e umida, '
 . 'doppia e volubile. Sa molte cose e poche a fondo.', 5, 'gemelli aria mobile'],

['segno_modo', 'gemelli', 'moderno', 'in Gemelli',
 'prende la forma dei Gemelli: si muove per curiosità, tiene aperte più strade, e a volte '
 . 'le tiene aperte troppo a lungo.', 5, 'gemelli aria mobile'],

['segno_modo', 'cancro', 'tradizionale', 'in Cancro, domicilio della Luna',
 'si trova in Cancro, segno cardinale d\'acqua e domicilio della Luna: natura fredda e umida, '
 . 'che ritira e conserva. Difende ciò che {gli|le} è caro, e ricorda.', 5, 'cancro acqua cardinale'],

['segno_modo', 'cancro', 'moderno', 'in Cancro',
 'prende la forma del Cancro: si avvicina di lato, protegge ciò che ha scelto, e chiede '
 . 'sicurezza prima di esporsi. La memoria affettiva qui pesa più dei fatti.', 5, 'cancro acqua cardinale'],

['segno_modo', 'leone', 'tradizionale', 'in Leone, domicilio del Sole',
 'si trova in Leone, segno fisso di fuoco e domicilio del Sole: natura calda e secca, regale e '
 . 'ferma. Vuole essere riconosciut{o|a} e non sopporta di essere ignorat{o|a}.', 5, 'leone fuoco fisso'],

['segno_modo', 'leone', 'moderno', 'in Leone',
 'prende la forma del Leone: ha bisogno che qualcuno veda. Generos{o|a} quando è riconosciut{o|a}, '
 . 'rigid{o|a} quando non lo è.', 5, 'leone fuoco fisso'],

['segno_modo', 'vergine', 'tradizionale', 'in Vergine, domicilio ed esaltazione di Mercurio',
 'si trova in Vergine, segno mobile di terra, domicilio ed esaltazione di Mercurio: natura '
 . 'fredda e secca, analitica e serva. Distingue, separa, corregge.', 5, 'vergine terra mobile'],

['segno_modo', 'vergine', 'moderno', 'in Vergine',
 'prende la forma della Vergine: guarda i dettagli, vuole che le cose funzionino, e fatica a '
 . 'lasciar correre. Utile agli altri anche quando nessuno ringrazia.', 5, 'vergine terra mobile'],

['segno_modo', 'bilancia', 'tradizionale', 'in Bilancia, domicilio di Venere',
 'si trova in Bilancia, segno cardinale d\'aria e domicilio di Venere: natura calda e umida, '
 . 'che pesa e concilia. Non decide senza aver considerato l\'altra parte.', 5, 'bilancia aria cardinale'],

['segno_modo', 'bilancia', 'moderno', 'in Bilancia',
 'prende la forma della Bilancia: si definisce nel rapporto con gli altri, cerca la misura, e '
 . 'rimanda la decisione finché può.', 5, 'bilancia aria cardinale'],

['segno_modo', 'scorpione', 'tradizionale', 'in Scorpione, domicilio notturno di Marte',
 'si trova in Scorpione, segno fisso d\'acqua e domicilio notturno di Marte: natura fredda e '
 . 'umida, tenace e nascosta. Non dimentica e non perdona alla leggera.', 5, 'scorpione acqua fisso'],

['segno_modo', 'scorpione', 'moderno', 'in Scorpione',
 'prende la forma dello Scorpione: va a fondo o non va affatto. Guarda quello che gli altri '
 . 'evitano di guardare, e fatica a fidarsi.', 5, 'scorpione acqua fisso'],

['segno_modo', 'sagittario', 'tradizionale', 'in Sagittario, domicilio di Giove',
 'si trova in Sagittario, segno mobile di fuoco e domicilio di Giove: natura calda e secca, '
 . 'larga e libera. Guarda lontano e trascura ciò che ha sotto i piedi.', 5, 'sagittario fuoco mobile'],

['segno_modo', 'sagittario', 'moderno', 'in Sagittario',
 'prende la forma del Sagittario: ha bisogno di orizzonte, di un perché più grande. '
 . 'Generos{o|a} di visione, distratt{o|a} sui particolari.', 5, 'sagittario fuoco mobile'],

['segno_modo', 'capricorno', 'tradizionale', 'in Capricorno, domicilio di Saturno',
 'si trova in Capricorno, segno cardinale di terra e domicilio di Saturno: natura fredda e '
 . 'secca, dura e paziente. Sale piano e non torna indietro.', 5, 'capricorno terra cardinale'],

['segno_modo', 'capricorno', 'moderno', 'in Capricorno',
 'prende la forma del Capricorno: si assume la responsabilità anche quando nessuno gliel\'ha '
 . 'chiesta, e misura tutto sul tempo lungo. Si concede poco.', 5, 'capricorno terra cardinale'],

['segno_modo', 'acquario', 'tradizionale', 'in Acquario, domicilio diurno di Saturno',
 'si trova in Acquario, segno fisso d\'aria e domicilio diurno di Saturno: natura calda e '
 . 'umida secondo alcuni, ma saturnina nella fermezza. Si attiene al principio più che alla '
 . 'persona.', 5, 'acquario aria fisso'],

['segno_modo', 'acquario', 'moderno', 'in Acquario',
 'prende la forma dell\'Acquario: ragiona per principi, prende le distanze per vedere meglio, '
 . 'e preferisce l\'idea giusta alla pace in famiglia.', 5, 'acquario aria fisso'],

['segno_modo', 'pesci', 'tradizionale', 'in Pesci, domicilio di Giove ed esaltazione di Venere',
 'si trova in Pesci, segno mobile d\'acqua, domicilio di Giove ed esaltazione di Venere: natura '
 . 'fredda e umida, che si mescola e non tiene forma. Sente molto e distingue poco.',
 5, 'pesci acqua mobile'],

['segno_modo', 'pesci', 'moderno', 'in Pesci',
 'prende la forma dei Pesci: assorbe quello che ha intorno senza accorgersene, e ha bisogno di '
 . 'ritirarsi per capire che cosa sia davvero suo.', 5, 'pesci acqua mobile'],

];
