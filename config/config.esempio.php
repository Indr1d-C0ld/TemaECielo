<?php

declare(strict_types=1);

/**
 * Tema e Cielo — modello di configurazione.
 *
 * Questo file e' un SEGNAPOSTO e sta nel repository. Quello vero lo genera
 * deploy/00-bootstrap.sh fuori dal DocumentRoot, con password e chiave
 * generate a caso. Dove esattamente lo decide Core\Config::percorsi().
 *
 * Non copiarlo a mano in produzione: i due segreti qui sotto sono finti apposta.
 */

return [
    'app' => [
        'nome'         => 'Tema e Cielo',
        'env'          => 'production',
        'debug'        => false,
        'timezone'     => 'Europe/Rome',
        'base_path'    => '/temaecielo',
        'url_pubblico' => 'https://ESEMPIO.TLD/temaecielo',
    ],
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'tec_temaecielo',
        'user'    => 'tec_temaecielo',
        'pass'    => 'DA_GENERARE',
        'charset' => 'utf8mb4',
    ],
    'sicurezza' => [
        'nome_sessione'   => 'temaecielo_sess',
        'durata_sessione' => 7200,
        'chiave'          => 'DA_GENERARE',
        'proxy_fidati'    => ['127.0.0.1', '::1'],
    ],
    'astro' => [
        // Percorsi tipici di Debian; il bootstrap li rileva da solo.
        'libswe'     => '/usr/lib/x86_64-linux-gnu/libswe.so.2',
        'effemeridi' => '/usr/share/libswe/ephe',
        'php_cli'    => '/usr/bin/php',
        'timeout'    => 20,
    ],
    'privacy' => [
        // 0 = conservazione illimitata degli accessi.
        'purga_accessi_giorni' => 0,
        'anonimizza_ip'        => false,
    ],
];
