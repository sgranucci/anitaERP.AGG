<?php

namespace App\Support\Contable;

use App\Models\Caja\Flash\FlashCaja;
use App\Models\Caja\RendicionMaquina;
use App\Models\Configuracion\Empresa;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Concilia rendiciones máquinas (turno C) vs flash_caja win_ol_slot + win_ol_rul.
 */
final class CierreRendicionMaquinaConciliacionFlashSupport
{
    private const TOLERANCIA_DEFAULT = 0.02;

    /**
     * @return array{
     *   empresa_id: int,
     *   empresa_nombre: string,
     *   empresa_codigo_anita: int,
     *   fecha_desde: string,
     *   fecha_hasta: string,
     *   tolerancia: float,
     *   dias: list<array<string, mixed>>,
     *   resumen: array{
     *     total_dias: int,
     *     dias_ok: int,
     *     dias_dif: int,
     *     total_pendiente_cierre: int,
     *     total_grupos_pendientes: int,
     *     jornadas_con_pendientes: int
     *   }
     * }
     */
    public function conciliar(int $empresaId, string $fechaDesde, string $fechaHasta, ?float $tolerancia = null): array
    {
        $empresa = Empresa::query()->findOrFail($empresaId);
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $tolerancia = $tolerancia ?? (float) config(
            'rendicion_maquina_anita.cierre_rendicion_contable.conciliacion_flash_tolerancia',
            self::TOLERANCIA_DEFAULT,
        );

        $rendiciones = $this->cargarRendiciones($empresaId, $desde, $hasta);
        $flashPorFecha = $this->cargarFlashPorFecha($empresaId, $desde, $hasta);

        $dias = [];
        $diasOk = 0;
        $diasDif = 0;
        $totalPendienteCierre = 0;
        $totalGruposPendientes = 0;
        $jornadasConPendientes = 0;

        foreach (CarbonPeriod::create($desde, $hasta) as $fecha) {
            $fechaStr = $fecha->toDateString();
            $dia = $this->armarDia($empresaId, $fechaStr, $rendiciones, $flashPorFecha, $tolerancia);
            if ($dia['cantidad_rendiciones'] <= 0 && abs($dia['total_flash']) <= $tolerancia) {
                continue;
            }
            $dias[] = $dia;
            if (($dia['estado'] ?? '') === 'OK') {
                $diasOk++;
            } elseif (($dia['estado'] ?? '') === 'DIF') {
                $diasDif++;
            }
            $pendDia = (int) ($dia['cantidad_pendiente'] ?? 0);
            $gruposDia = (int) ($dia['cantidad_grupos_pendientes'] ?? 0);
            $totalPendienteCierre += $pendDia;
            $totalGruposPendientes += $gruposDia;
            if ($pendDia > 0) {
                $jornadasConPendientes++;
            }
        }

        return [
            'empresa_id' => $empresaId,
            'empresa_nombre' => (string) ($empresa->nombre ?? ''),
            'empresa_codigo_anita' => (int) ($empresa->codigo ?? 0),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'tolerancia' => $tolerancia,
            'dias' => $dias,
            'resumen' => [
                'total_dias' => count($dias),
                'dias_ok' => $diasOk,
                'dias_dif' => $diasDif,
                'total_pendiente_cierre' => $totalPendienteCierre,
                'total_grupos_pendientes' => $totalGruposPendientes,
                'jornadas_con_pendientes' => $jornadasConPendientes,
            ],
        ];
    }

