<?php

namespace App\Services\Compras;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Caja_Movimiento_Estado;
use App\Models\Caja\Cheque;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Estado;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Models\Contable\Asiento;
use App\Repositories\Caja\Caja_Movimiento_CuentacajaRepositoryInterface;
use App\Repositories\Caja\Caja_Movimiento_EstadoRepositoryInterface;
use App\Repositories\Caja\Caja_MovimientoRepositoryInterface;
use App\Repositories\Compras\PagoproveedorRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Support\Caja\CajaMovimientoEloquentDeleteSupport;
use App\Support\Caja\IngresoEgresoAnitaTesmovSupport;
use App\Support\Compras\AnitaSync\Pagoproveedor\PagoproveedorAnitaRetencionEscrituraSupport;
use App\Support\Compras\PagoproveedorAplicacionCuentacorrienteSupport;
use App\Support\Contable\AsientoReversoSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Auth;
use DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Anulación física y reversión compensatoria de órdenes de pago a proveedores.
 */
class PagoproveedorAnularRevertirService
{
    public function __construct(
        private PagoproveedorRepositoryInterface $pagoproveedorRepository,
        private AsientoRepositoryInterface $asientoRepository,
        private AsientoReversoSupport $asientoReversoSupport,
        private Caja_MovimientoRepositoryInterface $cajaMovimientoRepository,
        private Caja_Movimiento_CuentacajaRepositoryInterface $cajaMovimientoCuentacajaRepository,
        private Caja_Movimiento_EstadoRepositoryInterface $cajaMovimientoEstadoRepository,
        private ProveedorCuentacorrienteAplicacionAnitaSyncService $cuentacorrienteAnitaSyncService,
    ) {}

