<?php

namespace App\Services\Stock;

use App\Models\Compras\Ordencompra;
use App\Repositories\Compras\OrdencompraRepositoryInterface;
use App\Services\Compras\OrdencompraAnitaSyncService;
use App\Support\Compras\ArticuloProveedorPrecioListaSupport;
use App\Support\Stock\RecepcionProveedorDiferenciaSupport;
use App\Support\Stock\RecepcionProveedorConversionSupport;
use App\Support\Stock\RecepcionProveedorDepositoSupport;
use App\Support\Stock\RecepcionProveedorFormItemsSupport;
use App\Support\Stock\RecepcionProveedorOcPendienteSupport;
use App\Support\Stock\RecepcionProveedorParteUnicaSupport;
use App\Support\Stock\RecepcionProveedorVisibilidadSupport;
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
        $oc = $this->buscarOcConRelaciones($numeroOc);

        if (! $oc) {
            $resultado = $this->ordencompraAnitaSyncService->traerRegistroDeAnita($numeroOc);
            if (in_array($resultado, ['importado', 'omitido', 'lineas_completadas'], true)) {
                $oc = $this->buscarOcConRelaciones($numeroOc);
            }
        } elseif ($oc->ordencompra_articulos->isEmpty()) {
            $this->ordencompraAnitaSyncService->completarLineasFaltantesDesdeAnita($numeroOc);
            $oc = $this->buscarOcConRelaciones($numeroOc);
        }

        if (! $oc) {
            throw new \RuntimeException("Orden de compra {$numeroOc} inexistente en AnitaERP y en Anita.");
        }

        if (! RecepcionProveedorVisibilidadSupport::ordencompraAccesible((int) $oc->id)) {
            throw new \RuntimeException("Orden de compra {$numeroOc} no encontrada o sin acceso.");
        }

        $this->ensurePenvpOrdenEnLineasOc($oc);

        return [
            'cabecera' => $oc,
            'lineas' => $this->armarLineasPrecarga($oc),
        ];
    }

    public function resolverPorId(int $ordencompraId): array
    {
        if (! RecepcionProveedorVisibilidadSupport::ordencompraAccesible($ordencompraId)) {
            throw new \RuntimeException('Orden de compra no encontrada o sin acceso.');
        }

        $oc = $this->ordencompraRepository->find($ordencompraId);
        $this->ensurePenvpOrdenEnLineasOc($oc);

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
        $recibidosPorLinea = RecepcionProveedorOcPendienteSupport::cantidadesRecibidasPorLineaOc((int) $oc->id);

        RecepcionProveedorDepositoSupport::reiniciarCache();
        $depositoIds = [];
        $articulosOc = $oc->ordencompra_articulos
            ->sortBy([['penvp_orden', 'asc'], ['id', 'asc']])
            ->values();

        foreach ($articulosOc as $ocArt) {
            $depArt = (int) ($ocArt->articulos->depositoentrega_id ?? 0);
            if ($depArt > 0) {
                $depositoIds[] = $depArt;
            }
        }
        $depositosQuery = \App\Models\Stock\Depmae::query()
            ->whereIn('id', array_unique($depositoIds))
            ->paraUsuarioAutorizado();
        if ($empresaId > 0) {
            $depositosQuery->paraEmpresa($empresaId);
        }
        $depositos = $depositoIds !== []
            ? $depositosQuery->get()->keyBy('id')
            : collect();

        $articuloIdsOc = $articulosOc->pluck('articulo_id')->filter()->unique()->values()->all();
        $articulosProveedor = ($proveedorId > 0 && $articuloIdsOc !== [])
            ? \App\Models\Stock\Articulo_Proveedor::query()
                ->with('unidadesmedidacompra')
                ->where('proveedor_id', $proveedorId)
                ->whereIn('articulo_id', $articuloIdsOc)
                ->where('activo', true)
                ->orderByDesc('preferido')
                ->orderBy('id')
                ->get()
                ->unique('articulo_id')
                ->keyBy('articulo_id')
            : collect();

        foreach ($articulosOc as $ocArt) {
            if ((string) ($ocArt->estado_linea_oc ?? \App\Support\Compras\OrdencompraLineaEstados::ACTIVA)
                === \App\Support\Compras\OrdencompraLineaEstados::CERRADA) {
                continue;
            }

            $articulo = $ocArt->articulos;
            $coefProveedor = RecepcionProveedorDepositoSupport::coeficienteProveedor(
                (int) $ocArt->articulo_id,
                $proveedorId
            );
            $depositoEntregaId = RecepcionProveedorDepositoSupport::depositoEntregaVisible(
                (int) ($articulo->depositoentrega_id ?? 0) ?: null,
                $empresaId
            ) ?? 0;
            $depositoEntrega = $depositoEntregaId > 0 ? $depositos->get($depositoEntregaId) : null;
            $coefArticulo = (float) ($articulo->coeficienteconversion ?? 0);
            $esFormula = RecepcionProveedorDepositoSupport::esDepositoFormula($depositoEntrega);
            $insumo = ($esFormula && $depositoEntrega !== null)
                ? RecepcionProveedorDepositoSupport::resolverArticuloInsumo($articulo, $empresaId)
                : null;
            if ($insumo !== null && ! $insumo->relationLoaded('unidadesdemedidas')) {
                $insumo->load('unidadesdemedidas');
            }
            $coefEfectivo = ($esFormula && $coefArticulo > 0) ? $coefArticulo : $coefProveedor;

            $etiquetasUm = RecepcionProveedorFormItemsSupport::resolverEtiquetasUnidadLinea(
                [],
                $articulo,
                $insumo,
                $articulosProveedor->get((int) $ocArt->articulo_id)
            );

            $precioLista = null;
            if ($proveedorId > 0 && $ocArt->articulo_id) {
                $precioLista = ArticuloProveedorPrecioListaSupport::precioVigente(
                    (int) $ocArt->articulo_id,
                    $proveedorId,
                    null,
                    $oc->fecha ? Carbon::parse($oc->fecha)->format('Y-m-d') : date('Y-m-d')
                );
            }

            $cantidadOc = (float) $ocArt->cantidad;
            $recibido = (float) ($recibidosPorLinea[$ocArt->id] ?? 0);
            $cantidadPendiente = max(0, $cantidadOc - $recibido);
            $penvpOrden = (int) ($ocArt->penvp_orden ?? 0);
            $penvpNroInterno = (int) ($ocArt->penvp_nro_interno ?? 0);

            $lineas[] = [
                '_empresa_id' => $empresaId,
                'orden' => $orden++,
                'penvp_orden' => $penvpOrden > 0 ? $penvpOrden : null,
                'penvp_nro_interno' => $penvpNroInterno > 0 ? $penvpNroInterno : null,
                'tipo_linea' => RecepcionProveedorDiferenciaSupport::TIPO_OC,
                'ordencompra_articulo_id' => $ocArt->id,
                'articulo_id' => $ocArt->articulo_id,
                'sku' => $articulo->sku ?? '',
                'descripcion' => $articulo->descripcion ?? ($ocArt->detalle ?? ''),
                'cantidad_oc' => $cantidadOc,
                'cantidad_recibida' => $recibido,
                'cantidad' => $cantidadPendiente,
                'cantidad_rechazada' => 0,
                'accion_linea_oc' => $cantidadPendiente > 0.000001
                    ? \App\Support\Stock\RecepcionProveedorAccionLineaOc::RECIBIR
                    : '',
                'fl_cerrar_linea_oc' => false,
                'comentario_diferencia' => '',
                'motivorechazo' => '',
                'cantidad_stock' => RecepcionProveedorConversionSupport::cantidadStock($cantidadPendiente, $coefEfectivo),
                'coeficienteconversion' => $coefEfectivo,
                'coeficiente_proveedor' => $coefProveedor,
                'coeficiente_articulo' => $coefArticulo > 0 ? $coefArticulo : 1,
                'um_compra' => $etiquetasUm['um_compra'],
                'um_stock' => $etiquetasUm['um_stock'],
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
                'codigo_proveedor' => trim((string) ($precioLista['codigo_articulo_proveedor'] ?? $articulo->skuproveedor ?? '')),
                'moneda_id' => (int) ($ocArt->moneda_id ?: 1),
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

    private function ensurePenvpOrdenEnLineasOc(Ordencompra $oc): void
    {
        $oc->loadMissing('ordencompra_articulos');
        $this->ordencompraAnitaSyncService->reconciliarLineasOcDesdeAnita((int) $oc->numeroordencompra);

        $tieneSinDatosAnita = $oc->ordencompra_articulos->contains(
            static fn ($ocArt) => empty($ocArt->penvp_nro_interno) || (int) $ocArt->penvp_nro_interno <= 0
                || empty($ocArt->penvp_orden) || (int) $ocArt->penvp_orden <= 0
        );

        if ($tieneSinDatosAnita) {
            $this->ordencompraAnitaSyncService->sincronizarPenvpOrdenDesdeAnita((int) $oc->numeroordencompra);
        }

        $oc->load('ordencompra_articulos.articulos');
    }

    private function buscarOcConRelaciones(int $numeroOc): ?Ordencompra
    {
        return Ordencompra::query()
            ->with([
                'empresas', 'proveedores', 'centrocostos',
                'ordencompra_articulos' => static fn ($q) => $q->orderBy('penvp_orden')->orderBy('id'),
                'ordencompra_articulos.articulos.unidadesdemedidas',
                'ordencompra_articulos.monedas',
                'ordencompra_articulos.centrocostos_destino',
            ])
            ->where('numeroordencompra', $numeroOc)
            ->first();
    }
}
