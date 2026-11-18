<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Precio;
use App\Support\Stock\ListaPrecioArchivoParserSupport;
use App\Support\Stock\PrecioSoloFacturableSupport;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ListaPrecioArchivoImportService
{
    /**
     * @return array<string, mixed>
     */
    public function importar(
        UploadedFile $archivo,
        int $listaprecioId,
        int $monedaId,
        string $fechavigencia
    ): array {
        if ($listaprecioId < 1) {
            throw new RuntimeException('Debe indicar la lista de precios destino.');
        }
        if ($monedaId < 1) {
            throw new RuntimeException('Debe indicar la moneda.');
        }

        $lista = Listaprecio::query()->select('id', 'codigo', 'nombre')->find($listaprecioId);
        if (! $lista) {
            throw new RuntimeException('Lista de precios inexistente.');
        }

        $parseado = ListaPrecioArchivoParserSupport::parsearUploaded($archivo);
        $fecha = $this->parseFechaVigencia($fechavigencia);

        $skus = array_values(array_unique(array_map(
            static fn (array $i): string => $i['sku'],
            $parseado['items']
        )));

        $articulos = Articulo::query()
            ->select('id', 'sku', 'nofactura')
            ->whereIn('sku', $skus)
            ->get()
            ->keyBy(static fn (Articulo $a): string => (string) $a->sku);

        $creados = 0;
        $actualizados = 0;
        $omitidas = 0;
        $duplicadas = 0;
        $skusGrabados = [];
        $claves = [];

        DB::transaction(function () use (
            $parseado,
            $articulos,
            $lista,
            $monedaId,
            $fecha,
            &$creados,
            &$actualizados,
            &$omitidas,
            &$duplicadas,
            &$skusGrabados,
            &$claves
        ) {
            foreach ($parseado['items'] as $item) {
                $sku = $item['sku'];
                /** @var Articulo|null $articulo */
                $articulo = $articulos->get($sku);
                if (! $articulo
                    || (string) $articulo->nofactura !== PrecioSoloFacturableSupport::NOFACTURA_FACTURABLE) {
                    $omitidas++;

                    continue;
                }

                $articuloId = (int) $articulo->id;
                $clave = $articuloId.'|'.$lista->id.'|'.$fecha->format('Y-m-d');
                $duplicado = isset($claves[$clave]);

                $existente = Precio::query()
                    ->where('articulo_id', $articuloId)
                    ->where('listaprecio_id', $lista->id)
                    ->whereDate('fechavigencia', $fecha)
                    ->first();

                $payload = [
                    'articulo_id' => $articuloId,
                    'listaprecio_id' => (int) $lista->id,
                    'fechavigencia' => $fecha,
                    'moneda_id' => $monedaId,
                    'precio' => $item['precio'],
                    'precioanterior' => 0,
                    'usuarioultcambio_id' => Auth::id(),
                ];

                if ($existente) {
                    $existente->update($payload);
                    if (! $duplicado) {
                        $actualizados++;
                    }
                } else {
                    Precio::create($payload);
                    if (! $duplicado) {
                        $creados++;
                    }
                }

                if ($duplicado) {
                    $duplicadas++;
                } else {
                    $claves[$clave] = true;
                    $skusGrabados[] = $sku;
                }
            }
        });

        return [
            'filas_leidas' => count($parseado['items']),
            'filas_omitidas' => $omitidas,
            'filas_duplicadas' => $duplicadas,
            'precios_creados' => $creados,
            'precios_actualizados' => $actualizados,
            'precios_grabados' => $creados + $actualizados,
            'articulos_distintos' => count($claves),
            'skus_grabados' => array_values(array_unique($skusGrabados)),
            'fechavigencia' => $fecha->format('d/m/Y'),
            'listaprecio_id' => (int) $lista->id,
            'listaprecio_nombre' => trim(($lista->codigo ? $lista->codigo.' — ' : '').$lista->nombre),
            'fuente' => $parseado['fuente'],
            'titulo' => $parseado['titulo'],
        ];
    }

    private function parseFechaVigencia(string $fechavigencia): Carbon
    {
        $fecha = trim($fechavigencia);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return Carbon::createFromFormat('Y-m-d', $fecha)->startOfDay();
        }
        if (preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $fecha)) {
            return Carbon::createFromFormat('d-m-Y', $fecha)->startOfDay();
        }
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $fecha)) {
            return Carbon::createFromFormat('d/m/Y', $fecha)->startOfDay();
        }

        throw new RuntimeException('Fecha de vigencia inválida.');
    }
}
