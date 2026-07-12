<?php

namespace App\Support\Contable;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Estacionamiento\VentaEstacionamientoEmision;
use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Models\Contable\Cuentacontable;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Preview del asiento contable por rendición de turno estacionamiento.
 */
final class CierreRendicionEstacionamientoAsientoSupport
{
    public const DESCRIPCION_ASIENTO = 'Cierre rendición estacionamiento';

    private const TASA_IVA = 21.0;

    private const TOLERANCIA_CUADRE = 0.02;

    /**
     * @return array{
     *   lineas: list<array<string, mixed>>,
     *   advertencias: list<string>,
     *   resumen_debe: float,
     *   resumen_haber: float,
     *   titulo: string
     * }
     */
    public static function generarPreview(RendicionEstacionamientoCaja $rendicion, array $config): array
    {
        self::assertRendicionCerrable($rendicion);

        return self::generarPreviewGrupo(new Collection([$rendicion]), $config);
    }

    /**
     * Preview consolidado: un asiento por fecha (jornada) + punto de venta.
     *
     * @param  Collection<int, RendicionEstacionamientoCaja>  $rendiciones
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
            if (CierreRendicionEstacionamientoGrupoSupport::fechaDiaDesdeRendicion($rendicion)
                !== CierreRendicionEstacionamientoGrupoSupport::fechaDiaDesdeRendicion($primera)) {
                throw new InvalidArgumentException('El grupo mezcla fechas distintas.');
            }
        }

        /** @var Collection<int, VentaEstacionamientoEmision> $emisionesConsolidadas */
        $emisionesConsolidadas = new Collection([]);
        $difCajaTotal = 0.;
        $advertencias = [];

        foreach ($rendiciones as $rendicion) {
            $emisionesRendicion = self::emisionesParaRendicion($rendicion);
            $datosRendicion = self::datosDesdeEmisiones($emisionesRendicion, $empresaId);
            $difCajaTotal = round($difCajaTotal + self::calcularDiferenciaCajaPreview($rendicion, $datosRendicion), 2);
            $advertencias = array_merge($advertencias, $datosRendicion['advertencias'] ?? []);
            foreach ($emisionesRendicion as $emision) {
                $emisionesConsolidadas->push($emision);
            }
        }

        $emisionesConsolidadas = $emisionesConsolidadas
            ->unique(fn (VentaEstacionamientoEmision $e) => (int) ($e->venta_id ?? 0))
            ->values();

        $datos = self::datosDesdeEmisiones($emisionesConsolidadas, $empresaId);
        $advertencias = array_merge($advertencias, $datos['advertencias'] ?? []);

        $cuentaVentas = (int) ($config['cuenta_ventas_id'] ?? 0);
        $cuentaIvaDebito = (int) ($config['cuenta_iva_debito_id'] ?? 0);
        $cuentaIvaCredito = (int) ($config['cuenta_iva_credito_id'] ?? 0);
        $cuentaDifCaja = (int) ($config['cuenta_diferencia_caja_id'] ?? 0);

        $lineas = [];

        foreach ($datos['debe_por_cuentacaja'] as $row) {
            $monto = round((float) ($row['monto'] ?? 0), 2);
            if (abs($monto) <= 0.0001) {
                continue;
            }
            if ($monto > 0) {
                $lineas[] = self::lineaDebe(
                    (string) ($row['concepto'] ?? 'Medio de cobro'),
                    (int) $row['cuentacaja_id'],
                    $monto,
                );
            } else {
                $lineas[] = self::lineaHaber(
                    (string) ($row['concepto'] ?? 'Medio de cobro').' (NC)',
                    (int) $row['cuentacaja_id'],
                    abs($monto),
                );
            }
        }

        $ventasPos = round((float) ($datos['ventas_positivas'] ?? 0), 2);
        $ventasNc = round((float) ($datos['ventas_nc'] ?? 0), 2);
        $ivaPos = round((float) ($datos['iva_positivo'] ?? 0), 2);
        $ivaNc = round((float) ($datos['iva_nc'] ?? 0), 2);

