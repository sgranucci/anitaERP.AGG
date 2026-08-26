<?php

namespace App\Services\Stock;

use App\Imports\Stock\PrecioImportLecturaCruda;
use App\Support\Stock\PrecioImportColumnasSupport;
use App\Support\Stock\RecuentoImportColumnasSupport;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class RecuentoImportPreviewService
{
    private const MAX_FILAS_MUESTRA = 25;

    public function __construct(private readonly RecuentoService $recuentoService) {}

    /**
     * @return array<string, mixed>
     */
    public function previsualizar(
        UploadedFile $archivo,
        ?int $depositoId,
        string $colSku,
        string $colCantidad,
        ?string $colDetalle,
        ?string $colColor,
        ?string $colTalle,
        ?int $filaEncabezadoManual,
        ?int $hojaIndice1Based = null
    ): array {
        $colSku = trim($colSku) !== '' ? trim($colSku) : RecuentoImportColumnasSupport::COL_SKU_DEFAULT;
        $colCantidad = trim($colCantidad) !== '' ? trim($colCantidad) : RecuentoImportColumnasSupport::COL_CANTIDAD_DEFAULT;
        $colDetalle = trim((string) $colDetalle) !== '' ? trim((string) $colDetalle) : RecuentoImportColumnasSupport::COL_DETALLE_DEFAULT;
        $colColor = trim((string) $colColor) !== '' ? trim((string) $colColor) : RecuentoImportColumnasSupport::COL_COLOR_DEFAULT;
        $colTalle = trim((string) $colTalle) !== '' ? trim((string) $colTalle) : RecuentoImportColumnasSupport::COL_TALLE_DEFAULT;

        $hojas = PrecioImportColumnasSupport::hojasParaSelector($archivo);
        $hojaIndice0 = PrecioImportColumnasSupport::indiceHojaDesdeRequest($hojaIndice1Based, count($hojas));
        $hojaSeleccionada = $hojas[$hojaIndice0] ?? $hojas[0] ?? ['indice' => 1, 'nombre' => 'Hoja1'];

        $hoja = Excel::toArray(new PrecioImportLecturaCruda(), $archivo)[$hojaIndice0] ?? [];
        if ($hoja === []) {
            return $this->anexarMetaHojas([
                'ok' => false,
                'mensaje' => 'La hoja seleccionada ('.($hojaSeleccionada['nombre'] ?? ('#'.$hojaIndice0)).') no tiene filas legibles.',
                'lineas' => [],
            ], $hojas, $hojaSeleccionada);
        }

        $filaEncabezado = RecuentoImportColumnasSupport::detectarFilaEncabezado($archivo, $filaEncabezadoManual, $hojaIndice0);
        $indiceEncabezado = $filaEncabezado - 1;
        $encabezados = $hoja[$indiceEncabezado] ?? [];

        if (! is_array($encabezados) || ! RecuentoImportColumnasSupport::pareceFilaEncabezado($encabezados)) {
            return $this->anexarMetaHojas([
                'ok' => false,
                'mensaje' => 'No se detectó fila de encabezados en la fila '.$filaEncabezado
                    .' de «'.($hojaSeleccionada['nombre'] ?? '').'». Indique la fila manualmente.',
                'fila_encabezado' => $filaEncabezado,
                'lineas' => [],
            ], $hojas, $hojaSeleccionada);
        }

        $colSkuInfo = RecuentoImportColumnasSupport::resolverColumna(
            $encabezados,
            $colSku,
            RecuentoImportColumnasSupport::COL_SKU_DEFAULT,
            RecuentoImportColumnasSupport::ALIAS_SKU
        );
        $colCantidadInfo = RecuentoImportColumnasSupport::resolverColumna(
            $encabezados,
            $colCantidad,
            RecuentoImportColumnasSupport::COL_CANTIDAD_DEFAULT,
            RecuentoImportColumnasSupport::ALIAS_CANTIDAD
        );
        $colDetalleInfo = RecuentoImportColumnasSupport::resolverColumna(
            $encabezados,
            $colDetalle,
            RecuentoImportColumnasSupport::COL_DETALLE_DEFAULT,
            RecuentoImportColumnasSupport::ALIAS_DETALLE
        );
        $colColorInfo = RecuentoImportColumnasSupport::resolverColumna(
            $encabezados,
            $colColor,
            RecuentoImportColumnasSupport::COL_COLOR_DEFAULT,
            RecuentoImportColumnasSupport::ALIAS_COLOR
        );
        $colTalleInfo = RecuentoImportColumnasSupport::resolverColumna(
            $encabezados,
            $colTalle,
            RecuentoImportColumnasSupport::COL_TALLE_DEFAULT,
            RecuentoImportColumnasSupport::ALIAS_TALLE
        );

        $advertencias = [];
        if ($colSkuInfo === null) {
            $advertencias[] = 'No se encontró columna SKU (buscando «'.$colSku.'» y alias habituales).';
        }
        if ($colCantidadInfo === null) {
            $advertencias[] = 'No se encontró columna de cantidad (buscando «'.$colCantidad.'», «contado», etc.).';
        }
        if ($depositoId === null || $depositoId <= 0) {
            $advertencias[] = 'Elija el depósito para calcular el saldo del sistema y cargar las líneas.';
        }

        $filasCrudas = [];
        for ($i = $indiceEncabezado + 1, $total = count($hoja); $i < $total; $i++) {
            $fila = $hoja[$i] ?? [];
            if (! is_array($fila) || RecuentoImportColumnasSupport::filaVacia($fila)) {
                continue;
            }

            $sku = RecuentoImportColumnasSupport::normalizarSkuCelda(
                PrecioImportColumnasSupport::valorCeldaFila($fila, $colSkuInfo)
            );
            $cantidad = RecuentoImportColumnasSupport::normalizarCantidad(
                PrecioImportColumnasSupport::valorCeldaFila($fila, $colCantidadInfo)
            );
            $detalle = trim((string) PrecioImportColumnasSupport::valorCeldaFila($fila, $colDetalleInfo));
            $color = trim((string) PrecioImportColumnasSupport::valorCeldaFila($fila, $colColorInfo));
            $talle = trim((string) PrecioImportColumnasSupport::valorCeldaFila($fila, $colTalleInfo));

            if ($sku === '' && ($cantidad === null || $cantidad == 0.0) && $detalle === '') {
                continue;
            }

            $filasCrudas[] = [
                'fila_excel' => $i + 1,
                'sku' => $sku,
                'cantidad_contada' => $cantidad ?? 0.0,
                'detalle' => $detalle !== '' ? $detalle : null,
                'color' => RecuentoImportColumnasSupport::esValorVacioColorTalle($color) ? null : $color,
                'talle' => RecuentoImportColumnasSupport::esValorVacioColorTalle($talle) ? null : $talle,
            ];
        }

        $evaluacion = $this->recuentoService->evaluarFilasImportacion(
            $depositoId && $depositoId > 0 ? $depositoId : null,
            $filasCrudas
        );

        $muestra = array_slice($evaluacion['evaluaciones'], 0, self::MAX_FILAS_MUESTRA);

        return $this->anexarMetaHojas([
            'ok' => $colSkuInfo !== null && $colCantidadInfo !== null && ($evaluacion['resumen']['importables'] ?? 0) > 0,
            'preview' => true,
            'fila_encabezado' => $filaEncabezado,
            'fila_encabezado_automatica' => $filaEncabezadoManual === null,
            'columnas' => [
                'sku' => $this->presentarColumna($colSku, $colSkuInfo),
                'cantidad' => $this->presentarColumna($colCantidad, $colCantidadInfo),
                'detalle' => $this->presentarColumna($colDetalle, $colDetalleInfo, false),
                'color' => $this->presentarColumna($colColor, $colColorInfo, false),
                'talle' => $this->presentarColumna($colTalle, $colTalleInfo, false),
            ],
            'resumen' => $evaluacion['resumen'],
            'filas' => $muestra,
            'advertencias' => array_values(array_merge($advertencias, $evaluacion['advertencias'] ?? [])),
            'hay_mas_filas' => ($evaluacion['resumen']['total_filas_datos'] ?? 0) > count($muestra),
            'lineas' => $evaluacion['lineas'],
            'mensaje' => $this->mensajeResumen($evaluacion['resumen'], $colSkuInfo !== null && $colCantidadInfo !== null),
        ], $hojas, $hojaSeleccionada);
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
        $preview['hoja_seleccionada'] = (int) ($hojaSeleccionada['indice'] ?? 1);
        $preview['hoja_nombre'] = (string) ($hojaSeleccionada['nombre'] ?? '');

        if ($preview['multiple_hojas']) {
            $preview['advertencias'] = array_values(array_merge(
                ['El archivo tiene '.count($hojas).' hojas. Elija cuál importar (por defecto hoja 1).'],
                $preview['advertencias'] ?? []
            ));
        }

        return $preview;
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
     * @param  array{total_filas_datos?: int, importables?: int, omitidas?: int}  $resumen
     */
    private function mensajeResumen(array $resumen, bool $columnasOk): string
    {
        $importables = (int) ($resumen['importables'] ?? 0);
        $omitidas = (int) ($resumen['omitidas'] ?? 0);
        $total = (int) ($resumen['total_filas_datos'] ?? 0);

        if (! $columnasOk) {
            return 'Revise los nombres de columna: no se pudieron mapear SKU y/o cantidad.';
        }

        if ($importables === 0) {
            return $total === 0
                ? 'No se encontraron filas de datos debajo del encabezado.'
                : 'Ninguna fila es importable ('.$omitidas.' omitida'.($omitidas === 1 ? '' : 's').').';
        }

        $msg = $importables.' línea'.($importables === 1 ? '' : 's').' lista'.($importables === 1 ? '' : 's').' para cargar.';
        if ($omitidas > 0) {
            $msg .= ' '.$omitidas.' omitida'.($omitidas === 1 ? '' : 's').'.';
        }

        return $msg;
    }
}
