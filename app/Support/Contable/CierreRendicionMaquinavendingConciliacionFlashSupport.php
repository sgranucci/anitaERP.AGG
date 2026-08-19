<?php

namespace App\Support\Contable;

use App\Models\Caja\Flash\FlashCaja;
use App\Models\Caja\RendicionMaquinavendingCaja;
use App\Support\Caja\Flash\FlashCajaValidacionSupport;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Support\Contable\CierreRendicionMaquinavendingGrupoSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionVendingRendgSupport;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Concilia rendiciones vending (caja ERP) día a día contra:
 *  - flash vending (campo flash_caja.vending del módulo flash ERP; puede estar en 0 por ahora),
 *  - rendgastro Z del punto de venta (Anita, vía GastronomiaConciliacionVendingRendgSupport),
 *  - Σ debe de los asientos contables generados por el cierre.
 */
final class CierreRendicionMaquinavendingConciliacionFlashSupport
{
    private const TOLERANCIA_DEFAULT = 0.02;

    public function __construct(
        private readonly GastronomiaConciliacionVendingRendgSupport $vendingRendgSupport,
    ) {
    }

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
            'gastronomia.cierre_rendicion_vending_contable.conciliacion_flash_tolerancia',
            self::TOLERANCIA_DEFAULT,
        );

        $rendiciones = $this->cargarRendiciones($empresaId, $desde, $hasta);
        $flashPorFecha = $this->cargarFlashVendingPorFecha($empresaId, $desde, $hasta);
        $flashValidadoPorFecha = FlashCajaValidacionSupport::mapaValidadoPorFecha($empresaId, $desde, $hasta);
        $rendgastroPorFecha = $this->cargarRendgastroZPorFecha($empresaId, $desde, $hasta, $tolerancia);

        $dias = [];
        $diasOk = 0;
        $diasDif = 0;
        $totalPendienteCierre = 0;
        $totalGruposPendientes = 0;
        $jornadasConPendientes = 0;

        foreach (CarbonPeriod::create($desde, $hasta) as $fecha) {
            $fechaStr = $fecha->toDateString();
            $dia = $this->armarDia($fechaStr, $rendiciones, $flashPorFecha, $rendgastroPorFecha, $tolerancia, $flashValidadoPorFecha);
            if ($dia['cantidad_rendiciones'] <= 0
                && abs($dia['total_flash_vending']) <= $tolerancia
                && abs($dia['total_rendgastro_z']) <= $tolerancia) {
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
     * @return Collection<int, RendicionMaquinavendingCaja>
     */
    private function cargarRendiciones(int $empresaId, string $desde, string $hasta): Collection
    {
        return RendicionMaquinavendingCaja::query()
            ->with([
                'puntoventaCae:id,codigo,nombre',
                'puntoventaCaea:id,codigo,nombre',
                'maquinavendingRendicion:id,fecha_jornada',
                'asiento:id,numeroasiento,fecha',
                'asiento.asiento_movimientos:id,asiento_id,monto',
            ])
            ->where('empresa_id', $empresaId)
            ->where(function ($w) use ($desde, $hasta) {
                $w->whereHas('maquinavendingRendicion', function ($mr) use ($desde, $hasta) {
                    $mr->whereDate('fecha_jornada', '>=', $desde)
                        ->whereDate('fecha_jornada', '<=', $hasta);
                })->orWhere(function ($q) use ($desde, $hasta) {
                    $q->whereDoesntHave('maquinavendingRendicion')
                        ->whereDate('fecharendicion', '>=', $desde)
                        ->whereDate('fecharendicion', '<=', $hasta);
                });
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, float> Y-m-d => flash_caja.vending
     */
    private function cargarFlashVendingPorFecha(int $empresaId, string $desde, string $hasta): array
    {
        $out = [];
        FlashCaja::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->get(['fecha', 'vending'])
            ->each(function (FlashCaja $flash) use (&$out) {
                $fecha = $flash->fecha?->format('Y-m-d');
                if ($fecha !== null && $fecha !== '') {
                    $out[$fecha] = round((float) ($flash->vending ?? 0), 2);
                }
            });

        return $out;
    }

    /**
     * @return array<string, float> Y-m-d => rendgastro Z vending (Anita)
     */
    private function cargarRendgastroZPorFecha(int $empresaId, string $desde, string $hasta, float $tolerancia): array
    {
        $out = [];
        foreach (CarbonPeriod::create($desde, $hasta) as $fecha) {
            $fechaStr = $fecha->toDateString();
            try {
                $reporte = $this->vendingRendgSupport->filasReporte($empresaId, $fechaStr, $tolerancia, false);
                $z = round((float) ($reporte['totales']['rendgastro_z'] ?? 0), 2);
                if (abs($z) > 0.0) {
                    $out[$fechaStr] = $z;
                }
            } catch (\Throwable $e) {
                // Anita no disponible para ese día: se deja sin dato (0) sin abortar la conciliación.
            }
        }

        return $out;
    }

    /**
     * @param  Collection<int, RendicionMaquinavendingCaja>  $rendiciones
     * @param  array<string, float>  $flashPorFecha
     * @param  array<string, float>  $rendgastroPorFecha
     * @param  array<string, bool>  $flashValidadoPorFecha
     * @return array<string, mixed>
     */
    private function armarDia(
        string $fechaJornada,
        Collection $rendiciones,
        array $flashPorFecha,
        array $rendgastroPorFecha,
        float $tolerancia,
        array $flashValidadoPorFecha = [],
    ): array {
        /** @var array<string, array<string, mixed>> $porPv */
        $porPv = [];
        $totalCobrado = 0.0;
        $totalInvitaciones = 0.0;
        $totalFacturacion = 0.0;
        $totalNotasCredito = 0.0;
        $totalVentasBrutas = 0.0;
        $totalAsientos = 0.0;
        $cantidad = 0;
        $cantidadAsientos = 0;
        $cantidadPendiente = 0;
        $cantidadLegacy = 0;
        /** @var array<int, true> $asientosVistosDia */
        $asientosVistosDia = [];

        foreach ($rendiciones as $r) {
            $fecha = $r->maquinavendingRendicion?->fecha_jornada?->format('Y-m-d')
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
                    'total_notas_credito' => 0.0,
                    'total_ventas_brutas' => 0.0,
                    'total_asientos_debe' => 0.0,
                    'cantidad_asientos' => 0,
                    'cantidad_pendiente' => 0,
                    'cantidad_legacy' => 0,
                    'rendicion_ids' => [],
                    'asientos' => [],
                    'asientos_vistos' => [],
                ];
            }

            $cobrado = round((float) ($r->totalcobrado ?? 0), 2);
            $invitacion = round((float) ($r->totalinvitacion ?? 0), 2);
            $facturacion = round((float) ($r->totalfactura ?? 0), 2);
            $notasCredito = round((float) ($r->totalnotacredito ?? 0), 2);
            if ($facturacion <= 0.0 && ($cobrado > 0.0 || $invitacion > 0.0)) {
                $facturacion = round($cobrado + $invitacion, 2);
            }
            $ventasBrutas = round($facturacion + $notasCredito, 2);
            $porPv[$key]['cantidad']++;
            $porPv[$key]['total_cobrado'] = round($porPv[$key]['total_cobrado'] + $cobrado, 2);
            $porPv[$key]['total_invitaciones'] = round($porPv[$key]['total_invitaciones'] + $invitacion, 2);
            $porPv[$key]['total_facturacion'] = round($porPv[$key]['total_facturacion'] + $facturacion, 2);
            $porPv[$key]['total_notas_credito'] = round($porPv[$key]['total_notas_credito'] + $notasCredito, 2);
            $porPv[$key]['total_ventas_brutas'] = round($porPv[$key]['total_ventas_brutas'] + $ventasBrutas, 2);
            $porPv[$key]['rendicion_ids'][] = (int) $r->id;
            $totalCobrado = round($totalCobrado + $cobrado, 2);
            $totalInvitaciones = round($totalInvitaciones + $invitacion, 2);
            $totalFacturacion = round($totalFacturacion + $facturacion, 2);
            $totalNotasCredito = round($totalNotasCredito + $notasCredito, 2);
            $totalVentasBrutas = round($totalVentasBrutas + $ventasBrutas, 2);
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
                if (! isset($asientosVistosDia[$asientoId])) {
                    $asientosVistosDia[$asientoId] = true;
                    $totalAsientos = round($totalAsientos + $totalDebe, 2);
                    $cantidadAsientos++;
                }
                if (! isset($porPv[$key]['asientos_vistos'][$asientoId])) {
                    $porPv[$key]['asientos_vistos'][$asientoId] = true;
                    $porPv[$key]['cantidad_asientos']++;
                    $porPv[$key]['total_asientos_debe'] = round($porPv[$key]['total_asientos_debe'] + $totalDebe, 2);
                    $porPv[$key]['asientos'][] = [
                        'asiento_id' => $asientoId,
                        'numeroasiento' => (string) ($r->asiento->numeroasiento ?? '#'.$asientoId),
                        'rendicion_id' => (int) $r->id,
                        'total_debe' => $totalDebe,
                        'legacy' => false,
                    ];
                }
            } else {
                $porPv[$key]['cantidad_pendiente']++;
                $cantidadPendiente++;
            }
        }

        $puntosVenta = array_values($porPv);
        foreach ($puntosVenta as &$pvRow) {
            unset($pvRow['asientos_vistos']);
        }
        unset($pvRow);
        usort($puntosVenta, static fn (array $a, array $b): int => strcmp($a['pv_codigo'], $b['pv_codigo']));

        $flashVending = round((float) ($flashPorFecha[$fechaJornada] ?? 0), 2);
        $rendgastroZ = round((float) ($rendgastroPorFecha[$fechaJornada] ?? 0), 2);
        $diferenciaFlash = round($totalFacturacion - $flashVending, 2);
        $diferenciaRendgastro = round($totalFacturacion - $rendgastroZ, 2);
        $diferenciaCobradoFlash = round($totalCobrado - $flashVending, 2);
        $diferenciaAsientos = round($totalCobrado - $totalAsientos, 2);
        $diferenciaVentaTotalAsientos = round($totalVentasBrutas - $totalAsientos, 2);

        // Estado: comparar contra la fuente externa con dato (rendgastro Z primero, luego flash);
        // si ninguna tiene dato, contra Σ debe de asientos. Flash puede estar en 0 (aún sin fuente).
        if ($cantidad === 0 && abs($flashVending) <= $tolerancia && abs($rendgastroZ) <= $tolerancia) {
            $estado = '—';
        } else {
            if (abs($rendgastroZ) > $tolerancia) {
                $difPrimaria = $diferenciaRendgastro;
            } elseif (abs($flashVending) > $tolerancia) {
                $difPrimaria = $diferenciaFlash;
            } else {
                $difPrimaria = $diferenciaVentaTotalAsientos;
            }
            $estado = abs($difPrimaria) <= $tolerancia ? 'OK' : 'DIF';
        }

        $rendicionesPendientesDia = $rendiciones->filter(function (RendicionMaquinavendingCaja $r) use ($fechaJornada) {
            $fecha = $r->maquinavendingRendicion?->fecha_jornada?->format('Y-m-d')
                ?? $r->fecharendicion?->format('Y-m-d');

            return $fecha === $fechaJornada && $r->puedeCerrarContablemente();
        });
        $gruposPendientes = CierreRendicionMaquinavendingGrupoSupport::agrupar(
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
            'total_rendiciones_cobrado' => $totalCobrado,
            'total_rendiciones_invitaciones' => $totalInvitaciones,
            'total_rendiciones_facturacion' => $totalFacturacion,
            'total_rendiciones_notas_credito' => $totalNotasCredito,
            'total_rendiciones_ventas_brutas' => $totalVentasBrutas,
            'total_asientos_debe' => $totalAsientos,
            'total_flash_vending' => $flashVending,
            'flash_validado' => ! empty($flashValidadoPorFecha[$fechaJornada]),
            'total_rendgastro_z' => $rendgastroZ,
            'diferencia_cobrado_flash' => $diferenciaCobradoFlash,
            'diferencia' => $diferenciaFlash,
            'diferencia_rendgastro' => $diferenciaRendgastro,
            'diferencia_rend_asientos' => $diferenciaAsientos,
            'diferencia_venta_total_asientos' => $diferenciaVentaTotalAsientos,
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
