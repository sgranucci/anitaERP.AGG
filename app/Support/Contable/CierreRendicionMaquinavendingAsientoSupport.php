<?php

namespace App\Support\Contable;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\RendicionMaquinavendingCaja;
use App\Models\Contable\Cuentacontable;
use App\Models\Stock\Articulo;
use App\Support\Ventas\Gastronomia\CierreJornadaVentasCigarrillosSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Preview del asiento contable por rendición vending (lógica haber tipo cierre Waitry, sin NC).
 */
final class CierreRendicionMaquinavendingAsientoSupport
{
    public const DESCRIPCION_ASIENTO = 'Cierre rendición vending';

    private const TOLERANCIA_CUADRE = 0.02;

    /**
     * @return array{
     *   lineas: list<array<string, mixed>>,
     *   advertencias: list<string>,
     *   resumen_debe: float,
     *   resumen_haber: float,
     *   titulo: string,
     *   cantidad_rendiciones: int,
     *   rendicion_ids: list<int>
     * }
     */
    public static function generarPreviewGrupo(Collection $rendiciones, array $config): array
    {
        if ($rendiciones->isEmpty()) {
            throw new InvalidArgumentException('No hay rendiciones en el grupo.');
        }

        foreach ($rendiciones as $rendicion) {
            self::assertRendicionCerrable($rendicion);
        }

        $primera = $rendiciones->first();
        $empresaId = (int) $primera->empresa_id;
        foreach ($rendiciones as $rendicion) {
            if ((int) $rendicion->empresa_id !== $empresaId) {
                throw new InvalidArgumentException('El grupo mezcla empresas distintas.');
            }
            if ((int) $rendicion->puntoventa_cae_id !== (int) $primera->puntoventa_cae_id) {
                throw new InvalidArgumentException('El grupo mezcla puntos de venta distintos.');
            }
            if (CierreRendicionMaquinavendingGrupoSupport::fechaDiaDesdeRendicion($rendicion)
                !== CierreRendicionMaquinavendingGrupoSupport::fechaDiaDesdeRendicion($primera)) {
                throw new InvalidArgumentException('El grupo mezcla fechas distintas.');
            }
        }

        $rendiciones->loadMissing([
            'movimientos.cuentacaja',
            'maquinavendingRendicion.articulos.articulo',
        ]);

        $datosDebe = self::datosDebeDesdeMovimientos($rendiciones);
        $datosVentas = self::datosVentasConsolidadas($rendiciones, $empresaId);
        $difCajaTotal = 0.;
        foreach ($rendiciones as $rendicion) {
            $difCajaTotal = round($difCajaTotal + self::calcularDiferenciaCajaPreview($rendicion), 2);
        }

        $cuentaVentas = (int) ($config['cuenta_ventas_id'] ?? 0);
        $cuentaVentasKiosco = (int) ($config['cuenta_ventas_kiosco_id'] ?? 0);
        if ($cuentaVentasKiosco <= 0) {
            $cuentaVentasKiosco = $cuentaVentas;
        }
        $cuentaIva = (int) ($config['cuenta_iva_id'] ?? 0);
        $cuentaDifCaja = (int) ($config['cuenta_diferencia_caja_id'] ?? 0);

        $lineas = [];

        foreach ($datosDebe['debe_por_cuentacaja'] as $row) {
            $monto = round((float) ($row['monto'] ?? 0), 2);
            if ($monto <= 0.0001) {
                continue;
            }
            $lineas[] = self::lineaDebe(
                (string) ($row['concepto'] ?? 'Medio de cobro'),
                (int) $row['cuentacaja_id'],
                $monto,
            );
        }

        $lineas = array_merge(
            $lineas,
            self::lineasHaberVentasSinNc(
                $datosVentas['ventas_gravadas'],
                $datosVentas['ventas_kiosco'],
                $datosVentas['iva_normal'],
                $datosVentas['iva_cigarrillos'],
                $cuentaVentas,
                $cuentaVentasKiosco,
                $cuentaIva,
            ),
        );

        if (abs($difCajaTotal) > 0.0001 && $cuentaDifCaja > 0) {
            if ($difCajaTotal > 0) {
                $lineas[] = self::lineaDebe('Diferencia de caja', $cuentaDifCaja, $difCajaTotal);
            } else {
                $lineas[] = self::lineaHaber('Diferencia de caja', $cuentaDifCaja, abs($difCajaTotal));
            }
        }

        $debe = 0.;
        $haber = 0.;
        foreach ($lineas as $ln) {
            $debe += (float) ($ln['debe'] ?? 0);
            $haber += (float) ($ln['haber'] ?? 0);
        }

        $pv = $primera->puntoventaCae?->codigo ?? $primera->puntoventaCaea?->codigo ?? '';
        $fechaDia = CierreRendicionMaquinavendingGrupoSupport::fechaDiaDesdeRendicion($primera);
        $fechaFmt = Carbon::parse($fechaDia)->format('d/m/Y');
        $ids = $rendiciones->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $titulo = 'Cierre vending — '.$fechaFmt
            .($pv !== '' ? ' — PV '.$pv : '')
            .' — '.$rendiciones->count().' rendición(es)';

        $advertencias = array_values(array_unique($datosDebe['advertencias'] ?? []));
        if (abs($debe - $haber) > self::TOLERANCIA_CUADRE) {
            $advertencias[] = 'El asiento no cuadra: debe '.number_format($debe, 2, ',', '.')
                .' vs haber '.number_format($haber, 2, ',', '.').'.';
        }

        return [
            'lineas' => $lineas,
            'advertencias' => $advertencias,
            'resumen_debe' => round($debe, 2),
            'resumen_haber' => round($haber, 2),
            'titulo' => $titulo,
            'cantidad_rendiciones' => $rendiciones->count(),
            'rendicion_ids' => $ids,
        ];
    }

