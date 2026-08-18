<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Models\Caja\RendicionGastronomiaCaja;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Models\Ventas\VentaGastronomiaNcOrigen;
use App\Services\Caja\RendicionGastronomiaJornadaPresentacionService;
use App\Support\Ventas\Gastronomia\RecalcularAsientoVentasMedioRealCierreJornadaSupport;
use App\Support\Ventas\GastronomiaTurnoOperativoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Reaplica en ERP los Z afectados por una FAC recuperada y su NC fiscal retroactiva.
 *
 * No sincroniza ventas, rendiciones ni asientos con Anita. La pareja debe netear tanto
 * facturación como cobranza; por eso los movimientos de tesorería ya presentados no cambian.
 */
final class GastronomiaAjusteFiscalZService
{
    private const TOLERANCIA = 0.02;

    public function __construct(
        private readonly RendicionGastronomiaJornadaPresentacionService $jornadaPresentacionService,
        private readonly RecalcularAsientoVentasMedioRealCierreJornadaSupport $recalcularAsientoSupport,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function actualizar(int $ventaFacturaId, int $ventaNotaCreditoId): array
    {
        [$factura, $notaCredito, $emisionFactura, $emisionNc] = $this->validarPareja(
            $ventaFacturaId,
            $ventaNotaCreditoId,
        );

        $empresaId = (int) $factura->puntoventas?->empresa_id;
        $fechaJornada = Carbon::parse($factura->fechajornada)->toDateString();
        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->firstOrFail();

        $cuenta = $emisionFactura->cuenta;
        $fechaImputacion = Carbon::parse($cuenta->created_at);
        $pc = (string) $emisionFactura->identificador_pc;
        $turno = TurnoOperativoGastronomia::query()
            ->where('jornada_gastronomia_id', $jornada->id)
            ->where('identificador_pc', $pc)
            ->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO)
            ->where('habilitacion_en', '<=', $fechaImputacion)
            ->where('cierre_en', '>=', $fechaImputacion)
            ->first();
        if ($turno === null) {
            throw new RuntimeException(
                'No se encontró el turno cerrado que contenía la cuenta #'.$cuenta->id
                .' ('.$fechaImputacion->format('Y-m-d H:i:s').').',
            );
        }

        return DB::transaction(function () use (
            $empresaId,
            $fechaJornada,
            $jornada,
            $turno,
            $pc,
        ): array {
            $totalesTurno = GastronomiaTurnoOperativoTotalesSupport::calcular(
                $pc,
                $empresaId,
                $fechaJornada,
                Carbon::parse($turno->habilitacion_en),
                Carbon::parse($turno->cierre_en),
            );
            $rendicionTurno = RendicionGastronomiaCaja::query()
                ->where('turno_operativo_gastronomia_id', $turno->id)
                ->first();
            $this->assertNetoPresentadoSinCambios($rendicionTurno, $totalesTurno, 'turno');

            // La pareja FAC/NC netea cero: el total del turno no cambia. No recalcular
            // monto_facturacion_dia porque es el acumulado histórico al momento del cierre.
            $turno->update([
                'monto_facturacion_turno' => round((float) $totalesTurno['total_general'], 2),
            ]);
            if ($rendicionTurno !== null) {
                $this->actualizarCabeceraRendicion($rendicionTurno, $totalesTurno);
            }

            $totalesJornada = GastronomiaTurnoOperativoTotalesSupport::calcularPorJornada($jornada);
            $rendicionJornada = RendicionGastronomiaCaja::query()
                ->where('jornada_gastronomia_id', $jornada->id)
                ->where('tipo', RendicionGastronomiaCaja::TIPO_JORNADA)
                ->first();
            $this->assertNetoPresentadoSinCambios($rendicionJornada, $totalesJornada, 'jornada');

            if ($rendicionJornada !== null) {
                $marcadores = $this->jornadaPresentacionService->resolverMarcadoresAuditoria($jornada);
                $this->actualizarCabeceraRendicion($rendicionJornada, $totalesJornada, [
                    'numeracion_comprobantes_json' => $marcadores['numeracion_comprobantes_json'] ?? null,
                    'waitry_order_id_hasta' => (int) ($marcadores['waitry_order_id_hasta'] ?? 0),
                    'cierre_totem_jornada_gastronomia_id' => $marcadores['cierre_totem_jornada_gastronomia_id'] ?? null,
                ]);
            }

            $asiento = $this->recalcularAsientoSupport->actualizarSiExiste(
                $empresaId,
                $fechaJornada,
                false,
            );

            return [
                'turno_id' => (int) $turno->id,
                'rendicion_turno_id' => $rendicionTurno?->id,
                'jornada_id' => (int) $jornada->id,
                'rendicion_jornada_id' => $rendicionJornada?->id,
                'total_facturas_turno' => round((float) $totalesTurno['total_facturas'], 2),
                'total_nc_turno' => round(abs((float) $totalesTurno['total_notas_credito']), 2),
                'neto_turno' => round((float) $totalesTurno['total_ventas'], 2),
                'total_facturas_jornada' => round((float) $totalesJornada['total_facturas'], 2),
                'total_nc_jornada' => round(abs((float) $totalesJornada['total_notas_credito']), 2),
                'neto_jornada' => round((float) $totalesJornada['total_ventas'], 2),
                'asiento_cierre' => $asiento,
                'anita_actualizado' => false,
            ];
        });
    }

