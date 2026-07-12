<?php

namespace App\Services\Stock;

use App\Imports\Stock\PrecioImportLecturaCruda;
use App\Models\Stock\Articulo;
use App\Models\Stock\Listaprecio;
use App\Support\Stock\PrecioImportColumnasSupport;
use App\Support\Stock\PrecioSoloFacturableSupport;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class PrecioImportPreviewService
{
    private const MAX_FILAS_MUESTRA = 25;

    /**
     * @return array<string, mixed>
     */
    public function previsualizar(
        UploadedFile $archivo,
        string $formato,
        ?int $listaprecioId,
        ?string $colSku,
        ?string $colDescripcion,
        ?string $colPrecio,
        ?int $filaEncabezadoManual,
        ?int $hojaIndice1Based = null
    ): array {
        $colSku = trim($colSku ?? '') !== '' ? trim((string) $colSku) : PrecioImportColumnasSupport::COL_SKU_DEFAULT;
        $colDescripcion = trim($colDescripcion ?? '') !== ''
            ? trim((string) $colDescripcion)
            : PrecioImportColumnasSupport::COL_DESCRIPCION_DEFAULT;
        $colPrecio = trim($colPrecio ?? '') !== '' ? trim((string) $colPrecio) : PrecioImportColumnasSupport::COL_PRECIO_DEFAULT;

        $hojas = PrecioImportColumnasSupport::hojasParaSelector($archivo);
        $hojaIndice0 = PrecioImportColumnasSupport::indiceHojaDesdeRequest($hojaIndice1Based, count($hojas));
        $hojaSeleccionada = $hojas[$hojaIndice0] ?? $hojas[0];

        $hoja = Excel::toArray(new PrecioImportLecturaCruda(), $archivo)[$hojaIndice0] ?? [];
        if ($hoja === []) {
            return $this->anexarMetaHojas([
                'ok' => false,
                'mensaje' => 'La hoja seleccionada ('.($hojaSeleccionada['nombre'] ?? ('#'.$hojaIndice0)).') no tiene filas legibles.',
            ], $hojas, $hojaSeleccionada);
        }

        $filaEncabezado = PrecioImportColumnasSupport::detectarFilaEncabezado($archivo, $filaEncabezadoManual, $hojaIndice0);
        $indiceEncabezado = $filaEncabezado - 1;
        $encabezados = $hoja[$indiceEncabezado] ?? [];

        if (! is_array($encabezados) || ! PrecioImportColumnasSupport::pareceFilaEncabezado($encabezados)) {
            return $this->anexarMetaHojas([
                'ok' => false,
                'mensaje' => 'No se detectó fila de encabezados en la fila '.$filaEncabezado.' de «'.($hojaSeleccionada['nombre'] ?? '').'». Revise el archivo o indique la fila manualmente.',
                'fila_encabezado' => $filaEncabezado,
            ], $hojas, $hojaSeleccionada);
        }

        if ($formato === PrecioImportColumnasSupport::FORMATO_LISTAS) {
            return $this->anexarMetaHojas(
                $this->previsualizarListas($hoja, $indiceEncabezado, $encabezados, $filaEncabezado, $filaEncabezadoManual),
                $hojas,
                $hojaSeleccionada
            );
        }

        return $this->anexarMetaHojas(
            $this->previsualizarSimple(
                $hoja,
                $indiceEncabezado,
                $encabezados,
                $filaEncabezado,
                $filaEncabezadoManual,
                $listaprecioId,
                $colSku,
                $colDescripcion,
                $colPrecio
            ),
            $hojas,
            $hojaSeleccionada
        );
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
                [
                    'El archivo tiene '.count($hojas).' hojas. Elija cuál importar (por defecto hoja 1).',
                ],
                $preview['advertencias'] ?? []
            ));
        }

        return $preview;
    }

    /**
     * @param  list<array<int, mixed>>  $hoja
     * @param  array<int, mixed>  $encabezados
     * @return array<string, mixed>
     */
    private function previsualizarSimple(
        array $hoja,
        int $indiceEncabezado,
        array $encabezados,
        int $filaEncabezado,
        ?int $filaEncabezadoManual,
        ?int $listaprecioId,
        string $colSku,
        string $colDescripcion,
        string $colPrecio
    ): array {
        $colSkuInfo = PrecioImportColumnasSupport::resolverColumnaEnEncabezados(
            $encabezados,
            $colSku,
            PrecioImportColumnasSupport::COL_SKU_DEFAULT,
            PrecioImportColumnasSupport::ALIAS_ENCABEZADO_SKU
        );
        $colDescripcionInfo = PrecioImportColumnasSupport::resolverColumnaEnEncabezados(
            $encabezados,
            $colDescripcion,
            PrecioImportColumnasSupport::COL_DESCRIPCION_DEFAULT,
            PrecioImportColumnasSupport::ALIAS_ENCABEZADO_DESCRIPCION
        );
        $colPrecioInfo = PrecioImportColumnasSupport::resolverColumnaEnEncabezados(
            $encabezados,
            $colPrecio,
            PrecioImportColumnasSupport::COL_PRECIO_DEFAULT,
            PrecioImportColumnasSupport::ALIAS_ENCABEZADO_PRECIO
        );

        $listaDestino = null;
        if ($listaprecioId !== null && $listaprecioId > 0) {
            $lista = Listaprecio::query()->select('id', 'codigo', 'nombre')->find($listaprecioId);
            if ($lista) {
                $listaDestino = [
                    'id' => (int) $lista->id,
                    'codigo' => (string) $lista->codigo,
                    'nombre' => (string) $lista->nombre,
                ];
            }
        }

        $advertencias = [];
        if ($colSkuInfo === null) {
            $advertencias[] = 'No se encontró columna SKU (buscando «'.$colSku.'» y alias habituales).';
        }
        if ($colPrecioInfo === null) {
            $advertencias[] = 'No se encontró columna precio (buscando «'.$colPrecio.'» y alias habituales).';
        }
        if ($listaDestino === null) {
            $advertencias[] = 'Elija la lista de precios destino para importar.';
        }

        $resumen = [
            'total_filas_datos' => 0,
            'importables' => 0,
            'omitidas' => 0,
        ];
        $filas = [];

        for ($i = $indiceEncabezado + 1, $total = count($hoja); $i < $total; $i++) {
            $fila = $hoja[$i] ?? [];
            if (! is_array($fila) || $this->filaVacia($fila)) {
                continue;
            }

            $resumen['total_filas_datos']++;
            $evaluacion = $this->evaluarFilaSimple(
                $fila,
                $i + 1,
                $colSkuInfo,
                $colDescripcionInfo,
                $colPrecioInfo,
                $listaDestino !== null
            );

            if ($evaluacion['estado'] === 'ok') {
                $resumen['importables']++;
            } else {
                $resumen['omitidas']++;
            }

            if (count($filas) < self::MAX_FILAS_MUESTRA) {
                $filas[] = $evaluacion;
            }
        }

        return [
            'ok' => $colSkuInfo !== null && $colPrecioInfo !== null,
            'formato' => PrecioImportColumnasSupport::FORMATO_SIMPLE,
            'fila_encabezado' => $filaEncabezado,
            'fila_encabezado_automatica' => $filaEncabezadoManual === null,
            'columnas' => [
                'sku' => $this->presentarColumna($colSku, $colSkuInfo),
                'descripcion' => $this->presentarColumna($colDescripcion, $colDescripcionInfo, false),
                'precio' => $this->presentarColumna($colPrecio, $colPrecioInfo),
            ],
            'lista_destino' => $listaDestino,
            'resumen' => $resumen,
            'filas' => $filas,
            'advertencias' => $advertencias,
            'hay_mas_filas' => $resumen['total_filas_datos'] > count($filas),
        ];
    }

    /**
     * @param  list<array<int, mixed>>  $hoja
     * @param  array<int, mixed>  $encabezados
     * @return array<string, mixed>
     */
    private function previsualizarListas(
        array $hoja,
        int $indiceEncabezado,
        array $encabezados,
        int $filaEncabezado,
        ?int $filaEncabezadoManual
    ): array {
        $colSkuInfo = PrecioImportColumnasSupport::resolverColumnaEnEncabezados(
            $encabezados,
            'articulo',
            'articulo',
            PrecioImportColumnasSupport::ALIAS_ENCABEZADO_SKU
        );
        $columnasListas = PrecioImportColumnasSupport::columnasListasEnEncabezados($encabezados);

        $advertencias = [];
        if ($colSkuInfo === null) {
            $advertencias[] = 'No se encontró columna «articulo» (SKU).';
        }
        if ($columnasListas === []) {
            $advertencias[] = 'No se encontraron columnas L_<código> de listas de precio.';
        } else {
            foreach ($columnasListas as $columnaLista) {
                if ($columnaLista['listaprecio_id'] === null) {
                    $advertencias[] = 'Columna '.$columnaLista['titulo'].': lista código «'.$columnaLista['codigo_lista'].'» no existe en el sistema.';
                }
            }
        }

        $resumen = [
            'total_filas_datos' => 0,
            'importables' => 0,
            'omitidas' => 0,
            'precios_detectados' => 0,
        ];
        $filas = [];

        for ($i = $indiceEncabezado + 1, $total = count($hoja); $i < $total; $i++) {
            $fila = $hoja[$i] ?? [];
            if (! is_array($fila) || $this->filaVacia($fila)) {
                continue;
            }

            $resumen['total_filas_datos']++;
            $evaluacion = $this->evaluarFilaListas($fila, $i + 1, $colSkuInfo, $columnasListas);

            if ($evaluacion['estado'] === 'ok') {
                $resumen['importables']++;
                $resumen['precios_detectados'] += (int) ($evaluacion['precios_count'] ?? 0);
            } else {
                $resumen['omitidas']++;
            }

            if (count($filas) < self::MAX_FILAS_MUESTRA) {
                $filas[] = $evaluacion;
            }
        }

        return [
            'ok' => $colSkuInfo !== null && $columnasListas !== [],
            'formato' => PrecioImportColumnasSupport::FORMATO_LISTAS,
            'fila_encabezado' => $filaEncabezado,
            'fila_encabezado_automatica' => $filaEncabezadoManual === null,
            'columnas' => [
                'sku' => $this->presentarColumna('articulo', $colSkuInfo),
                'listas' => array_map(static fn (array $c) => [
                    'titulo' => $c['titulo'],
                    'codigo_lista' => $c['codigo_lista'],
                    'encontrada' => $c['listaprecio_id'] !== null,
                    'listaprecio_nombre' => $c['listaprecio_nombre'],
                ], $columnasListas),
            ],
            'lista_destino' => null,
            'resumen' => $resumen,
            'filas' => $filas,
            'advertencias' => $advertencias,
            'hay_mas_filas' => $resumen['total_filas_datos'] > count($filas),
        ];
    }

    /**
     * @param  array<int, mixed>  $fila
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $colSkuInfo
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $colDescripcionInfo
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $colPrecioInfo
     * @return array<string, mixed>
     */
    private function evaluarFilaSimple(
        array $fila,
        int $filaExcel,
        ?array $colSkuInfo,
        ?array $colDescripcionInfo,
        ?array $colPrecioInfo,
        bool $listaSeleccionada
    ): array {
        $sku = trim((string) PrecioImportColumnasSupport::valorCeldaFila($fila, $colSkuInfo));
        $descripcion = trim((string) PrecioImportColumnasSupport::valorCeldaFila($fila, $colDescripcionInfo));
        $precio = PrecioImportColumnasSupport::normalizarValorPrecio(
            PrecioImportColumnasSupport::valorCeldaFila($fila, $colPrecioInfo)
        );

        $base = [
            'fila_excel' => $filaExcel,
            'sku' => $sku,
            'descripcion' => $descripcion,
            'precio' => $precio,
            'precio_texto' => $precio !== null ? number_format($precio, 2, ',', '.') : '',
        ];

        if ($sku === '') {
            return $base + ['estado' => 'omitido', 'mensaje' => 'SKU vacío'];
        }

        if ($precio === null || $precio == 0.0) {
            return $base + ['estado' => 'omitido', 'mensaje' => 'Precio vacío o cero'];
        }

        if (! $listaSeleccionada) {
            return $base + ['estado' => 'omitido', 'mensaje' => 'Falta elegir lista destino'];
        }

        $articulo = Articulo::query()
            ->select('id', 'sku', 'descripcion', 'detalle', 'nofactura')
            ->where('sku', $sku)
            ->first();

        if (! $articulo) {
            return $base + ['estado' => 'omitido', 'mensaje' => 'SKU no encontrado'];
        }

        if ((string) $articulo->nofactura !== PrecioSoloFacturableSupport::NOFACTURA_FACTURABLE) {
            return $base + ['estado' => 'omitido', 'mensaje' => 'Artículo no facturable'];
        }

        $descArticulo = trim((string) ($articulo->descripcion ?: $articulo->detalle ?: $articulo->sku));

        return $base + [
            'estado' => 'ok',
            'mensaje' => 'Se importará',
            'articulo_id' => (int) $articulo->id,
            'articulo_descripcion' => $descArticulo,
        ];
    }

    /**
     * @param  array<int, mixed>  $fila
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $colSkuInfo
     * @param  list<array{indice: int, titulo: string, codigo_lista: string, listaprecio_id: ?int, listaprecio_nombre: ?string}>  $columnasListas
     * @return array<string, mixed>
     */
    private function evaluarFilaListas(array $fila, int $filaExcel, ?array $colSkuInfo, array $columnasListas): array
    {
        $sku = trim((string) PrecioImportColumnasSupport::valorCeldaFila($fila, $colSkuInfo));
        $precios = [];

        foreach ($columnasListas as $columnaLista) {
            if ($columnaLista['listaprecio_id'] === null) {
                continue;
            }

            $precio = PrecioImportColumnasSupport::normalizarValorPrecio(
                PrecioImportColumnasSupport::valorCeldaFila($fila, [
                    'indice' => $columnaLista['indice'],
                    'titulo' => $columnaLista['titulo'],
                    'clave_normalizada' => '',
                ])
            );

            if ($precio !== null && $precio != 0.0) {
                $precios[] = [
                    'columna' => $columnaLista['titulo'],
                    'lista' => $columnaLista['listaprecio_nombre'],
                    'precio' => $precio,
                    'precio_texto' => number_format($precio, 2, ',', '.'),
                ];
            }
        }

        $base = [
            'fila_excel' => $filaExcel,
            'sku' => $sku,
            'precios' => $precios,
            'precios_count' => count($precios),
        ];

        if ($sku === '') {
            return $base + ['estado' => 'omitido', 'mensaje' => 'SKU vacío', 'descripcion' => ''];
        }

        if ($precios === []) {
            return $base + ['estado' => 'omitido', 'mensaje' => 'Sin precios > 0 en columnas L_', 'descripcion' => ''];
        }

        $articulo = Articulo::query()
            ->select('id', 'descripcion', 'detalle', 'nofactura')
            ->where('sku', $sku)
            ->first();

        if (! $articulo) {
            return $base + ['estado' => 'omitido', 'mensaje' => 'SKU no encontrado', 'descripcion' => ''];
        }

        if ((string) $articulo->nofactura !== PrecioSoloFacturableSupport::NOFACTURA_FACTURABLE) {
            return $base + ['estado' => 'omitido', 'mensaje' => 'Artículo no facturable', 'descripcion' => ''];
        }

        $descArticulo = trim((string) ($articulo->descripcion ?: $articulo->detalle ?: ''));

        return $base + [
            'estado' => 'ok',
            'mensaje' => count($precios).' precio(s) a importar',
            'descripcion' => $descArticulo,
            'articulo_id' => (int) $articulo->id,
        ];
    }

    /**
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $info
     * @return array{configurado: string, encontrada: bool, titulo: ?string, requerida: bool}
     */
    private function presentarColumna(string $configurado, ?array $info, bool $requerida = true): array
    {
        return [
            'configurado' => $configurado,
            'encontrada' => $info !== null,
            'titulo' => $info['titulo'] ?? null,
            'requerida' => $requerida,
        ];
    }

    /**
     * @param  array<int, mixed>  $fila
     */
    private function filaVacia(array $fila): bool
    {
        foreach ($fila as $valor) {
            if (trim((string) $valor) !== '') {
                return false;
            }
        }

        return true;
    }
}
