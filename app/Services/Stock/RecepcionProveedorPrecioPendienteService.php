<?php

namespace App\Services\Stock;

use App\Models\Compras\Ordencompra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Services\Configuracion\ModuloAvisoService;
use App\Services\Compras\OrdencompraRecepcionPrecioSyncService;
use App\Support\Stock\RecepcionProveedorEstados;
use App\Support\Stock\RecepcionProveedorPrecioPendienteSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecepcionProveedorPrecioPendienteService
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
        private readonly OrdencompraRecepcionPrecioSyncService $ordencompraRecepcionPrecioSyncService,
    ) {
    }

    public function evaluarTrasGuardarBorrador(Recepcion_Proveedor $recepcion, bool $notificarCompras): Recepcion_Proveedor
    {
        if ($recepcion->tipo !== Recepcion_Proveedor::TIPO_RECEPCION) {
            return $recepcion;
        }

        if (RecepcionProveedorPrecioPendienteSupport::puedeModificarPrecioEnRecepcion()) {
            if ($recepcion->fl_precio_pendiente_aprobacion) {
                $recepcion->update(['fl_precio_pendiente_aprobacion' => false]);
            }

            return $recepcion->fresh();
        }

        $recepcion->loadMissing('recepcion_proveedor_articulos');
        $pendiente = RecepcionProveedorPrecioPendienteSupport::recepcionTienePrecioSolicitadoPendiente(
            $recepcion->recepcion_proveedor_articulos
        );

        $estabaPendiente = (bool) $recepcion->fl_precio_pendiente_aprobacion;
        $recepcion->update(['fl_precio_pendiente_aprobacion' => $pendiente]);

        if ($pendiente && $notificarCompras && ! $estabaPendiente) {
            try {
                $this->moduloAvisoService->enviar(
                    'stock',
                    'recepcion_proveedor_precio_pendiente_compras',
                    (int) $recepcion->id
                );
            } catch (\Throwable $e) {
                Log::warning('RecepcionProveedorPrecioPendiente: falló aviso a compras', [
                    'recepcion_id' => $recepcion->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $recepcion->fresh();
    }

    public function assertPuedeConfirmar(Recepcion_Proveedor $recepcion): void
    {
        if ((bool) $recepcion->fl_precio_pendiente_aprobacion) {
            throw new \RuntimeException(
                'La recepción tiene cambio de precios pendiente de aprobación en compras. '
                .'Espere a que actualicen la OC o solicite la corrección.'
            );
        }
    }

    /**
     * Compras aplica precios solicitados de un borrador a la OC y libera la recepción.
     *
     * @return array{actualizadas: int, liberada: bool}
     */
    public function aplicarPreciosSolicitadosDesdeBorrador(int $recepcionId): array
    {
        if (! RecepcionProveedorPrecioPendienteSupport::puedeModificarPrecioEnOrdencompra()) {
            throw new \RuntimeException('No tiene permiso para modificar precios en la orden de compra.');
        }

        $recepcion = Recepcion_Proveedor::query()
            ->with(['recepcion_proveedor_articulos.articulos', 'ordencompras'])
            ->findOrFail($recepcionId);

        if ($recepcion->estado !== RecepcionProveedorEstados::BORRADOR) {
            throw new \RuntimeException('Solo aplica a recepciones en BORRADOR.');
        }

        if (! $recepcion->fl_precio_pendiente_aprobacion) {
            throw new \RuntimeException('La recepción no tiene precios pendientes de aprobación.');
        }

        $oc = $recepcion->ordencompras;
        if ($oc === null) {
            throw new \RuntimeException('Recepción sin orden de compra vinculada.');
        }

        return DB::transaction(function () use ($recepcion, $oc) {
            $actualizadas = $this->ordencompraRecepcionPrecioSyncService
                ->actualizarPreciosDesdePreciosSolicitadosBorrador($recepcion->fresh(['recepcion_proveedor_articulos']));

            $ocFresh = Ordencompra::query()
                ->with('ordencompra_articulos')
                ->findOrFail((int) $oc->id);

            if (! RecepcionProveedorPrecioPendienteSupport::ocCoincideConPreciosSolicitados($ocFresh, $recepcion->fresh(['recepcion_proveedor_articulos']))) {
                throw new \RuntimeException(
                    'Tras aplicar, la OC aún no coincide con los precios solicitados. Revise las líneas.'
                );
            }

            $this->liberarRecepcion($recepcion->fresh(['recepcion_proveedor_articulos']), $ocFresh);

            return ['actualizadas' => $actualizadas, 'liberada' => true];
        });
    }

    /**
     * Tras actualizar precios en OC por cualquier vía, intenta liberar borradores colgados.
     *
     * @return list<int> IDs de recepciones liberadas
     */
    public function liberarRecepcionesPendientesPorOc(int $ordencompraId): array
    {
        $oc = Ordencompra::query()->with('ordencompra_articulos')->find($ordencompraId);
        if ($oc === null) {
            return [];
        }

        $recepciones = Recepcion_Proveedor::query()
            ->with('recepcion_proveedor_articulos')
            ->where('ordencompra_id', $ordencompraId)
            ->where('estado', RecepcionProveedorEstados::BORRADOR)
            ->where('fl_precio_pendiente_aprobacion', true)
            ->get();

        $liberadas = [];
        foreach ($recepciones as $recepcion) {
            if (! RecepcionProveedorPrecioPendienteSupport::ocCoincideConPreciosSolicitados($oc, $recepcion)) {
                continue;
            }

            DB::transaction(function () use ($recepcion, $oc, &$liberadas) {
                $this->liberarRecepcion($recepcion, $oc);
                $liberadas[] = (int) $recepcion->id;
            });
        }

        return $liberadas;
    }

    private function liberarRecepcion(Recepcion_Proveedor $recepcion, Ordencompra $oc): void
    {
        RecepcionProveedorPrecioPendienteSupport::aplicarPreciosOcALineasRecepcion($recepcion, $oc);

        $recepcion->update([
            'fl_precio_pendiente_aprobacion' => false,
            'fl_precio_diferencia' => false,
            'comentario_precio' => null,
        ]);

        try {
            $this->moduloAvisoService->enviar(
                'stock',
                'recepcion_proveedor_precio_liberado',
                (int) $recepcion->id
            );
        } catch (\Throwable $e) {
            Log::warning('RecepcionProveedorPrecioPendiente: falló aviso al usuario', [
                'recepcion_id' => $recepcion->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
