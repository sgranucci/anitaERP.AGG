<?php

namespace App\Support\Stock;

use App\Models\Compras\Ordencompra;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Proveedor;
use App\Models\Stock\Depmae;
use App\Support\Compras\OrdencompraDescuentoSupport;

class RecepcionProveedorFormItemsSupport
{
    /**
     * Repuebla ítems enviados en old() con SKU, descripción, depósito y flags de conversión
     * para que la grilla JS se renderice igual que tras precargar la OC.
     *
     * @param  list<array<string, mixed>>|array<int, array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function enriquecerItemsParaVista(
        array $items,
        ?int $depositoCabeceraId = null,
        ?int $proveedorId = null,
        ?int $empresaId = null
    ): array {
        if ($items === []) {
            return [];
        }

        RecepcionProveedorDepositoSupport::reiniciarCache();

        $articuloIds = array_values(array_unique(array_filter(array_map(
            static fn (array $item): int => (int) ($item['articulo_id'] ?? 0),
            $items
        ))));

        $articulos = $articuloIds !== []
            ? Articulo::query()->with('unidadesdemedidas')->whereIn('id', $articuloIds)->get()->keyBy('id')
            : collect();

        $articulosProveedor = ($proveedorId !== null && $proveedorId > 0 && $articuloIds !== [])
            ? Articulo_Proveedor::query()
                ->with('unidadesmedidacompra')
                ->where('proveedor_id', $proveedorId)
                ->whereIn('articulo_id', $articuloIds)
                ->where('activo', true)
                ->orderByDesc('preferido')
                ->orderBy('id')
                ->get()
                ->unique('articulo_id')
                ->keyBy('articulo_id')
            : collect();

        $depositoIds = [];
        if ($depositoCabeceraId !== null && $depositoCabeceraId > 0) {
            $depositoIds[] = $depositoCabeceraId;
        }
        foreach ($articulos as $articulo) {
            $depArt = (int) ($articulo->depositoentrega_id ?? 0);
            if ($depArt > 0) {
                $depositoIds[] = $depArt;
            }
        }
        $empresaFiltroDepositos = $empresaId ?? 0;
        if ($empresaFiltroDepositos <= 0) {
            foreach ($items as $itemEmp) {
                if (! is_array($itemEmp)) {
                    continue;
                }
                $empresaItem = (int) ($itemEmp['_empresa_id'] ?? 0);
                if ($empresaItem > 0) {
                    $empresaFiltroDepositos = $empresaItem;
                    break;
                }
            }
        }
        $depositosQuery = Depmae::query()
            ->whereIn('id', array_unique($depositoIds))
            ->paraUsuarioAutorizado();
        if ($empresaFiltroDepositos > 0) {
            $depositosQuery->paraEmpresa($empresaFiltroDepositos);
        }
        $depositos = $depositoIds !== []
            ? $depositosQuery->get()->keyBy('id')
            : collect();

        $enriquecidos = [];
        foreach (array_values($items) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $articuloId = (int) ($item['articulo_id'] ?? 0);
            $articulo = $articulos->get($articuloId);
            $proveedorLinea = $proveedorId ?? (int) ($item['_proveedor_id'] ?? 0);

            $empresaLinea = $empresaId ?? (int) ($item['_empresa_id'] ?? 0);

            $depositoLineaId = (int) ($item['deposito_id'] ?? 0);
            if ($depositoLineaId <= 0 && $articulo !== null) {
                try {
                    $depositoLineaId = RecepcionProveedorDepositoSupport::resolverDepositoLinea($depositoCabeceraId, $articulo);
                } catch (\RuntimeException) {
                    $depositoLineaId = 0;
                }
            }
            if (
                ($depositoCabeceraId === null || $depositoCabeceraId <= 0)
                && $depositoLineaId > 0
                && $empresaLinea > 0
                && ! RecepcionProveedorDepositoSupport::depositoPermitidoUsuario($depositoLineaId, $empresaLinea)
            ) {
                $depositoLineaId = 0;
            }
            $deposito = $depositoLineaId > 0 ? $depositos->get($depositoLineaId) : null;
            if ($deposito === null && $depositoLineaId > 0) {
                $deposito = Depmae::query()->find($depositoLineaId);
            }

            $coefProveedor = (float) ($item['coeficiente_proveedor'] ?? 0);
            if ($coefProveedor <= 0 && $articulo !== null && $proveedorLinea > 0) {
                $coefProveedor = RecepcionProveedorDepositoSupport::coeficienteProveedor(
                    $articuloId,
                    $proveedorLinea
                );
            }
            if ($coefProveedor <= 0) {
                $coefProveedor = (float) ($item['coeficienteconversion'] ?? 1) ?: 1;
            }

            $coefArticulo = (float) ($item['coeficiente_articulo'] ?? 0);
            if ($coefArticulo <= 0 && $articulo !== null) {
                $coefArticulo = (float) ($articulo->coeficienteconversion ?? 1) ?: 1;
            }

            $esFormula = ! empty($item['es_deposito_formula'])
                || RecepcionProveedorDepositoSupport::esDepositoFormula($deposito);
            $insumo = ($esFormula && $articulo !== null)
                ? RecepcionProveedorDepositoSupport::resolverArticuloInsumo($articulo, $empresaLinea > 0 ? $empresaLinea : null)
                : null;
            if ($insumo !== null && ! $insumo->relationLoaded('unidadesdemedidas')) {
                $insumo->load('unidadesdemedidas');
            }

            $coefEfectivo = (! empty($item['es_deposito_formula']) || $esFormula) && $coefArticulo > 0
                ? $coefArticulo
                : $coefProveedor;

            $etiquetasUm = self::resolverEtiquetasUnidadLinea(
                $item,
                $articulo,
                $insumo,
                $articulosProveedor->get($articuloId)
            );

            $colorId = (int) ($item['color_id'] ?? 0) ?: null;
            $talleId = (int) ($item['talle_id'] ?? 0) ?: null;
            $manejaColorTalle = ArticuloStockColorTalleSupport::articuloManejaColorTalle($articulo)
                || $colorId !== null
                || $talleId !== null;

            $enriquecidos[] = array_merge($item, [
                'moneda_id' => (int) ($item['moneda_id'] ?? 1) ?: 1,
                'cotizacion' => (float) ($item['cotizacion'] ?? 1) ?: 1,
                'sku' => $item['sku'] ?? ($articulo->sku ?? ''),
                'descripcion' => $item['descripcion'] ?? ($articulo->descripcion ?? ''),
                'deposito_id' => $depositoLineaId > 0 ? $depositoLineaId : ($item['deposito_id'] ?? null),
                'deposito_nombre' => $item['deposito_nombre'] ?? ($deposito->nombre ?? ''),
                'depositoentrega_id' => $item['depositoentrega_id'] ?? ($articulo->depositoentrega_id ?? null),
                'coeficiente_proveedor' => $coefProveedor,
                'coeficiente_articulo' => $coefArticulo,
                'coeficienteconversion' => (float) ($item['coeficienteconversion'] ?? $coefEfectivo) ?: $coefEfectivo,
                'um_compra' => $etiquetasUm['um_compra'],
                'um_stock' => $etiquetasUm['um_stock'],
                'es_deposito_formula' => $esFormula,
                'articulo_stock_id' => $item['articulo_stock_id'] ?? $insumo?->id,
                'articulo_stock_sku' => $item['articulo_stock_sku'] ?? $insumo?->sku,
                'skualternativo' => $item['skualternativo'] ?? ($articulo->skualternativo ?? ''),
                'tipoarticulo_id' => (int) ($item['tipoarticulo_id'] ?? $articulo?->tipoarticulo_id ?? 0) ?: null,
                'maneja_parte_unica' => array_key_exists('maneja_parte_unica', $item)
                    ? (bool) $item['maneja_parte_unica']
                    : RecepcionProveedorParteUnicaSupport::articuloManejaParteUnica($articulo),
                'color_id' => $colorId,
                'talle_id' => $talleId,
                'maneja_stock_color_talle' => $manejaColorTalle,
                'tipo_linea' => $item['tipo_linea'] ?? RecepcionProveedorDiferenciaSupport::TIPO_OC,
            ]);
        }

        return $enriquecidos;
    }

    /** @return array{numero_oc: ?int, proveedor_nombre: ?string, proveedor_id: ?int, empresa_id: ?int, empresa_nombre: ?string, descuento_ordencompra: float} */
    public static function datosCabeceraDesdeOrdencompra(
        ?int $ordencompraId,
        ?string $proveedorNombreOld = null,
        mixed $numeroOcOld = null
    ): array {
        $numeroOc = is_numeric($numeroOcOld) ? (int) $numeroOcOld : null;
        $proveedorNombre = $proveedorNombreOld !== null && trim($proveedorNombreOld) !== ''
            ? trim($proveedorNombreOld)
            : null;
        $proveedorId = null;
        $empresaId = null;
        $empresaNombre = null;
        $descuentoOrdencompra = 0.0;

        if ($ordencompraId !== null && $ordencompraId > 0) {
            $oc = Ordencompra::query()->with(['proveedores', 'empresas'])->find($ordencompraId);
            if ($oc !== null) {
                $numeroOc = $numeroOc ?? (int) $oc->numeroordencompra;
                $proveedorNombre = $proveedorNombre ?? optional($oc->proveedores)->nombre;
                $proveedorId = (int) $oc->proveedor_id;
                $empresaId = (int) $oc->empresa_id;
                $empresaNombre = optional($oc->empresas)->nombre;
                $descuentoOrdencompra = OrdencompraDescuentoSupport::porcentajeEfectivoDesdeOrdencompra($oc);
            }
        }

        return [
            'numero_oc' => $numeroOc,
            'proveedor_nombre' => $proveedorNombre,
            'proveedor_id' => $proveedorId,
            'empresa_id' => $empresaId,
            'empresa_nombre' => $empresaNombre,
            'descuento_ordencompra' => $descuentoOrdencompra,
        ];
    }

