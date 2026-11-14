<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Sku_Mapeo;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recodificación de SKU e historial para imports que aún traen el código viejo.
 */
final class ArticuloSkuMapeoSupport
{
    public const MOTIVO_NOFACTURABLE_CON_M = 'nofacturable_sku_con_m';

    /** Base numérica libre en Crown (rango 200000–299999 vacío al 2026-11-13). */
    public const BASE_SKU_NUMERICA = 200001;

    /**
     * Tablas con sku denormalizado + articulo_id (actualizar al recodificar).
     *
     * @var list<string>
     */
    private const TABLAS_SKU_DENORMALIZADO = [
        'comprobante_proveedor_articulo',
        'precarga_comprobante_proveedor_articulo',
        'gastro_crown_cuenta_linea',
        'vianda_consumo_linea',
        'prenda_articulo_sueldos',
        'certificado_sanitario_articulo',
    ];

    public static function tablaExiste(): bool
    {
        return Schema::hasTable('articulo_sku_mapeo');
    }

    /**
     * Resuelve el SKU vigente si el dato importado es un código anterior mapeado.
     */
    public static function skuActualDesdeAnterior(string $skuAnterior): ?string
    {
        $skuAnterior = ArticuloSkuMatchSupport::normalizar($skuAnterior);
        if ($skuAnterior === '' || ! self::tablaExiste()) {
            return null;
        }

        $nuevo = Articulo_Sku_Mapeo::query()
            ->whereRaw('UPPER(TRIM(sku_anterior)) = ?', [$skuAnterior])
            ->orderByDesc('id')
            ->value('sku_nuevo');

        return $nuevo !== null ? (string) $nuevo : null;
    }

    /**
     * Resuelve artículo por SKU actual o por SKU anterior del mapeo.
     */
    public static function resolverArticuloPorSkuOMapeo(string $sku): ?Articulo
    {
        $articulo = ArticuloSkuMatchSupport::resolverCanonico($sku);
        if ($articulo !== null) {
            return $articulo;
        }

        $skuNuevo = self::skuActualDesdeAnterior($sku);
        if ($skuNuevo === null) {
            return null;
        }

        return ArticuloSkuMatchSupport::resolverCanonico($skuNuevo);
    }

