<?php

namespace App\Support\Sueldos\Ganancias;

use Illuminate\Support\Facades\DB;

/**
 * Lee deducciones Art. 30 acumuladas vigentes (codigo + anio + mes).
 */
class GananciasArt30Resolver
{
    /** @var array<string, float> */
    private array $cache = [];

    public function valorAcumulado(string $codigo, int $anio, int $mes): float
    {
        $clave = strtoupper($codigo).'|'.$anio.'|'.$mes;
        if (! array_key_exists($clave, $this->cache)) {
            $v = DB::table('ganancia_deduccion_valor_sueldos')
                ->where('codigo', strtoupper($codigo))
                ->where('anio', $anio)
                ->where('mes', $mes)
                ->value('valor_acumulado');
            $this->cache[$clave] = (float) ($v ?? 0);
        }

        return $this->cache[$clave];
    }
}
