<?php

namespace App\Support\Caja\Bingo;

use App\Models\Caja\Bingo\BingoPozoAcumulado;
use App\Models\Caja\Bingo\RendicionBingoCaja;
use App\Support\Contable\CierreRendicionBingoConcbingoSupport;
use App\Support\Contable\CierreRendicionBingoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;

/**
 * Persistencia del SI pozo acumulado bingo (reemplazo ERP de pozoacum Anita).
 *
 * - Cada fila es el Evol. SI Pozo AC al cierre de un día con rendición.
 * - Semilla al inicio de fecha D = último importe con fecha < D.
 */
final class BingoPozoAcumuladoSupport
{
    /**
     * Pozo AC al comenzar el día/período ($fecha inclusive aún no procesada).
     */
    public static function semillaAlInicioDe(int $empresaId, string $fecha): float
    {
        $fecha = Carbon::parse($fecha)->toDateString();

        $prev = BingoPozoAcumulado::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', '<', $fecha)
            ->orderByDesc('fecha')
            ->value('importe');

        if ($prev !== null) {
            return round((float) $prev, 2);
        }

        $semillas = config('bingo.cierre_rendicion_contable.pozo_acumulado_semilla_por_empresa', []);
        if (is_array($semillas) && array_key_exists($empresaId, $semillas)) {
            return round((float) $semillas[$empresaId], 2);
        }

        return 0.0;
    }

    /**
     * Calcula y persiste el SI al cierre del día (tras evolucionar desde la semilla previa).
     */
    public static function registrarCierreDia(
        int $empresaId,
        string $fechaDia,
        ?string $origen = null,
        ?int $usuarioId = null,
    ): float {
        $fechaDia = Carbon::parse($fechaDia)->toDateString();
        $rendiciones = RendicionBingoCaja::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaDia)
            ->orderBy('id')
            ->get();

        if ($rendiciones->isEmpty()) {
            return self::semillaAlInicioDe($empresaId, $fechaDia);
        }

        $concbIndex = CierreRendicionBingoConcbingoSupport::indicePorConcepto($empresaId);
        $totalesDia = CierreRendicionBingoTotalesSupport::calcular(
            new EloquentCollection($rendiciones->all()),
            $empresaId,
            $fechaDia,
        );
        $acum = is_array($totalesDia['acum_concepto'] ?? null) ? $totalesDia['acum_concepto'] : [];
        $recaudacion = round((float) ($totalesDia['tot_recaudacion'] ?? 0), 2);

        $pozo = CierreRendicionBingoTotalesSupport::evolSiPozoAc(
            self::semillaAlInicioDe($empresaId, $fechaDia),
            $recaudacion,
            $concbIndex,
            $acum,
        );

        BingoPozoAcumulado::query()->updateOrCreate(
            [
                'empresa_id' => $empresaId,
                'fecha' => $fechaDia,
            ],
            [
                'importe' => $pozo,
                'origen' => $origen ?? BingoPozoAcumulado::ORIGEN_CIERRE,
                'usuario_id' => $usuarioId ?? Auth::id(),
            ],
        );

        return $pozo;
    }

    /**
     * Borra el SI del día y los posteriores (al anular un cierre).
     */
    public static function borrarDesdeFecha(int $empresaId, string $fechaDesde): int
    {
        $fechaDesde = Carbon::parse($fechaDesde)->toDateString();

        return (int) BingoPozoAcumulado::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', '>=', $fechaDesde)
            ->where('origen', '!=', BingoPozoAcumulado::ORIGEN_SEMILLA_ANITA)
            ->delete();
    }

    /**
     * Recalcula y persiste SI de todos los días con rendición desde $fechaDesde (inclusive).
     * Útil tras sembrar o corregir.
     *
     * @return list<array{fecha: string, importe: float}>
     */
    public static function recalcularDesde(int $empresaId, string $fechaDesde, ?string $fechaHasta = null): array
    {
        $fechaDesde = Carbon::parse($fechaDesde)->toDateString();
        $fechaHasta = $fechaHasta !== null
            ? Carbon::parse($fechaHasta)->toDateString()
            : Carbon::parse($fechaDesde)->endOfMonth()->toDateString();

        self::borrarDesdeFecha($empresaId, $fechaDesde);

        $fechas = RendicionBingoCaja::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', '>=', $fechaDesde)
            ->whereDate('fecha_jornada', '<=', $fechaHasta)
            ->orderBy('fecha_jornada')
            ->pluck('fecha_jornada')
            ->map(function ($f) {
                if ($f instanceof Carbon) {
                    return $f->toDateString();
                }

                return Carbon::parse((string) $f)->toDateString();
            })
            ->unique()
            ->values();

        $out = [];
        foreach ($fechas as $fecha) {
            // Solo días que ya tienen cierre contable completo, o todos si se fuerza recalculo operativo.
            $importe = self::registrarCierreDia(
                $empresaId,
                $fecha,
                BingoPozoAcumulado::ORIGEN_CIERRE,
            );
            $out[] = ['fecha' => $fecha, 'importe' => $importe];
        }

        return $out;
    }

    /**
     * @param  EloquentCollection<int, RendicionBingoCaja>  $rendicionesDia
     */
    public static function evolDelDiaDesdeSemilla(
        int $empresaId,
        string $fechaDia,
        EloquentCollection $rendicionesDia,
    ): float {
        if ($rendicionesDia->isEmpty()) {
            return self::semillaAlInicioDe($empresaId, $fechaDia);
        }

        $concbIndex = CierreRendicionBingoConcbingoSupport::indicePorConcepto($empresaId);
        $totalesDia = CierreRendicionBingoTotalesSupport::calcular(
            $rendicionesDia,
            $empresaId,
            $fechaDia,
        );
        $acum = is_array($totalesDia['acum_concepto'] ?? null) ? $totalesDia['acum_concepto'] : [];

        return CierreRendicionBingoTotalesSupport::evolSiPozoAc(
            self::semillaAlInicioDe($empresaId, $fechaDia),
            round((float) ($totalesDia['tot_recaudacion'] ?? 0), 2),
            $concbIndex,
            $acum,
        );
    }
}
