<?php

declare(strict_types=1);

namespace App\Support\Ventas\IvaVentas;

use App\Support\Contable\CierreRendicionEstacionamientoAsientoSupport;
use App\Support\Contable\CierreRendicionEstacionamientoConfigSupport;
use App\Support\Contable\CierreRendicionMaquinavendingAsientoSupport;
use App\Support\Contable\CierreRendicionMaquinavendingConfigSupport;
use App\Support\Database\SqlDialectSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosGrabacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoConfigSupport;
use App\Support\Ventas\IvaVentasListadoFiltros;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Cuentas contables de imputación por unidad de negocio (gastronomía, estacionamiento, vending, otros).
 */
final class IvaVentasConciliacionUnidadCuentaSupport
{
    /**
     * @return array<string, array{
     *   key: string,
     *   label: string,
     *   ventas_gravadas: list<int>,
     *   ventas_kiosco: list<int>,
     *   iva: list<int>,
     *   cuentas_detalle: list<array{rol: string, id: int, codigo: string, nombre: string}>
     * }>
     */
    public static function mapaUnidades(int $empresaId): array
    {
        $gastro = CierreJornadaProcesoConfigSupport::paraEmpresaConDetalle($empresaId);
        $est = CierreRendicionEstacionamientoConfigSupport::paraEmpresa($empresaId);
        $vend = CierreRendicionMaquinavendingConfigSupport::paraEmpresa($empresaId);

        $ivaGlobal = self::idsIvaGlobalEmpresa($empresaId);

        $gastroVentas = self::idsDesdeEnteros([
            (int) ($gastro['cuenta_ventas_id'] ?? 0),
        ]);
        $gastroKiosco = self::idsDesdeEnteros([
            (int) ($gastro['cuenta_ventas_kiosco_id'] ?? 0),
        ]);
        $gastroIva = self::idsDesdeEnteros([
            (int) ($gastro['cuenta_iva_id'] ?? 0),
        ]);
        $gastroIva = array_values(array_unique(array_merge($gastroIva, $ivaGlobal)));

        $estVentas = self::idsDesdeEnteros([
            (int) ($est['cuenta_ventas_id'] ?? 0),
        ]);
        $estIva = self::idsDesdeEnteros([
            (int) ($est['cuenta_iva_debito_id'] ?? 0),
            (int) ($est['cuenta_iva_credito_id'] ?? 0),
        ]);
        $estIva = array_values(array_unique(array_merge($estIva, $ivaGlobal)));

        $vendVentas = self::idsDesdeEnteros([
            (int) ($vend['cuenta_ventas_id'] ?? 0),
        ]);
        $vendKiosco = self::idsDesdeEnteros([
            (int) ($vend['cuenta_ventas_kiosco_id'] ?? 0),
        ]);
        $vendIva = self::idsDesdeEnteros([
            (int) ($vend['cuenta_iva_id'] ?? 0),
        ]);
        $vendIva = array_values(array_unique(array_merge($vendIva, $ivaGlobal)));

        $otrosCuentas = IvaVentasConciliacionCuentaSupport::cuentasConciliacionEmpresa($empresaId);
        $otrosVentas = array_values(array_diff(
            array_merge(
                $otrosCuentas['ventas_gravadas'] ?? [],
                $otrosCuentas['ventas_kiosco'] ?? [],
            ),
            array_merge($gastroVentas, $gastroKiosco, $estVentas, $vendVentas, $vendKiosco),
        ));
        $otrosIva = array_values(array_diff(
            array_merge(
                $otrosCuentas['iva_debito'] ?? [],
                $otrosCuentas['percepcion_iva'] ?? [],
                $otrosCuentas['iva_credito'] ?? [],
            ),
            array_merge($gastroIva, $estIva, $vendIva),
        ));

        $mapa = [
            IvaVentasUnidadNegocioSupport::GASTRONOMIA => self::armarUnidad(
                IvaVentasUnidadNegocioSupport::GASTRONOMIA,
                $gastroVentas,
                $gastroKiosco,
                $gastroIva,
                $empresaId,
            ),
            IvaVentasUnidadNegocioSupport::ESTACIONAMIENTO => self::armarUnidad(
                IvaVentasUnidadNegocioSupport::ESTACIONAMIENTO,
                $estVentas,
                [],
                $estIva,
                $empresaId,
            ),
            IvaVentasUnidadNegocioSupport::VENDING => self::armarUnidad(
                IvaVentasUnidadNegocioSupport::VENDING,
                $vendVentas,
                $vendKiosco,
                $vendIva,
                $empresaId,
            ),
            IvaVentasUnidadNegocioSupport::OTROS => self::armarUnidad(
                IvaVentasUnidadNegocioSupport::OTROS,
                array_values(array_unique(array_filter($otrosVentas, static fn (int $id) => ! in_array($id, $gastroKiosco, true)))),
                [],
                $otrosIva,
                $empresaId,
            ),
        ];

        return $mapa;
    }

