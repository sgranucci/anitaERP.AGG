<?php

namespace App\Services\Caja;

use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\Waitry\WaitryAnalyticsOrdenesService;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Cierre de jornada Waitry (tesorería): concilia getordersdetails vs ventas Anita por fechajornada.
 */
final class WaitryCierreJornadaService
{
    private const TOLERANCIA_MONTO = 0.02;

    public function __construct(
        private readonly WaitryAnalyticsOrdenesService $analyticsOrdenesService,
    ) {
    }

    /**
     * @return array{
     *     ok:bool,
     *     error?:string,
     *     empresa_id?:int,
     *     fecha_jornada?:string,
     *     fecha_jornada_fmt?:string,
     *     jornada?:array<string,mixed>|null,
     *     resumen?:array<string,mixed>,
     *     filas?:list<array<string,mixed>>
     * }
     */
    public function conciliar(int $empresaId, string $fechaJornada): array
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Debe seleccionar una empresa.');
        }

        $fechaJornada = $this->normalizarFecha($fechaJornada);
        $fechaFmt = $this->formatearFecha($fechaJornada);

        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->orderByDesc('id')
            ->first();

        $waitry = $this->analyticsOrdenesService->ordenesPorRangoFecha($empresaId, $fechaJornada, $fechaJornada);
        if (! ($waitry['ok'] ?? false)) {
            return [
                'ok' => false,
                'error' => $waitry['error'] ?? 'No se pudieron obtener órdenes Waitry.',
                'empresa_id' => $empresaId,
                'fecha_jornada' => $fechaJornada,
                'fecha_jornada_fmt' => $fechaFmt,
                'jornada' => $this->jornadaResumen($jornada),
            ];
        }

        $ordenesWaitry = $waitry['ordenes'] ?? [];
        $mapWaitry = [];
        foreach ($ordenesWaitry as $orden) {
            $id = (int) ($orden['orderId'] ?? $orden['id'] ?? 0);
            if ($id > 0) {
                $mapWaitry[$id] = $orden;
            }
        }

        $emisionesJornada = VentaGastronomiaEmision::query()
            ->with(['venta', 'cuenta'])
            ->whereNotNull('waitry_order_id')
            ->whereHas('venta', function ($q) use ($empresaId, $fechaJornada) {
                $q->whereDate('fechajornada', $fechaJornada)
                    ->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
            })
            ->get();

        $emisionesPorWaitry = VentaGastronomiaEmision::query()
            ->with(['venta', 'cuenta'])
            ->whereNotNull('waitry_order_id')
            ->whereHas('venta', fn ($q) => $q->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId)))
            ->get();

        $mapAnitaJornada = [];
        foreach ($emisionesJornada as $emision) {
            $wid = (int) ($emision->waitry_order_id ?? 0);
            if ($wid > 0) {
                $mapAnitaJornada[$wid] = $emision;
            }
        }

        $mapAnitaPorWaitry = [];
        foreach ($emisionesPorWaitry as $emision) {
            $wid = (int) ($emision->waitry_order_id ?? 0);
            if ($wid > 0) {
                $mapAnitaPorWaitry[$wid] = $emision;
            }
        }

        $cuentasPendientes = CuentaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('waitry_order_id')
            ->where('estado', CuentaGastronomia::ESTADO_ABIERTA)
            ->whereHas('lineas')
            ->get()
            ->keyBy(fn (CuentaGastronomia $c) => (int) $c->waitry_order_id);

        $filas = [];
        $idsProcesados = [];

        foreach ($mapWaitry as $orderId => $orden) {
            $idsProcesados[] = $orderId;
            $emision = $mapAnitaPorWaitry[$orderId] ?? null;
            $cuentaPend = $cuentasPendientes->get($orderId);
            $filas[] = $this->armarFila($orderId, $orden, $emision, $cuentaPend, $fechaJornada);
        }

        foreach ($mapAnitaJornada as $orderId => $emision) {
            if (in_array($orderId, $idsProcesados, true)) {
                continue;
            }
            $filas[] = $this->armarFilaSoloAnita($orderId, $emision);
        }

        usort($filas, static function (array $a, array $b): int {
            return ($b['waitry_order_id'] ?? 0) <=> ($a['waitry_order_id'] ?? 0);
        });

        $resumen = $this->calcularResumen($filas, $mapWaitry, $emisionesJornada);

        return [
            'ok' => true,
            'empresa_id' => $empresaId,
            'fecha_jornada' => $fechaJornada,
            'fecha_jornada_fmt' => $fechaFmt,
            'jornada' => $this->jornadaResumen($jornada),
            'resumen' => $resumen,
            'filas' => $filas,
        ];
    }

    /**
     * @param  array<string, mixed>  $orden
     * @return array<string, mixed>
     */
    private function armarFila(
        int $orderId,
        array $orden,
        ?VentaGastronomiaEmision $emision,
        ?CuentaGastronomia $cuentaPendiente,
        string $fechaJornadaConsulta,
    ): array {
        $waitryTotal = round((float) ($orden['totalAmount'] ?? 0), 2);
        $waitryPaid = $this->esPagadaWaitry($orden);
        $anitaTotal = $emision !== null ? round((float) ($emision->venta?->total ?? 0), 2) : null;
        $diferencia = $anitaTotal !== null ? round($waitryTotal - $anitaTotal, 2) : null;
        $anitaFechajornada = $this->fechaJornadaVenta($emision);
        $jornadaCoincide = $anitaFechajornada === null || $anitaFechajornada === $fechaJornadaConsulta;

        $estado = 'sin_factura_anita';
        if ($emision !== null) {
            if (! $jornadaCoincide) {
                $estado = abs((float) $diferencia) > self::TOLERANCIA_MONTO ? 'jornada_distinta_monto' : 'jornada_distinta';
            } else {
                $estado = abs((float) $diferencia) > self::TOLERANCIA_MONTO ? 'monto_distinto' : 'conciliada';
            }
        } elseif ($cuentaPendiente !== null) {
            $estado = 'importada_pendiente';
        }

        return [
            'waitry_order_id' => $orderId,
            'referencia_waitry' => trim((string) (
                $orden['display_id']
                ?? $orden['externalDeliveryId']
                ?? $orden['external_reference_id']
                ?? ''
            )),
            'hora_waitry' => $this->formatearHora($orden['placed_at'] ?? null),
            'waitry_paid' => $waitryPaid,
            'waitry_total' => $waitryTotal,
            'anita_venta_id' => $emision?->venta_id,
            'anita_codigo' => $emision?->venta?->codigo,
            'anita_fechajornada' => $anitaFechajornada,
            'anita_fechajornada_fmt' => $anitaFechajornada ? $this->formatearFecha($anitaFechajornada) : null,
            'anita_total' => $anitaTotal,
            'anita_totem' => (bool) ($emision?->cuenta?->waitry_cobro_totem ?? $cuentaPendiente?->waitry_cobro_totem),
            'cuenta_pendiente_id' => $cuentaPendiente?->id,
            'diferencia' => $diferencia,
            'estado' => $estado,
            'estado_label' => $this->etiquetaEstado($estado),
        ];
    }

    private function armarFilaSoloAnita(int $orderId, VentaGastronomiaEmision $emision): array
    {
        return [
            'waitry_order_id' => $orderId,
            'referencia_waitry' => '',
            'hora_waitry' => '',
            'waitry_paid' => null,
            'waitry_total' => null,
            'anita_venta_id' => $emision->venta_id,
            'anita_codigo' => $emision->venta?->codigo,
            'anita_total' => round((float) ($emision->venta?->total ?? 0), 2),
            'anita_totem' => (bool) ($emision->cuenta?->waitry_cobro_totem),
            'cuenta_pendiente_id' => null,
            'diferencia' => null,
            'estado' => 'solo_anita',
            'estado_label' => $this->etiquetaEstado('solo_anita'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<int, array<string, mixed>>  $mapWaitry
     * @param  \Illuminate\Support\Collection<int, VentaGastronomiaEmision>  $emisiones
     * @return array<string, mixed>
     */
    private function calcularResumen(array $filas, array $mapWaitry, $emisiones): array
    {
        $totalWaitry = 0.0;
        $totalWaitryPagado = 0.0;
        $totalAnita = 0.0;
        $conciliadas = 0;
        $sinFactura = 0;
        $importadasPendientes = 0;
        $montoDistinto = 0;
        $soloAnita = 0;

        $jornadaDistinta = 0;

        foreach ($filas as $f) {
            match ($f['estado']) {
                'conciliada' => $conciliadas++,
                'sin_factura_anita' => $sinFactura++,
                'importada_pendiente' => $importadasPendientes++,
                'monto_distinto' => $montoDistinto++,
                'jornada_distinta', 'jornada_distinta_monto' => $jornadaDistinta++,
                'solo_anita' => $soloAnita++,
                default => null,
            };
            if ($f['anita_total'] !== null) {
                $totalAnita += (float) $f['anita_total'];
            }
        }

        foreach ($mapWaitry as $orden) {
            $monto = round((float) ($orden['totalAmount'] ?? 0), 2);
            $totalWaitry += $monto;
            if ($this->esPagadaWaitry($orden)) {
                $totalWaitryPagado += $monto;
            }
        }

        return [
            'ordenes_waitry' => count($mapWaitry),
            'facturas_anita_waitry' => $emisiones->count(),
            'total_waitry' => round($totalWaitry, 2),
            'total_waitry_pagado' => round($totalWaitryPagado, 2),
            'total_anita_facturado' => round($totalAnita, 2),
            'diferencia_global' => round($totalWaitry - $totalAnita, 2),
            'conciliadas' => $conciliadas,
            'sin_factura_anita' => $sinFactura,
            'importadas_pendientes' => $importadasPendientes,
            'monto_distinto' => $montoDistinto,
            'solo_anita' => $soloAnita,
            'jornada_distinta' => $jornadaDistinta,
            'tiene_diferencias' => ($sinFactura + $importadasPendientes + $montoDistinto + $soloAnita + $jornadaDistinta) > 0,
        ];
    }

    private function fechaJornadaVenta(?VentaGastronomiaEmision $emision): ?string
    {
        $fecha = $emision?->venta?->fechajornada;
        if ($fecha === null || $fecha === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $fecha)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    private function esPagadaWaitry(array $orden): bool
    {
        if (array_key_exists('paid', $orden)) {
            return in_array($orden['paid'], [1, '1', true], true);
        }

        return false;
    }

    private function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            'conciliada' => 'Conciliada',
            'sin_factura_anita' => 'Waitry sin factura Anita',
            'importada_pendiente' => 'Importada, pendiente de facturar',
            'monto_distinto' => 'Diferencia de monto',
            'jornada_distinta' => 'Facturada Anita (otra jornada)',
            'jornada_distinta_monto' => 'Facturada Anita (otra jornada) + dif. monto',
            'solo_anita' => 'Facturada Anita, no en Waitry',
            default => $estado,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function jornadaResumen(?JornadaGastronomia $jornada): ?array
    {
        if ($jornada === null) {
            return null;
        }

        return [
            'id' => (int) $jornada->id,
            'estado' => (string) $jornada->estado,
            'fecha_jornada' => $jornada->fecha_jornada?->format('Y-m-d'),
            'apertura_en' => $jornada->apertura_en?->format('d/m/Y H:i'),
            'cierre_en' => $jornada->cierre_en?->format('d/m/Y H:i'),
        ];
    }

    private function normalizarFecha(string $fecha): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            throw new InvalidArgumentException('Debe indicar la fecha de jornada.');
        }

        try {
            return Carbon::parse($fecha)->format('Y-m-d');
        } catch (\Throwable) {
            throw new InvalidArgumentException('Fecha de jornada inválida.');
        }
    }

    private function formatearFecha(string $fechaYmd): string
    {
        try {
            return Carbon::parse($fechaYmd)->format('d/m/Y');
        } catch (\Throwable) {
            return $fechaYmd;
        }
    }

    private function formatearHora(mixed $placed): string
    {
        if ($placed === null || $placed === '') {
            return '';
        }

        try {
            return Carbon::parse((string) $placed)->format('H:i');
        } catch (\Throwable) {
            return (string) $placed;
        }
    }
}
