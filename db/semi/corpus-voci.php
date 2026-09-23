<?php

declare(strict_types=1);

/**
 * Tema e Cielo — voci complete del corpus.
 *
 * A differenza dei frammenti, queste sono paragrafi autonomi: il montatore le
 * prende cosi' come sono, senza comporre niente.
 */

return [

// ═══════════════════════════════════════════════════════════════════════════
// CONFIGURAZIONI
// ═══════════════════════════════════════════════════════════════════════════

['configurazione', 'stellium', 'tradizionale', 'Stellium',
 'Piu' . "'" . ' pianeti raccolti in pochi gradi formano uno stellium. La tradizione guarda '
 . 'anzitutto quale ne sia il signore e in che dignita' . "'" . ' si trovi: e' . "'" . ' quello che governa '
 . 'l\'intero grappolo. Dove cade, la casa e\' sovraccarica e le altre restano sguarnite.',
 8, 'stellium concentrazione'],
['configurazione', 'stellium', 'moderno', 'Stellium',
 'Tre o piu\' corpi nello stesso tratto di cielo concentrano un\'enorme quantita\' di energia in '
 . 'un solo settore della vita. E\' una forza e un rischio: quel campo assorbe tutto, e il '
 . 'resto della carta rischia di restare inesplorato.', 8, 'stellium concentrazione'],

['configurazione', 'gran_trigono', 'tradizionale', 'Gran Trigono',
 'Tre pianeti in trigono reciproco, nel medesimo elemento, formano un triangolo perfetto. E\' '
 . 'la configurazione piu\' benigna che si conosca: cio\' che i tre significano riesce senza '
 . 'impedimento. Proprio per questo raramente si mette alla prova.', 8, 'gran_trigono armonia'],
['configurazione', 'gran_trigono', 'moderno', 'Gran Trigono',
 'Un circuito chiuso di facilita\'. Quello che questi tre corpi rappresentano funziona da solo, '
 . 'e funziona bene — al punto che spesso non viene mai usato davvero. Il talento che non '
 . 'costa fatica e\' anche quello che si dimentica di avere.', 8, 'gran_trigono talento'],

['configurazione', 't_quadrata', 'tradizionale', 'T-quadrata',
 'Un\'opposizione i cui due estremi sono entrambi in quadrato a un terzo pianeta. Il pianeta '
 . 'focale riceve la contesa di tutti e due e non ha scampo: e\' li\' che si scarica la '
 . 'tensione dell\'intera figura.', 9, 't_quadrata tensione focale'],
['configurazione', 't_quadrata', 'moderno', 'T-quadrata',
 'Due esigenze opposte, e una terza che le prende addosso entrambe. Il punto focale e\' dove '
 . 'la pressione si scarica — di solito il settore della vita in cui non si trova mai pace, e '
 . 'proprio per questo quello in cui si diventa piu\' competenti.', 9, 't_quadrata tensione motore'],

['configurazione', 'gran_croce', 'tradizionale', 'Gran Croce',
 'Quattro pianeti a novanta gradi l\'uno dall\'altro, due opposizioni incrociate. E\' la figura '
 . 'piu\' gravosa: ogni pianeta e\' in contesa con due e opposto al terzo, e non c\'e\' punto '
 . 'della figura da cui si possa uscire.', 9, 'gran_croce tensione'],
['configurazione', 'gran_croce', 'moderno', 'Gran Croce',
 'Quattro spinte che si contrastano a vicenda, chiuse in un quadrato. Nessuna puo\' prevalere, '
 . 'e nessuna si puo\' abbandonare. Chi ce l\'ha impara presto a reggere la tensione, perche\' '
 . 'non c\'e\' alternativa.', 9, 'gran_croce tensione resistenza'],

['configurazione', 'yod', 'tradizionale', 'Yod',
 'Due pianeti in sestile, entrambi in quinconce a un terzo. Gli antichi non la trattavano come '
 . 'figura a se\': il quinconce e\' aspetto di disagio, e due disagi convergenti sullo stesso '
 . 'punto lo rendono cronico.', 6, 'yod disagio'],
['configurazione', 'yod', 'moderno', 'Yod',
 'Detto anche «dito di Dio»: due funzioni che collaborano bene puntano entrambe su una terza '
 . 'con cui nessuna delle due va d\'accordo. Il vertice e\' un punto che va continuamente '
 . 'riaggiustato, e non arriva mai a un assetto stabile.', 6, 'yod disagio aggiustamento'],

['configurazione', 'rettangolo_mistico', 'tradizionale', 'Rettangolo mistico',
 'Due opposizioni i cui estremi si legano a coppie con trigoni e sestili. La tensione delle '
 . 'opposizioni trova sfogo negli aspetti benigni: figura difficile ma non disperata.',
 6, 'rettangolo tensione sbocco'],
['configurazione', 'rettangolo_mistico', 'moderno', 'Rettangolo mistico',
 'Tensione e sostegno intrecciati nella stessa figura. Le due opposizioni tirano, i trigoni e '
 . 'i sestili offrono la via d\'uscita: la difficolta\' c\'e\', ma c\'e\' anche lo strumento per '
 . 'lavorarci.', 6, 'rettangolo equilibrio'],

['configurazione', 'aquilone', 'tradizionale', 'Aquilone',
 'Un gran trigono a cui un quarto pianeta si oppone da uno dei vertici. L\'opposizione da\' al '
 . 'triangolo il punto d\'appoggio che gli manca: il talento trova finalmente dove applicarsi.',
 7, 'aquilone talento sbocco'],
['configurazione', 'aquilone', 'moderno', 'Aquilone',
 'Un gran trigono con una punta di tensione. E\' la versione utile del talento facile: '
 . 'l\'opposizione impedisce che il circuito resti chiuso su se\' stesso e lo costringe a '
 . 'produrre qualcosa.', 7, 'aquilone talento'],

// ═══════════════════════════════════════════════════════════════════════════
// DIGNITA'
// ═══════════════════════════════════════════════════════════════════════════

['dignita', 'domicilio', 'tradizionale', '%s in domicilio',
 '%s si trova nel segno di cui e\' {signore|signora}: e\' a casa propria. Agisce con pieno possesso dei '
 . 'propri mezzi, senza dover chiedere permesso a nessuno, e cio\' che significa lo porta a '
 . 'compimento.', 8, 'dignita domicilio forza'],
['dignita', 'domicilio', 'moderno', '%s nel proprio segno',
 '%s si esprime nella forma che {gli|le} e\' piu\' naturale: nessuna traduzione, nessun compromesso. '
 . 'E\' una forza — con l\'unico rischio di non essere mai messa in discussione.',
 8, 'dignita domicilio forza'],

['dignita', 'esaltazione', 'tradizionale', '%s in esaltazione',
 '%s e\' ospite d\'onore in un segno non suo. Agisce con grande efficacia, forse piu\' che in '
 . 'domicilio, ma con una certa sproporzione: l\'esaltato tende a eccedere.',
 7, 'dignita esaltazione'],
['dignita', 'esaltazione', 'moderno', '%s esaltat{o|a}',
 '%s trova un contesto che {lo|la} valorizza piu\' del dovuto. Rende bene, e tende a prendersi piu\' '
 . 'spazio di quanto {gli|le} spetti.', 7, 'dignita esaltazione'],

['dignita', 'esilio', 'tradizionale', '%s in esilio',
 '%s si trova nel segno opposto al proprio domicilio: e\' in terra straniera, e deve agire con '
 . 'mezzi che non sono i suoi. Non e\' impotente, ma tutto {gli|le} costa di piu\'.',
 8, 'dignita esilio debolezza'],
['dignita', 'esilio', 'moderno', '%s in detrimento',
 '%s deve esprimersi nella forma che {gli|le} e\' meno congeniale. Non e\' un difetto: e\' un lavoro '
 . 'in piu\'. Quello che altri fanno per istinto, qui si impara.', 8, 'dignita esilio lavoro'],

['dignita', 'caduta', 'tradizionale', '%s in caduta',
 '%s si trova nel segno opposto alla propria esaltazione: e\' avvilit{o|a}, non ascoltat{o|a}, '
 . 'sottovalutat{o|a}. Cio\' che significa fatica a essere riconosciuto, anche da chi {lo|la} porta.',
 8, 'dignita caduta debolezza'],
['dignita', 'caduta', 'moderno', '%s in caduta',
 '%s e\' fuori posto e tende a essere svalutat{o|a} — spesso per prim{o|a} da chi {lo|la} porta. '
 . 'Riconoscer{lo|la} e\' meta\' del lavoro.', 8, 'dignita caduta'],

['dignita', 'peregrino', 'tradizionale', '%s peregrin{o|a}',
 '%s non ha alcuna dignita\' essenziale nel grado in cui si trova: ne\' domicilio, ne\' '
 . 'esaltazione, ne\' triplicita\', ne\' termine, ne\' faccia. E\' senza appoggi, come un forestiero '
 . 'senza lettere di presentazione.', 6, 'dignita peregrino'],
['dignita', 'peregrino', 'moderno', '%s senza appoggi',
 '%s non trova nel segno nessun sostegno particolare. Non e\' danneggiat{o|a}: e\' sol{o|a}, e dipende '
 . 'interamente da come viene usat{o|a}.', 6, 'dignita peregrino'],

['dignita', 'combusto', 'tradizionale', '%s combust{o|a}',
 '%s dista meno di otto gradi e mezzo dal Sole ed e\' bruciat{o|a} dai suoi raggi: non si vede piu\' '
 . 'in cielo, e nella carta agisce senza potersi manifestare. Fra le afflizioni e\' una delle '
 . 'piu\' gravi.', 7, 'combustione sole'],
['dignita', 'combusto', 'moderno', '%s troppo vicin{o|a} al Sole',
 '%s e\' talmente assorbit{o|a} nell\'identita\' da non riuscire a distinguersene. E\' difficile '
 . 'accorgersi di aver{lo|la}, perche\' sembra semplicemente «come sono io».',
 7, 'combustione sole'],

['dignita', 'cazimi', 'tradizionale', '%s cazimi',
 '%s e\' entro diciassette primi dal centro del Sole: non bruciat{o|a}, ma «nel cuore» del re. E\' '
 . 'la condizione piu\' fortunata che esista, l\'esatto rovescio della combustione, e capita di '
 . 'rado.', 9, 'cazimi sole fortuna'],
['dignita', 'cazimi', 'moderno', '%s nel cuore del Sole',
 'Condizione rarissima: %s coincide col centro stesso della persona. Non e\' assorbit{o|a} come '
 . 'nella combustione — e\' proprio il nucleo.', 9, 'cazimi sole'],

['dignita', 'retrogrado', 'tradizionale', '%s retrograd{o|a}',
 '%s appare tornare indietro nello zodiaco. La tradizione {lo|la} considera debilitat{o|a}: agisce in '
 . 'modo contrario, indiretto, tardivo. Le cose che significa si ottengono, ma per vie storte e '
 . 'con ritardo.', 7, 'retrogrado debolezza'],
['dignita', 'retrogrado', 'moderno', '%s retrograd{o|a}',
 '%s si rivolge all\'interno prima che all\'esterno. Matura piu\' lentamente e spesso fuori '
 . 'tempo rispetto agli altri, ma quando emerge e\' stat{o|a} digerit{o|a} davvero.',
 7, 'retrogrado interiorizzazione'],

// ═══════════════════════════════════════════════════════════════════════════
// FASI LUNARI
// ═══════════════════════════════════════════════════════════════════════════

['fase_luna', 'Luna nuova', 'tradizionale', 'Nato di novilunio',
 'La Luna era congiunta al Sole e invisibile in cielo. La tradizione considera debole la Luna '
 . 'sotto i raggi, ma il novilunio e\' anche inizio assoluto di ciclo.', 6, 'fase novilunio'],
['fase_luna', 'Luna nuova', 'moderno', 'Nato di Luna nuova',
 'Nascere a Luna nuova significa cominciare senza modelli: si agisce d\'istinto, prima di aver '
 . 'capito. La consapevolezza arriva dopo il gesto, non prima.', 6, 'fase novilunio inizio'],

['fase_luna', 'Falce crescente', 'moderno', 'Nato di falce crescente',
 'La fase della spinta in avanti contro l\'inerzia di cio\' che c\'era prima. Una certa '
 . 'irrequietezza di fondo, e la tendenza a dover staccarsi da qualcosa per crescere.',
 5, 'fase crescente'],
['fase_luna', 'Primo quarto', 'moderno', 'Nato di primo quarto',
 'La fase della crisi d\'azione: quello che si e\' cominciato incontra resistenza, e va imposto '
 . 'o abbandonato. Tendenza a costruire rompendo.', 5, 'fase primo_quarto crisi'],
['fase_luna', 'Gibbosa crescente', 'moderno', 'Nato di gibbosa crescente',
 'La fase del perfezionamento: c\'e\' gia\' una forma, e la si lima. Bisogno di capire il '
 . 'perche\' delle cose prima di considerarle finite.', 5, 'fase gibbosa'],
['fase_luna', 'Luna piena', 'tradizionale', 'Nato di plenilunio',
 'La Luna era opposta al Sole e piena di luce. E\' forte per lume, ma in opposizione al '
 . 'luminare del giorno: la tradizione vi legge una tensione fra la volonta\' e l\'istinto.',
 7, 'fase plenilunio'],
['fase_luna', 'Luna piena', 'moderno', 'Nato di Luna piena',
 'Sole e Luna si guardano da due parti opposte del cielo: quello che si vuole e quello di cui '
 . 'si ha bisogno non coincidono, e si vedono benissimo l\'un l\'altro. Grande chiarezza, e una '
 . 'tensione che non si risolve ma si abita.', 7, 'fase plenilunio tensione'],
['fase_luna', 'Gibbosa calante', 'moderno', 'Nato di gibbosa calante',
 'La fase in cui quello che si e\' capito si comunica. Bisogno di trasmettere, di spiegare, di '
 . 'lasciare qualcosa a qualcuno.', 5, 'fase calante'],
['fase_luna', 'Ultimo quarto', 'moderno', 'Nato di ultimo quarto',
 'La fase della crisi di coscienza: quello che si e\' costruito viene messo in discussione dal '
 . 'di dentro. Tendenza a smontare per capire.', 5, 'fase ultimo_quarto'],
['fase_luna', 'Falce calante', 'moderno', 'Nato di falce calante',
 'La fase del congedo: si porta a termine qualcosa che e\' cominciato prima di noi. Spesso una '
 . 'sensazione di essere fuori tempo rispetto ai coetanei.', 5, 'fase calante congedo'],

// ═══════════════════════════════════════════════════════════════════════════
// FIGURA PLANETARIA
// ═══════════════════════════════════════════════════════════════════════════

['figura', 'fascio', 'moderno', 'Fascio',
 'Tutti i corpi raccolti in un quarto di cielo. Un\'energia straordinariamente concentrata su '
 . 'pochi fronti: grande capacita\' di specializzazione, e un\'intera meta\' di carta che resta '
 . 'in ombra.', 6, 'figura fascio'],
['figura', 'ciotola', 'moderno', 'Ciotola',
 'Tutti i corpi in una meta\' di cielo. Chi ha una ciotola sente di possedere pienamente un '
 . 'lato dell\'esperienza e di mancarne un altro — e spesso passa la vita a cercare fuori '
 . 'quello che gli sembra mancare.', 6, 'figura ciotola'],
['figura', 'secchio', 'moderno', 'Secchio',
 'I corpi raccolti in mezzo cielo, con uno solo dall\'altra parte a fare da manico. Quel '
 . 'pianeta isolato diventa il punto da cui passa tutto: e\' la valvola dell\'intera carta.',
 7, 'figura secchio manico'],
['figura', 'locomotiva', 'moderno', 'Locomotiva',
 'Due terzi di cielo occupati e un terzo vuoto. Il pianeta che apre lo spazio pieno, in senso '
 . 'orario, traina tutti gli altri: c\'e\' una spinta costante, quasi una fretta di fondo.',
 6, 'figura locomotiva'],
['figura', 'altalena', 'moderno', 'Altalena',
 'Due gruppi contrapposti separati da due vuoti. Una carta che vive di contrasti e che tende '
 . 'a pensare per opposizioni: o questo, o quello. Imparare a tenere insieme le due parti e\' '
 . 'il lavoro di una vita.', 6, 'figura altalena'],
['figura', 'spruzzo', 'moderno', 'Spruzzo',
 'Corpi sparsi su tutto il cerchio, nessun vuoto rilevante. Interessi molti e sparpagliati, '
 . 'grande adattabilita\', e la fatica di concentrarsi su una cosa sola abbastanza a lungo.',
 6, 'figura spruzzo'],
['figura', 'fionda', 'moderno', 'Fionda',
 'Tutti i corpi in un quarto di cielo, meno uno che tira dall\'altra parte. Concentrazione '
 . 'estrema piu\' un contrappeso: quel pianeta solitario e\' la direzione in cui l\'intera '
 . 'carta viene scagliata.', 7, 'figura fionda'],

];