    /**
     * Expresión SQL CASE para clasificar un asiento en unidad de negocio.
     *
     * @param  list<int>  $vendingPvIds
     */
    public static function sqlClasificarUnidadAsiento(array $vendingPvIds): string
    {
        $estPat = addslashes(CierreRendicionEstacionamientoAsientoSupport::DESCRIPCION_ASIENTO);
        $vendPat = addslashes(CierreRendicionMaquinavendingAsientoSupport::DESCRIPCION_ASIENTO);
        $gastroVenta = addslashes(CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO);
        $gastroFf = addslashes(CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO_COMPENSACION_FONDO_FIJO);

        $vendingIn = '';
        if ($vendingPvIds !== []) {
            $vendingIn = 'WHEN v.puntoventa_id IN ('.implode(',', array_map('intval', $vendingPvIds)).') THEN '
                ."'".IvaVentasUnidadNegocioSupport::VENDING."' ";
        }

        return 'CASE '
            ."WHEN a.observacion LIKE '{$estPat}%' THEN '".IvaVentasUnidadNegocioSupport::ESTACIONAMIENTO."' "
            ."WHEN a.observacion LIKE '{$vendPat}%' THEN '".IvaVentasUnidadNegocioSupport::VENDING."' "
            ."WHEN a.observacion LIKE '{$gastroVenta}%' THEN '"
            .IvaVentasUnidadNegocioSupport::GASTRONOMIA."' "
            ."WHEN a.observacion LIKE '%{$gastroFf}%' THEN '".IvaVentasUnidadNegocioSupport::GASTRONOMIA."' "
            ."WHEN a.observacion LIKE '%jornada%' OR a.observacion LIKE '%Waitry%' OR a.observacion LIKE '%waitry%' THEN '"
            .IvaVentasUnidadNegocioSupport::GASTRONOMIA."' "
            .'WHEN vee.venta_id IS NOT NULL THEN \''.IvaVentasUnidadNegocioSupport::ESTACIONAMIENTO.'\' '
            .'WHEN vge.venta_id IS NOT NULL THEN \''.IvaVentasUnidadNegocioSupport::GASTRONOMIA.'\' '
            .$vendingIn
            ."ELSE '".IvaVentasUnidadNegocioSupport::OTROS."' "
            .'END';
    }

    /**
     * Día contable alineado al reporte (fechajornada / jornada en observación / fecha asiento).
     */
    public static function sqlDiaContableExpr(string $ordenFecha): string
    {
        if ($ordenFecha !== IvaVentasListadoFiltros::ORDEN_FECHA_JORNADA) {
            return SqlDialectSupport::fecha('a.fecha');
        }

        $estPat = addslashes(CierreRendicionEstacionamientoAsientoSupport::DESCRIPCION_ASIENTO);
        $vendPat = addslashes(CierreRendicionMaquinavendingAsientoSupport::DESCRIPCION_ASIENTO);
        $fechaCierreRendicion = self::sqlFechaJornadaDesdeObservacionCierreRendicion('a.observacion');
        $fechaTrasJornada = SqlDialectSupport::aFecha(
            SqlDialectSupport::textoTrasLiteral('a.observacion', 'jornada', 8, 10),
            'Y-m-d'
        );

        return 'CASE '
            .'WHEN a.venta_id IS NOT NULL THEN '.SqlDialectSupport::fecha('v.fechajornada').' '
            .'WHEN a.observacion LIKE "%jornada%" THEN '.$fechaTrasJornada.' '
            ."WHEN a.observacion LIKE '{$estPat}%' OR a.observacion LIKE '{$vendPat}%' THEN {$fechaCierreRendicion} "
            .'ELSE '.SqlDialectSupport::fecha('a.fecha').' '
            .'END';
    }

