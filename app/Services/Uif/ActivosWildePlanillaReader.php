<?php

namespace App\Services\Uif;

/**
 * Lee DNIs desde planillas Excel (columna "Documento", formato "DNI - 12345678").
 */
final class ActivosWildePlanillaReader
{
    /**
     * @return array<int, array{dni: string, titular: string, planilla: string}>
     */
    public static function leerDesdeArchivos(array $rutas): array
    {
        $porDni = [];

        foreach ($rutas as $ruta) {
            $ruta = trim((string) $ruta);
            if ($ruta === '' || ! is_readable($ruta)) {
                continue;
            }

            foreach (self::leerUnArchivo($ruta) as $fila) {
                $dni = $fila['dni'];
                if ($dni === '') {
                    continue;
                }
                if (! isset($porDni[$dni])) {
                    $porDni[$dni] = $fila;
                }
            }
        }

        ksort($porDni, SORT_STRING);

        return array_values($porDni);
    }

    /**
     * @return array<int, array{dni: string, titular: string, planilla: string}>
     */
    private static function leerUnArchivo(string $ruta): array
    {
        $script = <<<'PY'
import json, re, sys
import openpyxl
path = sys.argv[1]
wb = openpyxl.load_workbook(path, read_only=True)
ws = wb.active
out = []
for row in ws.iter_rows(values_only=True):
    if len(row) < 3:
        continue
    doc = row[2]
    if doc is None:
        continue
    doc_s = str(doc).strip()
    if doc_s.lower() in ('documento', ''):
        continue
    m = re.search(r'(\d{6,8})', doc_s)
    if not m:
        continue
    titular = str(row[1]).strip() if row[1] is not None else ''
    out.append({'dni': m.group(1), 'titular': titular, 'planilla': path})
wb.close()
print(json.dumps(out, ensure_ascii=False))
PY;

        $cmd = 'python3 -c '.escapeshellarg($script).' '.escapeshellarg($ruta);
        $raw = shell_exec($cmd.' 2>/dev/null');
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode(trim($raw), true);
        if (! is_array($decoded)) {
            return [];
        }

        $filas = [];
        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }
            $dni = preg_replace('/\D/', '', (string) ($item['dni'] ?? '')) ?? '';
            if ($dni === '') {
                continue;
            }
            $filas[] = [
                'dni' => $dni,
                'titular' => trim((string) ($item['titular'] ?? '')),
                'planilla' => basename((string) ($item['planilla'] ?? $ruta)),
            ];
        }

        return $filas;
    }
}