    /**
     * @return array{mensaje: string, pagoproveedor_id: int}
     */
    public function anularFisicamente(int $id): array
    {
        $pago = $this->pagoproveedorRepository->findOrFail($id);
        $this->assertNoEsCompensatorio($pago);
        if ((int) ($pago->pagoproveedor_revertido_por_id ?? 0) > 0) {
            throw new RuntimeException('La OP ya fue revertida; no se puede anular físicamente.');
        }
        if (in_array((string) $pago->estado, ['BAJA', 'REVERTIDA'], true)) {
            throw new RuntimeException('La OP ya está anulada o revertida.');
        }

        PeriodoContableCierreSupport::assertOperacionPermitida(
            (int) $pago->empresa_id,
            $this->fechaYmd($pago->fecha),
            PeriodoContableCierreSupport::ALCANCE_CAJA
        );

        return DB::transaction(function () use ($pago) {
            // Revalidar sobre la fila tomada: los chequeos previos corren sin lock.
            $actual = Pagoproveedor::query()->whereKey((int) $pago->id)->lockForUpdate()->first();
            if ($actual === null) {
                throw new RuntimeException('La OP ya no existe.');
            }
            if ((int) ($actual->pagoproveedor_revertido_por_id ?? 0) > 0) {
                throw new RuntimeException('La OP ya fue revertida; no se puede anular físicamente.');
            }
            if (in_array((string) $actual->estado, ['BAJA', 'REVERTIDA'], true)) {
                throw new RuntimeException('La OP ya está anulada o revertida.');
            }

            // Anita CC primero: necesita las aplicaciones ERP aún presentes.
            //
            // Deuda técnica aceptada: a diferencia de revertir(), acá los escritos a Anita quedan
            // dentro de la TX local, así que un rollback posterior no los deshace. Se tolera porque
            // todo este camino borra y los borrados en Anita son idempotentes: la recuperación es
            // reejecutar la anulación. Runbook y condiciones para sacarlo de la TX en
            // .cursor/rules/pagoproveedor-anulacion-reversion-anita.mdc
            $this->cuentacorrienteAnitaSyncService->revertirYEliminarPagoAnita($pago);

            PagoproveedorAplicacionCuentacorrienteSupport::revertirAplicacionesExistentes($pago);

            $asientos = Asiento::query()->where('pagoproveedor_id', (int) $pago->id)->get();
            foreach ($asientos as $asiento) {
                $this->asientoRepository->delete((int) $asiento->id);
            }

            EloquentAuditDeleteSupport::each(
                Cheque::query()->where('pagoproveedor_id', (int) $pago->id)
            );

            $cajaIds = Caja_Movimiento::query()
                ->where('pagoproveedor_id', (int) $pago->id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->all();
            if ((int) ($pago->caja_movimiento_id ?? 0) > 0) {
                $cajaIds[] = (int) $pago->caja_movimiento_id;
            }

            $tesoreriaBorrada = false;
            foreach (array_unique($cajaIds) as $cajaId) {
                $mov = Caja_Movimiento::query()->find($cajaId);
                if ($mov) {
                    IngresoEgresoAnitaTesmovSupport::eliminarDesdeMovimiento($mov);
                    $tesoreriaBorrada = true;
                }
            }

            // Sin caja local (imports / OP huérfana): igual hay que limpiar pago/auxpag/tesmov.
            if (! $tesoreriaBorrada) {
                $this->eliminarTesoreriaAnitaPorPago($pago);
            }

            PagoproveedorAnitaRetencionEscrituraSupport::eliminarDesdePago($pago);

            CajaMovimientoEloquentDeleteSupport::eliminarPorQuery(
                Caja_Movimiento::query()->where('pagoproveedor_id', (int) $pago->id)
            );
            CajaMovimientoEloquentDeleteSupport::eliminarPorId((int) ($pago->caja_movimiento_id ?? 0));

            EloquentAuditDeleteSupport::each(
                Pagoproveedor_Retencion::query()->where('pagoproveedor_id', (int) $pago->id)
            );

            $this->registrarEstado($pago, 'BAJA', 'Anulación física de OP');
            $this->pagoproveedorRepository->update(['estado' => 'BAJA'], (int) $pago->id);
            $this->pagoproveedorRepository->delete((int) $pago->id);

            return [
                'mensaje' => 'ok',
                'pagoproveedor_id' => (int) $pago->id,
            ];
        });
    }

    /**
     * @return array{mensaje: string, pagoproveedor_id: int, pagoproveedor_reverso_id: int, numerotransaccion: string, aviso?: string}
     */
    public function revertir(int $id, ?string $fecha = null): array
    {
        $pago = $this->pagoproveedorRepository->findOrFail($id);
        $this->assertNoEsCompensatorio($pago);
        if ((int) ($pago->pagoproveedor_revertido_por_id ?? 0) > 0) {
            throw new RuntimeException('La OP ya fue revertida.');
        }
        if (! in_array((string) $pago->estado, ['CONFIRMADA', 'PAGADA', 'CONCILIADA'], true)) {
            throw new RuntimeException('Solo se pueden revertir OP confirmadas, pagadas o conciliadas.');
        }

        $fechaOp = $fecha && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)
            ? $fecha
            : date('Y-m-d');

        PeriodoContableCierreSupport::assertOperacionPermitida(
            (int) $pago->empresa_id,
            $fechaOp,
            PeriodoContableCierreSupport::ALCANCE_CAJA
        );

        $nroOrig = (string) ($pago->numerotransaccion ?? '');
        $leyenda = 'ANULA OPP '.$nroOrig;

        // Todo lo local en una transacción; Anita recién después del commit. Un rollback de
        // MySQL no revierte lo ya escrito en Anita: así quedaba la reversión espejada allá
        // con la OP todavía viva acá.
        $local = DB::transaction(function () use ($pago, $fechaOp, $leyenda, $nroOrig) {
            // Revalidar sobre la fila tomada: los chequeos previos corren sin lock y dos
            // reversiones simultáneas pasaban ambas.
            $actual = Pagoproveedor::query()->whereKey((int) $pago->id)->lockForUpdate()->first();
            if ($actual === null) {
                throw new RuntimeException('La OP ya no existe.');
            }
            if ((int) ($actual->pagoproveedor_revertido_por_id ?? 0) > 0) {
                throw new RuntimeException('La OP ya fue revertida.');
            }
            if (! in_array((string) $actual->estado, ['CONFIRMADA', 'PAGADA', 'CONCILIADA'], true)) {
                throw new RuntimeException('Solo se pueden revertir OP confirmadas, pagadas o conciliadas.');
            }

            // AOP: mismo comprobante con importes negativos y el número del original.
            $reverso = $this->pagoproveedorRepository->create([
                'empresa_id' => (int) $pago->empresa_id,
                'tipotransaccion_caja_id' => $pago->tipotransaccion_caja_id,
                'tipocomprobante' => 'AOP',
                'letra' => (string) ($pago->letra ?? 'A'),
                'sucursal' => (int) ($pago->sucursal ?? 1),
                'numerotransaccion' => (string) $nroOrig,
                'fecha' => $fechaOp,
                'caja_id' => $pago->caja_id,
                'proveedor_id' => (int) $pago->proveedor_id,
                'detalle' => $leyenda.': '.trim((string) ($pago->detalle ?? '')),
                'estado' => 'REVERTIDA',
                'monto' => -1 * abs((float) $pago->monto),
                'cotizacion' => (float) ($pago->cotizacion ?: 1),
                'moneda_id' => (int) ($pago->moneda_id ?: 1),
                'modo_cotizacion' => (string) ($pago->modo_cotizacion ?? 'factura'),
                'usuario_id' => Auth::id(),
                'pagoproveedor_origen_id' => (int) $pago->id,
                'propuesta_pago_id' => $pago->propuesta_pago_id,
            ]);

            $this->registrarEstado($reverso, 'REVERTIDA', $leyenda);

            // Cuenta corriente: se compensa, no se borra. La factura vuelve a quedar
            // impaga y el saldo reaparece, conservando la traza de la OPP.
            PagoproveedorAplicacionCuentacorrienteSupport::compensarPorReverso($pago, $reverso);

            $cajaReverso = $this->crearMovimientoCajaCompensatorio($pago, $reverso, $fechaOp, $leyenda);

            foreach (Cheque::query()->where('pagoproveedor_id', (int) $pago->id)->get() as $cheque) {
                if (strtoupper((string) $cheque->origen) !== 'E') {
                    continue;
                }
                $cheque->estado = 'A';
                $cheque->save();
            }

            $retencionesOrigen = Pagoproveedor_Retencion::query()
                ->with('provincias')
                ->where('pagoproveedor_id', (int) $pago->id)
                ->get();

            $asientoOrig = Asiento::query()
                ->with('asiento_movimientos')
                ->where('pagoproveedor_id', (int) $pago->id)
                ->orderBy('id')
                ->first();

            if ($asientoOrig) {
                $resAsiento = $this->asientoReversoSupport->generarDesdeAsiento(
                    $asientoOrig,
                    $fechaOp,
                    null,
                    $leyenda,
                    alcanceCierre: PeriodoContableCierreSupport::ALCANCE_CAJA
                );
                if (! empty($resAsiento['asiento_id'])) {
                    Asiento::query()->where('id', (int) $resAsiento['asiento_id'])->update([
                        'pagoproveedor_id' => (int) $reverso->id,
                    ]);
                    $this->pagoproveedorRepository->update([
                        'asiento_id' => (int) $resAsiento['asiento_id'],
                    ], (int) $reverso->id);
                }
            }

            $this->pagoproveedorRepository->update([
                'estado' => 'REVERTIDA',
                'pagoproveedor_revertido_por_id' => (int) $reverso->id,
            ], (int) $pago->id);

            $this->registrarEstado($pago, 'REVERTIDA', $leyenda.' (compensatorio OP '.$reverso->id.')');

            // Las retenciones de la OP original NO se borran: la tabla no tiene softdeletes
            // ni auditoría, así que el borrado perdía sin rastro los certificados ya emitidos
            // al proveedor. SICORE, IIBB y SUSS filtran por estado CONFIRMADA, de modo que al
            // quedar la OP en REVERTIDA esas filas salen solas de las presentaciones.

            return [
                'reverso' => $reverso,
                'caja_reverso' => $cajaReverso,
                'retenciones_origen' => $retencionesOrigen,
            ];
        });

        $reverso = $local['reverso'];
        $salida = [
            'mensaje' => 'ok',
            'pagoproveedor_id' => (int) $pago->id,
            'pagoproveedor_reverso_id' => (int) $reverso->id,
            'numerotransaccion' => (string) $reverso->numerotransaccion,
        ];

        try {
            PagoproveedorAnitaRetencionEscrituraSupport::grabarReversoDesdeRetenciones(
                $pago,
                $reverso,
                $local['retenciones_origen'],
            );
            $this->sincronizarAnulacionAnita($pago, $reverso, $local['caja_reverso']);
        } catch (\Throwable $eAnita) {
            // La reversión ya está commitida: fallar acá haría que el operador reintente y
            // se genere una segunda AOP, duplicando la compensación en cuenta corriente.
            Log::error('pagoproveedor.reversion.anita.sync_post_commit.fallo', [
                'pagoproveedor_id' => $pago->id,
                'reverso_id' => $reverso->id,
                'mensaje' => $eAnita->getMessage(),
            ]);
            $salida['aviso'] = 'Reversión grabada en ERP (AOP '.$reverso->numerotransaccion
                .') pero falló la réplica Anita: '.$eAnita->getMessage()
                .'. No reintente la reversión; revise sincronización/auditoría.';
        }

        return $salida;
    }

