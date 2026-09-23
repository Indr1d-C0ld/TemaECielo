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
 . 'l\'intero grappolo. Dove cade, la casa è sovraccarica e le altre restano sguarnite.',
 8, 'stellium concentrazione'],
['configurazione', 'stellium', 'moderno', 'Stellium',
 'Tre o più corpi nello stesso tratto di cielo concentrano un\'enorme quantità di energia in '
 . 'un solo settore della vita. È una forza e un rischio: quel campo assorbe tutto, e il '
 . 'resto della carta rischia di restare inesplorato.', 8, 'stellium concentrazione'],

['configurazione', 'gran_trigono', 'tradizionale', 'Gran Trigono',
 'Tre pianeti in trigono reciproco, nel medesimo elemento, formano un triangolo perfetto. È '
 . 'la configurazione più benigna che si conosca: ciò che i tre significano riesce senza '
 . 'impedimento. Proprio per questo raramente si mette alla prova.', 8, 'gran_trigono armonia'],
['configurazione', 'gran_trigono', 'moderno', 'Gran Trigono',
 'Un circuito chiuso di facilità. Quello che questi tre corpi rappresentano funziona da solo, '
 . 'e funziona bene — al punto che spesso non viene mai usato davvero. Il talento che non '
 . 'costa fatica è anche quello che si dimentica di avere.', 8, 'gran_trigono talento'],

['configurazione', 't_quadrata', 'tradizionale', 'T-quadrata',
 'Un\'opposizione i cui due estremi sono entrambi in quadrato a un terzo pianeta. Il pianeta '
 . 'focale riceve la contesa di tutti e due e non ha scampo: è lì che si scarica la '
 . 'tensione dell\'intera figura.', 9, 't_quadrata tensione focale'],
['configurazione', 't_quadrata', 'moderno', 'T-quadrata',
 'Due esigenze opposte, e una terza che le prende addosso entrambe. Il punto focale è dove '
 . 'la pressione si scarica — di solito il settore della vita in cui non si trova mai pace, e '
 . 'proprio per questo quello in cui si diventa più competenti.', 9, 't_quadrata tensione motore'],

['configurazione', 'gran_croce', 'tradizionale', 'Gran Croce',
 'Quattro pianeti a novanta gradi l\'uno dall\'altro, due opposizioni incrociate. È la figura '
 . 'più gravosa: ogni pianeta è in contesa con due e opposto al terzo, e non c\'è punto '
 . 'della figura da cui si possa uscire.', 9, 'gran_croce tensione'],
['configurazione', 'gran_croce', 'moderno', 'Gran Croce',
 'Quattro spinte che si contrastano a vicenda, chiuse in un quadrato. Nessuna può prevalere, '
 . 'e nessuna si può abbandonare. Chi ce l\'ha impara presto a reggere la tensione, perché '
 . 'non c\'è alternativa.', 9, 'gran_croce tensione resistenza'],

['configurazione', 'yod', 'tradizionale', 'Yod',
 'Due pianeti in sestile, entrambi in quinconce a un terzo. Gli antichi non la trattavano come '
 . 'figura a sé: il quinconce è aspetto di disagio, e due disagi convergenti sullo stesso '
 . 'punto lo rendono cronico.', 6, 'yod disagio'],
['configurazione', 'yod', 'moderno', 'Yod',
 'Detto anche «dito di Dio»: due funzioni che collaborano bene puntano entrambe su una terza '
 . 'con cui nessuna delle due va d\'accordo. Il vertice è un punto che va continuamente '
 . 'riaggiustato, e non arriva mai a un assetto stabile.', 6, 'yod disagio aggiustamento'],

['configurazione', 'rettangolo_mistico', 'tradizionale', 'Rettangolo mistico',
 'Due opposizioni i cui estremi si legano a coppie con trigoni e sestili. La tensione delle '
 . 'opposizioni trova sfogo negli aspetti benigni: figura difficile ma non disperata.',
 6, 'rettangolo tensione sbocco'],
