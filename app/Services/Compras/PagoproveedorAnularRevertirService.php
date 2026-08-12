<?php

namespace App\Services\Compras;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cheque;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Estado;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Models\Contable\Asiento;
use App\Repositories\Compras\PagoproveedorRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Support\Compras\PagoproveedorAplicacionCuentacorrienteSupport;
use App\Support\Contable\AsientoReversoSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use Auth;
use DB;
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
    ) {
    }

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
            PagoproveedorAplicacionCuentacorrienteSupport::revertirAplicacionesExistentes($pago);

            $asientos = Asiento::query()->where('pagoproveedor_id', (int) $pago->id)->get();
            foreach ($asientos as $asiento) {
                $this->asientoRepository->delete((int) $asiento->id);
            }

            Cheque::query()->where('pagoproveedor_id', (int) $pago->id)->delete();

            Caja_Movimiento::query()->where('pagoproveedor_id', (int) $pago->id)->delete();
            if ((int) ($pago->caja_movimiento_id ?? 0) > 0) {
                Caja_Movimiento::query()->where('id', (int) $pago->caja_movimiento_id)->delete();
            }

            Pagoproveedor_Retencion::query()->where('pagoproveedor_id', (int) $pago->id)->delete();

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
     * @return array{mensaje: string, pagoproveedor_id: int, pagoproveedor_reverso_id: int, numerotransaccion: string}
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

        return DB::transaction(function () use ($pago, $fechaOp, $leyenda, $nroOrig) {
            PagoproveedorAplicacionCuentacorrienteSupport::revertirAplicacionesExistentes($pago);

            $reverso = $this->pagoproveedorRepository->create([
                'empresa_id' => (int) $pago->empresa_id,
                'tipotransaccion_caja_id' => $pago->tipotransaccion_caja_id,
                'tipocomprobante' => (string) ($pago->tipocomprobante ?? 'OPP'),
                'letra' => (string) ($pago->letra ?? 'A'),
                'sucursal' => (int) ($pago->sucursal ?? 1),
                'numerotransaccion' => 'R'.$nroOrig,
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

            foreach (Cheque::query()->where('pagoproveedor_id', (int) $pago->id)->get() as $cheque) {
                if (strtoupper((string) $cheque->origen) !== 'E') {
                    continue;
                }
                $cheque->estado = 'A';
                $cheque->save();
            }

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
                    false,
                    null,
                    null
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

            return [
                'mensaje' => 'ok',
                'pagoproveedor_id' => (int) $pago->id,
                'pagoproveedor_reverso_id' => (int) $reverso->id,
                'numerotransaccion' => (string) $reverso->numerotransaccion,
            ];
        });
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
