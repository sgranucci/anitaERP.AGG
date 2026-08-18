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
}
