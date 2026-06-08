<?php

namespace App\Services\Stock;

use App\Models\Compras\Ordencompra;
use App\Repositories\Compras\OrdencompraRepositoryInterface;
use App\Services\Compras\OrdencompraAnitaSyncService;
use App\Support\Compras\ArticuloProveedorPrecioListaSupport;
use App\Support\Stock\RecepcionProveedorDiferenciaSupport;
use App\Support\Stock\RecepcionProveedorConversionSupport;
use App\Support\Stock\RecepcionProveedorDepositoSupport;
use Illuminate\Support\Carbon;

class RecepcionProveedorOrdencompraResolverService
{
    public function __construct(
        private readonly OrdencompraRepositoryInterface $ordencompraRepository,
        private readonly OrdencompraAnitaSyncService $ordencompraAnitaSyncService,
    ) {
    }

    /**
     * @return array{cabecera: Ordencompra, lineas: list<array<string, mixed>>}
     */
    public function resolverPorNumeroOc(int $numeroOc, int $usuarioId): array
    {
        $oc = Ordencompra::query()
            ->with([
                'empresas', 'proveedores', 'centrocostos',
                'ordencompra_articulos.articulos', 'ordencompra_articulos.monedas',
                'ordencompra_articulos.centrocostos_destino',
            ])
            ->where('numeroordencompra', $numeroOc)
            ->first();

        if (! $oc) {
            $resultado = $this->ordencompraAnitaSyncService->traerRegistroDeAnita($numeroOc);
            if ($resultado === 'importado' || $resultado === 'omitido') {
                $oc = Ordencompra::query()
                    ->with([
                        'empresas', 'proveedores', 'centrocostos',
                        'ordencompra_articulos.articulos', 'ordencompra_articulos.monedas',
                        'ordencompra_articulos.centrocostos_destino',
                    ])
                    ->where('numeroordencompra', $numeroOc)
                    ->first();
            }
        }

        if (! $oc) {
            throw new \RuntimeException("Orden de compra {$numeroOc} inexistente en AnitaERP y en Anita.");
        }

        return [
            'cabecera' => $oc,
            'lineas' => $this->armarLineasPrecarga($oc),
        ];
    }

    public function resolverPorId(int $ordencompraId): array
    {
        $oc = $this->ordencompraRepository->find($ordencompraId);

        return [
            'cabecera' => $oc,
            'lineas' => $this->armarLineasPrecarga($oc),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function armarLineasPrecarga(Ordencompra $oc): array
    {
        $lineas = [];
        $orden = 1;
        $proveedorId = (int) $oc->proveedor_id;
        $empresaId = (int) $oc->empresa_id;
        RecepcionProveedorDepositoSupport::reiniciarCache();
        $depositoIds = [];
        foreach ($oc->ordencompra_articulos as $ocArt) {
            $depArt = (int) ($ocArt->articulos->depositoentrega_id ?? 0);
            if ($depArt > 0) {
                $depositoIds[] = $depArt;
            }
        }
        $depositos = $depositoIds !== []
            ? \App\Models\Stock\Depmae::query()->whereIn('id', array_unique($depositoIds))->get()->keyBy('id')
            : collect();

        foreach ($oc->ordencompra_articulos as $ocArt) {
            $articulo = $ocArt->articulos;
            $coefProveedor = RecepcionProveedorDepositoSupport::coeficienteProveedor(
                (int) $ocArt->articulo_id,
                $proveedorId
            );
            $depositoEntregaId = (int) ($articulo->depositoentrega_id ?? 0);
            $depositoEntrega = $depositoEntregaId > 0 ? $depositos->get($depositoEntregaId) : null;
            $coefArticulo = (float) ($articulo->coeficienteconversion ?? 0);
            $esFormula = RecepcionProveedorDepositoSupport::esDepositoFormula($depositoEntrega);
            $insumo = ($esFormula && $depositoEntrega !== null)
                ? RecepcionProveedorDepositoSupport::resolverArticuloInsumo($articulo, $empresaId)
                : null;
            $coefEfectivo = ($esFormula && $coefArticulo > 0) ? $coefArticulo : $coefProveedor;

            $precioLista = null;
            if ($proveedorId > 0 && $ocArt->articulo_id) {
                $precioLista = ArticuloProveedorPrecioListaSupport::precioVigente(
                    (int) $ocArt->articulo_id,
                    $proveedorId,
                    null,
                    $oc->fecha ? Carbon::parse($oc->fecha)->format('Y-m-d') : date('Y-m-d')
                );
            }

            $cantidadPendiente = max(0, (float) $ocArt->cantidad);

            $lineas[] = [
                'orden' => $orden++,
                'tipo_linea' => RecepcionProveedorDiferenciaSupport::TIPO_OC,
                'ordencompra_articulo_id' => $ocArt->id,
                'articulo_id' => $ocArt->articulo_id,
                'sku' => $articulo->sku ?? '',
                'descripcion' => $articulo->nombre ?? ($ocArt->detalle ?? ''),
                'cantidad_oc' => (float) $ocArt->cantidad,
                'cantidad' => $cantidadPendiente,
                'cantidad_stock' => RecepcionProveedorConversionSupport::cantidadStock($cantidadPendiente, $coefEfectivo),
                'coeficienteconversion' => $coefEfectivo,
                'coeficiente_proveedor' => $coefProveedor,
                'coeficiente_articulo' => $coefArticulo > 0 ? $coefArticulo : 1,
                'depositoentrega_id' => $depositoEntregaId > 0 ? $depositoEntregaId : null,
                'deposito_id' => $depositoEntregaId > 0 ? $depositoEntregaId : null,
                'deposito_nombre' => $depositoEntrega->nombre ?? '',
                'es_deposito_formula' => $esFormula,
                'articulo_stock_id' => $insumo?->id,
                'articulo_stock_sku' => $insumo?->sku,
                'skualternativo' => $articulo->skualternativo ?? '',
                'precio' => (float) $ocArt->precio,
                'precio_ordencompra' => (float) $ocArt->precio,
                'precio_lista_proveedor' => $precioLista,
                'moneda_id' => $ocArt->moneda_id,
                'cotizacion' => (float) ($ocArt->cotizacion ?: 1),
                'descuento' => (float) ($ocArt->descuento ?? 0),
                'centrocosto_id' => $ocArt->centrocostodestino_id ?? $oc->centrocosto_id,
                'detalle' => $ocArt->detalle,
                'maneja_parte_unica' => RecepcionProveedorParteUnicaSupport::articuloManejaParteUnica($articulo),
                'incluyeimpuesto' => 'N',
                'impuesto_id' => null,
            ];
        }

        return $lineas;
    }
}