['configurazione', 'rettangolo_mistico', 'moderno', 'Rettangolo mistico',
 'Tensione e sostegno intrecciati nella stessa figura. Le due opposizioni tirano, i trigoni e '
 . 'i sestili offrono la via d\'uscita: la difficoltà c\'è, ma c\'è anche lo strumento per '
 . 'lavorarci.', 6, 'rettangolo equilibrio'],

['configurazione', 'aquilone', 'tradizionale', 'Aquilone',
 'Un gran trigono a cui un quarto pianeta si oppone da uno dei vertici. L\'opposizione dà al '
 . 'triangolo il punto d\'appoggio che gli manca: il talento trova finalmente dove applicarsi.',
 7, 'aquilone talento sbocco'],
['configurazione', 'aquilone', 'moderno', 'Aquilone',
 'Un gran trigono con una punta di tensione. È la versione utile del talento facile: '
 . 'l\'opposizione impedisce che il circuito resti chiuso su sé stesso e lo costringe a '
 . 'produrre qualcosa.', 7, 'aquilone talento'],

// ═══════════════════════════════════════════════════════════════════════════
// DIGNITA'
// ═══════════════════════════════════════════════════════════════════════════

['dignita', 'domicilio', 'tradizionale', '%s in domicilio',
 '%s si trova nel segno di cui è {signore|signora}: è a casa propria. Agisce con pieno possesso dei '
 . 'propri mezzi, senza dover chiedere permesso a nessuno, e ciò che significa lo porta a '
 . 'compimento.', 8, 'dignita domicilio forza'],
['dignita', 'domicilio', 'moderno', '%s nel proprio segno',
 '%s si esprime nella forma che {gli|le} è più naturale: nessuna traduzione, nessun compromesso. '
 . 'È una forza — con l\'unico rischio di non essere mai messa in discussione.',
 8, 'dignita domicilio forza'],

['dignita', 'esaltazione', 'tradizionale', '%s in esaltazione',
 '%s è ospite d\'onore in un segno non suo. Agisce con grande efficacia, forse più che in '
 . 'domicilio, ma con una certa sproporzione: l\'esaltato tende a eccedere.',
 7, 'dignita esaltazione'],
['dignita', 'esaltazione', 'moderno', '%s esaltat{o|a}',
 '%s trova un contesto che {lo|la} valorizza più del dovuto. Rende bene, e tende a prendersi più '
 . 'spazio di quanto {gli|le} spetti.', 7, 'dignita esaltazione'],

['dignita', 'esilio', 'tradizionale', '%s in esilio',
 '%s si trova nel segno opposto al proprio domicilio: è in terra straniera, e deve agire con '
 . 'mezzi che non sono i suoi. Non è impotente, ma tutto {gli|le} costa di più.',
 8, 'dignita esilio debolezza'],
['dignita', 'esilio', 'moderno', '%s in detrimento',
 '%s deve esprimersi nella forma che {gli|le} è meno congeniale. Non è un difetto: è un lavoro '
 . 'in più. Quello che altri fanno per istinto, qui si impara.', 8, 'dignita esilio lavoro'],

['dignita', 'caduta', 'tradizionale', '%s in caduta',
 '%s si trova nel segno opposto alla propria esaltazione: è avvilit{o|a}, non ascoltat{o|a}, '
 . 'sottovalutat{o|a}. Ciò che significa fatica a essere riconosciuto, anche da chi {lo|la} porta.',
 8, 'dignita caduta debolezza'],
['dignita', 'caduta', 'moderno', '%s in caduta',
 '%s è fuori posto e tende a essere svalutat{o|a} — spesso per prim{o|a} da chi {lo|la} porta. '
 . 'Riconoscer{lo|la} è metà del lavoro.', 8, 'dignita caduta'],

['dignita', 'peregrino', 'tradizionale', '%s peregrin{o|a}',
 '%s non ha alcuna dignità essenziale nel grado in cui si trova: né domicilio, né '
 . 'esaltazione, né triplicità, né termine, né faccia. È senza appoggi, come un forestiero '
 . 'senza lettere di presentazione.', 6, 'dignita peregrino'],
