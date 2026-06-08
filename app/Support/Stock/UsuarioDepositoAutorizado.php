<?php

namespace App\Support\Stock;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Depmae;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Session;

final class UsuarioDepositoAutorizado
{
    /**
     * IDs de depósitos autorizados para el usuario logueado, o null si no hay restricción.
     *
     * @return array<int>|null
     */
    public static function idsRestringidos(): ?array
    {
        if (! Session::has('usuario_depositos_ids')) {
            return null;
        }

        $ids = Session::get('usuario_depositos_ids');
        if (! is_array($ids) || count($ids) === 0) {
            return null;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public static function tieneRestriccion(): bool
    {
        $ids = self::idsRestringidos();

        return is_array($ids) && count($ids) > 0;
    }

    public static function depositoAutorizado(int $depositoId): bool
    {
        if ($depositoId <= 0) {
            return false;
        }

        $ids = self::idsRestringidos();
        if ($ids === null) {
            return true;
        }

        return in_array($depositoId, $ids, true);
    }

    /**
     * @param  Builder<Depmae>  $query
     * @return Builder<Depmae>
     */
    public static function aplicarFiltroQuery(Builder $query): Builder
    {
        $ids = self::idsRestringidos();
        if ($ids === null) {
            return $query;
        }

        return $query->whereIn($query->getModel()->getTable().'.id', $ids);
    }

    public static function cargarEnSession(Usuario $usuario): void
    {
        $ids = $usuario->depositosAutorizados()->pluck('depmae.id')->all();

        if (count($ids) > 0) {
            Session::put('usuario_depositos_ids', array_values(array_map('intval', $ids)));
        } else {
            Session::forget('usuario_depositos_ids');
        }
    }

    /**
     * @param  array<int|string>  $depositoIds
     * @param  array<int|string>  $empresaIds
     * @return array<int>
     */
    public static function idsValidosParaEmpresas(array $depositoIds, array $empresaIds): array
    {
        $depositoIds = array_values(array_unique(array_filter(array_map('intval', $depositoIds))));
        $empresaIds = array_values(array_unique(array_filter(array_map('intval', $empresaIds))));

        if ($depositoIds === [] || $empresaIds === []) {
            return [];
        }

        return Depmae::query()
            ->whereIn('id', $depositoIds)
            ->whereIn('empresa_id', $empresaIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