    /** @return array{codigo: string, nombre: string}|null */
    public static function depositoCabeceraDesdeId(?int $depositoId): ?array
    {
        if ($depositoId === null || $depositoId <= 0) {
            return null;
        }

        $deposito = Depmae::query()->find($depositoId);
        if ($deposito === null) {
            return null;
        }

        $empresaId = (int) ($deposito->empresa_id ?? 0);
        if ($empresaId > 0 && ! Depmae::autorizadoParaUsuarioYEmpresa($depositoId, $empresaId)) {
            return null;
        }

        return [
            'codigo' => (string) ($deposito->codigo ?? ''),
            'nombre' => (string) ($deposito->nombre ?? ''),
        ];
    }

    /**
     * Etiquetas compactas de unidad de compra (remito) y stock ERP para la grilla.
     *
     * @return array{um_compra: string, um_stock: string}
     */
    public static function resolverEtiquetasUnidadLinea(
        array $item,
        ?Articulo $articulo,
        ?Articulo $articuloStock,
        ?Articulo_Proveedor $articuloProveedor = null
    ): array {
        $umCompra = trim((string) ($item['ocr_unidad_compra'] ?? $item['um_compra'] ?? ''));
        if ($umCompra === '' && $articuloProveedor !== null) {
            $um = $articuloProveedor->unidadesmedidacompra;
            if ($um !== null) {
                $umCompra = trim((string) ($um->abreviatura ?: $um->nombre ?: ''));
            }
        }
        if ($umCompra === '') {
            $umCompra = 'bulto';
        }

        $stock = $articuloStock ?? $articulo;
        $umStock = trim((string) ($item['um_stock'] ?? ''));
        if ($umStock === '' && $stock !== null) {
            $um = $stock->unidadesdemedidas;
            if ($um !== null) {
                $umStock = trim((string) ($um->abreviatura ?: $um->nombre ?: ''));
            }
        }
        if ($umStock === '') {
            $umStock = 'UN';
        }

        return [
            'um_compra' => $umCompra,
            'um_stock' => $umStock,
        ];
    }

