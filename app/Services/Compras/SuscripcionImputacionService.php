<?php

namespace App\Services\Compras;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Caja_Movimiento_Estado;
use App\Models\Compras\Suscripcion_Cargo;
use App\Models\Compras\Suscripcion_Conciliacion;
use App\Repositories\Caja\Caja_Movimiento_CuentacajaRepositoryInterface;
use App\Repositories\Caja\Caja_Movimiento_EstadoRepositoryInterface;
use App\Repositories\Caja\Caja_MovimientoRepositoryInterface;
use App\Support\Caja\IngresoEgresoAnitaTesmovSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lleva los cargos conciliados a Ingresos y egresos.
 *
 * Cada cargo genera un egreso contra la cuenta de caja que representa la tarjeta, con
 * el proveedor de la suscripción, por el mismo camino que usan OPP/OPA (incluida la
 * réplica en Anita). Es el paso que cierra el circuito: hasta acá el gasto estaba
 * identificado pero no imputado.
 */
class SuscripcionImputacionService
{
    public function __construct(
        private Caja_MovimientoRepositoryInterface $cajaMovimientoRepository,
        private Caja_Movimiento_CuentacajaRepositoryInterface $cajaMovimientoCuentacajaRepository,
        private Caja_Movimiento_EstadoRepositoryInterface $cajaMovimientoEstadoRepository,
    ) {}

    /**
     * Imputa todos los cargos conciliados del período que todavía no tengan movimiento.
     *
     * @return array{ok: bool, mensaje: string, imputados?: int, omitidos?: int}
     */
    public function imputarPeriodo(Suscripcion_Conciliacion $conciliacion, int $usuarioId): array
    {
        $cargos = $conciliacion->suscripcion_cargos()
            ->with(['ordencompras', 'suscripcion_tarjetas'])
            ->where('estado', Suscripcion_Cargo::ESTADO_CONCILIADO)
            ->whereNull('caja_movimiento_id')
            ->get();

        if ($cargos->isEmpty()) {
            return ['ok' => false, 'mensaje' => 'No hay cargos conciliados pendientes de imputar en este período.'];
        }

        $imputados = 0;
        $problemas = [];

        foreach ($cargos as $cargo) {
            $resultado = $this->imputarCargo($cargo, $usuarioId);
            if ($resultado['ok']) {
                $imputados++;

                continue;
            }
            $problemas[] = $cargo->comercio.': '.$resultado['mensaje'];
        }

        $omitidos = count($problemas);
        $mensaje = "Se imputaron {$imputados} cargos en Ingresos y egresos.";
        if ($omitidos > 0) {
            $mensaje .= ' Quedaron '.$omitidos.' sin imputar — '.implode(' | ', array_slice($problemas, 0, 3));
            if ($omitidos > 3) {
                $mensaje .= ' (y '.($omitidos - 3).' más)';
            }
        }

        return [
            'ok' => $imputados > 0,
            'imputados' => $imputados,
            'omitidos' => $omitidos,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * @return array{ok: bool, mensaje: string, caja_movimiento_id?: int}
     */
    public function imputarCargo(Suscripcion_Cargo $cargo, int $usuarioId): array
    {
        if ($cargo->imputado()) {
            return ['ok' => false, 'mensaje' => 'El cargo ya tiene un movimiento de caja.'];
        }

        $oc = $cargo->ordencompras;
        if (! $oc) {
            return ['ok' => false, 'mensaje' => 'El cargo no tiene suscripción asociada.'];
        }

        // La tarjeta del cargo manda; si el resumen no traía los 4 dígitos, se usa la de la OC.
        $tarjeta = $cargo->suscripcion_tarjetas ?: $oc->suscripcion_tarjetas;
        if (! $tarjeta) {
            return ['ok' => false, 'mensaje' => 'No se identificó la tarjeta corporativa del cargo.'];
        }
        if (! $tarjeta->imputable()) {
            return [
                'ok' => false,
                'mensaje' => 'La tarjeta '.$tarjeta->etiquetaCompleta()
                    .' no tiene cuenta de caja y tipo de transacción configurados.',
            ];
        }

        $fecha = ($cargo->fecha instanceof Carbon ? $cargo->fecha : Carbon::parse($cargo->fecha))->toDateString();
        $detalle = mb_substr(
            'Suscripción '.($oc->suscripcion_nombre ?: $oc->detalle).' — cargo tarjeta ••'.$tarjeta->ult4,
            0,
            255
        );

        $payload = [
            'empresa_id' => (int) $oc->empresa_id,
            'tipotransaccion_caja_id' => (int) $tarjeta->tipotransaccion_caja_id,
            'fecha' => $fecha,
            'detalle' => $detalle,
            'proveedor_id' => (int) $oc->proveedor_id ?: null,
            'monto' => (float) $cargo->monto,
            'usuario_id' => $usuarioId,
            'cuentacaja_ids' => [(int) $tarjeta->cuentacaja_id],
            'montos' => [(float) $cargo->monto],
            'moneda_ids' => [(int) ($cargo->moneda_id ?: $tarjeta->moneda_id ?: $oc->contrato_moneda_id)],
            'cotizaciones' => [1],
            'observaciones' => [mb_substr($cargo->comercio, 0, 255)],
        ];

        DB::beginTransaction();
        try {
            $movimiento = $this->cajaMovimientoRepository->create($payload);
            if (! $movimiento instanceof Caja_Movimiento) {
                throw new \RuntimeException('No se pudo grabar el movimiento de caja.');
            }

            $payload['tipotransaccion_caja_id'] = (int) $tarjeta->tipotransaccion_caja_id;
            $this->cajaMovimientoCuentacajaRepository->create($payload, (int) $movimiento->id);

            $this->cajaMovimientoEstadoRepository->create([
                'fechas' => [Carbon::now()],
                'estados' => [Caja_Movimiento_Estado::$enumEstado[0]['valor'] ?? 'ACTIVO'],
                'observacionestados' => ['Imputación de cargo de suscripción'],
                'usuario_ids' => [$usuarioId],
            ], (int) $movimiento->id);

            $cargo->update(['caja_movimiento_id' => (int) $movimiento->id]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::warning('suscripcion.imputacion.fallo', [
                'cargo_id' => $cargo->id,
                'mensaje' => $e->getMessage(),
            ]);

            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }

        $this->replicarEnAnita((int) $movimiento->id);

        return ['ok' => true, 'mensaje' => 'Cargo imputado.', 'caja_movimiento_id' => (int) $movimiento->id];
    }

    /**
     * Una falla del bridge no invalida la imputación en el ERP: queda registrada y se reintenta
     * desde Ingresos y egresos, igual que en el resto de los movimientos.
     */
    private function replicarEnAnita(int $cajaMovimientoId): void
    {
        try {
            $movimiento = Caja_Movimiento::query()->find($cajaMovimientoId);
            if ($movimiento) {
                IngresoEgresoAnitaTesmovSupport::grabarDesdeMovimiento($movimiento->fresh());
            }
        } catch (\Throwable $e) {
            Log::warning('suscripcion.imputacion.anita', [
                'caja_movimiento_id' => $cajaMovimientoId,
                'mensaje' => $e->getMessage(),
            ]);
        }
    }
}