        if ($ventasPos > 0.0001) {
            $lineas[] = self::lineaHaber('Ventas estacionamiento', $cuentaVentas, $ventasPos);
        }
        if ($ivaPos > 0.0001) {
            $lineas[] = self::lineaHaber('IVA débito fiscal', $cuentaIvaDebito, $ivaPos);
        }
        if ($ventasNc > 0.0001) {
            $lineas[] = self::lineaDebe('Ventas estacionamiento (NC)', $cuentaVentas, $ventasNc);
        }
        if ($ivaNc > 0.0001) {
            $lineas[] = self::lineaDebe('IVA crédito fiscal (NC)', $cuentaIvaCredito, $ivaNc);
        }

        if (abs($difCajaTotal) > 0.0001 && $cuentaDifCaja > 0) {
            if ($difCajaTotal > 0) {
                $lineas[] = self::lineaDebe('Diferencia de caja', $cuentaDifCaja, $difCajaTotal);
            } else {
                $lineas[] = self::lineaHaber('Diferencia de caja', $cuentaDifCaja, abs($difCajaTotal));
            }
        }

        // Cuadre automático: cualquier remanente entre cobros (debe) y facturado + IVA (haber)
        // —p. ej. comprobantes recuperados de ARCA por rollback/deadlock sin cobranza, o redondeos—
        // se imputa a Diferencia de caja para que el asiento cierre siempre en cero.
        if ($cuentaDifCaja > 0) {
            $debeParcial = 0.;
            $haberParcial = 0.;
            foreach ($lineas as $ln) {
                $debeParcial += (float) ($ln['debe'] ?? 0);
                $haberParcial += (float) ($ln['haber'] ?? 0);
            }
            $residualCuadre = round($debeParcial - $haberParcial, 2);
            if (abs($residualCuadre) > 0.0001) {
                if ($residualCuadre < 0) {
                    $lineas[] = self::lineaDebe('Diferencia de caja — ajuste de cuadre', $cuentaDifCaja, abs($residualCuadre));
                } else {
                    $lineas[] = self::lineaHaber('Diferencia de caja — ajuste de cuadre', $cuentaDifCaja, abs($residualCuadre));
                }
            }
        }

        $debe = 0.;
        $haber = 0.;
        foreach ($lineas as $ln) {
            $debe += (float) ($ln['debe'] ?? 0);
            $haber += (float) ($ln['haber'] ?? 0);
        }

        $pv = $primera->puntoventaCae?->codigo ?? $primera->puntoventaCaea?->codigo ?? '';
        $fechaDia = CierreRendicionEstacionamientoGrupoSupport::fechaDiaDesdeRendicion($primera);
        $fechaFmt = Carbon::parse($fechaDia)->format('d/m/Y');
        $ids = $rendiciones->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $titulo = 'Cierre estacionamiento — '.$fechaFmt
            .($pv !== '' ? ' — PV '.$pv : '')
            .' — '.$rendiciones->count().' rendición(es)';

        $advertencias = array_values(array_unique($advertencias));
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
            $centrocostoIds[] = CierreRendicionEstacionamientoCentrocostoSupport::resolverCentrocostoIdParaCuentacontable(
                $cuentacontableId,
                $config,
            );
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

    /**
     * Fecha contable del asiento: día de la rendición (registro en caja), con fallback a jornada del turno.
     */
    public static function fechaAsientoDesdeRendicion(RendicionEstacionamientoCaja $rendicion): string
    {
        $rendicion->loadMissing(['turnoOperativo.jornada', 'jornada']);

        $fechaRendicion = $rendicion->fecharendicion?->format('Y-m-d');
        if ($fechaRendicion !== null && $fechaRendicion !== '') {
            return $fechaRendicion;
        }

        $fechaJornada = $rendicion->turnoOperativo?->jornada?->fecha_jornada?->format('Y-m-d')
            ?? $rendicion->jornada?->fecha_jornada?->format('Y-m-d');
        if ($fechaJornada !== null && $fechaJornada !== '') {
            return $fechaJornada;
        }

        return now()->format('Y-m-d');
    }

