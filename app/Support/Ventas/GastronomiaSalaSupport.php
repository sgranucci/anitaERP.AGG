<?php

namespace App\Support\Ventas;

use Illuminate\Database\Eloquent\Builder;

/**
 * Filtro por sala (casino) en mesas/mozos/descuentos/POS Crown.
 * - sala_id informado: filtra esa sala (respetando salas asignadas al usuario en Crown).
 * - sala_id vacío en Crown: restringe a salas asignadas al usuario (si tiene).
 * - AGG: sin salas asignadas; solo aplica si se pasa sala_id.
 */
final class GastronomiaSalaSupport
{
    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function aplicarFiltroSiCorresponde(Builder $query, ?int $salaId): Builder
    {
        UsuarioSalaAsignadaSupport::aplicarFiltroConSalaOpcional($query, $salaId);

        return $query;
    }

    /**
     * Solo salas asignadas (Crown), sin sala puntual.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function aplicarFiltroSalasAsignadas(Builder $query, string $column = 'sala_id', bool $incluirNull = false): void
    {
        UsuarioSalaAsignadaSupport::aplicarFiltro($query, $column, $incluirNull);
    }

    public static function idPorCodigo(string $codigo): ?int
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $id = \App\Models\Configuracion\Sala::query()
            ->where('codigo', $codigo)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
