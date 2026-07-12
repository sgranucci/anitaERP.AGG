<?php

namespace App\Support\Ventas;

final class GastronomiaAutoDescarteVaciasSupport
{
    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function opcionesActualizacionPorLotes(string $contexto, array $extra = []): array
    {
        $cfg = config('gastronomia.auto_descarte_vacias', []);

        return array_merge([
            'tamano_lote' => max(1, (int) ($cfg['tamano_lote'] ?? 100)),
            'max_iteraciones' => max(1, (int) ($cfg['max_iteraciones'] ?? 500)),
            'reintentos_deadlock' => max(1, (int) ($cfg['reintentos_deadlock'] ?? 5)),
            'espera_reintento_ms' => max(50, (int) ($cfg['espera_reintento_ms'] ?? 150)),
            'contexto' => $contexto,
            'verificar_pendientes' => true,
        ], $extra);
    }
}
