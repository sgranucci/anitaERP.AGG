<?php

namespace App\Support\Contable\LibroIvaDigital;

use App\Models\Ventas\Venta;
use App\Support\Ventas\VentaNumerocomprobanteUnicidadSupport;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Schema;

/**
 * Período de ventas para Libro IVA Digital / IVA Simple.
 * Anita p-rg3685.c recorre ventas por ven_fecha_vto (equivalente a fechajornada).
 *
 * Población de ventas: misma regla que el reporte IVA ventas
 * (punto de venta y tipo con tilde «Va al IVA ventas»; sin exigir CAE;
 * incluye letra T / TurIVA cuando el tipo va al IVA ventas).
 */
final class LibroIvaDigitalVentasPeriodoSupport
{
    public static function columnaFecha(bool $porFechaJornada, string $alias = 'venta'): string
    {
        return $porFechaJornada ? $alias.'.fechajornada' : $alias.'.fecha';
    }

    public static function expresionFechaSql(bool $porFechaJornada, string $alias = 'venta'): string
    {
        return self::columnaFecha($porFechaJornada, $alias);
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
        $query->whereBetween(self::columnaFecha($porFechaJornada, $alias), [$desde, $hasta]);
    }

    /**
     * Misma población que IVA ventas: PV y tipo con tilde «Va al IVA ventas»,
     * sin PRE ni espejos Anita cedidos. No exige CAE ni excluye TurIVA.
     *
     * @param  EloquentBuilder<Venta>|QueryBuilder  $query
     * @param  string|null  $aliasPuntoventa  Alias ya joineado (ej. pv_lid / puntoventa); null → exists
     * @param  string|null  $aliasTipo  Alias ya joineado (ej. tt); null → exists
     */
    public static function aplicarFiltroPoblacionIvaVentas(
        EloquentBuilder|QueryBuilder $query,
        string $aliasVenta = 'venta',
        ?string $aliasPuntoventa = null,
        ?string $aliasTipo = null,
    ): void {
        $colCodigo = $aliasVenta.'.codigo';
        $colTipoId = $aliasVenta.'.tipotransaccion_id';
        $colPvId = $aliasVenta.'.puntoventa_id';

        if ($aliasPuntoventa !== null && Schema::hasColumn('puntoventa', 'iva_ventas')) {
            $query->where($aliasPuntoventa.'.iva_ventas', true);
        } elseif (Schema::hasColumn('puntoventa', 'iva_ventas')) {
            $query->whereExists(function ($sub) use ($colPvId): void {
                $sub->selectRaw('1')
                    ->from('puntoventa as pv_lid_iva')
                    ->whereColumn('pv_lid_iva.id', $colPvId)
                    ->where('pv_lid_iva.iva_ventas', true);
            });
        }

        if ($aliasTipo !== null) {
            if (Schema::hasColumn('tipotransaccion', 'iva_ventas')) {
                $query->where($aliasTipo.'.iva_ventas', true);
            }
            $query->where(function ($q) use ($aliasTipo): void {
                $q->whereNull($aliasTipo.'.abreviatura')
                    ->orWhereRaw('UPPER(TRIM('.$aliasTipo.'.abreviatura)) <> ?', ['PRE']);
            });
        } else {
            $query->whereExists(function ($sub) use ($colTipoId): void {
                $sub->selectRaw('1')
                    ->from('tipotransaccion as tt_lid_iva')
                    ->whereColumn('tt_lid_iva.id', $colTipoId)
                    ->whereNull('tt_lid_iva.deleted_at');
                if (Schema::hasColumn('tipotransaccion', 'iva_ventas')) {
                    $sub->where('tt_lid_iva.iva_ventas', true);
                }
                $sub->where(function ($q): void {
                    $q->whereNull('tt_lid_iva.abreviatura')
                        ->orWhereRaw('UPPER(TRIM(tt_lid_iva.abreviatura)) <> ?', ['PRE']);
                });
            });
        }

        $marca = VentaNumerocomprobanteUnicidadSupport::MARCA_ESPEJO_ANITA;
        $query->where(function ($q) use ($colCodigo, $marca): void {
            $q->whereNull($colCodigo)
                ->orWhere($colCodigo, 'not like', '%'.$marca.'%');
        });
    }

    /**
     * @deprecated Usar aplicarFiltroPoblacionIvaVentas (misma lógica que IVA ventas).
     * Conservado por compatibilidad; ya no se aplica en Libro / IVA Simple.
     *
     * @param  EloquentBuilder<Venta>|QueryBuilder  $query
     */
    public static function aplicarFiltroCaeORmv(
        EloquentBuilder|QueryBuilder $query,
        string $aliasVenta = 'venta',
        ?string $aliasTipo = null,
    ): void {
        self::aplicarFiltroPoblacionIvaVentas($query, $aliasVenta, null, $aliasTipo);
    }

    /**
     * @deprecated TurIVA entra si el tipo tiene «Va al IVA ventas» (igual que el reporte).
     * No-op conservado por compatibilidad.
     *
     * @param  EloquentBuilder<Venta>|QueryBuilder  $query
     */
    public static function aplicarExcluirTuriva(
        EloquentBuilder|QueryBuilder $query,
        string $aliasVenta = 'venta',
    ): void {
        // Intencionalmente vacío: alineado con IVA ventas (subdiario A/B/T/E).
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
