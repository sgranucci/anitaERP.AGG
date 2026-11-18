<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Precio;
use App\Support\Stock\ListaPrecioArchivoParserSupport;
use App\Support\Stock\PrecioSoloFacturableSupport;
use Illuminate\Http\UploadedFile;

class ListaPrecioArchivoImportPreviewService
{
    private const MAX_FILAS_MUESTRA = 50;

    /**
     * @return array<string, mixed>
     */
    public function previsualizar(UploadedFile $archivo, ?int $listaprecioId = null): array
    {
        try {
            $parseado = ListaPrecioArchivoParserSupport::parsearUploaded($archivo);
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'mensaje' => $e->getMessage(),
            ];
        }

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

        $skus = array_values(array_unique(array_map(
            static fn (array $i): string => $i['sku'],
            $parseado['items']
        )));

        $articulos = Articulo::query()
            ->select('id', 'sku', 'descripcion', 'nofactura')
            ->whereIn('sku', $skus)
            ->get()
            ->keyBy(static fn (Articulo $a): string => (string) $a->sku);

        $preciosExistentes = [];
        if ($listaDestino !== null && ! empty($parseado['vigencia_ymd'])) {
            $ids = $articulos->pluck('id')->all();
            if ($ids !== []) {
                $preciosExistentes = Precio::query()
                    ->where('listaprecio_id', $listaDestino['id'])
                    ->whereDate('fechavigencia', $parseado['vigencia_ymd'])
                    ->whereIn('articulo_id', $ids)
                    ->get(['articulo_id', 'precio'])
                    ->keyBy('articulo_id')
                    ->all();
            }
        }

        $filas = [];
        $importables = 0;
        $omitidas = 0;
        $sinArticulo = 0;
        $noFacturable = 0;

        foreach ($parseado['items'] as $item) {
            $sku = $item['sku'];
            /** @var Articulo|null $articulo */
            $articulo = $articulos->get($sku);
            $estado = 'ok';
            $mensaje = 'Listo para importar';
            $articuloDesc = null;
            $articuloId = null;
            $precioActual = null;
            $accion = 'crear';

            if (! $articulo) {
                $estado = 'omitido';
                $mensaje = 'SKU no encontrado en artículos';
                $omitidas++;
                $sinArticulo++;
            } elseif ((string) $articulo->nofactura !== PrecioSoloFacturableSupport::NOFACTURA_FACTURABLE) {
                $estado = 'omitido';
                $mensaje = 'Artículo no facturable';
                $omitidas++;
                $noFacturable++;
                $articuloDesc = (string) $articulo->descripcion;
                $articuloId = (int) $articulo->id;
            } else {
                $articuloDesc = (string) $articulo->descripcion;
                $articuloId = (int) $articulo->id;
                $importables++;
                if (isset($preciosExistentes[$articuloId])) {
                    $accion = 'actualizar';
                    $precioActual = (float) $preciosExistentes[$articuloId]->precio;
                    $mensaje = 'Actualizará precio vigente de la misma fecha';
                }
            }

            if ($listaDestino === null && $estado === 'ok') {
                $mensaje = 'Elija la lista de precios destino';
            }

            $filas[] = [
                'fila' => $item['fila'],
                'sku' => $sku,
                'detalle_archivo' => $item['detalle'],
                'seccion' => $item['seccion'],
                'precio' => $item['precio'],
                'precio_texto' => number_format($item['precio'], 2, ',', '.'),
                'articulo_id' => $articuloId,
                'articulo_descripcion' => $articuloDesc,
                'precio_actual' => $precioActual,
                'accion' => $accion,
                'estado' => $estado,
                'mensaje' => $mensaje,
            ];
        }

        $muestra = array_slice($filas, 0, self::MAX_FILAS_MUESTRA);
        $advertencias = $parseado['advertencias'];
        if ($listaDestino === null) {
            $advertencias[] = 'Seleccione la lista de precios destino para habilitar la importación.';
        }
        if ($sinArticulo > 0) {
            $advertencias[] = $sinArticulo.' SKU del archivo no existen en el maestro de artículos.';
        }

        return [
            'ok' => true,
            'fuente' => $parseado['fuente'],
            'titulo' => $parseado['titulo'],
            'vigencia' => $parseado['vigencia'],
            'vigencia_ymd' => $parseado['vigencia_ymd'],
            'lista_destino' => $listaDestino,
            'resumen' => [
                'total_filas' => count($filas),
                'importables' => $importables,
                'omitidas' => $omitidas,
                'sin_articulo' => $sinArticulo,
                'no_facturable' => $noFacturable,
                'secciones' => count(array_unique(array_filter(array_column($filas, 'seccion')))),
            ],
            'filas' => $muestra,
            'hay_mas_filas' => count($filas) > self::MAX_FILAS_MUESTRA,
            'advertencias' => array_values(array_unique($advertencias)),
        ];
    }
}