    /**
     * @param  list<int>  $ventaFacturaIds
     * @return array<string, mixed>
     */
    public function actualizarLote(array $ventaFacturaIds, int $ventaNotaCreditoId, ?int $turnoOperativoId = null): array
    {
        $ventaFacturaIds = array_values(array_unique(array_filter(array_map('intval', $ventaFacturaIds))));
        if ($ventaFacturaIds === []) {
            throw new InvalidArgumentException('El lote no tiene facturas.');
        }

        $notaCredito = Venta::query()->with('puntoventas')->findOrFail($ventaNotaCreditoId);
        $emisionNc = VentaGastronomiaEmision::query()
            ->with('cuenta')
            ->where('venta_id', $ventaNotaCreditoId)
            ->firstOrFail();

        if ((string) $emisionNc->origen_pos !== 'recuperacion_arca_ajuste') {
            throw new InvalidArgumentException('La NC no corresponde a un ajuste fiscal de recuperación ARCA.');
        }

        $sumaFac = 0.0;
        $emisionFactura = null;
        foreach ($ventaFacturaIds as $facId) {
            $fac = Venta::query()->findOrFail($facId);
            $em = VentaGastronomiaEmision::query()->where('venta_id', $facId)->firstOrFail();
            if (! in_array((string) $em->origen_pos, ['recuperacion_arca'], true)) {
                throw new InvalidArgumentException('La factura '.$facId.' no es recuperación ARCA.');
            }
            $sumaFac += round((float) $fac->total, 2);
            $emisionFactura ??= $em;
        }

        if (abs(round($sumaFac + (float) $notaCredito->total, 2)) > self::TOLERANCIA) {
            throw new InvalidArgumentException('El lote FAC/NC no netea en cero.');
        }

        $origenBridge = VentaGastronomiaNcOrigen::query()
            ->where('venta_nc_id', $ventaNotaCreditoId)
            ->pluck('venta_factura_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        sort($origenBridge);
        $esperados = $ventaFacturaIds;
        sort($esperados);
        if ($origenBridge !== [] && $origenBridge !== $esperados) {
            throw new InvalidArgumentException('El puente NC↔FAC no coincide con el lote.');
        }

        $empresaId = (int) $notaCredito->puntoventas?->empresa_id;
        $fechaJornada = Carbon::parse($notaCredito->fechajornada)->toDateString();
        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->firstOrFail();

        $pc = (string) ($emisionFactura?->identificador_pc ?? $emisionNc->identificador_pc);
        $turno = null;
        if ($turnoOperativoId !== null && $turnoOperativoId > 0) {
            $turno = TurnoOperativoGastronomia::query()->find($turnoOperativoId);
        }
        if ($turno === null) {
            $cuenta = $emisionFactura?->cuenta;
            $fechaImputacion = $cuenta?->created_at
                ? Carbon::parse($cuenta->created_at)
                : Carbon::parse($notaCredito->created_at);
            $turno = TurnoOperativoGastronomia::query()
                ->where('jornada_gastronomia_id', $jornada->id)
                ->where('identificador_pc', $pc)
                ->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO)
                ->where('habilitacion_en', '<=', $fechaImputacion)
                ->where('cierre_en', '>=', $fechaImputacion)
                ->first();
        }
        if ($turno === null || $turno->estado !== TurnoOperativoGastronomia::ESTADO_CERRADO) {
            throw new RuntimeException('No se encontró el turno cerrado para actualizar Z del lote.');
        }

        return DB::transaction(function () use (
            $empresaId,
            $fechaJornada,
            $jornada,
            $turno,
            $pc,
        ): array {
            $totalesTurno = GastronomiaTurnoOperativoTotalesSupport::calcular(
                $pc,
                $empresaId,
                $fechaJornada,
                Carbon::parse($turno->habilitacion_en),
                Carbon::parse($turno->cierre_en),
            );
            $rendicionTurno = RendicionGastronomiaCaja::query()
                ->where('turno_operativo_gastronomia_id', $turno->id)
                ->first();
            $this->assertNetoPresentadoSinCambios($rendicionTurno, $totalesTurno, 'turno');

            $turno->update([
                'monto_facturacion_turno' => round((float) $totalesTurno['total_general'], 2),
            ]);
            if ($rendicionTurno !== null) {
                $this->actualizarCabeceraRendicion($rendicionTurno, $totalesTurno);
            }

            $totalesJornada = GastronomiaTurnoOperativoTotalesSupport::calcularPorJornada($jornada);
            $rendicionJornada = RendicionGastronomiaCaja::query()
                ->where('jornada_gastronomia_id', $jornada->id)
                ->where('tipo', RendicionGastronomiaCaja::TIPO_JORNADA)
                ->first();
            $this->assertNetoPresentadoSinCambios($rendicionJornada, $totalesJornada, 'jornada');

            if ($rendicionJornada !== null) {
                $marcadores = $this->jornadaPresentacionService->resolverMarcadoresAuditoria($jornada);
                $this->actualizarCabeceraRendicion($rendicionJornada, $totalesJornada, [
                    'numeracion_comprobantes_json' => $marcadores['numeracion_comprobantes_json'] ?? null,
                    'waitry_order_id_hasta' => (int) ($marcadores['waitry_order_id_hasta'] ?? 0),
                    'cierre_totem_jornada_gastronomia_id' => $marcadores['cierre_totem_jornada_gastronomia_id'] ?? null,
                ]);
            }

            $asiento = $this->recalcularAsientoSupport->actualizarSiExiste(
                $empresaId,
                $fechaJornada,
                false,
            );

            return [
                'turno_id' => (int) $turno->id,
                'rendicion_turno_id' => $rendicionTurno?->id,
                'jornada_id' => (int) $jornada->id,
                'rendicion_jornada_id' => $rendicionJornada?->id,
                'total_facturas_turno' => round((float) $totalesTurno['total_facturas'], 2),
                'total_nc_turno' => round(abs((float) $totalesTurno['total_notas_credito']), 2),
                'neto_turno' => round((float) $totalesTurno['total_ventas'], 2),
                'asiento_cierre' => $asiento,
                'anita_actualizado' => false,
                'lote' => true,
            ];
        });
    }