    /**
     * Restringe asientos al período del reporte (misma lógica que el cuadre global).
     */
    public static function aplicarFiltroPeriodoConciliacion(Builder $query, array $filtros): void
    {
        $fechaDesde = (string) ($filtros['fecha_desde'] ?? '');
        $fechaHasta = (string) ($filtros['fecha_hasta'] ?? '');
        $ordenFecha = (string) ($filtros['orden_fecha'] ?? IvaVentasListadoFiltros::ORDEN_FECHA_JORNADA);

        if ($fechaDesde === '' || $fechaHasta === '') {
            return;
        }

        if ($ordenFecha !== IvaVentasListadoFiltros::ORDEN_FECHA_JORNADA) {
            $query->whereDate('a.fecha', '>=', $fechaDesde)
                ->whereDate('a.fecha', '<=', $fechaHasta);

            return;
        }

        $estPat = CierreRendicionEstacionamientoAsientoSupport::DESCRIPCION_ASIENTO;
        $vendPat = CierreRendicionMaquinavendingAsientoSupport::DESCRIPCION_ASIENTO;
        $fechaCierreRendicion = self::sqlFechaJornadaDesdeObservacionCierreRendicion('a.observacion');
        $textoJornada = SqlDialectSupport::textoTrasLiteral('a.observacion', 'jornada', 8, 10);

        $query->where(function ($q) use ($fechaDesde, $fechaHasta, $estPat, $vendPat, $fechaCierreRendicion, $textoJornada) {
            $q->where(function ($q2) use ($fechaDesde, $fechaHasta, $textoJornada) {
                $q2->whereNull('a.venta_id')
                    ->where('a.observacion', 'like', '%jornada%')
                    ->whereRaw(
                        $textoJornada.' BETWEEN ? AND ?',
                        [$fechaDesde, $fechaHasta],
                    );
            })->orWhere(function ($q2) use ($fechaDesde, $fechaHasta) {
                $q2->whereNotNull('a.venta_id')
                    ->whereDate('v.fechajornada', '>=', $fechaDesde)
                    ->whereDate('v.fechajornada', '<=', $fechaHasta);
            })->orWhere(function ($q2) use ($fechaDesde, $fechaHasta, $estPat, $vendPat, $fechaCierreRendicion) {
                $q2->whereNull('a.venta_id')
                    ->where(function ($q3) use ($estPat, $vendPat) {
                        $q3->where('a.observacion', 'like', $estPat.'%')
                            ->orWhere('a.observacion', 'like', $vendPat.'%');
                    })
                    ->whereRaw("{$fechaCierreRendicion} BETWEEN ? AND ?", [$fechaDesde, $fechaHasta]);
            })->orWhere(function ($q2) use ($fechaDesde, $fechaHasta) {
                $q2->whereNull('a.venta_id')
                    ->where('a.observacion', 'not like', '%jornada%')
                    ->where('a.observacion', 'not like', CierreRendicionEstacionamientoAsientoSupport::DESCRIPCION_ASIENTO.'%')
                    ->where('a.observacion', 'not like', CierreRendicionMaquinavendingAsientoSupport::DESCRIPCION_ASIENTO.'%')
                    ->whereDate('a.fecha', '>=', $fechaDesde)
                    ->whereDate('a.fecha', '<=', $fechaHasta);
            });
        });
    }

    /**
     * Si la cuenta pertenece a una sola unidad (ventas / kiosco), fuerza esa unidad.
     *
     * @param  array<string, array{ventas_gravadas: list<int>, ventas_kiosco: list<int>, iva: list<int>}>  $mapaUnidades
     */
    public static function resolverUnidadMovimiento(string $unidadSql, int $cuentaId, array $mapaUnidades): string
    {
        if ($unidadSql !== IvaVentasUnidadNegocioSupport::OTROS) {
            return $unidadSql;
        }

        $exclusiva = self::resolverUnidadExclusivaPorCuenta($mapaUnidades, $cuentaId);
        if ($exclusiva !== null) {
            return $exclusiva;
        }

        return IvaVentasUnidadNegocioSupport::OTROS;
    }

