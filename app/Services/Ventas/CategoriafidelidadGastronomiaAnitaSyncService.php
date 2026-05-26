<?php

namespace App\Services\Ventas;

use App\ApiAnita;
use App\Models\Ventas\CategoriafidelidadGastronomia;
use App\Repositories\Ventas\CategoriafidelidadArticuloGastronomiaRepositoryInterface;
use App\Repositories\Ventas\CategoriafidelidadGastronomiaRepositoryInterface;
use App\Repositories\Ventas\CategoriafidelidadEntregaGastronomiaRepositoryInterface;
use App\Support\Stock\AnitaSync\Categoriafidelidad\CategoriafidelidadArticuloFieldMapper;
use App\Support\Stock\AnitaSync\Categoriafidelidad\CategoriafidelidadEntregaFieldMapper;
use App\Support\Stock\AnitaSync\Categoriafidelidad\CategoriafidelidadFieldMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CategoriafidelidadGastronomiaAnitaSyncService
{
    public function __construct(
        private readonly CategoriafidelidadGastronomiaRepositoryInterface $categoriafidelidadRepository,
        private readonly CategoriafidelidadArticuloGastronomiaRepositoryInterface $categoriafidelidadArticuloRepository,
        private readonly CategoriafidelidadEntregaGastronomiaRepositoryInterface $categoriafidelidadEntregaRepository,
    ) {
    }

    /**
     * @return array{
     *     en_anita_categorias:int,
     *     importados:int,
     *     actualizados:int,
     *     omitidos:int,
     *     en_anita_articulos:int,
     *     articulos_importados:int,
     *     articulos_omitidos:int,
     *     en_anita_entregas:int,
     *     entregas_importadas:int,
     *     entregas_actualizadas:int,
     *     entregas_omitidas:int,
     *     errores:list<string>
     * }
     */
    public function sincronizarConAnita(): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $ret = [
            'en_anita_categorias' => 0,
            'importados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'en_anita_articulos' => 0,
            'articulos_importados' => 0,
            'articulos_omitidos' => 0,
            'en_anita_entregas' => 0,
            'entregas_importadas' => 0,
            'entregas_actualizadas' => 0,
            'entregas_omitidas' => 0,
            'errores' => [],
        ];

        $categoriasAnita = $this->listarCategoriasDesdeAnita();
        $ret['en_anita_categorias'] = count($categoriasAnita);

        foreach ($categoriasAnita as $row) {
            $codigoAnita = CategoriafidelidadFieldMapper::mapCodigo($row);
            if ($codigoAnita === null) {
                $ret['omitidos']++;

                continue;
            }

            try {
                $estado = $this->importarCategoria($row, $codigoAnita);
                if ($estado === 'importado') {
                    $ret['importados']++;
                } elseif ($estado === 'actualizado') {
                    $ret['actualizados']++;
                } else {
                    $ret['omitidos']++;
                }
            } catch (\Throwable $e) {
                $msg = "Categoría Anita {$codigoAnita}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('CategoriafidelidadGastronomiaAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        $articulosAnita = $this->listarArticulosDesdeAnita();
        $ret['en_anita_articulos'] = count($articulosAnita);

        $porCategoria = [];
        foreach ($articulosAnita as $row) {
            $codigoCategoria = CategoriafidelidadArticuloFieldMapper::mapCodigoCategoria($row);
            if ($codigoCategoria === null) {
                $ret['articulos_omitidos']++;

                continue;
            }
            if (! isset($porCategoria[$codigoCategoria])) {
                $porCategoria[$codigoCategoria] = [];
            }
            $porCategoria[$codigoCategoria][] = $row;
        }

        foreach ($porCategoria as $codigoCategoria => $filas) {
            try {
                $stats = $this->importarArticulosCategoria($codigoCategoria, $filas);
                $ret['articulos_importados'] += $stats['importados'];
                $ret['articulos_omitidos'] += $stats['omitidos'];
                foreach ($stats['errores'] as $err) {
                    $ret['errores'][] = $err;
                }
            } catch (\Throwable $e) {
                $msg = "Artículos categoría Anita {$codigoCategoria}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('CategoriafidelidadGastronomiaAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        $entregasAnita = $this->listarEntregasDesdeAnita();
        $ret['en_anita_entregas'] = count($entregasAnita);

        foreach ($entregasAnita as $row) {
            try {
                $estado = $this->importarEntrega($row);
                if ($estado === 'importado') {
                    $ret['entregas_importadas']++;
                } elseif ($estado === 'actualizado') {
                    $ret['entregas_actualizadas']++;
                } else {
                    $ret['entregas_omitidas']++;
                }
            } catch (\Throwable $e) {
                $doc = CategoriafidelidadEntregaFieldMapper::mapDocumento($row);
                $fecha = CategoriafidelidadEntregaFieldMapper::mapFechaAnita($row) ?? 0;
                $msg = "Entrega Anita {$doc}/{$fecha}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('CategoriafidelidadGastronomiaAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    public function traerCategoriaDeAnita(int $clcatCategoria): string
    {
        $row = $this->leerCategoriaEnAnita($clcatCategoria);
        if ($row === null) {
            return 'omitido';
        }

        $codigoAnita = CategoriafidelidadFieldMapper::mapCodigo($row);
        if ($codigoAnita === null) {
            return 'omitido';
        }

        return $this->importarCategoria($row, $codigoAnita);
    }

    /**
     * @return list<object>
     */
    public function listarCategoriasDesdeAnita(): array
    {
        return $this->listarDesdeAnita(
            config('categoriafidelidad_gastronomia_anita.tabla_categoria', 'clicat'),
            config('categoriafidelidad_gastronomia_anita.campos_categoria'),
            'clcat_categoria',
        );
    }

    /**
     * @return list<object>
     */
    public function listarArticulosDesdeAnita(): array
    {
        return $this->listarDesdeAnita(
            config('categoriafidelidad_gastronomia_anita.tabla_articulo', 'clicatart'),
            config('categoriafidelidad_gastronomia_anita.campos_articulo'),
            'clcart_categoria, clcart_orden',
        );
    }

    /**
     * @return list<object>
     */
    public function listarEntregasDesdeAnita(): array
    {
        $fechaDesde = (int) config('categoriafidelidad_gastronomia_anita.fecha_desde', 20250101);

        return $this->listarDesdeAnita(
            config('categoriafidelidad_gastronomia_anita.tabla_entrega', 'clicatent'),
            config('categoriafidelidad_gastronomia_anita.campos_entrega'),
            'clcent_fecha, clcent_documento',
            " WHERE clcent_fecha >= {$fechaDesde}",
        );
    }

    /**
     * @return list<object>
     */
    private function listarDesdeAnita(string $tabla, string $campos, string $orderBy, ?string $whereArmado = null): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => $tabla,
            'campos' => $campos,
            'orderBy' => $orderBy,
        ];
        if ($whereArmado !== null && $whereArmado !== '') {
            $payload['whereArmado'] = $whereArmado;
        }
        $sistema = config('categoriafidelidad_gastronomia_anita.sistema');
        if ($sistema !== null && $sistema !== '') {
            $payload['sistema'] = $sistema;
        }

        $rows = json_decode($api->apiCall($payload));

        return is_array($rows) ? $rows : [];
    }

    private function leerCategoriaEnAnita(int $clcatCategoria): ?object
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => config('categoriafidelidad_gastronomia_anita.tabla_categoria', 'clicat'),
            'campos' => config('categoriafidelidad_gastronomia_anita.campos_categoria'),
            'whereArmado' => " WHERE clcat_categoria={$clcatCategoria}",
        ];
        $sistema = config('categoriafidelidad_gastronomia_anita.sistema');
        if ($sistema !== null && $sistema !== '') {
            $payload['sistema'] = $sistema;
        }

        $rows = json_decode($api->apiCall($payload));

        return (is_array($rows) && count($rows) > 0) ? $rows[0] : null;
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    private function importarCategoria(object $row, string $codigoAnita): string
    {
        $payload = CategoriafidelidadFieldMapper::mapAll($row);
        if (trim((string) ($payload['nombre'] ?? '')) === '') {
            throw new \InvalidArgumentException("nombre vacío (categoría {$codigoAnita}).");
        }

        $datos = [
            'codigo' => $payload['codigo'],
            'nombre' => $payload['nombre'],
        ];

        $existente = CategoriafidelidadGastronomia::query()->where('codigo', $codigoAnita)->first();

        DB::beginTransaction();
        try {
            if ($existente) {
                $existente->update($datos);
                DB::commit();

                return 'actualizado';
            }

            $this->categoriafidelidadRepository->create($datos);
            DB::commit();

            return 'importado';
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @param  list<object>  $filas
     * @return array{importados:int, omitidos:int, errores:list<string>}
     */
    private function importarArticulosCategoria(string $codigoCategoria, array $filas): array
    {
        $stats = ['importados' => 0, 'omitidos' => 0, 'errores' => []];

        $categoria = $this->categoriafidelidadRepository->findPorCodigo($codigoCategoria);
        if (! $categoria) {
            throw new \InvalidArgumentException('Categoría ERP inexistente para código Anita '.$codigoCategoria.'.');
        }

        usort($filas, static function ($a, $b) {
            return CategoriafidelidadArticuloFieldMapper::mapOrden($a)
                <=> CategoriafidelidadArticuloFieldMapper::mapOrden($b);
        });

        $articuloIds = [];
        foreach ($filas as $row) {
            $skuAnita = CategoriafidelidadArticuloFieldMapper::mapSkuAnita($row);
            if ($skuAnita === '') {
                $stats['omitidos']++;

                continue;
            }

            $articuloId = CategoriafidelidadArticuloFieldMapper::resolverArticuloId($skuAnita);
            if ($articuloId === null) {
                $orden = CategoriafidelidadArticuloFieldMapper::mapOrden($row);
                $stats['omitidos']++;
                $stats['errores'][] = "Categoría {$codigoCategoria} orden {$orden}: artículo SKU «{$skuAnita}» no encontrado en ERP.";

                continue;
            }

            $articuloIds[] = $articuloId;
            $stats['importados']++;
        }

        DB::beginTransaction();
        try {
            $this->categoriafidelidadArticuloRepository->reemplazarArticulos((int) $categoria->id, $articuloIds);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $stats;
    }

    /**
     * @return 'importado'|'actualizado'|'omitido'
     */
    private function importarEntrega(object $row): string
    {
        $payload = CategoriafidelidadEntregaFieldMapper::mapAll($row);
        if ($payload === null) {
            $doc = CategoriafidelidadEntregaFieldMapper::mapDocumento($row);
            $fecha = CategoriafidelidadEntregaFieldMapper::mapFechaAnita($row) ?? 0;
            $sku = CategoriafidelidadEntregaFieldMapper::mapSkuAnita($row);
            throw new \InvalidArgumentException(
                "datos incompletos (doc={$doc}, fecha={$fecha}, sku={$sku}, categoría no resuelta)."
            );
        }

        $articuloIdClave = $payload['articulo_id'] ?? null;

        $existente = $this->categoriafidelidadEntregaRepository->findPorClaveAnita(
            $payload['documento'],
            $payload['fechacanje'],
            $articuloIdClave !== null ? (int) $articuloIdClave : null,
        );

        DB::beginTransaction();
        try {
            if ($existente) {
                $this->categoriafidelidadEntregaRepository->updatePorId((int) $existente->id, $payload);
                DB::commit();

                return 'actualizado';
            }

            $this->categoriafidelidadEntregaRepository->create($payload);
            DB::commit();

            return 'importado';
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
