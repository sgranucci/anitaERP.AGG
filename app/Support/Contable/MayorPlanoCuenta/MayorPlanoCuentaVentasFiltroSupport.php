<?php

declare(strict_types=1);

namespace App\Support\Contable\MayorPlanoCuenta;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Filtro “solo movimientos de ventas” del mayor plano.
 *
 * Anita: subd_sistema / subh_sistema / ctav_sistema = V.
 * ERP: factura de venta (venta_id, tipo VTA) o metadatos Anita sistema V.
 */
final class MayorPlanoCuentaVentasFiltroSupport
{
    public const SISTEMA_VENTAS = 'V';

    public const TIPO_ASIENTO_VENTA = 'VTA';

    /**
     * @param  array<string, mixed>  $movimiento
     */
    public static function esMovimientoVentas(array $movimiento): bool
    {
        if (strtoupper(trim((string) ($movimiento['sistema'] ?? ''))) === self::SISTEMA_VENTAS) {
            return true;
        }

        if (strtoupper(trim((string) ($movimiento['tipo_asiento'] ?? ''))) === self::TIPO_ASIENTO_VENTA) {
            return true;
        }

        $fks = $movimiento['erp_asiento_fks'] ?? null;
        if (is_array($fks) && (int) ($fks['venta_id'] ?? 0) > 0) {
            return true;
        }

        return false;
    }

    public static function condicionSqlSistema(string $columna): string
    {
        $columna = trim($columna);
        if ($columna === '' || ! preg_match('/^[a-z_]+$/', $columna)) {
            return '';
        }

        return ' AND '.$columna."='".self::SISTEMA_VENTAS."'";
    }

    /**
     * @param  list<string>  $columnasAnita
     */
    public static function aplicarFiltroErpQuery(Builder $query, array $columnasAnita = []): void
    {
        $query->where(function (Builder $q) use ($columnasAnita): void {
            $q->where('t.abreviatura', self::TIPO_ASIENTO_VENTA);

            if (in_array('anita_sistema', $columnasAnita, true)) {
                $q->orWhere('a.anita_sistema', self::SISTEMA_VENTAS);
            }

            if (Schema::hasColumn('asiento', 'venta_id')) {
                $q->orWhere('a.venta_id', '>', 0);
            }

            $q->orWhere('a.observacion', 'like', '%[SUBH] V %')
                ->orWhere('a.observacion', 'like', '%[SUBD] V %')
                ->orWhere('a.observacion', 'like', '%[SUBHIST] V %')
                ->orWhere('a.observacion', 'like', '%[SUBDIARIO] V %');
        });
    }
}