    /**
     * Grilla en edición: líneas OC pendientes + ítems ya guardados en la recepción.
     *
     * @return list<array<string, mixed>>
     */
    public static function itemsGrillaDesdeRecepcion(
        \App\Models\Stock\Recepcion_Proveedor $recepcion,
        ?int $depositoCabeceraId = null
    ): array {
        $recepcion->loadMissing([
            'recepcion_proveedor_articulos.articulos',
            'recepcion_proveedor_articulos.articulo_stock',
            'recepcion_proveedor_articulos.depositos',
            'recepcion_proveedor_articulos.ordencompra_articulos',
            'recepcion_proveedor_articulos.color',
            'recepcion_proveedor_articulos.talle',
            'ordencompras',
        ]);

        $resolver = app(\App\Services\Stock\RecepcionProveedorOrdencompraResolverService::class);
        $ocData = $resolver->resolverPorId((int) $recepcion->ordencompra_id);
        $lineasOc = $ocData['lineas'];

        $lineasOcPorOcArt = [];
        foreach ($lineasOc as $lineaOc) {
            $ocArtId = (int) ($lineaOc['ordencompra_articulo_id'] ?? 0);
            if ($ocArtId > 0) {
                $lineasOcPorOcArt[$ocArtId] = $lineaOc;
            }
        }

        $guardadasPorOcArt = [];
        $extras = [];
        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            $ocArtId = (int) ($linea->ordencompra_articulo_id ?? 0);
            if ($ocArtId > 0 && (string) ($linea->tipo_linea ?? 'OC') !== RecepcionProveedorDiferenciaSupport::TIPO_EXTRA) {
                $guardadasPorOcArt[$ocArtId] = $linea;
            } else {
                $extras[] = $linea;
            }
        }

        $items = [];
        foreach ($lineasOc as $lineaOc) {
            $ocArtId = (int) ($lineaOc['ordencompra_articulo_id'] ?? 0);
            if ($ocArtId > 0 && isset($guardadasPorOcArt[$ocArtId])) {
                $items[] = self::enriquecerLineaGrillaConSaldoOc(
                    self::mapearLineaRecepcionParaGrilla($guardadasPorOcArt[$ocArtId]),
                    $lineasOcPorOcArt[$ocArtId] ?? $lineaOc
                );
            } else {
                $items[] = $lineaOc;
            }
        }