    /**
     * Movimiento de caja espejo con las líneas de cuentacaja invertidas: devuelve
     * los valores al banco/caja y da a Anita las líneas para auxpag/tesmov.
     */
    private function crearMovimientoCajaCompensatorio(
        Pagoproveedor $pago,
        Pagoproveedor $reverso,
        string $fechaOp,
        string $leyenda
    ): ?Caja_Movimiento {
        $cajaOriginal = Caja_Movimiento::query()
            ->with('caja_movimiento_cuentacajas')
            ->find((int) ($pago->caja_movimiento_id ?? 0));

        if ($cajaOriginal === null) {
            return null;
        }

        $movimiento = $this->cajaMovimientoRepository->create([
            'empresa_id' => (int) $pago->empresa_id,
            'tipotransaccion_caja_id' => (int) $cajaOriginal->tipotransaccion_caja_id,
            'fecha' => $fechaOp,
            'caja_id' => $cajaOriginal->caja_id,
            'proveedor_id' => $pago->proveedor_id,
            'detalle' => $leyenda,
            'usuario_id' => Auth::id(),
            'solicitudpago_id' => null,
            'caja_movimiento_origen_id' => (int) $cajaOriginal->id,
        ]);

        if (! $movimiento instanceof Caja_Movimiento) {
            throw new RuntimeException('No se pudo grabar el movimiento de caja de la anulación.');
        }

        $lineas = [
            'tipotransaccion_caja_id' => (int) $cajaOriginal->tipotransaccion_caja_id,
            'fecha' => $fechaOp,
            'observaciones' => [],
            'cuentacaja_ids' => [],
            'montos' => [],
            'moneda_ids' => [],
            'cotizaciones' => [],
        ];
        foreach ($cajaOriginal->caja_movimiento_cuentacajas as $linea) {
            $monto = (float) $linea->monto;
            $lineas['cuentacaja_ids'][] = (int) $linea->cuentacaja_id;
            $lineas['montos'][] = $monto >= 0 ? ($monto * -1.0) : abs($monto);
            $lineas['moneda_ids'][] = (int) ($linea->moneda_id ?: 1);
            $lineas['cotizaciones'][] = (float) ($linea->cotizacion ?: 1);
            $lineas['observaciones'][] = $leyenda;
        }
        if ($lineas['cuentacaja_ids'] !== []) {
            $this->cajaMovimientoCuentacajaRepository->create($lineas, (int) $movimiento->id);
        }

        $this->cajaMovimientoEstadoRepository->create([
            'fechas' => [$fechaOp],
            'estados' => [Caja_Movimiento_Estado::$enumEstado[0]['valor'] ?? 'A'],
            'observacionestados' => [$leyenda],
        ], (int) $movimiento->id);

        if (Schema::hasColumn('caja_movimiento', 'pagoproveedor_id')) {
            Caja_Movimiento::query()->where('id', (int) $movimiento->id)->update([
                'pagoproveedor_id' => (int) $reverso->id,
            ]);
        }

        Caja_Movimiento::query()->where('id', (int) $cajaOriginal->id)->update([
            'caja_movimiento_revertido_por_id' => (int) $movimiento->id,
        ]);

        $this->pagoproveedorRepository->update([
            'caja_movimiento_id' => (int) $movimiento->id,
        ], (int) $reverso->id);

        return $movimiento->fresh();
    }

