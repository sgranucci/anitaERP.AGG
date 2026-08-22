<?php

namespace App\Support\Contable\LibroIvaDigital;

use App\Models\Ventas\Venta;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Período de ventas para Libro IVA Digital / IVA Simple.
 * Anita p-rg3685.c recorre ventas por ven_fecha_vto (equivalente a fechajornada).
 */
final class LibroIvaDigitalVentasPeriodoSupport
{
    public static function expresionFechaSql(bool $porFechaJornada, string $alias = 'venta'): string
    {
        $fecha = $alias.'.fecha';
        if (! $porFechaJornada) {
            return $fecha;
        }

        return SqlDialectSupport::coalesce($alias.'.fechajornada', $fecha);
    }

    /**
     * @param  EloquentBuilder<Venta>|QueryBuilder  $query
     */
    public static function aplicarFiltroFecha(
        EloquentBuilder|QueryBuilder $query,
        string $desde,
        string $hasta,
        bool $porFechaJornada,
        string $alias = 'venta',
    ): void {
        $expr = SqlDialectSupport::fecha(self::expresionFechaSql($porFechaJornada, $alias));
        $query->whereBetween(DB::raw($expr), [$desde, $hasta]);
    }

    /**
     * CAE electrónico, o internos sin CAE informables (RMV / FBI / FSL).
     *
     * @param  EloquentBuilder<Venta>|QueryBuilder  $query
     */
    public static function aplicarFiltroCaeORmv(
        EloquentBuilder|QueryBuilder $query,
        string $aliasVenta = 'venta',
        ?string $aliasTipo = null,
    ): void {
        $colCae = $aliasVenta.'.cae';
        $abrevSinCae = LibroIvaDigitalMapeosSupport::abreviaturasSinCaeInformables();
        $query->where(function ($q) use ($colCae, $aliasTipo, $aliasVenta, $abrevSinCae): void {
            $q->where(function ($cae) use ($colCae): void {
                $cae->whereNotNull($colCae)->where($colCae, '<>', '');
            });
            if ($aliasTipo !== null) {
                $q->orWhereIn($aliasTipo.'.abreviatura', $abrevSinCae);

                return;
            }
            $q->orWhereExists(function ($sub) use ($aliasVenta, $abrevSinCae): void {
                $sub->selectRaw('1')
                    ->from('tipotransaccion as tt_lid_sin_cae')
                    ->whereColumn('tt_lid_sin_cae.id', $aliasVenta.'.tipotransaccion_id')
                    ->whereIn('tt_lid_sin_cae.abreviatura', $abrevSinCae)
                    ->whereNull('tt_lid_sin_cae.deleted_at');
            });
        });
    }

    public static function fechaYmd(?string $fechajornada, ?string $fecha, bool $porFechaJornada): string
    {
        $raw = $porFechaJornada
            ? (string) ($fechajornada ?: $fecha)
            : (string) $fecha;

        return date('Ymd', strtotime($raw));
    }

    public static function fechaDocumento(Venta $venta, bool $porFechaJornada): string
    {
        return self::fechaYmd(
            $venta->fechajornada !== null ? (string) $venta->fechajornada : null,
            (string) $venta->fecha,
            $porFechaJornada,
        );
    }
}