        foreach ($extras as $linea) {
            $items[] = self::mapearLineaRecepcionParaGrilla($linea);
        }

        return self::enriquecerItemsParaVista(
            $items,
            $depositoCabeceraId,
            (int) ($recepcion->proveedor_id ?? 0) ?: null,
            (int) ($recepcion->empresa_id ?? 0) ?: null
        );
    }

    /**
     * @param  array<string, mixed>  $lineaGrilla
     * @param  array<string, mixed>  $lineaOc
     * @return array<string, mixed>
     */
    private static function enriquecerLineaGrillaConSaldoOc(array $lineaGrilla, array $lineaOc): array
    {
        if (array_key_exists('cantidad_recibida', $lineaOc)) {
            $lineaGrilla['cantidad_recibida'] = $lineaOc['cantidad_recibida'];
        }
        if (array_key_exists('cantidad_oc', $lineaOc) && ($lineaGrilla['cantidad_oc'] ?? null) === null) {
            $lineaGrilla['cantidad_oc'] = $lineaOc['cantidad_oc'];
        }
        if ((int) ($lineaGrilla['color_id'] ?? 0) <= 0 && (int) ($lineaOc['color_id'] ?? 0) > 0) {
            $lineaGrilla['color_id'] = (int) $lineaOc['color_id'];
            $lineaGrilla['color_nombre'] = (string) ($lineaOc['color_nombre'] ?? $lineaGrilla['color_nombre'] ?? '');
        }
        if ((int) ($lineaGrilla['talle_id'] ?? 0) <= 0 && (int) ($lineaOc['talle_id'] ?? 0) > 0) {
            $lineaGrilla['talle_id'] = (int) $lineaOc['talle_id'];
            $lineaGrilla['talle_nombre'] = (string) ($lineaOc['talle_nombre'] ?? $lineaGrilla['talle_nombre'] ?? '');
        }
        if (! empty($lineaOc['maneja_stock_color_talle'])) {
            $lineaGrilla['maneja_stock_color_talle'] = true;
        }

        return $lineaGrilla;
    }

    /** @return array<string, mixed> */
    private static function mapearLineaRecepcionParaGrilla(\App\Models\Stock\Recepcion_Proveedor_Articulo $linea): array
    {
        $cantLinea = (float) ($linea->cantidad ?? 0) + (float) ($linea->cantidad_rechazada ?? 0);
        if ($linea->fl_cerrar_linea_oc ?? false) {
            $accion = RecepcionProveedorAccionLineaOc::CERRAR;
        } elseif ($cantLinea <= 0.000001) {
            $accion = RecepcionProveedorAccionLineaOc::PENDIENTE;
        } else {
            $accion = RecepcionProveedorAccionLineaOc::RECIBIR;
        }

        return array_merge($linea->toArray(), [
            'moneda_id' => (int) ($linea->moneda_id ?: 1),
            'cotizacion' => (float) ($linea->cotizacion ?: 1),
            'sku' => optional($linea->articulos)->sku ?? '',
            'descripcion' => optional($linea->articulos)->descripcion
                ?? $linea->detalle
                ?? optional($linea->ordencompra_articulos)->detalle
                ?? '',
            'deposito_nombre' => optional($linea->depositos)->nombre ?? '',
            'depositoentrega_id' => optional($linea->articulos)->depositoentrega_id ?? null,
            'coeficiente_articulo' => (float) (optional($linea->articulos)->coeficienteconversion ?? 1) ?: 1,
            'coeficiente_proveedor' => (float) ($linea->coeficienteconversion ?? 1),
            'es_deposito_formula' => optional($linea->depositos)->tipodeposito === 'Formulas',
            'articulo_stock_id' => $linea->articulo_stock_id,
            'articulo_stock_sku' => optional($linea->articulo_stock)->sku ?? '',
            'skualternativo' => optional($linea->articulos)->skualternativo ?? '',
            'maneja_parte_unica' => RecepcionProveedorParteUnicaSupport::articuloManejaParteUnica($linea->articulos),
            'color_id' => $linea->color_id ? (int) $linea->color_id : null,
            'talle_id' => $linea->talle_id ? (int) $linea->talle_id : null,
            'color_nombre' => optional($linea->color)->nombre ?? '',
            'talle_nombre' => optional($linea->talle)->nombre ?? '',
            'maneja_stock_color_talle' => (bool) (optional($linea->articulos)->maneja_stock_color_talle
                ?? (($linea->color_id || $linea->talle_id) ? true : false)),
            'accion_linea_oc' => $accion,
            'fl_cerrar_linea_oc' => (bool) ($linea->fl_cerrar_linea_oc ?? false),
            'comentario_diferencia' => $linea->comentario_diferencia ?? '',
        ]);
    }
}
