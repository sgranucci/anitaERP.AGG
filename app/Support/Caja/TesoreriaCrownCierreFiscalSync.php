<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownJornada;

/**
 * Antes sincronizaba `tesoreria_crown_cierre_fiscal` desde Anita `cierrefiscal`.
 * Corte Anita: no-op. Los Z/X salen del ERP (`TesoreriaCrownCierreFiscalDesdeVentaService`
 * / p-cierrefact) o del import histórico. Se conserva la fachada para no romper callers.
 */
final class TesoreriaCrownCierreFiscalSync
{
    /**
     * @return array{creados: int, actualizados: int, omitidos: int}
     */
    public static function sincronizarJornada(
        TesoreriaCrownJornada $jornada,
        bool $forzarActualizar = false,
    ): array {
        unset($jornada, $forzarActualizar);

        return ['creados' => 0, 'actualizados' => 0, 'omitidos' => 0];
    }

    /**
     * @param  iterable<int, TesoreriaCrownJornada>  $jornadas
     * @return array{creados: int, actualizados: int, omitidos: int}
     */
    public static function sincronizarJornadas($jornadas, bool $forzarActualizar = false): array
    {
        unset($jornadas, $forzarActualizar);

        return ['creados' => 0, 'actualizados' => 0, 'omitidos' => 0];
    }

    /**
     * @return array{creados: int, actualizados: int, omitidos: int}
     */
    public static function asegurarJornada(TesoreriaCrownJornada $jornada, bool $forzarActualizar = false): array
    {
        return self::sincronizarJornada($jornada, $forzarActualizar);
    }
}
