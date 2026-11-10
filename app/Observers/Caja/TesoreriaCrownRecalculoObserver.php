<?php

namespace App\Observers\Caja;

use App\Support\Caja\TesoreriaCrownRecalculoAdelanteSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Marca la jornada afectada para el recálculo hacia adelante de tesorería Crown.
 * Resuelve la jornada por `jornada_id`, por la cabecera (bóveda, stock del día, comprobante)
 * o por sala + fecha.
 */
class TesoreriaCrownRecalculoObserver
{
    private const PADRES = [
        'boveda_id' => 'tesoreria_crown_boveda',
        'stock_dia_id' => 'tesoreria_crown_boveda_stock_dia',
        'comprobante_id' => 'tesoreria_crown_comprobante',
        'arqueo_id' => 'tesoreria_crown_boveda_arqueo',
    ];

    public function saved(Model $modelo): void
    {
        $this->marcar($modelo);
    }

    public function deleted(Model $modelo): void
    {
        $this->marcar($modelo);
    }

    private function marcar(Model $modelo): void
    {
        foreach ([$modelo->getAttributes(), $modelo->getOriginal()] as $atributos) {
            if (! empty($atributos['jornada_id'])) {
                TesoreriaCrownRecalculoAdelanteSupport::marcar((int) $atributos['jornada_id']);

                continue;
            }
            foreach (self::PADRES as $columna => $tabla) {
                if (! empty($atributos[$columna])) {
                    $jornadaId = DB::table($tabla)->where('id', (int) $atributos[$columna])->value('jornada_id');
                    TesoreriaCrownRecalculoAdelanteSupport::marcar($jornadaId !== null ? (int) $jornadaId : null);

                    continue 2;
                }
            }
            if (! empty($atributos['sala_id']) && ! empty($atributos['fecha'])) {
                TesoreriaCrownRecalculoAdelanteSupport::marcarSalaFecha(
                    (int) $atributos['sala_id'],
                    substr((string) $atributos['fecha'], 0, 10)
                );
            }
        }
    }
}
