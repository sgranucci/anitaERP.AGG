<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Estado;
use App\Support\Compras\ComprobanteProveedorAnitaSyncEstado;
use App\Support\Compras\ComprobanteProveedorEstados;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ComprobanteProveedorContabilizarService
{
    /** @var list<string> */
    private const ESTADOS_PERMITIDOS = [
        ComprobanteProveedorEstados::BORRADOR,
        ComprobanteProveedorEstados::PENDIENTE_REVISION,
        ComprobanteProveedorEstados::PENDIENTE_APROBACION,
        ComprobanteProveedorEstados::PENDIENTE_DIFERENCIA,
        ComprobanteProveedorEstados::APROBADO,
    ];

    public function __construct(
        private ComprobanteProveedorAsientoService $asientoService,
        private ComprobanteProveedorCuentacorrienteService $cuentacorrienteService,
        private ComprobanteProveedorAnitaSyncService $anitaSyncService,
    ) {}

    public function contabilizar(int $comprobanteId): Comprobante_Proveedor
    {
        $comprobante = Comprobante_Proveedor::query()->find($comprobanteId);
        if (! $comprobante) {
            throw new RuntimeException('Comprobante de proveedor inexistente.');
        }

        if ($comprobante->estado === ComprobanteProveedorEstados::CONTABILIZADO) {
            throw new RuntimeException('El comprobante ya está contabilizado.');
        }

        if (! in_array($comprobante->estado, self::ESTADOS_PERMITIDOS, true)) {
            throw new RuntimeException('No se puede contabilizar en estado «'.$comprobante->estado.'».');
        }

        if ($comprobante->comprobante_proveedor_conceptos()->count() === 0) {
            throw new RuntimeException('Agregue al menos un concepto IVA antes de contabilizar.');
        }

        try {
            return DB::transaction(function () use ($comprobante) {
                $comprobante->load('comprobante_proveedor_recepciones.recepcion_proveedores');

                $asientoId = $this->asientoService->generarAsiento($comprobante);

                $this->cuentacorrienteService->generarDesdeComprobante($comprobante);

                $comprobante->forceFill([
                    'asiento_id' => $asientoId,
                    'estado' => ComprobanteProveedorEstados::CONTABILIZADO,
                ])->save();

                $this->anitaSyncService->syncCreate($comprobante->fresh([
                    'comprobante_proveedor_conceptos.concepto_ivacompras',
                    'comprobante_proveedor_cuotas',
                    'empresas', 'proveedores', 'tipotransaccion_compras', 'monedas', 'ordencompras',
                ]));

                Comprobante_Proveedor_Estado::query()->create([
                    'comprobante_proveedor_id' => $comprobante->id,
                    'fecha' => now()->format('Y-m-d'),
                    'estado' => ComprobanteProveedorEstados::CONTABILIZADO,
                    'usuario_id' => Auth::id(),
                    'observacion' => 'Contabilizado en ERP (asiento #'.$asientoId.')',
                ]);

                return $comprobante->fresh();
            });
        } catch (\Throwable $e) {
            $comprobante->forceFill([
                'anita_sync_estado' => ComprobanteProveedorAnitaSyncEstado::ERROR,
                'anita_sync_error' => $e->getMessage(),
                'anita_sync_at' => now(),
            ])->save();

            throw $e;
        }
    }
}
