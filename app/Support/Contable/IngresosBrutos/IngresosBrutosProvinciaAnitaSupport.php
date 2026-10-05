<?php

declare(strict_types=1);

namespace App\Support\Contable\IngresosBrutos;

use App\Models\Configuracion\Provincia;

/**
 * Códigos Anita (zonamult / venibr / retibrmov) asociados a una provincia ERP.
 * Buenos Aires: 2 y 902. CABA: 1 y 901 (mismo criterio que PercepcioniibbExport).
 */
final class IngresosBrutosProvinciaAnitaSupport
{
    /**
     * @return list<int>
     */
    public static function codigosAnita(?Provincia $provincia): array
    {
        if ($provincia === null) {
            return [];
        }

        $codigos = [];
        foreach ([(int) ($provincia->codigoexterno ?? 0), (int) ($provincia->jurisdiccion ?? 0), (int) ($provincia->codigo ?? 0)] as $c) {
            if ($c > 0) {
                $codigos[$c] = $c;
            }
        }

        // Anita guarda la jurisdicción corta y la AFIP. Ferli no carga codigoexterno:
        // CABA queda solo en 901 y se pierden las filas venibr con provincia 1.
        if (self::esCaba($provincia)) {
            $codigos[1] = 1;
            $codigos[901] = 901;
        }
        if (self::esBuenosAires($provincia)) {
            $codigos[2] = 2;
            $codigos[902] = 902;
        }

        return array_values($codigos);
    }

    public static function esBuenosAires(?Provincia $provincia): bool
    {
        if ($provincia === null) {
            return false;
        }
        $nombre = mb_strtolower(trim((string) $provincia->nombre));
        if (str_contains($nombre, 'buenos aires') && ! str_contains($nombre, 'ciudad')) {
            return true;
        }

        return in_array((int) ($provincia->codigoexterno ?? 0), [2], true)
            || in_array((int) ($provincia->jurisdiccion ?? 0), [902], true);
    }

    public static function esCaba(?Provincia $provincia): bool
    {
        if ($provincia === null) {
            return false;
        }
        $nombre = mb_strtolower(trim((string) $provincia->nombre));
        if (
            str_contains($nombre, 'caba')
            || str_contains($nombre, 'capital federal')
            || str_contains($nombre, 'ciudad aut')
        ) {
            return true;
        }

        return in_array((int) ($provincia->jurisdiccion ?? 0), [901], true)
            || in_array((int) ($provincia->codigo ?? 0), [901], true);
    }
}