    public static function armarPayloadAsiento(
        array $lineas,
        int $empresaId,
        array $config,
        string $fecha,
        string $observacion = self::DESCRIPCION_ASIENTO,
    ): array {
        /** @var array<int, int> $cache */
        $cache = [];
        $cuentacontableIds = [];
        $debes = [];
        $haberes = [];
        $monedaIds = [];
        $centrocostoIds = [];
        $cotizaciones = [];
        $observaciones = [];

        foreach ($lineas as $ln) {
            $debe = round((float) ($ln['debe'] ?? 0), 2);
            $haber = round((float) ($ln['haber'] ?? 0), 2);
            if (abs($debe) <= 0.0001 && abs($haber) <= 0.0001) {
                continue;
            }

            $cuentaRefId = (int) ($ln['cuenta_id'] ?? 0);
            if ($cuentaRefId <= 0) {
                throw new InvalidArgumentException('Línea sin cuenta: '.trim((string) ($ln['concepto'] ?? '')));
            }

            $cuentacontableId = self::resolverCuentacontableId($cuentaRefId, $empresaId, $config, $cache);
            $cuentacontableIds[] = $cuentacontableId;
            $debes[] = $debe > 0.0001 ? $debe : '';
            $haberes[] = $haber > 0.0001 ? $haber : '';
            $monedaIds[] = 1;
            $centrocostoIds[] = null;
            $cotizaciones[] = 1.;
            $observaciones[] = $observacion;
        }

        $sumDebe = round(array_sum(array_filter($debes, 'is_numeric')), 2);
        $sumHaber = round(array_sum(array_filter($haberes, 'is_numeric')), 2);
        if (abs($sumDebe - $sumHaber) > self::TOLERANCIA_CUADRE) {
            throw new InvalidArgumentException(
                'El asiento no cuadra (debe '.$sumDebe.' vs haber '.$sumHaber.').',
            );
        }

        return [
            'empresa_id' => $empresaId,
            'fecha' => $fecha,
            'observacion' => $observacion,
            'cuentacontable_ids' => $cuentacontableIds,
            'debes' => $debes,
            'haberes' => $haberes,
            'moneda_ids' => $monedaIds,
            'centrocosto_ids' => $centrocostoIds,
            'cotizaciones' => $cotizaciones,
            'observaciones' => $observaciones,
            'path_sistema' => 'V',
        ];
    }