['dignita', 'peregrino', 'moderno', '%s senza appoggi',
 '%s non trova nel segno nessun sostegno particolare. Non è danneggiat{o|a}: è sol{o|a}, e dipende '
 . 'interamente da come viene usat{o|a}.', 6, 'dignita peregrino'],

['dignita', 'combusto', 'tradizionale', '%s combust{o|a}',
 '%s dista meno di otto gradi e mezzo dal Sole ed è bruciat{o|a} dai suoi raggi: non si vede più '
 . 'in cielo, e nella carta agisce senza potersi manifestare. Fra le afflizioni è una delle '
 . 'più gravi.', 7, 'combustione sole'],
['dignita', 'combusto', 'moderno', '%s troppo vicin{o|a} al Sole',
 '%s è talmente assorbit{o|a} nell\'identità da non riuscire a distinguersene. È difficile '
 . 'accorgersi di aver{lo|la}, perché sembra semplicemente «come sono io».',
 7, 'combustione sole'],

['dignita', 'cazimi', 'tradizionale', '%s cazimi',
 '%s è entro diciassette primi dal centro del Sole: non bruciat{o|a}, ma «nel cuore» del re. È '
 . 'la condizione più fortunata che esista, l\'esatto rovescio della combustione, e capita di '
 . 'rado.', 9, 'cazimi sole fortuna'],
['dignita', 'cazimi', 'moderno', '%s nel cuore del Sole',
 'Condizione rarissima: %s coincide col centro stesso della persona. Non è assorbit{o|a} come '
 . 'nella combustione — è proprio il nucleo.', 9, 'cazimi sole'],

['dignita', 'retrogrado', 'tradizionale', '%s retrograd{o|a}',
 '%s appare tornare indietro nello zodiaco. La tradizione {lo|la} considera debilitat{o|a}: agisce in '
 . 'modo contrario, indiretto, tardivo. Le cose che significa si ottengono, ma per vie storte e '
 . 'con ritardo.', 7, 'retrogrado debolezza'],
['dignita', 'retrogrado', 'moderno', '%s retrograd{o|a}',
 '%s si rivolge all\'interno prima che all\'esterno. Matura più lentamente e spesso fuori '
 . 'tempo rispetto agli altri, ma quando emerge è stat{o|a} digerit{o|a} davvero.',
 7, 'retrogrado interiorizzazione'],

// ═══════════════════════════════════════════════════════════════════════════
// FASI LUNARI
// ═══════════════════════════════════════════════════════════════════════════

['fase_luna', 'Luna nuova', 'tradizionale', 'Nato di novilunio',
 'La Luna era congiunta al Sole e invisibile in cielo. La tradizione considera debole la Luna '
 . 'sotto i raggi, ma il novilunio è anche inizio assoluto di ciclo.', 6, 'fase novilunio'],
['fase_luna', 'Luna nuova', 'moderno', 'Nato di Luna nuova',
 'Nascere a Luna nuova significa cominciare senza modelli: si agisce d\'istinto, prima di aver '
 . 'capito. La consapevolezza arriva dopo il gesto, non prima.', 6, 'fase novilunio inizio'],

['fase_luna', 'Falce crescente', 'moderno', 'Nato di falce crescente',
 'La fase della spinta in avanti contro l\'inerzia di ciò che c\'era prima. Una certa '
 . 'irrequietezza di fondo, e la tendenza a dover staccarsi da qualcosa per crescere.',
 5, 'fase crescente'],
['fase_luna', 'Primo quarto', 'moderno', 'Nato di primo quarto',
 'La fase della crisi d\'azione: quello che si è cominciato incontra resistenza, e va imposto '
 . 'o abbandonato. Tendenza a costruire rompendo.', 5, 'fase primo_quarto crisi'],
['fase_luna', 'Gibbosa crescente', 'moderno', 'Nato di gibbosa crescente',
 'La fase del perfezionamento: c\'è già una forma, e la si lima. Bisogno di capire il '
 . 'perché delle cose prima di considerarle finite.', 5, 'fase gibbosa'],
