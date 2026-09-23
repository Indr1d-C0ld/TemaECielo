<?php

declare(strict_types=1);

namespace App\Archivio;

use App\Core\Database;
use InvalidArgumentException;

/**
 * L'archivio: schede che danno un nome pubblico a una carta.
 *
 * Una scheda non contiene la carta, la indica. Persone celebri, eventi storici
 * e fondazioni di Stati sono carte come tutte le altre — stesso calcolo, stessa
 * ruota, stesse letture — con in piu' cio' che serve a chi le trova in un
 * elenco: che cosa sono, da dove vengono i dati, quanto ci si puo' fidare
 * dell'ora.
 */
final class Archivio
{
    /** @var array<string,string> */
    public const TIPI = [
        'persona' => 'Persone',
        'evento'  => 'Eventi',
        'nazione' => 'Nazioni e istituzioni',
    ];

    /** @var array<string,list<string>> le categorie ammesse, per tipo */
    public const CATEGORIE = [
        'persona' => ['scienza', 'arte', 'letteratura', 'musica', 'cinema', 'spettacolo',
                      'filosofia', 'politica', 'esplorazione', 'sport', 'religione'],
        'evento'  => ['guerra', 'rivoluzione', 'politica', 'scienza', 'esplorazione',
                      'disastro', 'economia', 'cultura'],
        'nazione' => ['fondazione', 'costituzione', 'unione', 'moneta'],
    ];

    /**
     * Le classi di affidabilita' di Lois Rodden: quanto e' certa l'ORA, che e'
     * la cosa che conta — la data di una persona celebre e' quasi sempre nota,
     * l'ora molto meno, e con l'ora cambiano Ascendente e case.
     *
     * @var array<string,array{0:string,1:string}>
     */
    public const RODDEN = [
        'AA' => ['Documento ufficiale', 'Ora da un atto di nascita, un verbale o una registrazione.'],
        'A'  => ['Fonte diretta', 'Ora dalla persona stessa, dai familiari o da chi era presente.'],
        'B'  => ['Biografia', 'Ora da una biografia o da una ricostruzione storica.'],
        'C'  => ['Approssimativa', 'Ora incerta, arrotondata o convenzionale.'],
        'DD' => ['Fonti in contrasto', 'Esistono ore diverse, e nessuna prevale con certezza.'],
        'X'  => ['Ora ignota', 'La data e\' certa, l\'ora no: carta solare.'],
    ];

    /** La scheda di una carta, se ce l'ha. @return array<string,mixed>|null */
    public static function perCalcolo(int $calcoloId): ?array
    {
        return Database::riga('SELECT * FROM archivio WHERE calcolo_id = ? LIMIT 1', [$calcoloId]);
    }

    /**
     * Una scheda pubblicata, col gettone della sua carta.
     *
     * @return array<string,mixed>|null
     */
    public static function perSlug(string $slug): ?array
    {
        return Database::riga(
            'SELECT a.*, c.gettone FROM archivio a JOIN calcoli c ON c.id = a.calcolo_id
              WHERE a.slug = ? AND a.pubblicata = 1 LIMIT 1',
            [$slug],
        );
    }

    /**
     * Crea o aggiorna la scheda di una carta.
     *
     * @param array<string,mixed> $d
     * @return string lo slug
     */
    public static function salva(int $calcoloId, array $d): string
    {
        $d = self::pulisci($d);
        $esistente = self::perCalcolo($calcoloId);

        if ($esistente !== null) {
            $slug = (string) $esistente['slug'];
            // Il nome cambiato cambia anche l'indirizzo, ma solo se era derivato
            // dal nome vecchio: uno slug scelto a mano non si tocca.
            if ($d['nome'] !== $esistente['nome'] && $slug === self::slugDi((string) $esistente['nome'])) {
                $slug = self::slugLibero($d['nome'], $calcoloId);
            }
            Database::esegui(
                'UPDATE archivio SET slug = ?, tipo = ?, nome = ?, categoria = ?, nota = ?, fonte = ?,
                        url_fonte = ?, rodden = ?, pubblicata = ?, aggiornato = NOW()
                  WHERE calcolo_id = ?',
                [$slug, $d['tipo'], $d['nome'], $d['categoria'], $d['nota'], $d['fonte'],
                 $d['url_fonte'], $d['rodden'], $d['pubblicata'], $calcoloId],
            );

            return $slug;
        }

        $slug = self::slugLibero($d['nome'], $calcoloId);
        Database::esegui(
            'INSERT INTO archivio (calcolo_id, slug, tipo, nome, categoria, nota, fonte, url_fonte, rodden,
                                   pubblicata, creato, aggiornato)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [$calcoloId, $slug, $d['tipo'], $d['nome'], $d['categoria'], $d['nota'], $d['fonte'],
             $d['url_fonte'], $d['rodden'], $d['pubblicata']],
        );

