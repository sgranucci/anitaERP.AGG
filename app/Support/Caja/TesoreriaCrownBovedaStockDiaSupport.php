<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownBovedaArqueo;
use App\Models\Caja\TesoreriaCrownBovedaStockDia;
use App\Models\Caja\TesoreriaCrownBovedaStockDiaLinea;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot del arqueo final de bóveda del día (equivalente a rendmbill origen A).
 * Lo alimenta el import Anita o el último arqueo de turno conforme del ERP.
 */
final class TesoreriaCrownBovedaStockDiaSupport
{
    public static function tablasListas(): bool
    {
        return Schema::hasTable('tesoreria_crown_boveda_stock_dia')
            && Schema::hasTable('tesoreria_crown_boveda_stock_dia_linea');
    }

    /**
     * @return array<int, float>|null valor_id => cantidad
     */
    public static function cantidadesDeJornada(TesoreriaCrownJornada $jornada): ?array
    {
        if (! self::tablasListas()) {
            return null;
        }
        $stock = TesoreriaCrownBovedaStockDia::query()
            ->with('lineas')
            ->where('jornada_id', $jornada->id)
            ->first();
        if ($stock === null) {
            return null;
        }
        $out = [];
        foreach ($stock->lineas as $linea) {
            $cant = (float) $linea->cantidad;
            if (abs($cant) < 0.0001) {
                continue;
            }
            $out[(int) $linea->valor_id] = $cant;
        }

        return $out;
    }

    /**
     * Último arqueo de turno conforme de la jornada → cantidades `actual`.
     *
     * @return array<int, float>
     */
    public static function cantidadesDesdeArqueoConforme(TesoreriaCrownJornada $jornada): array
    {
        $arqueo = TesoreriaCrownBovedaArqueo::query()
            ->with('lineas')
            ->where('jornada_id', $jornada->id)
            ->where('estado', TesoreriaCrownBovedaArqueo::CONFORME)
            ->orderByDesc('corte_at')
            ->orderByDesc('id')
            ->first();
        if ($arqueo === null) {
            return [];
        }
        $out = [];
        foreach ($arqueo->lineas as $linea) {
            $cant = (float) $linea->actual;
            if (abs($cant) < 0.0001) {
                continue;
            }
            $out[(int) $linea->valor_id] = ($out[(int) $linea->valor_id] ?? 0.0) + $cant;
        }

        return $out;
    }

    /**
     * @param  array<int, float>  $cantidades  valor_id => cantidad
     */
    public static function persistir(
        TesoreriaCrownJornada $jornada,
        array $cantidades,
        string $fuente = TesoreriaCrownBovedaStockDia::FUENTE_ERP
    ): TesoreriaCrownBovedaStockDia {
        return DB::transaction(function () use ($jornada, $cantidades, $fuente) {
            $stock = TesoreriaCrownBovedaStockDia::query()->firstOrNew([
                'jornada_id' => $jornada->id,
            ]);
            $stock->fill([
                'sala_id' => $jornada->sala_id,
                'empresa_id' => $jornada->empresa_id,
                'fuente' => $fuente,
            ])->save();

            EloquentAuditDeleteSupport::each(
                TesoreriaCrownBovedaStockDiaLinea::query()->where('stock_dia_id', $stock->id)
            );

            foreach ($cantidades as $valorId => $cantidad) {
                $cant = round((float) $cantidad, 2);
                if (abs($cant) < 0.0001) {
                    continue;
                }
                TesoreriaCrownBovedaStockDiaLinea::query()->create([
                    'stock_dia_id' => $stock->id,
                    'valor_id' => (int) $valorId,
                    'cantidad' => $cant,
                ]);
            }

            TesoreriaCrownAnexo8Calculo::invalidarCache($jornada);

            return $stock->fresh(['lineas']);
        });
    }

    /** Al quedar conforme un arqueo de turno, congela el stock del día para el VIII. */
    public static function sincronizarDesdeArqueo(TesoreriaCrownBovedaArqueo $arqueo): void
    {
        if (! self::tablasListas() || ! $arqueo->estaConforme()) {
            return;
        }
        $arqueo->loadMissing(['lineas', 'jornada']);
        $jornada = $arqueo->jornada;
        if ($jornada === null) {
            return;
        }
        $cantidades = [];
        foreach ($arqueo->lineas as $linea) {
            $cant = (float) $linea->actual;
            if (abs($cant) < 0.0001) {
                continue;
            }
            $cantidades[(int) $linea->valor_id] = ($cantidades[(int) $linea->valor_id] ?? 0.0) + $cant;
        }
        self::persistir($jornada, $cantidades, TesoreriaCrownBovedaStockDia::FUENTE_ERP);
    }
}
