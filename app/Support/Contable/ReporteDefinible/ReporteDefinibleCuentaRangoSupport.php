<?php

namespace App\Support\Contable\ReporteDefinible;

use Illuminate\Support\Facades\DB;

/**
 * Expande rangos de código de cuenta (codigo_cuenta … codigo_hasta) en runtime.
 */
class ReporteDefinibleCuentaRangoSupport
{
    /**
     * @return list<int>
     */
    public function expandirCodigo(int $desde, ?int $hasta): array
    {
        if ($desde <= 0) {
            return [];
        }
        if ($hasta === null || $hasta <= 0 || $hasta === $desde) {
            return [$desde];
        }
        if ($hasta < $desde) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $codigos = DB::table('cuentacontable')
            ->where('tipocuenta', 1)
            ->whereRaw('CAST(codigo AS UNSIGNED) BETWEEN ? AND ?', [$desde, $hasta])
            ->orderByRaw('CAST(codigo AS UNSIGNED)')
            ->pluck('codigo')
            ->map(fn ($c) => (int) $c)
            ->unique()
            ->values()
            ->all();

        if ($codigos === []) {
            // Fallback: incluir extremos aunque no existan en el plan (movimientos raros)
            return [$desde, $hasta];
        }

        return $codigos;
    }

    /**
     * @param  object{codigo_cuenta?: mixed, codigo_hasta?: mixed}  $cta
     * @return list<int>
     */
    public function expandirAsignacion(object $cta): array
    {
        $desde = (int) ($cta->codigo_cuenta ?? 0);
        $hasta = isset($cta->codigo_hasta) && $cta->codigo_hasta !== null && $cta->codigo_hasta !== ''
            ? (int) $cta->codigo_hasta
            : null;

        return $this->expandirCodigo($desde, $hasta);
    }

    public function etiqueta(int $desde, ?int $hasta): string
    {
        if ($hasta === null || $hasta <= 0 || $hasta === $desde) {
            return (string) $desde;
        }

        return $desde.'–'.$hasta;
    }
}