    /**
     * @return array{
     *   candidatos: int,
     *   a_recodificar: int,
     *   ya_mapeados: int,
     *   omitidos: int,
     *   base: int,
     *   filas: list<array{articulo_id:int,sku_anterior:string,sku_nuevo:string,descripcion:string}>,
     *   actualizados: int,
     *   mapeos: int,
     *   denormalizados: int,
     *   dry_run: bool,
     *   mensaje: string
     * }
     */
    public static function recodificarNofacturablesConM(bool $ejecutar, ?int $base = null): array
    {
        $base = $base ?? self::BASE_SKU_NUMERICA;
        $candidatos = self::queryCandidatosNofacturableConM()->orderBy('id')->get(['id', 'sku', 'descripcion']);

        $yaMapeadosIds = self::tablaExiste()
            ? Articulo_Sku_Mapeo::query()
                ->where('motivo', self::MOTIVO_NOFACTURABLE_CON_M)
                ->pluck('articulo_id')
                ->all()
            : [];
        $yaMapeadosSet = array_fill_keys($yaMapeadosIds, true);

        $ocupados = Articulo::query()
            ->whereRaw(SqlDialectSupport::coincideRegex('sku'), ['^[0-9]+$'])
            ->pluck('sku')
            ->mapWithKeys(static fn ($sku) => [(string) (int) $sku => true])
            ->all();

        $siguiente = $base;
        $filas = [];
        $omitidos = 0;
        $yaMapeados = 0;

        foreach ($candidatos as $art) {
            if (isset($yaMapeadosSet[$art->id])) {
                $yaMapeados++;

                continue;
            }

            $skuAnterior = trim((string) $art->sku);
            if ($skuAnterior === '' || ! str_contains(strtoupper($skuAnterior), 'M')) {
                $omitidos++;

                continue;
            }

            while (isset($ocupados[(string) $siguiente])) {
                $siguiente++;
            }

            $skuNuevo = (string) $siguiente;
            $ocupados[$skuNuevo] = true;
            $siguiente++;

            $filas[] = [
                'articulo_id' => (int) $art->id,
                'sku_anterior' => $skuAnterior,
                'sku_nuevo' => $skuNuevo,
                'descripcion' => (string) ($art->descripcion ?? ''),
            ];
        }

        $resultado = [
            'candidatos' => $candidatos->count(),
            'a_recodificar' => count($filas),
            'ya_mapeados' => $yaMapeados,
            'omitidos' => $omitidos,
            'base' => $base,
            'filas' => $filas,
            'actualizados' => 0,
            'mapeos' => 0,
            'denormalizados' => 0,
            'dry_run' => ! $ejecutar,
            'mensaje' => '',
        ];

        if (! $ejecutar) {
            $resultado['mensaje'] = sprintf(
                'Dry-run: %d candidatos, %d a recodificar (base %d), %d ya mapeados, %d omitidos.',
                $resultado['candidatos'],
                $resultado['a_recodificar'],
                $base,
                $yaMapeados,
                $omitidos
            );

            return $resultado;
        }

        if (! self::tablaExiste()) {
            throw new \RuntimeException('Falta la tabla articulo_sku_mapeo. Correr la migración primero.');
        }

        $usuarioId = Auth::id();
        $actualizados = 0;
        $mapeos = 0;
        $denormalizados = 0;

        DB::transaction(function () use ($filas, $usuarioId, &$actualizados, &$mapeos, &$denormalizados) {
            foreach ($filas as $fila) {
                $articulo = Articulo::query()->lockForUpdate()->find($fila['articulo_id']);
                if ($articulo === null) {
                    continue;
                }

                if (ArticuloSkuMatchSupport::existe($fila['sku_nuevo'])) {
                    throw new \RuntimeException(
                        "SKU nuevo {$fila['sku_nuevo']} ya existe al persistir (artículo {$fila['articulo_id']})."
                    );
                }

                $articulo->sku = $fila['sku_nuevo'];
                $articulo->save();
                $actualizados++;

                Articulo_Sku_Mapeo::query()->create([
                    'articulo_id' => $fila['articulo_id'],
                    'sku_anterior' => $fila['sku_anterior'],
                    'sku_nuevo' => $fila['sku_nuevo'],
                    'motivo' => self::MOTIVO_NOFACTURABLE_CON_M,
                    'usuario_id' => $usuarioId,
                ]);
                $mapeos++;

                $denormalizados += self::actualizarSkuDenormalizado(
                    $fila['articulo_id'],
                    $fila['sku_anterior'],
                    $fila['sku_nuevo']
                );
            }
        });

        $resultado['actualizados'] = $actualizados;
        $resultado['mapeos'] = $mapeos;
        $resultado['denormalizados'] = $denormalizados;
        $resultado['mensaje'] = sprintf(
            'Grabado: %d artículos recodificados, %d filas en articulo_sku_mapeo, %d denormalizados actualizados (base %d).',
            $actualizados,
            $mapeos,
            $denormalizados,
            $base
        );

        return $resultado;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Articulo>
     */
    public static function queryCandidatosNofacturableConM()
    {
        return Articulo::query()
            ->where('nofactura', ArticuloNofacturaSupport::NO_FACTURABLE)
            ->whereRaw('UPPER(sku) LIKE ?', ['%M%']);
    }

    private static function actualizarSkuDenormalizado(int $articuloId, string $skuAnterior, string $skuNuevo): int
    {
        $total = 0;

        foreach (self::TABLAS_SKU_DENORMALIZADO as $tabla) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'sku')) {
                continue;
            }

            $q = DB::table($tabla)->where('sku', $skuAnterior);
            if (Schema::hasColumn($tabla, 'articulo_id')) {
                $q->where('articulo_id', $articuloId);
            }

            $total += $q->update(['sku' => $skuNuevo]);
        }

        // gastro_crown_comanda_linea: suele tener sku sin articulo_id
        if (Schema::hasTable('gastro_crown_comanda_linea') && Schema::hasColumn('gastro_crown_comanda_linea', 'sku')) {
            $total += DB::table('gastro_crown_comanda_linea')
                ->where('sku', $skuAnterior)
                ->update(['sku' => $skuNuevo]);
        }

        return $total;
    }

    /**
     * @param  list<array{articulo_id:int,sku_anterior:string,sku_nuevo:string,descripcion:string}>  $filas
     */
    public static function exportarCsvPreview(array $filas, string $path): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fh = fopen($path, 'w');
        if ($fh === false) {
            throw new \RuntimeException("No se pudo escribir {$path}");
        }

        fputcsv($fh, ['articulo_id', 'sku_anterior', 'sku_nuevo', 'descripcion']);
        foreach ($filas as $fila) {
            fputcsv($fh, [
                $fila['articulo_id'],
                $fila['sku_anterior'],
                $fila['sku_nuevo'],
                $fila['descripcion'],
            ]);
        }
        fclose($fh);
    }
}