    public static function assertRendicionCerrable(RendicionEstacionamientoCaja $rendicion): void
    {
        if (! $rendicion->esRendicionTurno()) {
            throw new InvalidArgumentException(
                'Solo las rendiciones de turno (por punto de venta) admiten cierre contable.',
            );
        }
        if ($rendicion->tieneCierreContable()) {
            throw new InvalidArgumentException(
                'La rendición #'.$rendicion->id.' ya tiene cierre contable registrado.',
            );
        }
        if ((int) ($rendicion->turno_operativo_estacionamiento_id ?? 0) <= 0) {
            throw new InvalidArgumentException('La rendición no está vinculada a un turno operativo.');
        }
    }

    /**
     * Importe de la línea «Diferencia de caja» en el preview.
     *
     * Las invitaciones $0,01 sin cobranza se imputan por comprobante (debe_diferencia_caja).
     * totalredondeoinvitacion en la rendición repite ese mismo concepto para cuadrar caja;
     * solo se suma el excedente manual sobre lo detectado en emisiones.
     *
     * @param  array{debe_diferencia_caja?: float}  $datosDesdeEmisiones
     */
    public static function calcularDiferenciaCajaPreview(
        RendicionEstacionamientoCaja $rendicion,
        array $datosDesdeEmisiones,
    ): float {
        $debeDesdeEmisiones = round((float) ($datosDesdeEmisiones['debe_diferencia_caja'] ?? 0), 2);
        $redondeoInvitacion = round((float) ($rendicion->totalredondeoinvitacion ?? 0), 2);
        $extraRedondeoInvitacion = round(max(0.0, $redondeoInvitacion - $debeDesdeEmisiones), 2);

        return round(
            (float) ($rendicion->sobrantefaltante ?? 0)
            + (float) ($rendicion->totalredondeo ?? 0)
            + $debeDesdeEmisiones
            + $extraRedondeoInvitacion,
            2,
        );
    }

    /**
     * @return Collection<int, VentaEstacionamientoEmision>
     */
    public static function emisionesParaRendicion(RendicionEstacionamientoCaja $rendicion): Collection
    {
        $rendicion->loadMissing(['turnoOperativo.jornada']);
        $turno = $rendicion->turnoOperativo;
        if ($turno === null) {
            return new Collection([]);
        }

        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
            ?? $rendicion->fecharendicion?->format('Y-m-d')
            ?? now()->format('Y-m-d');

        $desde = $turno->habilitacion_en !== null ? Carbon::parse($turno->habilitacion_en) : null;
        $hasta = $turno->cierre_en !== null ? Carbon::parse($turno->cierre_en) : null;

        return self::queryEmisionesEnAlcance(
            (string) $turno->identificador_pc,
            (int) $rendicion->empresa_id,
            $fechaJornada,
            $desde,
            $hasta,
        )
            ->with([
                'venta.cobranzasDirectas',
                'venta.caja_movimientos.cobranzas',
            ])
            ->get();
    }

