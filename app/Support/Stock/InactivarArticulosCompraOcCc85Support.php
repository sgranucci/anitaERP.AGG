<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Estado;
use App\Repositories\Stock\Articulo_EstadoRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Inactiva artículos de compra (uso COMPRA, tipo ALIMENTO/BEBIDA) que no figuran
 * en el listado OC CC 85. Solo anitaERP; no sincroniza con Anita legacy.
 */
final class InactivarArticulosCompraOcCc85Support
{
    public const USO_COMPRA_ID = 2;

    /** @var list<int> */
    public const TIPOS_ALIMENTO_BEBIDA = [1, 2];

    public const OBSERVACION = 'Inactivación masiva OC CC 85: artículo de compra alimento/bebida sin pedidos en reporte';

    /**
     * @return list<string>
     */
    public static function leerSkusDesdeCsv(string $rutaCsv): array
    {
        if (! is_readable($rutaCsv)) {
            throw new \InvalidArgumentException('No se puede leer el CSV: '.$rutaCsv);
        }

        $handle = fopen($rutaCsv, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir el CSV: '.$rutaCsv);
        }

        $skus = [];
        $linea = 0;
        try {
            while (($raw = fgets($handle)) !== false) {
                $linea++;
                if ($linea <= 8) {
                    continue;
                }

                $trimmed = ltrim($raw);
                if ($trimmed === '' || str_starts_with($trimmed, 'Total ')) {
                    continue;
                }

                $cols = str_getcsv($raw, ',', '"', '\\');
                $sku = trim((string) ($cols[0] ?? ''));
                if ($sku === '' || strcasecmp($sku, 'Articulo') === 0) {
                    continue;
                }

                $skus[$sku] = true;
            }
        } finally {
            fclose($handle);
        }

        return array_keys($skus);
    }

    public static function skuExcluidoPorPatron(string $sku): bool
    {
        $s = strtoupper(trim($sku));
        if ($s === '') {
            return true;
        }

        return (bool) preg_match('/^V\d/', $s)
            || (bool) preg_match('/^I\d/', $s)
            || str_starts_with($s, 'LAB')
            || str_starts_with($s, 'LIB');
    }

    /**
     * @param  list<string>  $csvSkus
     * @return array{
     *     mantener: Collection<int, object>,
     *     inactivar: Collection<int, object>,
     *     excluidos_patron: Collection<int, object>,
     *     csv_skus_total: int
     * }
     */
    public static function resolverCandidatos(array $csvSkus): array
    {
        $csvSet = array_fill_keys($csvSkus, true);

        $activos = DB::table('articulo as a')
            ->leftJoin('tipoarticulo as t', 't.id', '=', 'a.tipoarticulo_id')
            ->where('a.usoarticulo_id', self::USO_COMPRA_ID)
            ->whereIn('a.tipoarticulo_id', self::TIPOS_ALIMENTO_BEBIDA)
            ->where('a.estado', 'ACTIVO')
            ->select(
                'a.id',
                'a.sku',
                'a.descripcion',
                'a.tipoarticulo_id',
                't.nombre as tipo_nombre',
            )
            ->orderBy('a.sku')
            ->get();

        $excluidosPatron = collect();
        $mantener = collect();
        $inactivar = collect();

        foreach ($activos as $row) {
            if (self::skuExcluidoPorPatron((string) $row->sku)) {
                $excluidosPatron->push($row);

                continue;
            }

            if (isset($csvSet[(string) $row->sku])) {
                $mantener->push($row);

                continue;
            }

            $inactivar->push($row);
        }

        return [
            'mantener' => $mantener,
            'inactivar' => $inactivar,
            'excluidos_patron' => $excluidosPatron,
            'csv_skus_total' => count($csvSkus),
        ];
    }

    /**
     * @param  list<int>  $articuloIds
     * @return array{inactivados: int, errores: list<string>}
     */
    public static function inactivar(
        array $articuloIds,
        Articulo_EstadoRepositoryInterface $articuloEstadoRepository,
        int $usuarioId,
        bool $dryRun,
    ): array {
        $inactivados = 0;
        $errores = [];

        $estadoInactivo = Articulo_Estado::$enumEstado[
            array_search('I', array_column(Articulo_Estado::$enumEstado, 'valor'), true)
        ]['nombre'];

        foreach ($articuloIds as $articuloId) {
            $articulo = Articulo::query()
                ->whereKey($articuloId)
                ->where('usoarticulo_id', self::USO_COMPRA_ID)
                ->whereIn('tipoarticulo_id', self::TIPOS_ALIMENTO_BEBIDA)
                ->where('estado', 'ACTIVO')
                ->first(['id', 'sku']);

            if ($articulo === null) {
                $errores[] = 'ID '.$articuloId.': no cumple filtros de seguridad o ya está inactivo';

                continue;
            }

            if (self::skuExcluidoPorPatron((string) $articulo->sku)) {
                $errores[] = 'SKU '.$articulo->sku.' (id '.$articuloId.'): patrón excluido V/I/LAB/LIB';

                continue;
            }

            if ($dryRun) {
                $inactivados++;

                continue;
            }

            DB::transaction(function () use (
                $articulo,
                $articuloEstadoRepository,
                $usuarioId,
                $estadoInactivo,
                &$inactivados,
            ): void {
                Articulo::query()
                    ->whereKey($articulo->id)
                    ->where('usoarticulo_id', self::USO_COMPRA_ID)
                    ->update(['estado' => 'INACTIVO']);

                $articuloEstadoRepository->create([
                    'estadofechas' => [Carbon::now()],
                    'estados' => [$estadoInactivo],
                    'estadoobservaciones' => [self::OBSERVACION],
                    'estadousuarios' => [$usuarioId],
                ], (int) $articulo->id);

                $inactivados++;
            });
        }

        return [
            'inactivados' => $inactivados,
            'errores' => $errores,
        ];
    }
}