    /**
     * Tesorería y cuenta corriente de Anita: AOP con importes negativos (pago/auxpag/
     * tesmov + cpromae de los cheques propios) y aplicaciones compensatorias en
     * aplmovp/promov.
     */
    private function sincronizarAnulacionAnita(
        Pagoproveedor $pago,
        Pagoproveedor $reverso,
        ?Caja_Movimiento $cajaReverso
    ): void {
        $cajaOriginal = Caja_Movimiento::query()->find((int) ($pago->caja_movimiento_id ?? 0));

        if ($cajaReverso !== null && $cajaOriginal !== null) {
            IngresoEgresoAnitaTesmovSupport::grabarAnulacionDesdeMovimiento($cajaReverso, $cajaOriginal);
        } else {
            Log::warning('pagoproveedor.reversion.sin_movimiento_caja', [
                'pagoproveedor_id' => $pago->id,
                'reverso_id' => $reverso->id,
            ]);
        }

        $this->cuentacorrienteAnitaSyncService->syncPorPagoproveedor((int) $reverso->id);
    }

    private function eliminarTesoreriaAnitaPorPago(Pagoproveedor $pago): void
    {
        $nro = (int) ($pago->numerotransaccion ?? 0);
        if ($nro <= 0) {
            return;
        }

        $empresa = SicoreEmpresaAnitaSupport::codigoEmpresaAnita(
            (int) $pago->empresa_id
        );
        if ($empresa <= 0) {
            $empresa = (int) $pago->empresa_id;
        }

        $tipo = strtoupper(substr(trim((string) ($pago->tipocomprobante ?: 'OPP')), 0, 3));
        if ($tipo === '') {
            $tipo = 'OPP';
        }

        IngresoEgresoAnitaTesmovSupport::eliminarTesoreriaPorClave(
            $tipo,
            $nro,
            $empresa,
            'pagoproveedor anulación física '.$pago->id
        );
    }

    private function assertNoEsCompensatorio(Pagoproveedor $pago): void
    {
        if ((int) ($pago->pagoproveedor_origen_id ?? 0) > 0) {
            throw new RuntimeException('No se puede operar sobre una OP compensatoria.');
        }
    }

    private function fechaYmd($fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        return (string) ($fecha ?: date('Y-m-d'));
    }

    private function registrarEstado(Pagoproveedor $pago, string $estado, string $observacion): void
    {
        Pagoproveedor_Estado::query()->create([
            'pagoproveedor_id' => $pago->id,
            'fecha' => now(),
            'estado' => $estado,
            'usuario_id' => Auth::id(),
            'observacion' => $observacion,
        ]);
    }
}
