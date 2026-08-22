<?php

namespace App\Support\Seguridad;

use App\Models\Seguridad\IngresoProveedorPersona;

/**
 * Conteo de visitas / tickets de ingreso para el control de contratos de OC.
 */
final class IngresoProveedorConsultaSupport
{
    /**
     * Tickets del proveedor en el período (autorizado, ingresado o finalizado).
     */
    public static function cantidadEnPeriodo(
        int $proveedorId,
        string $desdeYmd,
        string $hastaYmd,
        ?int $empresaId = null,
        ?int $ordencompraId = null
    ): int {
        if ($proveedorId <= 0 || $desdeYmd === '' || $hastaYmd === '') {
            return 0;
        }

        $query = IngresoProveedorPersona::query()
            ->whereNotNull('fecha_ingreso')
            ->whereHas('ingreso', function ($q) use ($proveedorId, $desdeYmd, $hastaYmd, $empresaId, $ordencompraId) {
                $q->where('proveedor_id', $proveedorId)
                    ->whereDate('fecha', '>=', $desdeYmd)
                    ->whereDate('fecha', '<=', $hastaYmd)
                    ->whereIn('estado', [
                        IngresoProveedorEstados::AUTORIZADO,
                        IngresoProveedorEstados::INGRESADO,
                        IngresoProveedorEstados::FINALIZADO,
                    ]);
                if ($empresaId && $empresaId > 0) {
                    $q->where('empresa_id', $empresaId);
                }
                if ($ordencompraId && $ordencompraId > 0) {
                    $q->where('ordencompra_id', $ordencompraId);
                }
            });

        return (int) $query->count();
    }
}
