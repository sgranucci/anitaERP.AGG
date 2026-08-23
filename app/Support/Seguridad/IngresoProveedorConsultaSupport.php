<?php

namespace App\Support\Seguridad;

use App\Models\Seguridad\IngresoProveedor;
use App\Models\Seguridad\IngresoProveedorPersona;

/**
 * Conteo de visitas / tickets de ingreso para el control de contratos de OC.
 */
final class IngresoProveedorConsultaSupport
{
    /**
     * Tickets Finalizado del proveedor en el período (spec §9 / §11).
     * El mínimo del abono cuenta tickets, no personas con ENTRO.
     */
    public static function cantidadEnPeriodo(
        int $proveedorId,
        string $desdeYmd,
        string $hastaYmd,
        ?int $empresaId = null,
        ?int $ordencompraId = null
    ): int {
        return self::cantidadTicketsFinalizadosEnPeriodo(
            $proveedorId,
            $desdeYmd,
            $hastaYmd,
            $empresaId,
            $ordencompraId
        );
    }

    public static function cantidadTicketsFinalizadosEnPeriodo(
        int $proveedorId,
        string $desdeYmd,
        string $hastaYmd,
        ?int $empresaId = null,
        ?int $ordencompraId = null
    ): int {
        if ($proveedorId <= 0 || $desdeYmd === '' || $hastaYmd === '') {
            return 0;
        }

        $query = IngresoProveedor::query()
            ->where('proveedor_id', $proveedorId)
            ->where('estado', IngresoProveedorEstados::FINALIZADO)
            ->whereDate('fecha', '>=', $desdeYmd)
            ->whereDate('fecha', '<=', $hastaYmd);
        if ($empresaId && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }
        if ($ordencompraId && $ordencompraId > 0) {
            $query->where('ordencompra_id', $ordencompraId);
        }

        return (int) $query->count();
    }

    /**
     * Personas que efectivamente ingresaron (portería), cualquier estado posterior al ENTRO.
     */
    public static function cantidadPersonasIngresadasEnPeriodo(
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