    /**
     * @param  array<string, array{ventas_gravadas: list<int>, ventas_kiosco: list<int>, iva: list<int>}>  $mapaUnidades
     */
    private static function resolverUnidadExclusivaPorCuenta(array $mapaUnidades, int $cuentaId): ?string
    {
        if ($cuentaId <= 0) {
            return null;
        }

        static $cache = [];
        $cacheKey = md5(serialize($mapaUnidades)).':'.$cuentaId;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $dueño = null;
        foreach ($mapaUnidades as $key => $cfg) {
            $roles = array_merge($cfg['ventas_gravadas'] ?? [], $cfg['ventas_kiosco'] ?? []);
            if (! in_array($cuentaId, $roles, true)) {
                continue;
            }

            if ($dueño !== null) {
                return $cache[$cacheKey] = null;
            }

            $dueño = (string) $key;
        }

        return $cache[$cacheKey] = $dueño;
    }

    /**
     * Fecha jornada embebida en observación de cierre rendición (dd/mm/yyyy).
     */
    private static function sqlFechaJornadaDesdeObservacionCierreRendicion(string $columnaObservacion): string
    {
        return SqlDialectSupport::aFecha(
            'TRIM('.SqlDialectSupport::parteDelimitada($columnaObservacion, ' — ', 2).')',
            'd/m/Y'
        );
    }

    /**
     * @return list<int>
     */
    private static function idsIvaGlobalEmpresa(int $empresaId): array
    {
        $cuentas = IvaVentasConciliacionCuentaSupport::cuentasConciliacionEmpresa($empresaId);

        return array_values(array_unique(array_merge(
            $cuentas['iva_debito'] ?? [],
            $cuentas['percepcion_iva'] ?? [],
            $cuentas['iva_credito'] ?? [],
        )));
    }

    /**
     * @param  list<int>  $ventasGravadas
     * @param  list<int>  $ventasKiosco
     * @param  list<int>  $iva
     * @return array{
     *   key: string,
     *   label: string,
     *   ventas_gravadas: list<int>,
     *   ventas_kiosco: list<int>,
     *   iva: list<int>,
     *   cuentas_detalle: list<array{rol: string, id: int, codigo: string, nombre: string}>
     * }
     */
    private static function armarUnidad(string $key, array $ventasGravadas, array $ventasKiosco, array $iva, int $empresaId): array
    {
        $detalle = [];
        foreach ($ventasGravadas as $id) {
            self::agregarDetalle($detalle, 'ventas_gravadas', $id, $empresaId);
        }
        foreach ($ventasKiosco as $id) {
            self::agregarDetalle($detalle, 'ventas_kiosco', $id, $empresaId);
        }
        foreach ($iva as $id) {
            self::agregarDetalle($detalle, 'iva', $id, $empresaId);
        }

        return [
            'key' => $key,
            'label' => IvaVentasUnidadNegocioSupport::label($key),
            'ventas_gravadas' => $ventasGravadas,
            'ventas_kiosco' => $ventasKiosco,
            'iva' => $iva,
            'cuentas_detalle' => $detalle,
        ];
    }

    /**
     * @param  list<array{rol: string, id: int, codigo: string, nombre: string}>  $detalle
     */
    private static function agregarDetalle(array &$detalle, string $rol, int $id, int $empresaId): void
    {
        if ($id <= 0) {
            return;
        }

        foreach ($detalle as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                return;
            }
        }

        $row = DB::table('cuentacontable')
            ->where('id', $id)
            ->where('empresa_id', $empresaId)
            ->first(['codigo', 'nombre']);

        $detalle[] = [
            'rol' => $rol,
            'id' => $id,
            'codigo' => (string) ($row->codigo ?? ''),
            'nombre' => (string) ($row->nombre ?? ''),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private static function idsDesdeEnteros(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }
}
