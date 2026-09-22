<?php
/**
 * Sprite dei glifi, incorporato una volta per pagina.
 *
 * Incorporato e non referenziato da file esterno: <use href="glifi.svg#x">
 * funziona quasi sempre, e «quasi sempre» in produzione vuol dire che un
 * giorno qualcuno vede dei quadrati vuoti. Costa due chilobyte compressi.
 */
$sprite = (string) @file_get_contents((string) ($GLOBALS['__project_root'] ?? '') . '/assets/img/glifi.svg');
echo (string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $sprite);
