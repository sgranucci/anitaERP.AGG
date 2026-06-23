<?php

namespace App\Support\Contable;

use App\Models\Contable\Cuentacontable;
use App\Models\Contable\Usuario_Cuentacontable;

/**
 * Restricción de cuentas contables por usuario (lista blanca).
 */
class AsientoCuentaUsuarioSupport
{
    public static function usuarioTieneRestriccionCuentas(int $usuarioId): bool
    {
        if ($usuarioId <= 0) {
            return false;
        }

        return Usuario_Cuentacontable::query()
            ->where('usuario_id', $usuarioId)
            ->exists();
    }

    /**
     * @param  list<int|string|null>  $cuentacontableIds
     * @return list<int>
     */
    public static function cuentasNoAutorizadas(int $usuarioId, array $cuentacontableIds): array
    {
        if (! self::usuarioTieneRestriccionCuentas($usuarioId)) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $cuentacontableIds), fn ($id) => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $permitidas = Usuario_Cuentacontable::query()
            ->where('usuario_id', $usuarioId)
            ->whereIn('cuentacontable_id', $ids)
            ->pluck('cuentacontable_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $noAutorizadas = array_values(array_diff($ids, $permitidas));

        return $noAutorizadas;
    }

    public static function puedeUsarCuenta(int $usuarioId, int $cuentacontableId): bool
    {
        return self::cuentasNoAutorizadas($usuarioId, [$cuentacontableId]) === [];
    }

    /**
     * @param  list<int>  $cuentacontableIds
     * @return list<array{id:int, codigo:string, nombre:string}>
     */
    public static function detalleCuentas(array $cuentacontableIds): array
    {
        if ($cuentacontableIds === []) {
            return [];
        }

        return Cuentacontable::query()
            ->whereIn('id', $cuentacontableIds)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre'])
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'codigo' => (string) $c->codigo,
                'nombre' => (string) $c->nombre,
            ])
            ->all();
    }
}
