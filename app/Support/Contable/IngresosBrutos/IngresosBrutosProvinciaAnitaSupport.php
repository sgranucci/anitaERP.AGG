<?php

declare(strict_types=1);

namespace App\Support\Contable\IngresosBrutos;

use App\Models\Configuracion\Provincia;
use App\Support\Configuracion\EmpresaJurisdiccionIibbSupport;

/**
 * Códigos Anita (zonamult / venibr / retibrmov) asociados a una provincia ERP.
 * Buenos Aires: 2 y 902. CABA: 1 y 901 (mismo criterio que PercepcioniibbExport).
 */
final class IngresosBrutosProvinciaAnitaSupport
{
    /**
     * Crown / agente solo RN: hasta ~28/10/2026 las retenciones se grabaron en
     * retibrmov con retibr_provincia = 0 y en ERP con provincia_id NULL.
     * Sin incluirlas, el mes queda incompleto (solo aparecen las ya marcadas con 3).
     *
     * Solo aplica a Río Negro y cuando la empresa retiene únicamente en 916.
     */
    public static function incluirSinCodigoProvincia(?Provincia $provincia, int $empresaId): bool
    {
        if ($empresaId <= 0 || ! self::esRioNegro($provincia)) {
            return false;
        }

        $jurs = array_values(array_unique(array_map(
            static fn ($j) => (int) $j,
            EmpresaJurisdiccionIibbSupport::jurisdiccionesRetencion($empresaId),
        )));
        sort($jurs);

        return $jurs === [IngresosBrutosAgenteOpcionesSupport::JURISDICCION_RIO_NEGRO];
    }

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
        if (self::esRioNegro($provincia)) {
            $codigos[3] = 3;
            $codigos[916] = 916;
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

    public static function esRioNegro(?Provincia $provincia): bool
    {
        if ($provincia === null) {
            return false;
        }
        $nombre = mb_strtolower(trim((string) $provincia->nombre));
        if (str_contains($nombre, 'rio negro') || str_contains($nombre, 'río negro')) {
            return true;
        }

        return in_array((int) ($provincia->jurisdiccion ?? 0), [916], true)
            || in_array((int) ($provincia->codigoexterno ?? 0), [3], true)
            || in_array((int) ($provincia->codigo ?? 0), [3], true);
    }
}
