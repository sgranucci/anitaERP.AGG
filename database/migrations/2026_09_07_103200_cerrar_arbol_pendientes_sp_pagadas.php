<?php

use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Solicitudpago\Solicitudpago;
use App\Services\Configuracion\ArbolaprobacionService;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill: avisos N4 / firmas SP quedaron Pendiente tras PAGADA (IE o sync Anita).
 */
return new class extends Migration
{
    public function up(): void
    {
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        $ids = Arbolaprobacion_Movimiento::query()
            ->whereNotNull('solicitudpago_id')
            ->where('estado', $nombrePendiente)
            ->whereNull('fechaproceso')
            ->whereIn(
                'solicitudpago_id',
                Solicitudpago::query()->select('id')->where('estado', SolicitudpagoEstados::PAGADA)
            )
            ->distinct()
            ->pluck('solicitudpago_id')
            ->all();

        if ($ids === []) {
            return;
        }

        app(ArbolaprobacionService::class)
            ->anulaMovimientosArbolPendientesAbiertosSolicitudpagoIds(
                $ids,
                'Sin efecto (solicitud pagada)'
            );
    }

    public function down(): void
    {
        // No reversible: no se puede reconstruir el Pendiente original con certeza.
    }
};
