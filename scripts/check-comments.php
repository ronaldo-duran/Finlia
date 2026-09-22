<?php

declare(strict_types=1);

/**
 * Falla si el código lleva comentarios narrativos o emojis.
 *
 * El único comentario admitido es PHPDoc (`/** *\/`, y JSDoc en JS).
 * La regla completa está en docs/CONVENTIONS.md §1.1 y AGENTS.md §1.1.
 *
 * Uso: php scripts/check-comments.php
 */
const RAIZ = __DIR__.'/..';

const RUTAS = [
    'app', 'routes', 'database', 'config', 'tests', 'ee',
    'bootstrap', 'resources/views', 'resources/js', 'resources/css',
];

const EXTENSIONES = ['php', 'js', 'css', 'ts'];

/** @return list<string> */
function archivos(): array
{
    $encontrados = [];

    foreach (RUTAS as $ruta) {
        $absoluta = RAIZ.'/'.$ruta;
        if (! is_dir($absoluta)) {
            continue;
        }

        $iterador = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absoluta, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterador as $archivo) {
            if ($archivo->isFile() && in_array($archivo->getExtension(), EXTENSIONES, true)) {
                $encontrados[] = $archivo->getPathname();
            }
        }
    }

    sort($encontrados);

    return $encontrados;
}

/** @return list<array{int, string, string}> */
function revisarPhp(string $codigo): array
{
    $hallazgos = [];
    foreach (token_get_all($codigo) as $token) {
        if (is_array($token) && $token[0] === T_COMMENT) {
            $hallazgos[] = [$token[2], 'comentario no-PHPDoc', trim($token[1])];
        }
    }

    return $hallazgos;
}

/** @return list<array{int, string, string}> */
function revisarTexto(string $codigo, bool $esBlade): array
{
    $hallazgos = [];
    $lineas = preg_split("/\r\n|\n/", $codigo) ?: [];

    foreach ($lineas as $i => $linea) {
        $numero = $i + 1;
        $limpia = trim($linea);

        if (str_starts_with($limpia, '//') && ! str_contains($limpia, 'http')) {
            $hallazgos[] = [$numero, 'comentario //', $limpia];

            continue;
        }

        if (preg_match('/\S\s+\/\/(?!\/)/', $linea) && ! preg_match('~https?://~', $linea)) {
            $hallazgos[] = [$numero, 'comentario // al final de línea', $limpia];
        }

        if ($esBlade && str_contains($linea, '{{--')) {
            $hallazgos[] = [$numero, 'comentario Blade {{-- --}}', $limpia];
        }

        if (preg_match('/(?<!\S)\/\*(?!\*)/', $linea)) {
            $hallazgos[] = [$numero, 'bloque /* */ que no es JSDoc', $limpia];
        }
    }

    return $hallazgos;
}

/** @return list<array{int, string, string}> */
function revisarEmojis(string $codigo): array
{
    $patron = '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}]/u';
    $hallazgos = [];

    foreach (preg_split("/\r\n|\n/", $codigo) ?: [] as $i => $linea) {
        if (preg_match($patron, $linea)) {
            $hallazgos[] = [$i + 1, 'emoji', trim($linea)];
        }
    }

    return $hallazgos;
}

$total = 0;

foreach (archivos() as $archivo) {
    $codigo = file_get_contents($archivo);
    if ($codigo === false) {
        continue;
    }

    $extension = pathinfo($archivo, PATHINFO_EXTENSION);
    $esBlade = str_ends_with($archivo, '.blade.php');

    $hallazgos = $extension === 'php' && ! $esBlade
        ? revisarPhp($codigo)
        : revisarTexto($codigo, $esBlade);

    $hallazgos = array_merge($hallazgos, revisarEmojis($codigo));

    if ($hallazgos === []) {
        continue;
    }

    $raiz = str_replace('\\', '/', realpath(RAIZ) ?: RAIZ);
    $relativa = substr(str_replace('\\', '/', $archivo), strlen($raiz) + 1);

    foreach ($hallazgos as [$linea, $tipo, $texto]) {
        $extracto = mb_substr($texto, 0, 80);
        echo "{$relativa}:{$linea}: {$tipo} — {$extracto}\n";
        $total++;
    }
}

if ($total > 0) {
    echo "\n{$total} comentario(s) o emoji(s) prohibidos. Solo se admite PHPDoc.\n";
    echo "Regla: docs/CONVENTIONS.md §1.1 · AGENTS.md §1.1\n";
    exit(1);
}

echo "Sin comentarios narrativos ni emojis en el código.\n";
exit(0);
