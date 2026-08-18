<?php

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Borrado que dispara SoftDeletes y OwenIt Auditing.
 *
 * Query Builder {@see Builder::delete()} o {@see Builder::update()} de `deleted_at`
 * no dispara eventos del modelo: la fila se va y `audits` queda vacío
 * (incidente 18/ago/2026: nivel Gastronomía de REQUIS KANDIKO).
 */
final class EloquentAuditDeleteSupport
{
    public static function each(Builder $query): int
    {
        $borrados = 0;
        foreach ($query->get() as $model) {
            if (! $model instanceof Model) {
                continue;
            }
            $model->delete();
            $borrados++;
        }

        return $borrados;
    }

    /**
     * Sincronizar hijas de ABM: borra las filas del query que no están en $idsConservar.
     * Si $idsConservar está vacío no aplica whereNotIn (en Laravel eso no matchea filas).
     *
     * @param  list<int|string>  $idsConservar
     */
    public static function exceptIds(Builder $query, array $idsConservar, string $key = 'id'): int
    {
        $ids = array_values(array_filter(
            array_map('intval', $idsConservar),
            static fn (int $id) => $id > 0
        ));
        if ($ids !== []) {
            $query->whereNotIn($key, $ids);
        }

        return self::each($query);
    }
}
