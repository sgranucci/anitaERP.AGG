<?php

namespace App\Queries\Compras;

use App\Models\Compras\Requisicion;

interface RequisicionQueryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function leeRequisicion($filtros, $flPaginando = null, $withArticulos = false);

    /**
     * Indica si la requisición existe y el usuario actual puede verla en el listado (empresa, centro de costo;
     * oficina de compra solo si requisicion.filtro_oficina_compras_activo).
     */
    public function requisicionAccesiblePorUsuario(int $id): bool;

    /**
     * Usuario con permiso crear-ordencompra, acceso a la requisición y estado APROBADA.
     */
    public function puedeUsuarioGenerarMultiplesOcDesdeRequisicion(Requisicion $r): bool;

    /**
     * Conteos por estado con los mismos filtros del listado, excepto el chip de estado.
     *
     * @param  array<string, mixed>|string|null  $filtros
     * @return array{total: int, por_estado: array<string, int>, provisorio: int, pendiente: int, en_compras: int, en_arbol: int, aprobada: int, genero_oc: int, cumplida: int, suspendida: int}
     */
    public function resumenIndex($filtros): array;
}
