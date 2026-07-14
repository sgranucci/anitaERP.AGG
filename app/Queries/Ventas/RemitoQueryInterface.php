<?php

namespace App\Queries\Ventas;

interface RemitoQueryInterface
{
    public function allRemitoIndexPaginando($busqueda, $estado, $reparto, $fechaentrega);

    public function allRemitoIndexSinPaginar($busqueda, $estado = '', $reparto = '', $fechaentrega = '');

    public function allRemitoIndexListadoCursor($busqueda, $estado = '', $reparto = '', $fechaentrega = '');

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function allRemitoIndexFiltros(array $filtros, bool $flPaginando = true);

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function allRemitoIndexFiltrosCursor(array $filtros);

    public function leeRemitoporId($id);
}
