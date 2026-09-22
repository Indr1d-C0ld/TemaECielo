<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Applica i file .sql di db/migrazioni/ in ordine di nome, una volta sola.
 *
 * Niente strumenti esterni: le migrazioni sono SQL leggibile, numerato e
 * versionato, e la tabella `migrazioni` tiene il conto di cio' che e' passato.
 */
final class Migrazioni
{
    public function __construct(private string $cartella)
    {
    }

    public function assicuraTabella(): void
    {
        Database::esegui(
            'CREATE TABLE IF NOT EXISTS migrazioni (
                id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
                nome     VARCHAR(190) NOT NULL,
                applicata DATETIME NOT NULL,
                durata_ms INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY u_nome (nome)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return list<string> */
    public function disponibili(): array
    {
        $file = glob($this->cartella . '/*.sql') ?: [];
        sort($file, SORT_STRING);

        return array_map(static fn (string $f): string => basename($f), $file);
    }

    /** @return list<string> */
    public function applicate(): array
    {
        $this->assicuraTabella();

        return array_map(
            static fn (array $r): string => (string) $r['nome'],
            Database::righe('SELECT nome FROM migrazioni ORDER BY nome'),
        );
    }

    /** @return list<string> */
    public function pendenti(): array
    {
        return array_values(array_diff($this->disponibili(), $this->applicate()));
    }

    /**
     * @param callable(string,string):void|null $eco
     * @return list<string> i nomi applicati adesso
     */
    public function applica(?callable $eco = null): array
    {
        $fatte = [];

        foreach ($this->pendenti() as $nome) {
            $sql = (string) file_get_contents($this->cartella . '/' . $nome);
            $t0  = microtime(true);

            foreach (self::spezza($sql) as $istruzione) {
                try {
                    Database::pdo()->exec($istruzione);
                } catch (\Throwable $e) {
                    throw new RuntimeException(
                        "Migrazione {$nome} fallita.\n"
                        . 'Istruzione: ' . substr(preg_replace('/\s+/', ' ', $istruzione) ?? '', 0, 200) . "\n"
                        . $e->getMessage(),
                        0,
                        $e,
                    );
                }
            }

            $ms = (int) round((microtime(true) - $t0) * 1000);
            Database::esegui(
                'INSERT INTO migrazioni (nome, applicata, durata_ms) VALUES (?, NOW(), ?)',
                [$nome, $ms],
            );

            $fatte[] = $nome;
            if ($eco !== null) {
                $eco($nome, "{$ms} ms");
            }
        }

        return $fatte;
    }

    /**
     * Spezza un file SQL nelle singole istruzioni.
     *
     * Rispetta apici, virgolette, backtick e commenti: un punto e virgola dentro
     * una stringa non e' un separatore, e un `--` dentro un apice non apre un
     * commento. E' l'unico pezzo delicato di questa classe.
     *
     * @return list<string>
     */
    public static function spezza(string $sql): array
    {
        $istruzioni = [];
        $corrente   = '';
        $len        = strlen($sql);
        $apice      = null;

        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            $p = $sql[$i + 1] ?? '';

            if ($apice !== null) {
                $corrente .= $c;
                if ($c === '\\' && $apice !== '`') {
                    $corrente .= $p;
                    $i++;
                } elseif ($c === $apice) {
                    $apice = null;
                }
                continue;
            }

            if ($c === "'" || $c === '"' || $c === '`') {
                $apice = $c;
                $corrente .= $c;
                continue;
            }

            // Commento di riga: -- oppure #
            if (($c === '-' && $p === '-') || $c === '#') {
                $fine = strpos($sql, "\n", $i);
                $i    = $fine === false ? $len : $fine;
                $corrente .= "\n";
                continue;
            }

            // Commento a blocco
            if ($c === '/' && $p === '*') {
                $fine = strpos($sql, '*/', $i + 2);
                $i    = $fine === false ? $len : $fine + 1;
                continue;
            }

            if ($c === ';') {
                $t = trim($corrente);
                if ($t !== '') {
                    $istruzioni[] = $t;
                }
                $corrente = '';
                continue;
            }

            $corrente .= $c;
        }

        $t = trim($corrente);
        if ($t !== '') {
            $istruzioni[] = $t;
        }

        return $istruzioni;
    }
}
