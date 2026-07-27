<?php

declare(strict_types=1);

namespace App\Support\Contable\IngresosBrutos;

use App\Models\Configuracion\Provincia;

/**
 * Códigos Anita (zonamult / venibr / retibrmov) asociados a una provincia ERP.
 * Buenos Aires: codigoexterno 2 y jurisdicción 902 (mismo criterio que PercepcioniibbExport).
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
}
