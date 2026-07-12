<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Proveedor;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Articulo;
use App\Models\Stock\Unidadmedida;
use App\Support\Compras\ArticuloProveedorCodigoSyncSupport;
use App\Support\Compras\ArticuloProveedorPrecioListaSupport;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrNumeroSupport;

/**
 * Completa articulo_proveedor al persistir recepciones cuando falta catálogo proveedor↔artículo.
 * Clave de catálogo: proveedor_id + codigo_articulo_proveedor (varias marcas/códigos por artículo ERP).
 */
final class RecepcionProveedorArticuloProveedorSyncSupport
{
    /** @var array<string, int>|null */
    private static ?array $unidadesPorAbreviatura = null;

    /**
     * @param  list<array<string, mixed>>  $itemsRequest  ítems del formulario (metadatos OCR opcionales)
     */
    public static function sincronizarDesdeRecepcion(Recepcion_Proveedor $recepcion, array $itemsRequest = []): void
    {
        $proveedorId = (int) ($recepcion->proveedor_id ?? 0);
        if ($proveedorId <= 0) {
            return;
        }

        $recepcion->loadMissing(['recepcion_proveedor_articulos.articulos']);

        $metaPorArticulo = self::metaPorArticuloDesdeRequest($itemsRequest);

        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            if ((float) ($linea->cantidad ?? 0) <= 0) {
                continue;
            }

            $articuloId = (int) ($linea->articulo_id ?? 0);
            if ($articuloId <= 0) {
                continue;
            }

            $meta = $metaPorArticulo[$articuloId] ?? [];
            self::completarCatalogoProveedor(
                $proveedorId,
                $linea,
                $linea->articulos,
                $meta,
                (string) ($recepcion->fecha?->format('Y-m-d') ?? date('Y-m-d'))
            );
        }
    }

    /**
     * Vista previa de líneas que impactarán articulo_proveedor al guardar la recepción.
     *
     * @param  list<array<string, mixed>>  $itemsRequest
     * @return array{requiere_modal: bool, lineas: list<array<string, mixed>>}
     */
    public static function previewDesdeItems(int $proveedorId, array $itemsRequest, string $fechaRef): array
    {
        if ($proveedorId <= 0) {
            return ['requiere_modal' => false, 'lineas' => []];
        }

        $lineas = [];

        foreach (array_values($itemsRequest) as $formIdx => $item) {
            if (! is_array($item)) {
                continue;
            }

            if ((float) ($item['cantidad'] ?? 0) <= 0) {
                continue;
            }

            $articuloId = (int) ($item['articulo_id'] ?? 0);
            if ($articuloId <= 0) {
                continue;
            }

            $articulo = Articulo::query()->with('unidadesdemedidas')->find($articuloId);
            if ($articulo === null) {
                continue;
            }

            $evaluacion = self::evaluarLineaCatalogoPreview(
                $proveedorId,
                $articulo,
                $item,
                self::metaDesdeItem($item),
                $fechaRef,
                $formIdx
            );

            if ($evaluacion !== null) {
                $lineas[] = $evaluacion;
            }
        }

        return [
            'requiere_modal' => $lineas !== [],
            'lineas' => $lineas,
        ];
    }

    /** @return list<array{id: int, abreviatura: string, nombre: string}> */
    public static function unidadesMedidaParaModal(): array
    {
        return Unidadmedida::query()
            ->select(['id', 'abreviatura', 'nombre'])
            ->orderBy('nombre')
            ->get()
            ->map(static fn (Unidadmedida $um): array => [
                'id' => (int) $um->id,
                'abreviatura' => (string) ($um->abreviatura ?? ''),
                'nombre' => (string) ($um->nombre ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $itemsRequest
     * @return array<int, array<string, mixed>>
     */
    private static function metaPorArticuloDesdeRequest(array $itemsRequest): array
    {
        $out = [];

        foreach ($itemsRequest as $item) {
            if (! is_array($item)) {
                continue;
            }

            $articuloId = (int) ($item['articulo_id'] ?? 0);
            if ($articuloId <= 0) {
                continue;
            }

            $out[$articuloId] = self::metaDesdeItem($item);
        }

        return $out;
    }

    /** @param  array<string, mixed>  $item */
    private static function metaDesdeItem(array $item): array
    {
        return [
            'ocr_codigo_proveedor' => self::texto($item['ocr_codigo_proveedor'] ?? null),
            'ocr_descripcion_proveedor' => self::texto($item['ocr_descripcion_proveedor'] ?? null, 255),
            'ocr_codigobarra' => self::texto($item['ocr_codigobarra'] ?? null, 50),
            'ocr_unidad_compra' => self::texto($item['ocr_unidad_compra'] ?? null, 30),
            'coeficienteconversion' => isset($item['coeficienteconversion'])
                ? (float) $item['coeficienteconversion']
                : null,
            'unidadmedida_id' => isset($item['unidadmedida_id']) ? (int) $item['unidadmedida_id'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>|null
     */
    private static function evaluarLineaCatalogoPreview(
        int $proveedorId,
        Articulo $articulo,
        array $item,
        array $meta,
        string $fechaRef,
        int $formIdx
    ): ?array {
        $lineaStub = self::lineaStubDesdeItem($item);
        $datos = self::armarDatosCatalogo($articulo, $lineaStub, $meta, $proveedorId, $fechaRef);
        $codigo = $datos['codigo_articulo_proveedor'];
        $articuloId = (int) $articulo->id;

        $filaExistente = null;
        if ($codigo !== null) {
            $filaExistente = Articulo_Proveedor::query()
                ->where('proveedor_id', $proveedorId)
                ->where('codigo_articulo_proveedor', $codigo)
                ->first();
        }

        if ($codigo !== null && $filaExistente !== null && (int) $filaExistente->articulo_id !== $articuloId) {
            return self::filaPreviewRespuesta(
                $formIdx,
                $articulo,
                'conflicto',
                'Conflicto',
                false,
                $datos,
                $meta,
                $filaExistente,
                'El código ya está asociado a otro artículo en el catálogo proveedor.'
            );
        }

        if ($codigo === null) {
            return self::filaPreviewRespuesta(
                $formIdx,
                $articulo,
                'sin_codigo',
                'Opcional',
                true,
                $datos,
                $meta,
                null,
                'Sin código de proveedor detectado. Complete solo si dispone del dato; puede guardar sin completar.'
            );
        }

        if ($filaExistente === null) {
            if (! self::tieneDatosMinimos($datos)) {
                return null;
            }

            return self::filaPreviewRespuesta(
                $formIdx,
                $articulo,
                'crear',
                'Alta catálogo',
                true,
                $datos,
                $meta,
                null,
                null
            );
        }

        if (self::filaCatalogoVacia($filaExistente)) {
            return self::filaPreviewRespuesta(
                $formIdx,
                $articulo,
                'completar',
                'Completar catálogo',
                true,
                $datos,
                $meta,
                $filaExistente,
                null
            );
        }

        if (self::calcularPayloadComplemento($filaExistente, $datos) === []) {
            return null;
        }

        return self::filaPreviewRespuesta(
            $formIdx,
            $articulo,
            'complementar',
            'Complementar catálogo',
            true,
            $datos,
            $meta,
            $filaExistente,
            null
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private static function filaPreviewRespuesta(
        int $formIdx,
        Articulo $articulo,
        string $accion,
        string $accionLabel,
        bool $editable,
        array $datos,
        array $meta,
        ?Articulo_Proveedor $filaExistente,
        ?string $mensaje
    ): array {
        $umId = (int) ($datos['unidadmedida_compra_id'] ?? 0);
        $umLabel = '';
        if ($umId > 0) {
            $um = Unidadmedida::query()->find($umId);
            $umLabel = trim((string) ($um->abreviatura ?? $um->nombre ?? ''));
        }

        return [
            'form_idx' => $formIdx,
            'articulo_id' => (int) $articulo->id,
            'sku' => (string) ($articulo->sku ?? ''),
            'descripcion_erp' => (string) ($articulo->descripcion ?? ''),
            'accion' => $accion,
            'accion_label' => $accionLabel,
            'editable' => $editable,
            'mensaje' => $mensaje,
            'codigo_articulo_proveedor' => $datos['codigo_articulo_proveedor'],
            'nombre_articulo_proveedor' => $datos['nombre_articulo_proveedor'],
            'codigobarra' => $datos['codigobarra'],
            'unidadmedida_compra_id' => $umId > 0 ? $umId : null,
            'unidadmedida_compra_label' => $umLabel !== '' ? $umLabel : ($meta['ocr_unidad_compra'] ?? null),
            'unidadmedida_stock_label' => self::labelUnidadmedidaStock($articulo),
            'coeficiente_conversion' => (float) ($datos['coeficiente_conversion'] ?? 1),
            'ocr_codigo_proveedor' => $meta['ocr_codigo_proveedor'] ?? null,
            'ocr_descripcion_proveedor' => $meta['ocr_descripcion_proveedor'] ?? null,
            'ocr_codigobarra' => $meta['ocr_codigobarra'] ?? null,
            'ocr_unidad_compra' => $meta['ocr_unidad_compra'] ?? null,
            'catalogo_existente' => $filaExistente !== null ? [
                'nombre_articulo_proveedor' => $filaExistente->nombre_articulo_proveedor,
                'codigobarra' => $filaExistente->codigobarra,
                'unidadmedida_compra_id' => $filaExistente->unidadmedida_compra_id,
                'coeficiente_conversion' => $filaExistente->coeficiente_conversion,
            ] : null,
        ];
    }

    /** @param  array<string, mixed>  $item */
    private static function lineaStubDesdeItem(array $item): Recepcion_Proveedor_Articulo
    {
        $linea = new Recepcion_Proveedor_Articulo;
        $linea->forceFill([
            'articulo_id' => (int) ($item['articulo_id'] ?? 0),
            'coeficienteconversion' => (float) ($item['coeficienteconversion'] ?? 0),
            'unidadmedida_id' => (int) ($item['unidadmedida_id'] ?? 0),
        ]);

        return $linea;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function completarCatalogoProveedor(
        int $proveedorId,
        Recepcion_Proveedor_Articulo $linea,
        ?Articulo $articulo,
        array $meta,
        string $fechaRef
    ): void {
        if ($articulo === null) {
            $articulo = Articulo::query()->find((int) $linea->articulo_id);
        }
        if ($articulo === null) {
            return;
        }

        $articuloId = (int) $articulo->id;
        $datos = self::armarDatosCatalogo($articulo, $linea, $meta, $proveedorId, $fechaRef);
        $codigo = $datos['codigo_articulo_proveedor'];

        if ($codigo === null) {
            return;
        }

        $fila = Articulo_Proveedor::query()
            ->where('proveedor_id', $proveedorId)
            ->where('codigo_articulo_proveedor', $codigo)
            ->first();

        if ($fila !== null) {
            if ((int) $fila->articulo_id !== $articuloId) {
                return;
            }

            if (self::filaCatalogoVacia($fila)) {
                $payload = self::filtrarSoloValoresUtiles($datos);
                unset($payload['codigo_articulo_proveedor']);
                if ($payload !== []) {
                    $fila->update($payload);
                }
            } else {
                self::complementarFilaExistente($fila, $datos);
            }

            ArticuloProveedorCodigoSyncSupport::desdeCatalogo($articuloId, $proveedorId, $codigo);

            return;
        }

        if (! self::tieneDatosMinimos($datos)) {
            return;
        }

        Articulo_Proveedor::query()->create(array_merge($datos, [
            'articulo_id' => $articuloId,
            'proveedor_id' => $proveedorId,
            'activo' => true,
            'preferido' => false,
        ]));

        ArticuloProveedorCodigoSyncSupport::desdeCatalogo($articuloId, $proveedorId, $codigo);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{
     *   nombre_articulo_proveedor: ?string,
     *   codigobarra: ?string,
     *   codigo_articulo_proveedor: ?string,
     *   unidadmedida_compra_id: ?int,
     *   coeficiente_conversion: float
     * }
     */
    private static function armarDatosCatalogo(
        Articulo $articulo,
        Recepcion_Proveedor_Articulo $linea,
        array $meta,
        int $proveedorId,
        string $fechaRef
    ): array {
        $skuErp = RecepcionProveedorOcrNumeroSupport::normalizarSku((string) ($articulo->sku ?? ''));

        $codigo = self::resolverCodigoProveedor(
            $articulo,
            $meta['ocr_codigo_proveedor'] ?? null,
            $proveedorId,
            $fechaRef,
            $skuErp
        );

        $barra = self::normalizarCodigo($meta['ocr_codigobarra'] ?? null)
            ?? self::normalizarCodigo($articulo->codigobarra ?? null);

        $nombre = self::texto($meta['ocr_descripcion_proveedor'] ?? null, 255)
            ?? self::texto($articulo->descripcion ?? null, 255);

        $coefLinea = (float) ($linea->coeficienteconversion ?? 0);
        $coefMeta = isset($meta['coeficienteconversion']) ? (float) $meta['coeficienteconversion'] : 0;
        $coefArticulo = (float) ($articulo->coeficienteconversion ?? 0);
        $coef = $coefLinea > 0 ? $coefLinea : ($coefMeta > 0 ? $coefMeta : ($coefArticulo > 0 ? $coefArticulo : 1.0));

        $umId = self::resolverUnidadMedidaCompra(
            $meta['ocr_unidad_compra'] ?? null,
            (int) ($meta['unidadmedida_id'] ?? $linea->unidadmedida_id ?? 0),
            $articulo
        );

        return [
            'nombre_articulo_proveedor' => $nombre,
            'codigobarra' => $barra !== null ? substr($barra, 0, 50) : null,
            'codigo_articulo_proveedor' => $codigo,
            'unidadmedida_compra_id' => $umId,
            'coeficiente_conversion' => $coef > 0 ? $coef : 1.0,
        ];
    }

    private static function resolverCodigoProveedor(
        Articulo $articulo,
        ?string $ocrCodigo,
        int $proveedorId,
        string $fechaRef,
        string $skuErp
    ): ?string {
        $ocr = self::normalizarCodigo($ocrCodigo);
        if ($ocr !== null && $ocr !== '' && ($skuErp === '' || $ocr !== $skuErp)) {
            return substr($ocr, 0, 100);
        }

        foreach ([$articulo->skuproveedor, $articulo->skualternativo, $articulo->skuproveedor2] as $candidato) {
            $codigo = self::normalizarCodigo($candidato);
            if ($codigo !== null && ($skuErp === '' || $codigo !== $skuErp)) {
                return substr($codigo, 0, 100);
            }
        }

        if ($ocr !== null && $ocr !== '') {
            return substr($ocr, 0, 100);
        }

        $vigente = ArticuloProveedorPrecioListaSupport::precioVigente(
            (int) $articulo->id,
            $proveedorId,
            null,
            $fechaRef
        );
        $lista = self::normalizarCodigo($vigente['codigo_articulo_proveedor'] ?? null);

        return $lista !== null ? substr($lista, 0, 100) : null;
    }

    private static function resolverUnidadMedidaCompra(?string $ocrUnidad, int $lineaUmId, Articulo $articulo): ?int
    {
        $porOcr = self::unidadMedidaIdDesdeEtiquetaOcr($ocrUnidad);
        if ($porOcr !== null) {
            return $porOcr;
        }

        if ($lineaUmId > 0) {
            return $lineaUmId;
        }

        $umArticulo = (int) ($articulo->unidadmedida_id ?? 0);

        return $umArticulo > 0 ? $umArticulo : null;
    }

    private static function unidadMedidaIdDesdeEtiquetaOcr(?string $etiqueta): ?int
    {
        $etiqueta = self::texto($etiqueta, 30);
        if ($etiqueta === null) {
            return null;
        }

        $clave = mb_strtoupper($etiqueta);
        $mapa = self::cargarUnidadesPorAbreviatura();

        if (isset($mapa[$clave])) {
            return $mapa[$clave];
        }

        $alias = [
            'UNID' => ['UNID', 'UNIDAD', 'UNIDADES', 'UN'],
            'CAJAS' => ['CAJAS', 'CAJA', 'CAJ', 'PACK', 'PACKS', 'BOL', 'BOLS', 'BIDON', 'BIDONES'],
            'KG' => ['KG', 'KILO', 'KILOS', 'KILOGRAMO', 'KILOGRAMOS'],
            'LITROS' => ['LITRO', 'LITROS', 'L', 'LT'],
        ];

        foreach ($alias as $tokens) {
            if (! in_array($clave, $tokens, true)) {
                continue;
            }
            foreach ($tokens as $token) {
                if (isset($mapa[$token])) {
                    return $mapa[$token];
                }
            }
        }

        return null;
    }

    /** @return array<string, int> */
    private static function cargarUnidadesPorAbreviatura(): array
    {
        if (self::$unidadesPorAbreviatura !== null) {
            return self::$unidadesPorAbreviatura;
        }

        $mapa = [];
        foreach (Unidadmedida::query()->select(['id', 'abreviatura', 'nombre'])->get() as $um) {
            foreach ([$um->abreviatura, $um->nombre] as $etiqueta) {
                $norm = mb_strtoupper(trim((string) $etiqueta));
                if ($norm !== '') {
                    $mapa[$norm] = (int) $um->id;
                }
            }
        }

        self::$unidadesPorAbreviatura = $mapa;

        return $mapa;
    }

    private static function filaCatalogoVacia(Articulo_Proveedor $fila): bool
    {
        return self::normalizarCodigo($fila->codigobarra) === null
            && trim((string) ($fila->nombre_articulo_proveedor ?? '')) === '';
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private static function tieneDatosMinimos(array $datos): bool
    {
        return $datos['codigo_articulo_proveedor'] !== null;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private static function filtrarSoloValoresUtiles(array $datos): array
    {
        $out = [];
        foreach ($datos as $campo => $valor) {
            if ($campo === 'coeficiente_conversion') {
                if ((float) $valor > 0 && abs((float) $valor - 1.0) > 0.000001) {
                    $out[$campo] = (float) $valor;
                }
                continue;
            }
            if ($campo === 'unidadmedida_compra_id') {
                if ((int) $valor > 0) {
                    $out[$campo] = (int) $valor;
                }
                continue;
            }
            if ($valor !== null && trim((string) $valor) !== '') {
                $out[$campo] = $valor;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private static function complementarFilaExistente(Articulo_Proveedor $fila, array $datos): void
    {
        $payload = self::calcularPayloadComplemento($fila, $datos);

        if ($payload !== []) {
            $fila->update($payload);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private static function calcularPayloadComplemento(Articulo_Proveedor $fila, array $datos): array
    {
        $payload = [];

        if (self::normalizarCodigo($fila->codigobarra) === null && $datos['codigobarra'] !== null) {
            $payload['codigobarra'] = $datos['codigobarra'];
        }

        if (trim((string) ($fila->nombre_articulo_proveedor ?? '')) === '' && $datos['nombre_articulo_proveedor'] !== null) {
            $payload['nombre_articulo_proveedor'] = $datos['nombre_articulo_proveedor'];
        }

        if (! $fila->unidadmedida_compra_id && ! empty($datos['unidadmedida_compra_id'])) {
            $payload['unidadmedida_compra_id'] = (int) $datos['unidadmedida_compra_id'];
        }

        $coefActual = (float) ($fila->coeficiente_conversion ?? 0);
        if ($coefActual <= 0 && (float) ($datos['coeficiente_conversion'] ?? 0) > 0) {
            $payload['coeficiente_conversion'] = (float) $datos['coeficiente_conversion'];
        }

        return $payload;
    }

    private static function labelUnidadmedidaStock(Articulo $articulo): string
    {
        $articulo->loadMissing('unidadesdemedidas');
        $um = $articulo->unidadesdemedidas;
        if ($um === null) {
            return '—';
        }

        $label = trim((string) ($um->abreviatura ?? ''));
        if ($label === '') {
            $label = trim((string) ($um->nombre ?? ''));
        }

        return $label !== '' ? $label : '—';
    }

    private static function normalizarCodigo(?string $codigo): ?string
    {
        if ($codigo === null) {
            return null;
        }

        $codigo = trim($codigo);

        return $codigo === '' ? null : $codigo;
    }

    private static function texto(?string $valor, int $max = 100): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim($valor);

        return $valor === '' ? null : substr($valor, 0, $max);
    }
}
