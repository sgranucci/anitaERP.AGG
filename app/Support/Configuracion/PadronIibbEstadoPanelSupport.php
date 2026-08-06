<?php

declare(strict_types=1);

namespace App\Support\Configuracion;

use App\Models\Configuracion\Padron_Iibb_Carga;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Datos del panel de estado de la pantalla de importación de padrones IIBB.
 */
final class PadronIibbEstadoPanelSupport
{
    private const CACHE_COBERTURA = 'padron_iibb:cobertura_tasa';

    private const CACHE_MINUTOS = 15;

    /**
     * Últimas importaciones registradas, con la provincia ya resuelta.
     *
     * @return \Illuminate\Support\Collection<int,Padron_Iibb_Carga>
     */
    public static function ultimasCargas(int $limite = 10)
    {
        try {
            return Padron_Iibb_Carga::query()
                ->with(['provincias:id,nombre,codigo', 'usuarios:id,nombre'])
                ->orderByDesc('id')
                ->limit($limite)
                ->get();
        } catch (Throwable $e) {
            return collect();
        }
    }

    /**
     * Períodos cargados en padron_iibb_tasa por provincia.
     *
     * Se cachea porque agrupa millones de filas; toda importación la invalida,
     * así que el panel muestra el resultado real apenas termina una carga.
     *
     * @return list<array<string,mixed>>
     */
    public static function coberturaTasa(): array
    {
        try {
            return Cache::remember(
                self::CACHE_COBERTURA,
                now()->addMinutes(self::CACHE_MINUTOS),
                static fn (): array => self::consultarCobertura()
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function olvidarCobertura(): void
    {
        try {
            Cache::forget(self::CACHE_COBERTURA);
        } catch (Throwable $e) {
            // El panel se recalcula igual al vencer el TTL.
        }
    }

    /**
     * Se consulta provincia por provincia en lugar de agrupar la tabla entera:
     * con el índice (provincia_id, desdefecha, hastafecha) cada MIN/MAX es una
     * lectura puntual, mientras que un GROUP BY global recorre millones de filas.
     *
     * @return list<array<string,mixed>>
     */
    private static function consultarCobertura(): array
    {
        $provincias = DB::table('provincia')
            ->select('id', 'nombre', 'codigo', 'jurisdiccion')
            ->orderBy('nombre')
            ->get();

        $cobertura = [];

        foreach ($provincias as $provincia) {
            $vigencias = DB::table('padron_iibb_tasa')
                ->where('provincia_id', $provincia->id)
                ->selectRaw('min(desdefecha) as primera, max(desdefecha) as ultima')
                ->first();

            if ($vigencias === null || $vigencias->ultima === null) {
                continue;
            }

            $ultimoPeriodo = DB::table('padron_iibb_tasa')
                ->where('provincia_id', $provincia->id)
                ->where('desdefecha', $vigencias->ultima)
                ->selectRaw('max(hastafecha) as hastafecha, count(*) as filas')
                ->first();

            $cobertura[] = [
                'provincia_id' => (int) $provincia->id,
                'provincia' => $provincia->nombre,
                'codigo' => $provincia->codigo,
                'jurisdiccion' => $provincia->jurisdiccion,
                'desdefecha' => $vigencias->ultima,
                'hastafecha' => $ultimoPeriodo->hastafecha ?? null,
                'filas' => (int) ($ultimoPeriodo->filas ?? 0),
                'primera_vigencia' => $vigencias->primera,
            ];
        }

        return $cobertura;
    }
}
