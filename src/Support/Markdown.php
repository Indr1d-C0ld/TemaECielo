<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Markdown ridotto all'essenziale, per le pagine redazionali.
 *
 * Nessuna dipendenza esterna e nessun HTML passante: il testo viene scappato
 * PRIMA di qualunque trasformazione, quindi un admin distratto (o un domani
 * meno attento) non puo' iniettare script in una pagina pubblica.
 *
 * Copre: titoli, paragrafi, grassetto, corsivo, codice, collegamenti, elenchi
 * puntati e numerati, citazioni, righe orizzontali. Basta e avanza per «chi
 * siamo», «come si legge una carta» e l'informativa.
 */
final class Markdown
{
    public static function rendi(string $testo): string
    {
        $testo = str_replace("\r\n", "\n", $testo);
        $testo = htmlspecialchars($testo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $fuori = [];
        $blocchi = explode("\n\n", $testo);

        foreach ($blocchi as $blocco) {
            $blocco = trim($blocco, "\n");
            if (trim($blocco) === '') {
                continue;
            }

            if (preg_match('/^(#{1,4})\s+(.*)$/s', $blocco, $m) === 1) {
                $liv = min(6, strlen($m[1]) + 1);
                $fuori[] = "<h{$liv}>" . self::inline(trim($m[2])) . "</h{$liv}>";
                continue;
            }

            if (preg_match('/^(---+|\*\*\*+)$/', trim($blocco)) === 1) {
                $fuori[] = '<hr>';
                continue;
            }

            if (str_starts_with(ltrim($blocco), '&gt;')) {
                $righe = array_map(
                    static fn (string $r): string => ltrim(preg_replace('/^\s*&gt;\s?/', '', $r) ?? ''),
                    explode("\n", $blocco),
                );
                $fuori[] = '<blockquote>' . self::inline(implode(' ', $righe)) . '</blockquote>';
                continue;
            }

            if (preg_match('/^\s*([-*+])\s+/', $blocco) === 1) {
                $fuori[] = self::elenco($blocco, 'ul', '/^\s*[-*+]\s+/');
                continue;
            }

            if (preg_match('/^\s*\d+[.)]\s+/', $blocco) === 1) {
                $fuori[] = self::elenco($blocco, 'ol', '/^\s*\d+[.)]\s+/');
                continue;
            }

            $fuori[] = '<p>' . self::inline(str_replace("\n", "<br>\n", $blocco)) . '</p>';
        }

        return implode("\n", $fuori);
    }

    private static function elenco(string $blocco, string $tag, string $marcatore): string
    {
        $voci = [];
        foreach (explode("\n", $blocco) as $riga) {
            if (trim($riga) === '') {
                continue;
            }
            if (preg_match($marcatore, $riga) === 1) {
                $voci[] = self::inline(trim((string) preg_replace($marcatore, '', $riga)));
            } elseif ($voci !== []) {
                // continuazione della voce precedente
                $voci[count($voci) - 1] .= ' ' . self::inline(trim($riga));
            }
        }

        return "<{$tag}><li>" . implode("</li>\n<li>", $voci) . "</li></{$tag}>";
    }

    private static function inline(string $t): string
    {
        // Codice per primo: dentro ai backtick non si trasforma piu' niente.
        $codici = [];
        $t = (string) preg_replace_callback('/`([^`]+)`/', static function (array $m) use (&$codici): string {
            $codici[] = '<code>' . $m[1] . '</code>';
            return "\x00" . (count($codici) - 1) . "\x00";
        }, $t);

        // Collegamenti: solo http, https e percorsi interni. Niente javascript:.
        $t = (string) preg_replace_callback(
            '/\[([^\]]+)\]\(([^)\s]+)\)/',
            static function (array $m): string {
                $href = $m[2];
                // «//altrosito» sembra un percorso interno ma porta fuori: si
                // rifiuta. Un percorso interno riceve la radice del portale
                // (/temaecielo), o porterebbe fuori dal portale.
                $interno = str_starts_with($href, '/') && !str_starts_with($href, '//');
                $ok = str_starts_with($href, 'http://')
                    || str_starts_with($href, 'https://')
                    || $interno
                    || str_starts_with($href, '#');
                if (!$ok) {
                    return $m[1];
                }
                $esterno = str_starts_with($href, 'http');
                $base = (string) ($GLOBALS['__base_path'] ?? '');
                if ($interno && $base !== '' && !str_starts_with($href, $base . '/') && $href !== $base) {
                    $href = $base . $href;
                }

                return '<a href="' . $href . '"'
                    . ($esterno ? ' rel="noopener noreferrer"' : '')
                    . '>' . $m[1] . '</a>';
            },
            $t,
        );

        $t = (string) preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $t);
        $t = (string) preg_replace('/(?<![\w*])\*([^*\n]+)\*(?![\w*])/', '<em>$1</em>', $t);
        $t = (string) preg_replace('/(?<![\w_])_([^_\n]+)_(?![\w_])/', '<em>$1</em>', $t);

        return (string) preg_replace_callback(
            '/\x00(\d+)\x00/',
            static fn (array $m): string => $codici[(int) $m[1]] ?? '',
            $t,
        );
    }
}