    /**
     * @param  Collection<int, VentaEstacionamientoEmision>  $emisiones
     * @return array{
     *   ventas_positivas: float,
     *   ventas_nc: float,
     *   iva_positivo: float,
     *   iva_nc: float,
     *   debe_por_cuentacaja: list<array{concepto:string,cuentacaja_id:int,monto:float}>,
     *   debe_diferencia_caja: float,
     *   advertencias: list<string>
     * }
     */
    private static function datosDesdeEmisiones(Collection $emisiones, int $empresaId): array
    {
        /** @var array<int, array{concepto:string,cuentacaja_id:int,monto:float}> */
        $debePorCuentacaja = [];
        $ventasPos = 0.;
        $ventasNc = 0.;
        $ivaPos = 0.;
        $ivaNc = 0.;
        $debeDifCaja = 0.;
        $advertencias = [];

        foreach ($emisiones as $emision) {
            $venta = $emision->venta;
            if ($venta === null) {
                continue;
            }

            $monto = round((float) ($venta->total ?? 0), 2);
            if (abs($monto) <= 0.0001) {
                continue;
            }

            $esNc = ($emision->venta_factura_origen_id ?? null) !== null;
            $desglose = self::desglosarIva($monto);

            if ($esNc) {
                $ventasNc = round($ventasNc + abs($desglose['base']), 2);
                $ivaNc = round($ivaNc + abs($desglose['iva']), 2);
            } else {
                $ventasPos = round($ventasPos + $desglose['base'], 2);
                $ivaPos = round($ivaPos + $desglose['iva'], 2);
            }

            $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
            $medios = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
            $sumCobranza = 0.;

            foreach ($medios as $lineas) {
                foreach ($lineas as $medio) {
                    $ccId = (int) ($medio->cuentacaja_id ?? 0);
                    $montoMedio = round((float) ($medio->monto ?? 0), 2);
                    if ($ccId <= 0 || abs($montoMedio) <= 0.0001) {
                        continue;
                    }
                    $sumCobranza = round($sumCobranza + $montoMedio, 2);
                    $label = self::etiquetaCuentacaja($medio);
                    if (! isset($debePorCuentacaja[$ccId])) {
                        $debePorCuentacaja[$ccId] = [
                            'concepto' => 'Medio de cobro — '.$label,
                            'cuentacaja_id' => $ccId,
                            'monto' => 0.,
                        ];
                    }
                    $debePorCuentacaja[$ccId]['monto'] = round(
                        $debePorCuentacaja[$ccId]['monto'] + $montoMedio,
                        2,
                    );
                }
            }

            if (self::esInvitacionSinCobranza($monto, $sumCobranza)) {
                $debeDifCaja = round($debeDifCaja + $monto, 2);

                continue;
            }

            if (abs($sumCobranza) <= 0.0001 && abs($monto) > 0.0001) {
                $advertencias[] = 'Venta #'.(int) $venta->id.': sin cobranza registrada.';
            }
        }

        return [
            'ventas_positivas' => $ventasPos,
            'ventas_nc' => $ventasNc,
            'iva_positivo' => $ivaPos,
            'iva_nc' => $ivaNc,
            'debe_por_cuentacaja' => array_values($debePorCuentacaja),
            'debe_diferencia_caja' => round($debeDifCaja, 2),
            'advertencias' => $advertencias,
        ];
    }

    /**
     * @return array{base: float, iva: float}
     */
    private static function desglosarIva(float $monto): array
    {
        $sign = $monto >= 0 ? 1 : -1;
        $abs = abs($monto);
        $base = round($abs / (1 + self::TASA_IVA / 100), 2);
        $iva = round($abs - $base, 2);

        return [
            'base' => round($sign * $base, 2),
            'iva' => round($sign * $iva, 2),
        ];
    }

    private static function esInvitacionSinCobranza(float $montoVenta, float $montoCobrado): bool
    {
        return abs($montoVenta - 0.01) < 0.001 && $montoCobrado < 0.001;
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
            'cuenta_iva_debito',
            'cuenta_iva_credito',
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

    private static function etiquetaCuentacaja(object $medio): string
    {
        $codigo = trim((string) ($medio->codigo ?? ''));
        $nombre = trim((string) ($medio->nombre ?? ''));

        return $codigo !== '' && $nombre !== ''
            ? $codigo.' — '.$nombre
            : ($codigo !== '' ? $codigo : ($nombre !== '' ? $nombre : '#'.(int) ($medio->cuentacaja_id ?? 0)));
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

    private static function queryEmisionesEnAlcance(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        ?Carbon $desdeHabilitacion,
        ?Carbon $hastaInclusive,
    ): Builder {
        return VentaEstacionamientoEmision::query()
            ->where('identificador_pc', $identificadorPc)
            ->whereHas('venta', function ($v) use ($empresaId, $fechaJornada, $desdeHabilitacion, $hastaInclusive) {
                $v->where(function ($fecha) use ($fechaJornada) {
                    $fecha->whereDate('fechajornada', $fechaJornada)
                        ->orWhere(function ($legacy) use ($fechaJornada) {
                            $legacy->whereNull('fechajornada')
                                ->whereDate('fecha', $fechaJornada);
                        });
                })->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
                if ($desdeHabilitacion !== null) {
                    $v->where('created_at', '>=', $desdeHabilitacion);
                }
                if ($hastaInclusive !== null) {
                    $v->where('created_at', '<=', $hastaInclusive);
                }
            })
            ->orderBy('venta_id');
    }
}