    public static function fechaAsientoDesdeGrupo(string $fechaDia): string
    {
        return CierreRendicionMaquinavendingGrupoSupport::fechaAsientoDesdeGrupo($fechaDia);
    }

    public static function assertRendicionCerrable(RendicionMaquinavendingCaja $rendicion): void
    {
        if ($rendicion->tieneCierreContable()) {
            throw new InvalidArgumentException(
                'La rendición #'.$rendicion->id.' ya tiene cierre contable registrado.',
            );
        }
    }

    public static function calcularDiferenciaCajaPreview(RendicionMaquinavendingCaja $rendicion): float
    {
        return round(
            (float) ($rendicion->sobrantefaltante ?? 0)
            + (float) ($rendicion->totalredondeo ?? 0)
            + (float) ($rendicion->totalinvitacion ?? 0),
            2,
        );
    }

    /**
     * @param  Collection<int, RendicionMaquinavendingCaja>  $rendiciones
     * @return array{
     *   ventas_gravadas: float,
     *   ventas_kiosco: float,
     *   iva_normal: float,
     *   iva_cigarrillos: float
     * }
     */
    private static function datosVentasConsolidadas(Collection $rendiciones, int $empresaId): array
    {
        $totalFactura = 0.;
        $importeCig = 0.;

        foreach ($rendiciones as $rendicion) {
            $monto = round((float) ($rendicion->totalfactura ?? 0), 2);
            if ($monto <= 0.0001) {
                continue;
            }
            $totalFactura = round($totalFactura + $monto, 2);

            $rendVentas = $rendicion->maquinavendingRendicion;
            if ($rendVentas === null) {
                continue;
            }
            foreach ($rendVentas->articulos ?? [] as $linea) {
                $articulo = $linea->articulo;
                if (! $articulo instanceof Articulo) {
                    continue;
                }
                if (CierreJornadaVentasCigarrillosSupport::articuloEsLineaMenuCigarrillos($articulo)) {
                    $importeCig = round($importeCig + (float) ($linea->importe_total ?? 0), 2);
                }
            }
        }

        $desglose = CierreJornadaVentasCigarrillosSupport::desglosarImportesContables(
            $totalFactura,
            0.0,
            $importeCig,
        );

        return [
            'ventas_gravadas' => round((float) ($desglose['ventas_gravadas'] ?? 0), 2),
            'ventas_kiosco' => round((float) ($desglose['ventas_kiosco'] ?? 0), 2),
            'iva_normal' => round((float) ($desglose['iva_normal'] ?? 0), 2),
            'iva_cigarrillos' => round((float) ($desglose['iva_cigarrillos'] ?? 0), 2),
        ];
    }

    /**
     * @param  Collection<int, RendicionMaquinavendingCaja>  $rendiciones
     * @return array{
     *   debe_por_cuentacaja: list<array{concepto:string,cuentacaja_id:int,monto:float}>,
     *   advertencias: list<string>
     * }
     */
    private static function datosDebeDesdeMovimientos(Collection $rendiciones): array
    {
        /** @var array<int, array{concepto:string,cuentacaja_id:int,monto:float}> */
        $debePorCuentacaja = [];
        $advertencias = [];

        foreach ($rendiciones as $rendicion) {
            $movimientos = $rendicion->movimientos;
            if ($movimientos === null || $movimientos->isEmpty()) {
                $advertencias[] = 'Rendición #'.(int) $rendicion->id.': sin movimientos de caja.';

                continue;
            }

            foreach ($movimientos as $mov) {
                $ccId = (int) ($mov->cuentacaja_id ?? 0);
                $monto = round((float) ($mov->monto ?? 0), 2);
                if ($ccId <= 0 || $monto <= 0.0001) {
                    continue;
                }
                $caja = $mov->cuentacaja;
                $label = self::etiquetaCuentacaja($caja, $ccId);
                if (! isset($debePorCuentacaja[$ccId])) {
                    $debePorCuentacaja[$ccId] = [
                        'concepto' => 'Medio de cobro — '.$label,
                        'cuentacaja_id' => $ccId,
                        'monto' => 0.,
                    ];
                }
                $debePorCuentacaja[$ccId]['monto'] = round($debePorCuentacaja[$ccId]['monto'] + $monto, 2);
            }
        }

        return [
            'debe_por_cuentacaja' => array_values($debePorCuentacaja),
            'advertencias' => $advertencias,
        ];
    }

