<?php

namespace App\Services\Compras;

use App\Imports\Stock\PrecioImportLecturaCruda;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Proveedor;
use App\Support\Compras\ListaprecioProveedorImportColumnasSupport as Cols;
use App\Support\Stock\PrecioImportColumnasSupport;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class ListaprecioProveedorImportPreviewService
{
    private const MAX_FILAS_MUESTRA = 25;

    private const MAX_LINEAS_GRILLA = 3000;

    private const MAX_LINEAS_PERSISTIR = 50000;

    /**
     * @return array<string, mixed>
     */
    public function previsualizar(
        UploadedFile $archivo,
        ?int $proveedorId,
        ?string $colSku,
        ?string $colDescripcion,
        ?string $colPrecio,
        ?string $colDescuento,
        ?string $colCodigoProveedor,
        ?int $filaEncabezadoManual,
        ?int $hojaIndice1Based = null,
        bool $incluirTodasLasLineas = false
    ): array {
        $colSku = $this->nombreODefault($colSku, Cols::COL_SKU_DEFAULT);
        $colDescripcion = $this->nombreODefault($colDescripcion, Cols::COL_DESCRIPCION_DEFAULT);
        $colPrecio = $this->nombreODefault($colPrecio, Cols::COL_PRECIO_DEFAULT);
        $colDescuento = $this->nombreODefault($colDescuento, Cols::COL_DESCUENTO_DEFAULT);
        $colCodigoProveedor = $this->nombreODefault($colCodigoProveedor, Cols::COL_CODIGO_PROVEEDOR_DEFAULT);

        $hojas = PrecioImportColumnasSupport::hojasParaSelector($archivo);
        $hojaIndice0 = PrecioImportColumnasSupport::indiceHojaDesdeRequest($hojaIndice1Based, count($hojas));
        $hojaSeleccionada = $hojas[$hojaIndice0] ?? $hojas[0];

        $hoja = Excel::toArray(new PrecioImportLecturaCruda(), $archivo)[$hojaIndice0] ?? [];
        if ($hoja === []) {
            return $this->anexarMetaHojas([
                'ok' => false,
                'mensaje' => 'La hoja seleccionada no tiene filas legibles.',
            ], $hojas, $hojaSeleccionada);
        }

        $filaEncabezado = Cols::detectarFilaEncabezado($archivo, $filaEncabezadoManual, $hojaIndice0);
        $indiceEncabezado = $filaEncabezado - 1;
        $encabezados = $hoja[$indiceEncabezado] ?? [];
        $sinEncabezado = ! is_array($encabezados) || ! Cols::pareceFilaEncabezado($encabezados);

        if ($sinEncabezado && $filaEncabezadoManual === null) {
            return $this->previsualizarPosicional($hoja, $hojas, $hojaSeleccionada, $proveedorId, $incluirTodasLasLineas);
        }

        if (! is_array($encabezados)) {
            return $this->anexarMetaHojas([
                'ok' => false,
                'mensaje' => 'No se pudo leer la fila de encabezados '.$filaEncabezado.'.',
                'fila_encabezado' => $filaEncabezado,
            ], $hojas, $hojaSeleccionada);
        }

        $colSkuInfo = Cols::resolverColumna($encabezados, $colSku, Cols::COL_SKU_DEFAULT, Cols::ALIAS_SKU);
        $colDescInfo = Cols::resolverColumna($encabezados, $colDescripcion, Cols::COL_DESCRIPCION_DEFAULT, Cols::ALIAS_DESCRIPCION);
        $colPrecioInfo = Cols::resolverColumnaPrecio($encabezados, $colPrecio);
        $colDtoInfo = Cols::resolverColumna($encabezados, $colDescuento, Cols::COL_DESCUENTO_DEFAULT, Cols::ALIAS_DESCUENTO);
        $colCodProvInfo = Cols::resolverColumna($encabezados, $colCodigoProveedor, Cols::COL_CODIGO_PROVEEDOR_DEFAULT, Cols::ALIAS_CODIGO_PROVEEDOR);

        return $this->anexarMetaHojas(
            $this->evaluarHoja(
                $hoja,
                $indiceEncabezado + 1,
                $filaEncabezado,
                $filaEncabezadoManual === null,
                $colSku,
                $colDescripcion,
                $colPrecio,
                $colDescuento,
                $colCodigoProveedor,
                $colSkuInfo,
                $colDescInfo,
                $colPrecioInfo,
                $colDtoInfo,
                $colCodProvInfo,
                $proveedorId,
                $this->advertenciasProveedor($proveedorId),
                $incluirTodasLasLineas
            ),
            $hojas,
            $hojaSeleccionada
        );
    }

    /**
     * Sin encabezado reconocible: A=SKU, B=precio, C=% desc, D=cód. proveedor (contrato histórico).
     *
     * @param  list<array<int, mixed>>  $hoja
     * @param  list<array{indice: int, nombre: string}>  $hojas
     * @param  array{indice: int, nombre: string}  $hojaSeleccionada
     * @return array<string, mixed>
     */
    private function previsualizarPosicional(
        array $hoja,
        array $hojas,
        array $hojaSeleccionada,
        ?int $proveedorId,
        bool $incluirTodasLasLineas
    ): array {
        $colSkuInfo = ['indice' => 0, 'titulo' => 'Columna A', 'clave_normalizada' => 'a'];
        $colPrecioInfo = ['indice' => 1, 'titulo' => 'Columna B', 'clave_normalizada' => 'b'];
        $colDtoInfo = ['indice' => 2, 'titulo' => 'Columna C', 'clave_normalizada' => 'c'];
        $colCodProvInfo = ['indice' => 3, 'titulo' => 'Columna D', 'clave_normalizada' => 'd'];

        $preview = $this->evaluarHoja(
            $hoja,
            0,
            0,
            true,
            'sku',
            Cols::COL_DESCRIPCION_DEFAULT,
            Cols::COL_PRECIO_DEFAULT,
            Cols::COL_DESCUENTO_DEFAULT,
            Cols::COL_CODIGO_PROVEEDOR_DEFAULT,
            $colSkuInfo,
            null,
            $colPrecioInfo,
            $colDtoInfo,
            $colCodProvInfo,
            $proveedorId,
            array_merge(
                ['No se detectó fila de encabezados: se asume A = SKU, B = precio, C = % descuento, D = código artículo proveedor.'],
                $this->advertenciasProveedor($proveedorId)
            ),
            $incluirTodasLasLineas
        );
        $preview['sin_encabezado'] = true;
        $preview['fila_encabezado'] = 0;

        return $this->anexarMetaHojas($preview, $hojas, $hojaSeleccionada);
    }

    /**
     * @param  list<array<int, mixed>>  $hoja
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $colSkuInfo
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $colDescInfo
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $colPrecioInfo
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $colDtoInfo
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $colCodProvInfo
     * @param  list<string>  $advertenciasIniciales
     * @return array<string, mixed>
     */
    private function evaluarHoja(
        array $hoja,
        int $indiceInicioDatos,
        int $filaEncabezado,
        bool $encabezadoAutomatico,
        string $colSku,
        string $colDescripcion,
        string $colPrecio,
        string $colDescuento,
        string $colCodigoProveedor,
        ?array $colSkuInfo,
        ?array $colDescInfo,
        ?array $colPrecioInfo,
        ?array $colDtoInfo,
        ?array $colCodProvInfo,
        ?int $proveedorId,
        array $advertenciasIniciales,
        bool $incluirTodasLasLineas = false
    ): array {
        $advertencias = $advertenciasIniciales;
        $limiteLineas = $incluirTodasLasLineas ? self::MAX_LINEAS_PERSISTIR : self::MAX_LINEAS_GRILLA;
        if ($colSkuInfo === null) {
            $advertencias[] = 'No se encontró columna SKU (sku, código, MATERIAL, artículo…).';
        }
        if ($colPrecioInfo === null) {
            $advertencias[] = 'No se encontró columna de precio (precio, importe, lista, unitario…).';
        }

        $filasCrudas = [];
        for ($i = $indiceInicioDatos, $total = count($hoja); $i < $total; $i++) {
            $fila = $hoja[$i] ?? [];
            if (! is_array($fila) || Cols::filaVacia($fila)) {
                continue;
            }
            $sku = trim((string) PrecioImportColumnasSupport::valorCeldaFila($fila, $colSkuInfo));
            $codProv = trim((string) PrecioImportColumnasSupport::valorCeldaFila($fila, $colCodProvInfo));
            $filasCrudas[] = [
                'fila_excel' => $i + 1,
                'fila' => $fila,
                'sku' => $sku,
                'codigo_proveedor' => $codProv,
            ];
        }

        $articulosPorSku = $this->indexarArticulosPorSku($filasCrudas);
        $articulosPorCodigoProv = $this->indexarArticulosPorCodigoProveedor($filasCrudas, $proveedorId);

        $resumen = [
            'total_filas_datos' => 0,
            'importables' => 0,
            'sin_sku' => 0,
            'omitidas' => 0,
        ];
        $filas = [];
        $lineas = [];
        $lineasSinSku = [];
        $errores = [];

        foreach ($filasCrudas as $cruda) {
            $resumen['total_filas_datos']++;
            $eval = $this->evaluarFila(
                $cruda['fila'],
                $cruda['fila_excel'],
                $colSkuInfo,
                $colDescInfo,
                $colPrecioInfo,
                $colDtoInfo,
                $colCodProvInfo,
                $articulosPorSku,
                $articulosPorCodigoProv
            );

            if ($eval['estado'] === 'ok') {
                $resumen['importables']++;
                if (count($lineas) < $limiteLineas) {
                    $lineas[] = [
                        'articulo_id' => $eval['articulo_id'],
                        'sku' => $eval['sku_erp'] ?: $eval['sku'],
                        'descripcion' => $eval['articulo_descripcion'] ?? '',
                        'precio' => $eval['precio'],
                        'descuento' => $eval['descuento'],
                        'codigo_articulo_proveedor' => $eval['codigo_proveedor'] !== ''
                            ? $eval['codigo_proveedor']
                            : ($eval['sku'] ?: $eval['sku_erp']),
                    ];
                }
            } elseif ($eval['estado'] === 'sin_sku') {
                $resumen['sin_sku']++;
                if (count($lineasSinSku) < $limiteLineas) {
                    $lineasSinSku[] = [
                        'sku' => $eval['sku'],
                        'descripcion' => $eval['descripcion'],
                        'precio' => $eval['precio'],
                        'descuento' => $eval['descuento'],
                        'codigo_articulo_proveedor' => $eval['codigo_proveedor'],
                    ];
                }
            } else {
                $resumen['omitidas']++;
                if ($eval['mensaje'] !== '' && count($errores) < 40) {
                    $errores[] = 'Fila '.$eval['fila_excel'].': '.$eval['mensaje'];
                }
            }

            if (count($filas) < self::MAX_FILAS_MUESTRA) {
                $filas[] = $eval;
            }
        }

        if (! $incluirTodasLasLineas && $resumen['importables'] > self::MAX_LINEAS_GRILLA) {
            $advertencias[] = 'Hay más de '.self::MAX_LINEAS_GRILLA.' ítems importables; se cargan los primeros en la grilla. El resto use Importar y grabar en una lista ya guardada.';
        }
        if ($resumen['sin_sku'] > 0) {
            $advertencias[] = $resumen['sin_sku'].' precio(s) sin SKU en el maestro: se muestran como informativos y no se graban.';
        }

        $ok = $colPrecioInfo !== null;

        return [
            'ok' => $ok,
            'fila_encabezado' => $filaEncabezado,
            'fila_encabezado_automatica' => $encabezadoAutomatico,
            'sin_encabezado' => false,
            'columnas' => [
                'sku' => Cols::presentarColumna($colSku, $colSkuInfo),
                'descripcion' => Cols::presentarColumna($colDescripcion, $colDescInfo, false),
                'precio' => Cols::presentarColumna($colPrecio, $colPrecioInfo),
                'descuento' => Cols::presentarColumna($colDescuento, $colDtoInfo, false),
                'codigo_proveedor' => Cols::presentarColumna($colCodigoProveedor, $colCodProvInfo, false),
            ],
            'resumen' => $resumen,
            'filas' => $filas,
            'lineas' => $lineas,
            'lineas_sin_sku' => $lineasSinSku,
            'errores' => $errores,
            'advertencias' => $advertencias,
            'hay_mas_filas' => $resumen['total_filas_datos'] > count($filas),
        ];
    }

    /**
     * @param  array<int, mixed>  $fila
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $colSkuInfo
     * @param  array<string, Articulo>  $articulosPorSku
     * @param  array<string, Articulo>  $articulosPorCodigoProv
     * @return array<string, mixed>
     */
    private function evaluarFila(
        array $fila,
        int $filaExcel,
        ?array $colSkuInfo,
        ?array $colDescInfo,
        ?array $colPrecioInfo,
        ?array $colDtoInfo,
        ?array $colCodProvInfo,
        array $articulosPorSku,
        array $articulosPorCodigoProv
    ): array {
        $sku = trim((string) PrecioImportColumnasSupport::valorCeldaFila($fila, $colSkuInfo));
        $descripcion = trim((string) PrecioImportColumnasSupport::valorCeldaFila($fila, $colDescInfo));
        $codProv = trim((string) PrecioImportColumnasSupport::valorCeldaFila($fila, $colCodProvInfo));
        $precio = PrecioImportColumnasSupport::normalizarValorPrecio(
            PrecioImportColumnasSupport::valorCeldaFila($fila, $colPrecioInfo)
        );
        $descuentoRaw = PrecioImportColumnasSupport::normalizarValorPrecio(
            PrecioImportColumnasSupport::valorCeldaFila($fila, $colDtoInfo)
        );
        $descuento = $descuentoRaw === null ? 0.0 : min(100, max(0, $descuentoRaw));

        $base = [
            'fila_excel' => $filaExcel,
            'sku' => $sku,
            'descripcion' => $descripcion,
            'codigo_proveedor' => $codProv,
            'precio' => $precio,
            'precio_texto' => $precio !== null ? number_format($precio, 4, ',', '.') : '',
            'descuento' => $descuento,
            'sku_erp' => '',
        ];

        if ($precio === null) {
            return $base + ['estado' => 'omitido', 'mensaje' => 'Falta precio'];
        }
        if ($sku === '' && $codProv === '' && $descripcion === '') {
            return $base + ['estado' => 'omitido', 'mensaje' => 'Fila vacía'];
        }

        $articulo = $this->resolverArticulo($sku, $codProv, $articulosPorSku, $articulosPorCodigoProv);
        if ($articulo === null) {
            $clave = $sku !== '' ? $sku : ($codProv !== '' ? $codProv : $descripcion);
            $mensaje = $sku === '' && $codProv === ''
                ? 'Sin SKU en el Excel (informativo)'
                : 'SKU no está en el maestro (informativo): '.$clave;

            return array_merge($base, [
                'estado' => 'sin_sku',
                'mensaje' => $mensaje,
            ]);
        }

        $match = 'SKU exacto';
        $skuNorm = $this->normalizarClave($sku);
        $skuErpNorm = $this->normalizarClave((string) $articulo->sku);
        if ($skuNorm !== '' && $skuNorm === $skuErpNorm) {
            $match = 'SKU';
        } elseif ($codProv !== '' && isset($articulosPorCodigoProv[$this->normalizarClave($codProv)])) {
            $match = 'Código artículo proveedor';
        } elseif ($sku !== '' && isset($articulosPorCodigoProv[$skuNorm])) {
            $match = 'Código artículo proveedor (columna SKU)';
        } else {
            $match = 'SKU (sin distinguir mayúsculas)';
        }

        return array_merge($base, [
            'estado' => 'ok',
            'mensaje' => 'Se importará ('.$match.')',
            'articulo_id' => (int) $articulo->id,
            'articulo_descripcion' => trim((string) ($articulo->descripcion ?: $articulo->sku)),
            'sku_erp' => (string) $articulo->sku,
        ]);
    }

    /**
     * @param  list<array{sku: string, codigo_proveedor: string}>  $filasCrudas
     * @return array<string, Articulo>
     */
    private function indexarArticulosPorSku(array $filasCrudas): array
    {
        $skus = [];
        foreach ($filasCrudas as $fila) {
            if ($fila['sku'] !== '') {
                $skus[] = $fila['sku'];
            }
        }
        $skus = array_values(array_unique($skus));
        if ($skus === []) {
            return [];
        }

        $out = [];
        Articulo::query()
            ->select('id', 'sku', 'descripcion')
            ->whereIn('sku', $skus)
            ->get()
            ->each(function (Articulo $art) use (&$out) {
                $out[$this->normalizarClave((string) $art->sku)] = $art;
            });

        $faltantes = [];
        foreach ($skus as $sku) {
            if (! isset($out[$this->normalizarClave($sku)])) {
                $faltantes[] = $sku;
            }
        }
        if ($faltantes !== []) {
            $upper = array_values(array_unique(array_map(
                static fn ($s) => mb_strtoupper(trim((string) $s)),
                $faltantes
            )));
            $placeholders = implode(',', array_fill(0, count($upper), '?'));
            Articulo::query()
                ->select('id', 'sku', 'descripcion')
                ->whereRaw('UPPER(TRIM(sku)) IN ('.$placeholders.')', $upper)
                ->get()
                ->each(function (Articulo $art) use (&$out) {
                    $out[$this->normalizarClave((string) $art->sku)] = $art;
                });
        }

        return $out;
    }

    /**
     * @param  list<array{sku: string, codigo_proveedor: string}>  $filasCrudas
     * @return array<string, Articulo>
     */
    private function indexarArticulosPorCodigoProveedor(array $filasCrudas, ?int $proveedorId): array
    {
        if ($proveedorId === null || $proveedorId <= 0) {
            return [];
        }

        $codigos = [];
        foreach ($filasCrudas as $fila) {
            if ($fila['codigo_proveedor'] !== '') {
                $codigos[] = $fila['codigo_proveedor'];
            }
            if ($fila['sku'] !== '') {
                $codigos[] = $fila['sku'];
            }
        }
        $codigos = array_values(array_unique($codigos));
        if ($codigos === []) {
            return [];
        }

        $out = [];
        Articulo_Proveedor::query()
            ->select('articulo_id', 'codigo_articulo_proveedor')
            ->where('proveedor_id', $proveedorId)
            ->whereIn('codigo_articulo_proveedor', $codigos)
            ->with(['articulos:id,sku,descripcion'])
            ->get()
            ->each(function (Articulo_Proveedor $ap) use (&$out) {
                $art = $ap->articulos;
                if (! $art) {
                    return;
                }
                $out[$this->normalizarClave((string) $ap->codigo_articulo_proveedor)] = $art;
            });

        return $out;
    }

    /**
     * @param  array<string, Articulo>  $porSku
     * @param  array<string, Articulo>  $porCodigoProv
     */
    private function resolverArticulo(string $sku, string $codProv, array $porSku, array $porCodigoProv): ?Articulo
    {
        if ($sku !== '' && isset($porSku[$this->normalizarClave($sku)])) {
            return $porSku[$this->normalizarClave($sku)];
        }
        if ($codProv !== '' && isset($porCodigoProv[$this->normalizarClave($codProv)])) {
            return $porCodigoProv[$this->normalizarClave($codProv)];
        }
        if ($sku !== '' && isset($porCodigoProv[$this->normalizarClave($sku)])) {
            return $porCodigoProv[$this->normalizarClave($sku)];
        }

        return null;
    }

    private function normalizarClave(string $valor): string
    {
        return mb_strtoupper(trim(str_replace("\xc2\xa0", ' ', $valor)));
    }

    /**
     * @return list<string>
     */
    private function advertenciasProveedor(?int $proveedorId): array
    {
        if ($proveedorId === null || $proveedorId <= 0) {
            return ['Elija el proveedor de la lista para cruzar también por código de artículo proveedor.'];
        }

        return [];
    }

    private function nombreODefault(?string $valor, string $default): string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? $valor : $default;
    }

    /**
     * @param  array<string, mixed>  $preview
     * @param  list<array{indice: int, nombre: string}>  $hojas
     * @param  array{indice: int, nombre: string}  $hojaSeleccionada
     * @return array<string, mixed>
     */
    private function anexarMetaHojas(array $preview, array $hojas, array $hojaSeleccionada): array
    {
        $preview['hojas'] = $hojas;
        $preview['multiple_hojas'] = count($hojas) > 1;
        $preview['hoja_seleccionada'] = (int) $hojaSeleccionada['indice'];
        $preview['hoja_nombre'] = (string) $hojaSeleccionada['nombre'];

        if ($preview['multiple_hojas']) {
            $preview['advertencias'] = array_values(array_merge(
                ['El archivo tiene '.count($hojas).' hojas. Elija cuál importar (por defecto hoja 1).'],
                $preview['advertencias'] ?? []
            ));
        }

        return $preview;
    }
}
