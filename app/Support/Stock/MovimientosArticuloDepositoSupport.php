<?php

namespace App\Support\Stock;

final class MovimientosArticuloDepositoSupport
{
    public static function puedeConsultar(): bool
    {
        return can('listar-articulos', false)
            || can('editar-articulos', false)
            || can('listar-recuento', false)
            || can('crear-recuento', false)
            || can('editar-recuento', false)
            || can('ver-recuento', false)
            || can('listar-movimientos-de-stock', false)
            || can('crear-movimientos-de-stock', false)
            || can('editar-movimientos-de-stock', false);
    }

    /**
     * @return array<string, mixed>
     */
    public static function parametrosUrlKardex(int $articuloId, int $depositoId = 0, ?string $volver = null): array
    {
        return array_filter([
            'articulo_id' => $articuloId,
            'deposito_id' => $depositoId > 0 ? $depositoId : 0,
            'vista' => 'consulta',
            'volver' => $volver,
        ], static fn ($v) => $v !== null && $v !== '');
    }
}