['fase_luna', 'Luna piena', 'tradizionale', 'Nato di plenilunio',
 'La Luna era opposta al Sole e piena di luce. È forte per lume, ma in opposizione al '
 . 'luminare del giorno: la tradizione vi legge una tensione fra la volontà e l\'istinto.',
 7, 'fase plenilunio'],
['fase_luna', 'Luna piena', 'moderno', 'Nato di Luna piena',
 'Sole e Luna si guardano da due parti opposte del cielo: quello che si vuole e quello di cui '
 . 'si ha bisogno non coincidono, e si vedono benissimo l\'un l\'altro. Grande chiarezza, e una '
 . 'tensione che non si risolve ma si abita.', 7, 'fase plenilunio tensione'],
['fase_luna', 'Gibbosa calante', 'moderno', 'Nato di gibbosa calante',
 'La fase in cui quello che si è capito si comunica. Bisogno di trasmettere, di spiegare, di '
 . 'lasciare qualcosa a qualcuno.', 5, 'fase calante'],
['fase_luna', 'Ultimo quarto', 'moderno', 'Nato di ultimo quarto',
 'La fase della crisi di coscienza: quello che si è costruito viene messo in discussione dal '
 . 'di dentro. Tendenza a smontare per capire.', 5, 'fase ultimo_quarto'],
['fase_luna', 'Falce calante', 'moderno', 'Nato di falce calante',
 'La fase del congedo: si porta a termine qualcosa che è cominciato prima di noi. Spesso una '
 . 'sensazione di essere fuori tempo rispetto ai coetanei.', 5, 'fase calante congedo'],

// ═══════════════════════════════════════════════════════════════════════════
// FIGURA PLANETARIA
// ═══════════════════════════════════════════════════════════════════════════

['figura', 'fascio', 'moderno', 'Fascio',
 'Tutti i corpi raccolti in un quarto di cielo. Un\'energia straordinariamente concentrata su '
 . 'pochi fronti: grande capacità di specializzazione, e un\'intera metà di carta che resta '
 . 'in ombra.', 6, 'figura fascio'],
['figura', 'ciotola', 'moderno', 'Ciotola',
 'Tutti i corpi in una metà di cielo. Chi ha una ciotola sente di possedere pienamente un '
 . 'lato dell\'esperienza e di mancarne un altro — e spesso passa la vita a cercare fuori '
 . 'quello che gli sembra mancare.', 6, 'figura ciotola'],
['figura', 'secchio', 'moderno', 'Secchio',
 'I corpi raccolti in mezzo cielo, con uno solo dall\'altra parte a fare da manico. Quel '
 . 'pianeta isolato diventa il punto da cui passa tutto: è la valvola dell\'intera carta.',
 7, 'figura secchio manico'],
['figura', 'locomotiva', 'moderno', 'Locomotiva',
 'Due terzi di cielo occupati e un terzo vuoto. Il pianeta che apre lo spazio pieno, in senso '
 . 'orario, traina tutti gli altri: c\'è una spinta costante, quasi una fretta di fondo.',
 6, 'figura locomotiva'],
['figura', 'altalena', 'moderno', 'Altalena',
 'Due gruppi contrapposti separati da due vuoti. Una carta che vive di contrasti e che tende '
 . 'a pensare per opposizioni: o questo, o quello. Imparare a tenere insieme le due parti è '
 . 'il lavoro di una vita.', 6, 'figura altalena'],
['figura', 'spruzzo', 'moderno', 'Spruzzo',
 'Corpi sparsi su tutto il cerchio, nessun vuoto rilevante. Interessi molti e sparpagliati, '
 . 'grande adattabilità, e la fatica di concentrarsi su una cosa sola abbastanza a lungo.',
 6, 'figura spruzzo'],
['figura', 'fionda', 'moderno', 'Fionda',
 'Tutti i corpi in un quarto di cielo, meno uno che tira dall\'altra parte. Concentrazione '
 . 'estrema più un contrappeso: quel pianeta solitario è la direzione in cui l\'intera '
 . 'carta viene scagliata.', 7, 'figura fionda'],

];
