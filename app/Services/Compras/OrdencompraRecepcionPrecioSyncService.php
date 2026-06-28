<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Articulo;
use App\Support\Compras\OrdencompraArticuloPrecioHistoriaOrigen;
use App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport;
use App\Support\Stock\RecepcionProveedorAnitaOrdenLineaSupport;
use App\Support\Stock\RecepcionProveedorAnitaWhereSupport;
use App\Support\Stock\RecepcionProveedorDiferenciaSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use Illuminate\Support\Facades\Log;

/**
 * Propaga a la OC (ERP + Anita pendmovp) el precio confirmado en recepción cuando difiere del pedido.
 */
class OrdencompraRecepcionPrecioSyncService
{
    public function __construct(
        private readonly OrdencompraArticuloPrecioHistoriaService $precioHistoriaService,
    ) {}

    /**
     * @return int Cantidad de líneas OC actualizadas
     */
    public function actualizarPreciosDesdeRecepcion(
        Recepcion_Proveedor $recepcion,
        bool $soloPendientes = true,
        string $origen = OrdencompraArticuloPrecioHistoriaOrigen::RECEPCION_CONFIRMADA,
    ): int {
        if (! config('recepcion_proveedor.actualizar_precio_oc_al_confirmar', true)) {
            return 0;
        }

        if ($recepcion->tipo !== Recepcion_Proveedor::TIPO_RECEPCION) {
            return 0;
        }

        if ($recepcion->estado !== RecepcionProveedorEstados::CONFIRMADA) {
            throw new \RuntimeException('Solo se pueden aplicar precios desde recepciones CONFIRMADAS.');
        }

        $recepcion->loadMissing([
            'ordencompras',
            'recepcion_proveedor_articulos.articulos',
        ]);

        $oc = $recepcion->ordencompras;
        if (! $oc) {
            return 0;
        }

        $actualizadas = 0;
        $cambiosLegajo = [];

        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            if (! $this->lineaAplicaActualizacionPrecio($linea, $soloPendientes)) {
                continue;
            }

            $ocArt = Ordencompra_Articulo::query()->find((int) $linea->ordencompra_articulo_id);
            if (! $ocArt || (int) $ocArt->ordencompra_id !== (int) $oc->id) {
                continue;
            }

            $precioAnterior = (float) $ocArt->precio;
            $precioRec = (float) $linea->precio;

            if ($soloPendientes && abs($precioAnterior - $precioRec) < 0.0001) {
                continue;
            }

            if (abs($precioAnterior - $precioRec) < 0.0001) {
                continue;
            }

            $ocArt->update(['precio' => $precioRec]);
            $this->actualizarPendmovpPrecio($recepcion, $linea, $precioRec);

            $this->precioHistoriaService->registrar(
                $ocArt,
                $precioAnterior,
                $precioRec,
                $recepcion,
                $linea,
                $origen,
            );

            $sku = trim((string) ($linea->articulos?->sku ?? ''));
            if ($sku === '') {
                $sku = 'Art.'.(int) ($ocArt->articulo_id ?? 0);
            }
            $cambiosLegajo[] = [
                'sku' => $sku,
                'precio_anterior' => $precioAnterior,
                'precio_nuevo' => $precioRec,
            ];

            $actualizadas++;
        }

        if ($actualizadas > 0) {
            $this->precioHistoriaService->registrarResumenLegajo($oc, $recepcion, $cambiosLegajo, $origen);
        }

        return $actualizadas;
    }

    private function lineaAplicaActualizacionPrecio(Recepcion_Proveedor_Articulo $linea, bool $soloPendientes): bool
    {
        if (! RecepcionProveedorAnitaOrdenLineaSupport::aplicaPendmovp($linea)) {
            return false;
        }

        if ((string) ($linea->tipo_linea ?? RecepcionProveedorDiferenciaSupport::TIPO_OC) === RecepcionProveedorDiferenciaSupport::TIPO_EXTRA) {
            return false;
        }

        $ocArtId = (int) ($linea->ordencompra_articulo_id ?? 0);
        if ($ocArtId <= 0) {
            return false;
        }

        $precioOcSnap = (float) ($linea->precio_ordencompra ?? 0);
        $precioRec = (float) ($linea->precio ?? 0);

        $tieneDiff = (bool) $linea->fl_precio_diferencia
            || ($precioOcSnap > 0 && abs($precioRec - $precioOcSnap) >= 0.0001);

        if (! $tieneDiff) {
            return false;
        }

        return true;
    }

    private function actualizarPendmovpPrecio(
        Recepcion_Proveedor $recepcion,
        Recepcion_Proveedor_Articulo $linea,
        float $precio
    ): void {
        $oc = $recepcion->ordencompras;
        if (! $oc) {
            return;
        }

        $cfg = config('recepcion_proveedor.anita');
        $claveOc = [
            'tipo' => $cfg['oc_tipo'],
            'letra' => $cfg['oc_letra'],
            'sucursal' => (int) $cfg['oc_sucursal'],
            'nro' => (int) $oc->numeroordencompra,
        ];

        $articulo = $linea->articulos;
        $sku = trim((string) ($articulo->sku ?? ''));
        $nroInterno = RecepcionProveedorAnitaOrdenLineaSupport::nroInternoLinea($linea);
        $penvpOrden = (int) ($linea->penvp_orden ?? 0);

        if ($nroInterno <= 0 && $penvpOrden <= 0) {
            Log::warning('OrdencompraRecepcionPrecioSync: línea sin clave pendmovp', [
                'recepcion_id' => $recepcion->id,
                'linea_id' => $linea->id,
                'sku' => $sku,
                'oc' => $oc->numeroordencompra,
            ]);

            return;
        }

        $where = RecepcionProveedorAnitaWhereSupport::pendmovpLinea(
            $claveOc,
            (int) $oc->numeroordencompra,
            $nroInterno,
            $penvpOrden,
            $sku
        );

        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => $cfg['sistema_compras'],
            'tabla' => $cfg['tablas']['oc_linea'],
            'whereArmado' => $where,
            'valores' => RecepcionProveedorAnitaEscrituraSupport::pendmovpPrecioUpdateSet($precio),
        ], 'OC precio desde recepción '.$recepcion->id.' línea '.$linea->id);
    }
}
