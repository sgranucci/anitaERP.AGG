<?php

declare(strict_types=1);

namespace App\Support\Configuracion\Sircip;

use App\Models\Configuracion\Provincia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Qué provincias están en SIRCIP y qué período de padrón hay cargado.
 *
 * La fecha de vigencia vive en provincia.sircip_desde (nula = no usa SIRCIP).
 * Quién percibe sigue siendo empresa_jurisdiccion_iibb.es_agente_percepcion.
 */
final class SircipVigenciaSupport
{
    public static function columnaExiste(): bool
    {
        try {
            return Schema::hasTable('provincia') && Schema::hasColumn('provincia', 'sircip_desde');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Jurisdicciones (901…924) marcadas con fecha SIRCIP, tengan o no vigencia hoy.
     *
     * @return array<int, string> jurisdicción => fecha Y-m-d
     */
    public static function jurisdiccionesMarcadas(): array
    {
        if (! self::columnaExiste()) {
            return [];
        }

        $salida = [];
        $filas = Provincia::query()
            ->whereNotNull('sircip_desde')
            ->whereNotNull('jurisdiccion')
            ->orderBy('jurisdiccion')
            ->get(['jurisdiccion', 'sircip_desde']);
        foreach ($filas as $fila) {
            $jur = (int) $fila->jurisdiccion;
            if ($jur >= 900 && $fila->sircip_desde !== null) {
                $salida[$jur] = $fila->sircip_desde->format('Y-m-d');
            }
        }

        return $salida;
    }

    /** Alguna provincia usa SIRCIP: hay que mantener padrón, bandeja y avisos. */
    public static function activo(): bool
    {
        return self::jurisdiccionesMarcadas() !== [];
    }

    /** La jurisdicción percibe por SIRCIP en esa fecha. */
    public static function aplica(int $jurisdiccion, string $fecha): bool
    {
        $desde = self::jurisdiccionesMarcadas()[$jurisdiccion] ?? null;

        return $desde !== null && $desde <= substr($fecha, 0, 10);
    }

    /** Período de padrón (aaaamm) que corresponde a una fecha de factura. */
    public static function periodoDeFecha(string $fecha): string
    {
        return Carbon::parse($fecha)->format('Ym');
    }

    public static function periodoSiguiente(string $fecha): string
    {
        return Carbon::parse($fecha)->startOfMonth()->addMonthNoOverflow()->format('Ym');
    }

    /**
     * Períodos que hay cargados, con su cantidad de filas. No usa count(*) sobre
     * 1,1 M de filas por período: agrupa por la columna indexada de la clave única.
     *
     * @return array<string, int> período => filas
     */
    public static function periodosCargados(): array
    {
        try {
            if (! Schema::hasTable('padron_sircip')) {
                return [];
            }

            return DB::table('padron_sircip')
                ->select('periodo', DB::raw('count(*) as filas'))
                ->groupBy('periodo')
                ->orderBy('periodo')
                ->pluck('filas', 'periodo')
                ->map(static fn ($n) => (int) $n)
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    public static function periodoCargado(string $periodo): bool
    {
        try {
            return Schema::hasTable('padron_sircip')
                && DB::table('padron_sircip')->where('periodo', $periodo)->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
