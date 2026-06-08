<?php

namespace App\Support\Compras;

class ListaprecioProveedorConsultaDesdeModal
{
    public static function puedeConsultar(): bool
    {
        return can('listar-listaprecio-proveedor', false)
            || can('editar-listaprecio-proveedor', false)
            || can('actualizar-listaprecio-proveedor', false)
            || can('editar-compras-articulos', false)
            || can('actualizar-compras-articulos', false)
            || can('listar-articulos', false)
            || can('editar-articulos', false);
    }

    public static function urlEditar(int $id): string
    {
        return route('editar_listaprecio_proveedor', [
            'id' => $id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    }
}
