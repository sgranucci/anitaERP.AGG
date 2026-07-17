<?php

namespace App\Services\Stock;

use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Articulo;
use App\Models\Stock\Recepcion_Proveedor_Estado;
use App\Repositories\Stock\Recepcion_ProveedorRepositoryInterface;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cambia únicamente la cotización de una recepción de proveedor confirmada y propaga el cambio a:
 *  - Cabecera y líneas de la recepción (recepcion_proveedor / recepcion_proveedor_articulo).
 *  - Asiento contable del ERP (recuadre) y ctamov de Anita.
 *  - recepmov de Anita (recv_cotizacion).
 * No modifica cantidades, precios ni stock.
 */
class RecepcionProveedorCambioCotizacionService
{
    public function __construct(
        private readonly Recepcion_ProveedorRepositoryInterface $repository,
        private readonly RecepcionProveedorAsientoService $asientoService,
        private readonly RecepcionProveedorAnitaBridgeService $anitaBridge,
    ) {
    }

    public function cambiar(int $id, float $nuevaCotizacion): Recepcion_Proveedor
    {
        $nuevaCotizacion = round($nuevaCotizacion, 6);
        if ($nuevaCotizacion <= 0) {
            throw new \RuntimeException('La cotización debe ser mayor a cero.');
        }

        $recepcion = $this->repository->find($id);

        if ($recepcion->estado !== RecepcionProveedorEstados::CONFIRMADA) {
            throw new \RuntimeException('Solo se puede cambiar la cotización de una recepción CONFIRMADA.');
        }

        $cotizacionAnterior = (float) ($recepcion->cotizacion ?: 1);
        if (abs($cotizacionAnterior - $nuevaCotizacion) < 0.0000005) {
            throw new \RuntimeException('La cotización indicada es igual a la cotización vigente.');
        }

        $this->assertPeriodoContable($recepcion);

        $recepcion = DB::transaction(function () use ($recepcion, $nuevaCotizacion) {
            // 1) Solo la cotización, en cabecera y líneas.
            Recepcion_Proveedor_Articulo::query()
                ->where('recepcion_proveedor_id', $recepcion->id)
                ->update(['cotizacion' => $nuevaCotizacion]);

            $recepcion->update(['cotizacion' => $nuevaCotizacion]);

            $recepcion = $recepcion->fresh([
                'proveedores',
                'empresas',
                'ordencompras',
                'recepcion_proveedor_articulos.articulos.articulo_cuentacontables',
                'recepcion_proveedor_articulos.centrocostos',
                'asientos',
            ]);

            // 2) Asiento contable ERP + ctamov Anita (recuadre con la nueva cotización).
            if ((int) ($recepcion->asiento_id ?? 0) > 0) {
                $this->asientoService->recuadrarAsientoExistente($recepcion);
            }

            // 3) recepmov Anita (recv_cotizacion).
            $this->anitaBridge->actualizarCotizacionRecepmov($recepcion, $nuevaCotizacion);

            return $recepcion->fresh();
        });

        $this->logCambio($recepcion, $cotizacionAnterior, $nuevaCotizacion);

        return $recepcion;
    }

    private function assertPeriodoContable(Recepcion_Proveedor $recepcion): void
    {
        $empresaId = (int) $recepcion->empresa_id;
        $fecha = (string) ($recepcion->fecha?->format('Y-m-d') ?? '');
        if ($empresaId <= 0 || $fecha === '') {
            return;
        }

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            $fecha,
            PeriodoContableCierreSupport::ALCANCE_RECEPCION_PROVEEDOR
        );
    }

    private function logCambio(Recepcion_Proveedor $recepcion, float $anterior, float $nueva): void
    {
        Recepcion_Proveedor_Estado::create([
            'recepcion_proveedor_id' => $recepcion->id,
            'estado' => RecepcionProveedorEstados::CONFIRMADA,
            'fecha' => now(),
            'usuario_id' => Auth::id(),
            'observacion' => 'Cambio de cotización: '.rtrim(rtrim(number_format($anterior, 6, '.', ''), '0'), '.')
                .' → '.rtrim(rtrim(number_format($nueva, 6, '.', ''), '0'), '.'),
        ]);
    }
}