    /**
     * @return array{0:Venta,1:Venta,2:VentaGastronomiaEmision,3:VentaGastronomiaEmision}
     */
    private function validarPareja(int $ventaFacturaId, int $ventaNotaCreditoId): array
    {
        $factura = Venta::query()->with('puntoventas')->findOrFail($ventaFacturaId);
        $notaCredito = Venta::query()->with('puntoventas')->findOrFail($ventaNotaCreditoId);
        $emisionFactura = VentaGastronomiaEmision::query()
            ->with('cuenta')
            ->where('venta_id', $ventaFacturaId)
            ->firstOrFail();
        $emisionNc = VentaGastronomiaEmision::query()
            ->with('cuenta')
            ->where('venta_id', $ventaNotaCreditoId)
            ->firstOrFail();

        if ((int) $emisionNc->venta_factura_origen_id !== $ventaFacturaId) {
            throw new InvalidArgumentException('La nota de crédito no está vinculada con la factura recuperada.');
        }
        if (! in_array((string) $emisionFactura->origen_pos, ['recuperacion_arca'], true)
            || (string) $emisionNc->origen_pos !== 'recuperacion_arca_ajuste') {
            throw new InvalidArgumentException('La pareja no corresponde a un ajuste fiscal de recuperación ARCA.');
        }
        if ((int) $emisionFactura->cuenta_gastronomia_id <= 0
            || (int) $emisionFactura->cuenta_gastronomia_id !== (int) $emisionNc->cuenta_gastronomia_id) {
            throw new InvalidArgumentException('La factura y la NC deben compartir la cuenta gastronómica.');
        }
        if ($factura->fechajornada === null
            || Carbon::parse($factura->fechajornada)->toDateString()
                !== Carbon::parse($notaCredito->fechajornada)->toDateString()) {
            throw new InvalidArgumentException('La factura y la NC deben pertenecer a la misma jornada.');
        }
        if (abs(round((float) $factura->total + (float) $notaCredito->total, 2)) > self::TOLERANCIA) {
            throw new InvalidArgumentException('La factura y la NC no netean en cero.');
        }

        return [$factura, $notaCredito, $emisionFactura, $emisionNc];
    }

    /**
     * @param  array<string, mixed>  $totales
     */
    private function assertNetoPresentadoSinCambios(
        ?RendicionGastronomiaCaja $rendicion,
        array $totales,
        string $contexto,
    ): void {
        if ($rendicion === null) {
            return;
        }

        $netoVenta = round((float) ($totales['total_ventas'] ?? 0), 2);
        $netoCobrado = round((float) ($totales['total_cobrado'] ?? 0), 2);
        if (abs($netoVenta - round((float) $rendicion->totalfactura, 2)) > self::TOLERANCIA
            || abs($netoCobrado - round((float) $rendicion->totalcobrado, 2)) > self::TOLERANCIA) {
            throw new RuntimeException(
                'El ajuste alteraría el neto ya presentado del '.$contexto
                .'; se abortó antes de actualizar los Z.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $totales
     * @param  array<string, mixed>  $extra
     */
    private function actualizarCabeceraRendicion(
        RendicionGastronomiaCaja $rendicion,
        array $totales,
        array $extra = [],
    ): void {
        $rendicion->update(array_merge([
            'totalfactura' => round((float) ($totales['total_ventas'] ?? 0), 2),
            'totalcobrado' => round((float) ($totales['total_cobrado'] ?? 0), 2),
            'totalinvitacion' => round((float) ($totales['total_invitaciones'] ?? 0), 2),
            'totalnotacredito' => round(abs((float) ($totales['total_notas_credito'] ?? 0)), 2),
        ], $extra));
    }
}