    /**
     * Haber ventas/IVA al estilo cierre Waitry, solo importes positivos (sin NC).
     *
     * @return list<array<string, mixed>>
     */
    private static function lineasHaberVentasSinNc(
        float $ventasGravadas,
        float $ventasKiosco,
        float $ivaNormal,
        float $ivaCigarrillos,
        int $cuentaVentas,
        int $cuentaVentasKiosco,
        int $cuentaIva,
    ): array {
        $lineas = [];
        if ($ventasGravadas > 0.0001) {
            $lineas[] = self::lineaHaber('Ventas gravadas', $cuentaVentas, $ventasGravadas);
        }
        if ($ventasKiosco > 0.0001) {
            $lineas[] = self::lineaHaber(
                'Ventas kiosco (gravado + imp. interno)',
                $cuentaVentasKiosco,
                $ventasKiosco,
            );
        }
        if ($ivaNormal > 0.0001) {
            $lineas[] = self::lineaHaber('IVA débito fiscal', $cuentaIva, $ivaNormal);
        }
        if ($ivaCigarrillos > 0.0001) {
            $lineas[] = self::lineaHaber(
                'IVA débito fiscal — cigarrillos / kiosco (imp. interno)',
                $cuentaIva,
                $ivaCigarrillos,
            );
        }

        return $lineas;
    }

    /**
     * @param  array<int, int>  $cache
     */
    private static function resolverCuentacontableId(
        int $cuentaRefId,
        int $empresaId,
        array $config,
        array &$cache,
    ): int {
        if (isset($cache[$cuentaRefId])) {
            return $cache[$cuentaRefId];
        }

        foreach ([
            'cuenta_ventas',
            'cuenta_ventas_kiosco',
            'cuenta_iva',
            'cuenta_diferencia_caja',
        ] as $base) {
            $cfgId = (int) ($config[$base.'_id'] ?? 0);
            if ($cfgId > 0 && $cfgId === $cuentaRefId) {
                $cache[$cuentaRefId] = $cfgId;

                return $cfgId;
            }
        }

        $caja = Cuentacaja::query()
            ->with('cuentacontables:id,codigo,nombre,empresa_id')
            ->find($cuentaRefId);

        if ($caja !== null) {
            $cc = $caja->cuentacontables;
            if ($cc !== null && (int) ($cc->empresa_id ?? 0) === $empresaId) {
                $cache[$cuentaRefId] = (int) $cc->id;

                return (int) $cc->id;
            }
        }

        $directa = (int) (Cuentacontable::query()
            ->where('empresa_id', $empresaId)
            ->whereKey($cuentaRefId)
            ->value('id') ?? 0);
        if ($directa > 0) {
            $cache[$cuentaRefId] = $directa;

            return $directa;
        }

        throw new InvalidArgumentException(
            'No se pudo resolver cuenta contable para referencia #'.$cuentaRefId.' (empresa '.$empresaId.').',
        );
    }

    private static function etiquetaCuentacaja(?Cuentacaja $caja, int $fallbackId): string
    {
        if ($caja === null) {
            return '#'.$fallbackId;
        }
        $codigo = trim((string) ($caja->codigo ?? ''));
        $nombre = trim((string) ($caja->nombre ?? ''));

        return $codigo !== '' && $nombre !== ''
            ? $codigo.' — '.$nombre
            : ($codigo !== '' ? $codigo : ($nombre !== '' ? $nombre : '#'.$fallbackId));
    }

    /**
     * @return array<string, mixed>
     */
    private static function lineaDebe(string $concepto, int $cuentaId, float $monto): array
    {
        return [
            'concepto' => $concepto,
            'cuenta_id' => $cuentaId,
            'debe' => round($monto, 2),
            'haber' => 0.,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function lineaHaber(string $concepto, int $cuentaId, float $monto): array
    {
        return [
            'concepto' => $concepto,
            'cuenta_id' => $cuentaId,
            'debe' => 0.,
            'haber' => round($monto, 2),
        ];
    }
}