    /**
     * @return Collection<int, RendicionMaquina>
     */
    private function cargarRendiciones(int $empresaId, string $desde, string $hasta): Collection
    {
        return RendicionMaquina::query()
            ->with([
                'empresa:id,nombre',
                'asiento:id,numeroasiento,fecha',
            ])
            ->where('empresa_id', $empresaId)
            ->where('turno', CierreRendicionMaquinaGrupoSupport::TURNO_CIERRE)
            ->where('estado', RendicionMaquina::ESTADO_CONFIRMADA)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, array{flash: float, slot: float, ruleta: float, validado: bool}>
     */
    private function cargarFlashPorFecha(int $empresaId, string $desde, string $hasta): array
    {
        $out = [];
        FlashCaja::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->get(['fecha', 'win_ol_slot', 'win_ol_rul', 'validado'])
            ->each(function (FlashCaja $flash) use (&$out) {
                $fecha = $flash->fecha?->format('Y-m-d');
                if ($fecha === null || $fecha === '') {
                    return;
                }
                $slot = round((float) ($flash->win_ol_slot ?? 0), 2);
                $ruleta = round((float) ($flash->win_ol_rul ?? 0), 2);
                $out[$fecha] = [
                    'slot' => $slot,
                    'ruleta' => $ruleta,
                    'flash' => round($slot + $ruleta, 2),
                    'validado' => $flash->estaValidado(),
                ];
            });

        return $out;
    }

    /**
     * @param  Collection<int, RendicionMaquina>  $rendiciones
     * @param  array<string, array{flash: float, slot: float, ruleta: float, validado?: bool}>  $flashPorFecha
     * @return array<string, mixed>
     */
    private function armarDia(
        int $empresaId,
        string $fechaDia,
        Collection $rendiciones,
        array $flashPorFecha,
        float $tolerancia,
    ): array {
        $rendicionesDia = $rendiciones->filter(
            fn (RendicionMaquina $r) => CierreRendicionMaquinaGrupoSupport::fechaDiaDesdeRendicion($r) === $fechaDia,
        );

        $totales = CierreRendicionMaquinaTotalesSupport::calcular(
            new EloquentCollection($rendicionesDia->values()->all()),
            $empresaId,
            $fechaDia,
        );

        $flashSlot = round((float) ($flashPorFecha[$fechaDia]['slot'] ?? $totales['maquinas_online'] ?? 0), 2);
        $flashRuleta = round((float) ($flashPorFecha[$fechaDia]['ruleta'] ?? $totales['ruletas_online'] ?? 0), 2);
        $totalFlash = round((float) ($flashPorFecha[$fechaDia]['flash'] ?? ($flashSlot + $flashRuleta)), 2);

        $rendicionOnline = round((float) ($totales['resultado_online'] ?? 0), 2);
        $rendicionReal = round((float) ($totales['resultado_real'] ?? 0), 2);
        $diferenciaFlashRendicion = round($rendicionOnline - $totalFlash, 2);
        $diferenciaRealOnline = round($rendicionReal - $rendicionOnline, 2);

        $cantidad = $rendicionesDia->count();
        $cantidadPendiente = $rendicionesDia
            ->filter(fn (RendicionMaquina $r) => CierreRendicionMaquinaGrupoSupport::puedeCerrarContablemente($r))
            ->count();
        $cantidadCerrada = $rendicionesDia
            ->filter(fn (RendicionMaquina $r) => CierreRendicionMaquinaGrupoSupport::tieneCierreContable($r))
            ->count();

        $gruposPendientes = CierreRendicionMaquinaGrupoSupport::agrupar(
            new EloquentCollection(
                $rendicionesDia
                    ->filter(fn (RendicionMaquina $r) => CierreRendicionMaquinaGrupoSupport::puedeCerrarContablemente($r))
                    ->values()
                    ->all(),
            ),
        );

        $estadoCierre = $cantidadPendiente === 0 && $cantidadCerrada > 0
            ? CierreRendicionMaquinaGrupoSupport::ESTADO_CERRADA
            : ($cantidadCerrada > 0
                ? CierreRendicionMaquinaGrupoSupport::ESTADO_PARCIAL
                : CierreRendicionMaquinaGrupoSupport::ESTADO_PENDIENTE);

        $sinActividad = $cantidad === 0 && abs($totalFlash) <= $tolerancia;
        $estado = $sinActividad
            ? '—'
            : (abs($diferenciaFlashRendicion) <= $tolerancia ? 'OK' : 'DIF');

        return [
            'fecha' => $fechaDia,
            'fecha_fmt' => Carbon::parse($fechaDia)->format('d/m/Y'),
            'cantidad_rendiciones' => $cantidad,
            'cantidad_pendiente' => $cantidadPendiente,
            'cantidad_cerrada' => $cantidadCerrada,
            'cantidad_grupos_pendientes' => count($gruposPendientes),
            'estado_cierre' => $estadoCierre,
            'total_flash' => $totalFlash,
            'flash_slot' => $flashSlot,
            'flash_ruleta' => $flashRuleta,
            'rendicion_online' => $rendicionOnline,
            'rendicion_maquinas_online' => round((float) ($totales['maquinas_online'] ?? 0), 2),
            'rendicion_ruletas_online' => round((float) ($totales['ruletas_online'] ?? 0), 2),
            'rendicion_real' => $rendicionReal,
            'rendicion_maquinas_real' => round((float) ($totales['maquinas_real'] ?? 0), 2),
            'rendicion_ruletas_real' => round((float) ($totales['ruletas_real'] ?? 0), 2),
            'diferencia_flash_rendicion' => $diferenciaFlashRendicion,
            'diferencia_real_online' => $diferenciaRealOnline,
            'flash_validado' => ! empty($flashPorFecha[$fechaDia]['validado']),
            'estado' => $estado,
            'rendicion_ids' => $rendicionesDia->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        ];
    }
}
