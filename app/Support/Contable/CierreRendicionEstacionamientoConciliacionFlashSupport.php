<?php

namespace App\Support\Contable;

use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Support\Contable\CierreRendicionEstacionamientoGrupoSupport;
use App\Support\Ventas\Gastronomia\GastronomiaControlFlashSupport;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Concilia rendiciones estacionamiento (caja ERP) día a día vs flash_estac (Informix caja).
 */
final class CierreRendicionEstacionamientoConciliacionFlashSupport
{
    private const TOLERANCIA_DEFAULT = 0.02;

    public function __construct(
        private readonly GastronomiaControlFlashSupport $flashSupport,
    ) {
    }

    /**
     * @return array{
     *   empresa_id: int,
     *   empresa_nombre: string,
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
            'estacionamiento.cierre_rendicion_contable.conciliacion_flash_tolerancia',
            self::TOLERANCIA_DEFAULT,
        );

        $rendiciones = $this->cargarRendiciones($empresaId, $desde, $hasta);
        $flashPorFecha = $this->cargarFlashEstacPorFecha((int) $empresa->codigo, $desde, $hasta);

        $dias = [];
        $diasOk = 0;
        $diasDif = 0;
        $totalPendienteCierre = 0;
        $totalGruposPendientes = 0;
        $jornadasConPendientes = 0;

        foreach (CarbonPeriod::create($desde, $hasta) as $fecha) {
            $fechaStr = $fecha->toDateString();
            $dia = $this->armarDia($fechaStr, $rendiciones, $flashPorFecha, $tolerancia);
            if ($dia['cantidad_rendiciones'] <= 0 && abs($dia['total_flash_estac']) <= $tolerancia) {
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
     * @return Collection<int, RendicionEstacionamientoCaja>
     */
    private function cargarRendiciones(int $empresaId, string $desde, string $hasta): Collection
    {
        return RendicionEstacionamientoCaja::query()
            ->with([
                'puntoventaCae:id,codigo,nombre',
                'puntoventaCaea:id,codigo,nombre',
                'turnoOperativo.jornada:id,fecha_jornada',
                'asiento:id,numeroasiento,fecha',
                'asiento.asiento_movimientos:id,asiento_id,monto',
            ])
            ->where('empresa_id', $empresaId)
            ->where(function ($q) {
                $q->where('tipo', RendicionEstacionamientoCaja::TIPO_TURNO)
                    ->orWhereNull('tipo')
                    ->orWhere('tipo', '');
            })
            ->where(function ($w) use ($desde, $hasta) {
                $w->whereHas('turnoOperativo.jornada', function ($j) use ($desde, $hasta) {
                    $j->whereDate('fecha_jornada', '>=', $desde)
                        ->whereDate('fecha_jornada', '<=', $hasta);
                })->orWhere(function ($q) use ($desde, $hasta) {
                    $q->whereDoesntHave('turnoOperativo.jornada')
                        ->whereDate('fecharendicion', '>=', $desde)
                        ->whereDate('fecharendicion', '<=', $hasta);
                });
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, float> Y-m-d => flash_estac
     */
    private function cargarFlashEstacPorFecha(int $empresaCodigoAnita, string $desde, string $hasta): array
    {
        if ($empresaCodigoAnita <= 0) {
            return [];
        }

        try {
            $desglose = $this->flashSupport->desglosePorEmpresaJornada($desde, $hasta, [$empresaCodigoAnita]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('No se pudo leer flash (caja Anita): '.$e->getMessage(), 0, $e);
        }

        $porEmpresa = $desglose[$empresaCodigoAnita] ?? [];
        $out = [];
        foreach ($porEmpresa as $fecha => $partes) {
            $out[$fecha] = round((float) ($partes['flash_estac'] ?? 0), 2);
        }

        return $out;
    }

    /**
     * @param  Collection<int, RendicionEstacionamientoCaja>  $rendiciones
     * @param  array<string, float>  $flashPorFecha
     * @return array<string, mixed>
     */
    private function armarDia(
        string $fechaJornada,
        Collection $rendiciones,
        array $flashPorFecha,
        float $tolerancia,
    ): array {
        /** @var array<string, array{
         *   pv_codigo: string,
         *   pv_nombre: string,
         *   cantidad: int,
         *   total_cobrado: float,
         *   total_invitaciones: float,
         *   total_facturacion: float,
         *   total_asientos_debe: float,
         *   cantidad_asientos: int,
         *   cantidad_pendiente: int,
         *   cantidad_legacy: int,
         *   rendicion_ids: list<int>,
         *   asientos: list<array{asiento_id: int|null, numeroasiento: string, rendicion_id: int, total_debe: float, legacy: bool}>
         * }> $porPv */
        $porPv = [];
        $totalRendiciones = 0.0;
        $totalInvitaciones = 0.0;
        $totalFacturacion = 0.0;
        $totalAsientos = 0.0;
        $cantidad = 0;
        $cantidadAsientos = 0;
        $cantidadPendiente = 0;
        $cantidadLegacy = 0;

        foreach ($rendiciones as $r) {
            $fecha = $r->turnoOperativo?->jornada?->fecha_jornada?->format('Y-m-d')
                ?? $r->fecharendicion?->format('Y-m-d');
            if ($fecha !== $fechaJornada) {
                continue;
            }

            $pv = $r->puntoventaCae ?? $r->puntoventaCaea;
            $codigo = trim((string) ($pv?->codigo ?? ''));
            if ($codigo === '') {
                $codigo = '—';
            }
            $nombre = trim((string) ($pv?->nombre ?? ''));
            $key = $codigo.'|'.$nombre;

            if (! isset($porPv[$key])) {
                $porPv[$key] = [
                    'pv_codigo' => $codigo,
                    'pv_nombre' => $nombre !== '' ? $nombre : $codigo,
                    'cantidad' => 0,
                    'total_cobrado' => 0.0,
                    'total_invitaciones' => 0.0,
                    'total_facturacion' => 0.0,
                    'total_asientos_debe' => 0.0,
                    'cantidad_asientos' => 0,
                    'cantidad_pendiente' => 0,
                    'cantidad_legacy' => 0,
                    'rendicion_ids' => [],
                    'asientos' => [],
                ];
            }

            $cobrado = round((float) ($r->totalcobrado ?? 0), 2);
            $invitacion = round((float) ($r->totalinvitacion ?? 0), 2);
            $facturacion = round((float) ($r->totalfactura ?? 0), 2);
            if ($facturacion <= 0.0 && ($cobrado > 0.0 || $invitacion > 0.0)) {
                $facturacion = round($cobrado + $invitacion, 2);
            }
            $porPv[$key]['cantidad']++;
            $porPv[$key]['total_cobrado'] = round($porPv[$key]['total_cobrado'] + $cobrado, 2);
            $porPv[$key]['total_invitaciones'] = round($porPv[$key]['total_invitaciones'] + $invitacion, 2);
            $porPv[$key]['total_facturacion'] = round($porPv[$key]['total_facturacion'] + $facturacion, 2);
            $porPv[$key]['rendicion_ids'][] = (int) $r->id;
            $totalRendiciones = round($totalRendiciones + $cobrado, 2);
            $totalInvitaciones = round($totalInvitaciones + $invitacion, 2);
            $totalFacturacion = round($totalFacturacion + $facturacion, 2);
            $cantidad++;

            $legacy = $r->esCierreContableLegacy();
            $asientoId = (int) ($r->asiento_id ?? 0);
            $totalDebe = $legacy ? 0.0 : $this->totalDebeAsiento($r->asiento);

            if ($legacy) {
                $porPv[$key]['cantidad_legacy']++;
                $cantidadLegacy++;
                $porPv[$key]['asientos'][] = [
                    'asiento_id' => null,
                    'numeroasiento' => 'Histórico',
                    'rendicion_id' => (int) $r->id,
                    'total_debe' => 0.0,
                    'legacy' => true,
                ];
            } elseif ($asientoId > 0 && $r->asiento !== null) {
                $porPv[$key]['cantidad_asientos']++;
                $porPv[$key]['total_asientos_debe'] = round($porPv[$key]['total_asientos_debe'] + $totalDebe, 2);
                $totalAsientos = round($totalAsientos + $totalDebe, 2);
                $cantidadAsientos++;
                $porPv[$key]['asientos'][] = [
                    'asiento_id' => $asientoId,
                    'numeroasiento' => (string) ($r->asiento->numeroasiento ?? '#'.$asientoId),
                    'rendicion_id' => (int) $r->id,
                    'total_debe' => $totalDebe,
                    'legacy' => false,
                ];
            } else {
                $porPv[$key]['cantidad_pendiente']++;
                $cantidadPendiente++;
            }
        }

        $puntosVenta = array_values($porPv);
        usort($puntosVenta, static fn (array $a, array $b): int => strcmp($a['pv_codigo'], $b['pv_codigo']));

        $flashEstac = round((float) ($flashPorFecha[$fechaJornada] ?? 0), 2);
        $diferenciaCobradoFlash = round($totalRendiciones - $flashEstac, 2);
        $diferencia = round($totalFacturacion - $flashEstac, 2);
        $diferenciaAsientos = round($totalRendiciones - $totalAsientos, 2);

        $sinActividad = $cantidad === 0 && abs($flashEstac) <= $tolerancia;
        $estado = $sinActividad
            ? '—'
            : (abs($diferencia) <= $tolerancia ? 'OK' : 'DIF');

        $rendicionesPendientesDia = $rendiciones->filter(function (RendicionEstacionamientoCaja $r) use ($fechaJornada) {
            $fecha = $r->turnoOperativo?->jornada?->fecha_jornada?->format('Y-m-d')
                ?? $r->fecharendicion?->format('Y-m-d');

            return $fecha === $fechaJornada && $r->puedeCerrarContablemente();
        });
        $gruposPendientes = CierreRendicionEstacionamientoGrupoSupport::agrupar(
            new EloquentCollection($rendicionesPendientesDia->values()->all()),
        );

        return [
            'fecha_jornada' => $fechaJornada,
            'fecha_jornada_fmt' => Carbon::parse($fechaJornada)->format('d/m/Y'),
            'puntos_venta' => $puntosVenta,
            'cantidad_rendiciones' => $cantidad,
            'cantidad_asientos' => $cantidadAsientos,
            'cantidad_pendiente' => $cantidadPendiente,
            'cantidad_legacy' => $cantidadLegacy,
            'cantidad_grupos_pendientes' => count($gruposPendientes),
            'total_rendiciones_cobrado' => $totalRendiciones,
            'total_rendiciones_invitaciones' => $totalInvitaciones,
            'total_rendiciones_facturacion' => $totalFacturacion,
            'total_asientos_debe' => $totalAsientos,
            'total_flash_estac' => $flashEstac,
            'diferencia_cobrado_flash' => $diferenciaCobradoFlash,
            'diferencia' => $diferencia,
            'diferencia_rend_asientos' => $diferenciaAsientos,
            'estado' => $estado,
        ];
    }

    private function totalDebeAsiento(?Asiento $asiento): float
    {
        if ($asiento === null) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($asiento->asiento_movimientos ?? [] as $mov) {
            $monto = (float) ($mov->monto ?? 0);
            if ($monto > 0) {
                $total = round($total + $monto, 2);
            }
        }

        return $total;
    }
}
