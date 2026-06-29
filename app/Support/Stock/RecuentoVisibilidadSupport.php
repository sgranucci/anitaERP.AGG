<?php

namespace App\Support\Stock;

use Illuminate\Database\Eloquent\Builder;

/**
 * Alcance del listado de recuentos por usuario y depósitos autorizados.
 */
final class RecuentoVisibilidadSupport
{
    public const PERMISO_VER_TODOS = 'listar-todos-recuento';

    public static function puedeVerTodos(): bool
    {
        return can(self::PERMISO_VER_TODOS, false);
    }

    public static function tieneRestriccionPorDeposito(): bool
    {
        return UsuarioDepositoAutorizado::tieneRestriccion();
    }

    /**
     * @param  Builder<\App\Models\Stock\Recuento>  $query
     */
    public static function aplicarFiltroDepositos(Builder $query): void
    {
        $depositoIds = UsuarioDepositoAutorizado::idsRestringidos();
        if (! is_array($depositoIds) || count($depositoIds) === 0) {
            return;
        }

        $query->whereIn('recuento.deposito_id', $depositoIds);
    }

    /**
     * @param  Builder<\App\Models\Stock\Recuento>  $query
     */
    public static function aplicarFiltroUsuario(Builder $query, int $usuarioId): void
    {
        if ($usuarioId <= 0) {
            return;
        }

        $query->where('recuento.usuario_id', $usuarioId);
    }
}
