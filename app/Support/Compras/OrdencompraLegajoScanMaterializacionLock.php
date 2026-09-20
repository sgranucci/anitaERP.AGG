<?php

namespace App\Support\Compras;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

/**
 * Exclusión mutua por legajo al materializar los scans de Anita en precargas.
 *
 * Abrir el modal del legajo da de alta una precarga por cada scan, así que es un GET que escribe.
 * Sin esto, dos operadores abriendo el mismo legajo a la vez entran los dos al alta: el índice único
 * frena el duplicado, pero uno de los dos se come el error. Acá el segundo simplemente no materializa
 * (el primero ya está en eso) y el modal igual muestra los scans que todavía no tienen precarga.
 */
final class OrdencompraLegajoScanMaterializacionLock
{
    public static function clave(int $ordencompraId): string
    {
        return 'compras:legajo:materializar-scans:'.max(0, $ordencompraId);
    }

    public static function segundosBloqueo(): int
    {
        return max(15, (int) config('compras.legajo_materializar_scans_lock_segundos', 60));
    }

    /**
     * No espera: si otro request tiene el legajo tomado devuelve null. Abrir el modal tiene que ser
     * rápido, y la materialización que se saltea la hace el otro request o la siguiente apertura.
     */
    public static function intentar(int $ordencompraId): ?Lock
    {
        if ($ordencompraId <= 0) {
            return null;
        }

        $lock = Cache::lock(self::clave($ordencompraId), self::segundosBloqueo());

        return $lock->get() ? $lock : null;
    }

    public static function liberar(?Lock $lock): void
    {
        $lock?->release();
    }
}