        return $slug;
    }

    /** Toglie la scheda: la carta resta, torna una carta qualunque. */
    public static function ritira(int $calcoloId): void
    {
        Database::esegui('DELETE FROM archivio WHERE calcolo_id = ?', [$calcoloId]);
    }

    /**
     * Le schede pubblicate, con i dati della carta per ordinarle e mostrarle.
     *
     * @param array{tipo?:string,categoria?:string,q?:string,secolo?:int} $filtri
     * @return array{righe:list<array<string,mixed>>,totale:int}
     */
    public static function elenco(array $filtri, int $pagina = 1, int $per = 60): array
    {
        $dove = ['a.pubblicata = 1'];
        $par  = [];

        if (isset(self::TIPI[$filtri['tipo'] ?? ''])) {
            $dove[] = 'a.tipo = ?';
            $par[]  = $filtri['tipo'];
        }
        if (($filtri['categoria'] ?? '') !== '') {
            $dove[] = 'a.categoria = ?';
            $par[]  = $filtri['categoria'];
        }
        if (($filtri['q'] ?? '') !== '') {
            $dove[] = '(a.nome LIKE ? OR s.luogo_nome LIKE ?)';
            $like   = '%' . addcslashes((string) $filtri['q'], '%_\\') . '%';
            array_push($par, $like, $like);
        }
        if (($filtri['secolo'] ?? 0) >= 18 && ($filtri['secolo'] ?? 0) <= 24) {
            $dove[] = 'YEAR(s.data_nascita) BETWEEN ? AND ?';
            array_push($par, ($filtri['secolo'] - 1) * 100 + 1, $filtri['secolo'] * 100);
        }

        $da = 'FROM archivio a
               JOIN calcoli c ON c.id = a.calcolo_id
               JOIN calcoli_soggetti cs ON cs.calcolo_id = c.id AND cs.ruolo = \'primo\'
               JOIN soggetti s ON s.id = cs.soggetto_id
              WHERE ' . implode(' AND ', $dove);

        $totale = (int) Database::valore('SELECT COUNT(*) ' . $da, $par);
        $righe  = Database::righe(
            'SELECT a.slug, a.tipo, a.nome, a.categoria, a.rodden, a.nota, c.gettone,
                    s.data_nascita, s.ora_nascita, s.precisione_ora, s.luogo_nome,
                    JSON_UNQUOTE(JSON_EXTRACT(c.esito, \'$.corpi.sole.segno\')) AS segno_sole,
                    JSON_UNQUOTE(JSON_EXTRACT(c.esito, \'$.punti.asc.segno\'))  AS segno_asc,
                    JSON_UNQUOTE(JSON_EXTRACT(c.esito, \'$.corpi.luna.segno\')) AS segno_luna
             ' . $da . '
             ORDER BY s.data_nascita, a.nome
             LIMIT ' . $per . ' OFFSET ' . (($pagina - 1) * $per),
            $par,
        );

        return ['righe' => $righe, 'totale' => $totale];
    }

    /** Quante schede pubblicate per tipo e per categoria. @return array<string,array<string,int>> */
    public static function conteggi(): array
    {
        $fuori = [];
        foreach (Database::righe(
            'SELECT tipo, categoria, COUNT(*) AS n FROM archivio WHERE pubblicata = 1 GROUP BY tipo, categoria'
        ) as $r) {
            $fuori[(string) $r['tipo']][(string) $r['categoria']] = (int) $r['n'];
        }

        return $fuori;
    }

    /**
     * Controlla e normalizza i campi di una scheda.
     *
     * @param array<string,mixed> $d
     * @return array{tipo:string,nome:string,categoria:string,nota:string,fonte:string,url_fonte:string,rodden:string,pubblicata:int}
     */
    public static function pulisci(array $d): array
    {
        $tipo = (string) ($d['tipo'] ?? 'persona');
        if (!isset(self::TIPI[$tipo])) {
            throw new InvalidArgumentException('Tipo di scheda sconosciuto.');
        }
        $nome = trim(mb_substr((string) ($d['nome'] ?? ''), 0, 160));
        if ($nome === '') {
            throw new InvalidArgumentException('Una scheda dell\'archivio ha bisogno di un nome.');
        }
        $categoria = (string) ($d['categoria'] ?? '');
        if (!in_array($categoria, self::CATEGORIE[$tipo], true)) {
            $categoria = self::CATEGORIE[$tipo][0];
        }
        $rodden = strtoupper((string) ($d['rodden'] ?? 'C'));
        if (!isset(self::RODDEN[$rodden])) {
            $rodden = 'C';
        }
        $url = trim(mb_substr((string) ($d['url_fonte'] ?? ''), 0, 500));
        // Solo collegamenti web: un «javascript:» qui finirebbe in un href pubblico.
        if ($url !== '' && preg_match('#^https?://#i', $url) !== 1) {
            $url = '';
        }

        return [
            'tipo'       => $tipo,
            'nome'       => $nome,
            'categoria'  => $categoria,
            'nota'       => trim(mb_substr((string) ($d['nota'] ?? ''), 0, 4000)),
            'fonte'      => trim(mb_substr((string) ($d['fonte'] ?? ''), 0, 500)),
            'url_fonte'  => $url,
            'rodden'     => $rodden,
            'pubblicata' => !empty($d['pubblicata']) ? 1 : 0,
        ];
    }

    /** «Gabriele D'Annunzio» → «gabriele-d-annunzio». */
    public static function slugDi(string $nome): string
    {
        $ascii = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
        $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)), '-');

        return $s !== '' ? mb_substr($s, 0, 100) : 'carta';
    }

    private static function slugLibero(string $nome, int $calcoloId): string
    {
        $base = self::slugDi($nome);
        $slug = $base;
        for ($i = 2; ; $i++) {
            $altro = Database::valore('SELECT calcolo_id FROM archivio WHERE slug = ? LIMIT 1', [$slug]);
            if ($altro === null || (int) $altro === $calcoloId) {
                return $slug;
            }
            $slug = $base . '-' . $i;
        }
    }
}
