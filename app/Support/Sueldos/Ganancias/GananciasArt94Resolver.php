<?php

namespace App\Support\Sueldos\Ganancias;

use Illuminate\Support\Facades\DB;

/**
 * Aplica la escala Art. 94 vigente para un mes de pago:
 * impuesto = fijo + (base - excedente) * alicuota / 100
 */
class GananciasArt94Resolver
{
    /** @var array<string, list<object>> */
    private array $cache = [];

    public function impuesto(float $base, int $anio, int $mes): float
    {
        if ($base <= 0) {
            return 0.0;
        }
        $tramos = $this->tramos($anio, $mes);
        foreach ($tramos as $t) {
            $desde = (float) $t->desde;
            $hasta = $t->hasta !== null ? (float) $t->hasta : null;
            if ($base >= $desde && ($hasta === null || $base <= $hasta)) {
                return round((float) $t->fijo + ($base - (float) $t->excedente) * ((float) $t->alicuota) / 100.0, 2);
            }
        }

        return 0.0;
    }

    /**
     * @return list<object>
     */
    private function tramos(int $anio, int $mes): array
    {
        $clave = $anio.'-'.$mes;
        if (! isset($this->cache[$clave])) {
            $this->cache[$clave] = DB::table('ganancia_escala_tramo_sueldos')
                ->where('anio', $anio)
                ->where('mes', $mes)
                ->orderBy('nro_tramo')
                ->get()
                ->all();
        }

        return $this->cache[$clave];
    }
}
