<?php

namespace App\Observers\Stock;

use App\Models\Stock\Articulo_Movimiento_Talle;
use App\Support\Stock\CorteReemplazoExcelFerliSupport;
use Illuminate\Support\Facades\DB;

/**
 * El saldo de un lote/OT es la suma de talles: cambiar el talle de un movimiento ya
 * absorbido por el reemplazo de Excel descuadra el corte igual que cambiar la cabecera.
 */
class Articulo_Movimiento_TalleObserver
{
    public function updating(Articulo_Movimiento_Talle $talle): void
    {
        if ($talle->isDirty(['cantidad', 'talle_id', 'articulo_movimiento_id'])) {
            $this->bloquear((int) $talle->getRawOriginal('articulo_movimiento_id'), 'modificar los talles de');
        }
    }

    public function deleting(Articulo_Movimiento_Talle $talle): void
    {
        $this->bloquear((int) $talle->getRawOriginal('articulo_movimiento_id'), 'borrar los talles de');
    }

    private function bloquear(int $movimientoId, string $accion): void
    {
        if ($movimientoId <= 0 || ! app()->runningInConsole()) {
            return;
        }

        $mov = DB::table('articulo_movimiento')
            ->where('id', $movimientoId)
            ->first(['id', 'articulo_id', 'combinacion_id', 'deposito_id', 'lote', 'ordentrabajo_id', 'concepto']);
        if ($mov !== null) {
            CorteReemplazoExcelFerliSupport::bloquearSiAbsorbido((array) $mov, $accion);
        }
    }
}
