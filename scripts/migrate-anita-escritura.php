<?php

/**
 * Reemplaza apiCall() por apiCallEscritura() en escrituras (no en consultas list/decode).
 * Uso: php scripts/migrate-anita-escritura.php [--dry-run]
 */

$dryRun = in_array('--dry-run', $argv ?? [], true);
$root = dirname(__DIR__);
$dirs = [$root.'/app/Repositories', $root.'/app/Services', $root.'/app/Models'];

$readPatterns = [
    'json_decode',
    'primeraFilaLista',
    'decodificarListaFilas',
    'normalizarFilas',
    'decodificarRespuestaList',
];

$stats = ['files' => 0, 'lines' => 0, 'strpos_removed' => 0];

foreach ($dirs as $dir) {
    if (! is_dir($dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        if (str_contains($path, 'TransporteRepository copy.php')) {
            continue;
        }

        $lines = file($path);
        if ($lines === false) {
            continue;
        }

        $changed = false;
        $newLines = [];
        $skipUntil = -1;

        for ($i = 0; $i < count($lines); $i++) {
            if ($i < $skipUntil) {
                continue;
            }

            $line = $lines[$i];
            $trim = trim($line);

            // Quitar bloques if (strpos(..., 'Error') !== false) return ...
            if (preg_match("/if\s*\(\s*strpos\s*\([^)]+,\s*['\"]Error['\"]\s*\)\s*!==\s*false\s*\)/", $trim)) {
                $j = $i;
                $depth = 0;
                while ($j < count($lines)) {
                    $depth += substr_count($lines[$j], '{') - substr_count($lines[$j], '}');
                    $j++;
                    if ($depth <= 0 && $j > $i + 1) {
                        break;
                    }
                }
                $skipUntil = $j;
                $changed = true;
                $stats['strpos_removed']++;
                continue;
            }

            if (! preg_match('/->apiCall\s*\(/', $line)) {
                $newLines[] = $line;
                continue;
            }

            $isRead = false;
            foreach ($readPatterns as $pat) {
                if (str_contains($line, $pat)) {
                    $isRead = true;
                    break;
                }
            }
            if (! $isRead && $i > 0) {
                $prev = trim($lines[$i - 1]);
                foreach ($readPatterns as $pat) {
                    if (str_contains($prev, $pat) && ! str_contains($prev, ';')) {
                        $isRead = true;
                        break;
                    }
                }
            }

            if ($isRead) {
                $newLines[] = $line;
                continue;
            }

            $newLine = preg_replace('/->apiCall\s*\(/', '->apiCallEscritura(', $line, 1);
            if ($newLine !== $line) {
                $changed = true;
                $stats['lines']++;
            }
            $newLines[] = $newLine;
        }

        if ($changed) {
            $stats['files']++;
            if (! $dryRun) {
                file_put_contents($path, implode('', $newLines));
            }
            echo ($dryRun ? '[dry-run] ' : '').$path."\n";
        }
    }
}

echo "\nArchivos: {$stats['files']}, líneas apiCall→apiCallEscritura: {$stats['lines']}, bloques strpos Error eliminados: {$stats['strpos_removed']}\n";
