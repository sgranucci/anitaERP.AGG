<?php

namespace App\Support\Stock;

use Illuminate\Support\Facades\DB;

/**
 * Fórmulas que incluyen un artículo como componente o como subfórmula.
 */
final class ArticuloFormulasUsoSupport
{
    /**
     * @return list<array{
     *     formula_id:int,
     *     numero:string,
     *     codigo:string,
     *     detalle:string,
     *     estado:string,
     *     articulo_padre_id:int,
     *     articulo_sku:string,
     *     articulo_descripcion:string,
     *     cantidad:float,
     *     esopcional:bool,
     *     via:string
     * }>
     */
    public static function listar(int $articuloId): array
    {
        if ($articuloId <= 0) {
            return [];
        }

        $columnas = [
            'fa.id as formula_id',
            'fa.codigo',
            'fa.detalle',
            'fa.estado',
            'fa.articulo_id as articulo_padre_id',
            'a.sku as articulo_sku',
            'a.descripcion as articulo_descripcion',
            'h.cantidad',
            'h.esopcional',
            'h.id as linea_id',
        ];

        $directas = DB::table('formula_articulo_hijo as h')
            ->join('formula_articulo as fa', 'fa.id', '=', 'h.formula_articulo_id')
            ->leftJoin('articulo as a', 'a.id', '=', 'fa.articulo_id')
            ->where('h.articulo_id', $articuloId)
            ->orderBy('a.sku')
            ->orderBy('fa.id')
            ->orderBy('h.id')
            ->get($columnas);

        $subformulas = DB::table('formula_articulo_hijo as h')
            ->join('formula_articulo as hija', 'hija.id', '=', 'h.formula_hija_id')
            ->join('formula_articulo as fa', 'fa.id', '=', 'h.formula_articulo_id')
            ->leftJoin('articulo as a', 'a.id', '=', 'fa.articulo_id')
            ->where('hija.articulo_id', $articuloId)
            ->orderBy('a.sku')
            ->orderBy('fa.id')
            ->orderBy('h.id')
            ->get($columnas);

        $filas = [];
        foreach ($directas as $row) {
            $filas[] = self::fila($row, 'Componente');
        }
        foreach ($subformulas as $row) {
            $filas[] = self::fila($row, 'Subfórmula');
        }

        usort($filas, static function (array $a, array $b): int {
            return [$a['articulo_sku'], $a['formula_id'], $a['via'], $a['linea_id']]
                <=> [$b['articulo_sku'], $b['formula_id'], $b['via'], $b['linea_id']];
        });

        return $filas;
    }

    /**
     * @return array{
     *     formula_id:int,
     *     numero:string,
     *     codigo:string,
     *     detalle:string,
     *     estado:string,
     *     articulo_padre_id:int,
     *     articulo_sku:string,
     *     articulo_descripcion:string,
     *     cantidad:float,
     *     esopcional:bool,
     *     via:string,
     *     linea_id:int
     * }
     */
    private static function fila(object $row, string $via): array
    {
        $formulaId = (int) ($row->formula_id ?? 0);

        return [
            'formula_id' => $formulaId,
            'numero' => FormulaArticuloNumero::paraIdYCodigo($formulaId, $row->codigo ?? null),
            'codigo' => trim((string) ($row->codigo ?? '')),
            'detalle' => trim((string) ($row->detalle ?? '')),
            'estado' => trim((string) ($row->estado ?? '')),
            'articulo_padre_id' => (int) ($row->articulo_padre_id ?? 0),
            'articulo_sku' => trim((string) ($row->articulo_sku ?? '')),
            'articulo_descripcion' => trim((string) ($row->articulo_descripcion ?? '')),
            'cantidad' => (float) ($row->cantidad ?? 0),
            'esopcional' => (bool) ($row->esopcional ?? false),
            'via' => $via,
            'linea_id' => (int) ($row->linea_id ?? 0),
        ];
    }
}
