<?php

namespace App\Services\Ventas\Ferli;

/**
 * Normaliza filas L8 → columnas L12 (fechas cero, columnas desconocidas).
 */
final class FerliL8ImportRowSupport
{
    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $cols
     * @return array<string, mixed>
     */
    public static function normalize(string $table, array $row, array $cols): array
    {
        $allowed = array_flip($cols);
        $out = [];
        foreach ($row as $k => $v) {
            if (! isset($allowed[$k])) {
                continue;
            }
            if ($v === '' || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') {
                if (in_array($k, ['hastafecha', 'desdefecha', 'fecha', 'created_at', 'updated_at', 'deleted_at', 'picking_at'], true)) {
                    $v = null;
                }
            }
            if ($k === 'pedido_combinacion_id' && ($v === '' || $v === 0 || $v === '0')) {
                $v = null;
            }
            $out[$k] = $v;
        }

        if ($table === 'ordentrabajo') {
            if (! isset($out['tipoot']) || $out['tipoot'] === null || $out['tipoot'] === '') {
                $out['tipoot'] = 'N';
            }
            if (! isset($out['leyenda']) || $out['leyenda'] === null) {
                $out['leyenda'] = ' ';
            }
        }

        // L12 ya no usa SoftDeletes en estas tablas
        unset($out['deleted_at']);

        return $out;
    }
}
