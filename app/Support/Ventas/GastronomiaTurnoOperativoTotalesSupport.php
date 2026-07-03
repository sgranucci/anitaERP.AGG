<?php

namespace App\Support\Ventas;

use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\GastronomiaFacturacionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Totales de comprobantes gastronomía por PC: ventas y cobranzas leídas de cada factura emitida.
 */
final class GastronomiaTurnoOperativoTotalesSupport
{
    private const TOLERANCIA_CONCILIACION = 0.02;

    /**
     * Las notas de crédito gastronomía se discriminan como un bloque aparte de los medios
     * de pago tradicionales: `por_medio_pago` contiene únicamente las cobranzas de
     * facturas (importes brutos positivos) y la devolución por NC se acumula en
     * `total_notas_credito` (global) y `notas_credito.{total,cantidad}` (por mozo).
     *
     * Convenciones de totales:
     * - `total_facturas` (bruto): suma de venta.total de facturas (sin NC).
     * - `total_notas_credito` (negativo): suma de venta.total de NC.
     * - `total_ventas` (final): bruto − NC, es lo que debería cuadrar con `total_cobrado`
     *   después de restar invitaciones.
     *
     * @return array{
     *   total_general: float,
     *   total_ventas: float,
     *   total_facturas: float,
     *   total_cobrado: float,
     *   total_invitaciones: float,
     *   cantidad_invitaciones: int,
     *   total_ventas_cobrables: float,
     *   diferencia_cobranza: float,
     *   conciliacion_ok: bool,
     *   cantidad_comprobantes: int,
     *   cantidad_facturas: int,
     *   cantidad_notas_credito: int,
     *   total_notas_credito: float,
     *   redondeo_invitaciones_sugerido: float,
     *   por_mozo: list<array{
     *     mozo_id:?int,
     *     mozo_codigo:?string,
     *     mozo_nombre:string,
     *     total:float,
     *     total_facturas:float,
     *     total_cobrado:float,
     *     cantidad:int,
     *     por_medio_pago: list<array{cuentacaja_id:int, codigo:string, nombre:string, total:float}>,
     *     notas_credito: array{total:float, cantidad:int}
     *   }>,
     *   por_medio_pago: list<array{cuentacaja_id:int, codigo:string, nombre:string, total:float}>
     * }
     */
    /**
     * Comprobantes de la jornada en esta PC que no caen dentro de la ventana de ningún
     * turno operativo de la jornada: ni de un turno ya cerrado ([habilitacion_en, cierre_en])
     * ni del turno actualmente habilitado ([habilitacion_en, ∞) hasta que se cierre).
     *
     * El turno habilitado se considera cobertura abierta hacia adelante: si una factura
     * se emite después de su habilitacion_en, queda imputada a ese turno (encuadra al
     * cerrarlo). Esto evita marcar como huérfanas las facturas de turnos noche que
     * cruzan la medianoche y siguen abiertos.
     *
     * @return array{
     *   cantidad:int,
     *   ejemplos:list<array{venta_id:int, codigo:string, hora:string}>
     * }
     */
    /**
     * @return list<array{
     *   venta_id:int,
     *   codigo:string,
     *   hora:string,
     *   emitido_en:string,
     *   total:float,
     *   cliente:string
     * }>
     */
    public static function listarFacturasHuerfanasDelDia(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        int $jornadaGastronomiaId,
    ): array {
        $ventanas = TurnoOperativoGastronomia::query()
            ->where('identificador_pc', $identificadorPc)
            ->where('jornada_gastronomia_id', $jornadaGastronomiaId)
            ->whereIn('estado', [
                TurnoOperativoGastronomia::ESTADO_CERRADO,
                TurnoOperativoGastronomia::ESTADO_HABILITADO,
            ])
            ->whereNotNull('habilitacion_en')
            ->get(['habilitacion_en', 'cierre_en', 'estado']);

        $emisiones = self::emisionesEnAlcance($identificadorPc, $empresaId, $fechaJornada, null);
        $huerfanas = [];

        foreach ($emisiones as $em) {
            $venta = $em->venta;
            if (! $venta?->created_at) {
                continue;
            }

            $ts = $venta->created_at;
            $cubierta = false;
            foreach ($ventanas as $ventana) {
                if ($ts < $ventana->habilitacion_en) {
                    continue;
                }
                if ($ventana->cierre_en === null) {
                    $cubierta = true;
                    break;
                }
                if ($ts <= $ventana->cierre_en) {
                    $cubierta = true;
                    break;
                }
            }

            if (! $cubierta) {
                $huerfanas[] = [
                    'venta_id' => (int) $venta->id,
                    'codigo' => (string) ($venta->codigo ?? ''),
                    'hora' => $ts->format('H:i'),
                    'emitido_en' => $ts->format('Y-m-d H:i:s'),
                    'total' => round((float) $venta->total, 2),
                    'cliente' => (string) ($venta->nombre ?? ''),
                ];
            }
        }

        return $huerfanas;
    }

    public static function facturasHuerfanasDelDia(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        int $jornadaGastronomiaId,
    ): array {
        $huerfanas = self::listarFacturasHuerfanasDelDia(
            $identificadorPc,
            $empresaId,
            $fechaJornada,
            $jornadaGastronomiaId,
        );

        return [
            'cantidad' => count($huerfanas),
            'ejemplos' => array_slice($huerfanas, 0, 8),
        ];
    }

    public static function calcular(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        ?Carbon $desdeHabilitacion = null,
        ?Carbon $hastaInclusive = null,
    ): array {
        $emisiones = self::emisionesEnAlcance($identificadorPc, $empresaId, $fechaJornada, $desdeHabilitacion, $hastaInclusive);
        $importeMinimo = GastronomiaFacturacionService::IMPORTE_MINIMO_FACTURA;

        $porMozo = [];
        $porMedioGlobal = [];
        $totalVentas = 0.0;
        $totalFacturas = 0.0;
        $totalInvitaciones = 0.0;
        $cantidadInvitaciones = 0;
        $totalCobrado = 0.0;
        $totalNotasCredito = 0.0;
        $cantidadNotasCredito = 0;
        $cantidadFacturas = 0;

        $facturaVentaIdsEnPc = [];

        foreach ($emisiones as $em) {
            $venta = $em->venta;
            if (! $venta) {
                continue;
            }

            $esNotaCredito = ($em->venta_factura_origen_id ?? null) !== null;
            if (! $esNotaCredito) {
                $facturaVentaIdsEnPc[] = (int) $venta->id;
            }

            self::acumularEmisionEnTotales(
                $em,
                $importeMinimo,
                $porMozo,
                $porMedioGlobal,
                $totalVentas,
                $totalFacturas,
                $totalInvitaciones,
                $cantidadInvitaciones,
                $totalCobrado,
                $totalNotasCredito,
                $cantidadNotasCredito,
                $cantidadFacturas,
            );
        }

        if ($facturaVentaIdsEnPc !== []) {
            $ncExternas = self::emisionesNotasCreditoOtraPc(
                $identificadorPc,
                $empresaId,
                $fechaJornada,
                $desdeHabilitacion,
                $hastaInclusive,
                $facturaVentaIdsEnPc,
            );
            foreach ($ncExternas as $em) {
                self::acumularEmisionEnTotales(
                    $em,
                    $importeMinimo,
                    $porMozo,
                    $porMedioGlobal,
                    $totalVentas,
                    $totalFacturas,
                    $totalInvitaciones,
                    $cantidadInvitaciones,
                    $totalCobrado,
                    $totalNotasCredito,
                    $cantidadNotasCredito,
                    $cantidadFacturas,
                );
            }
        }

        $totalVentas = round($totalVentas, 2);
        $totalFacturas = round($totalFacturas, 2);
        $totalInvitaciones = round($totalInvitaciones, 2);
        $totalCobrado = round($totalCobrado, 2);
        $totalNotasCredito = round($totalNotasCredito, 2);
        $totalVentasCobrables = round($totalVentas - $totalInvitaciones, 2);
        $diferencia = round($totalCobrado - $totalVentasCobrables, 2);
        $redondeoInvitacionesSugerido = self::redondeoInvitacionesSugerido($totalInvitaciones, $diferencia);

        $porMedioGlobal = self::normalizarMediosPago($porMedioGlobal);

        foreach ($porMozo as &$row) {
            $row['total'] = round($row['total'], 2);
            $row['total_facturas'] = round($row['total_facturas'], 2);
            $row['total_cobrado'] = round($row['total_cobrado'], 2);
            $row['por_medio_pago'] = self::normalizarMediosPago($row['por_medio_pago']);
            $row['notas_credito']['total'] = round($row['notas_credito']['total'], 2);
            if (isset($row['invitaciones'])) {
                $row['invitaciones']['total'] = round($row['invitaciones']['total'], 2);
            }
        }
        unset($row);

        usort($porMozo, fn ($a, $b) => strcmp($a['mozo_nombre'], $b['mozo_nombre']));

        return [
            'total_general' => $totalVentas,
            'total_ventas' => $totalVentas,
            'total_facturas' => $totalFacturas,
            'total_cobrado' => $totalCobrado,
            'total_invitaciones' => $totalInvitaciones,
            'cantidad_invitaciones' => $cantidadInvitaciones,
            'total_ventas_cobrables' => $totalVentasCobrables,
            'diferencia_cobranza' => $diferencia,
            'conciliacion_ok' => abs($diferencia) < self::TOLERANCIA_CONCILIACION,
            'cantidad_comprobantes' => $emisiones->count(),
            'cantidad_facturas' => $cantidadFacturas,
            'cantidad_notas_credito' => $cantidadNotasCredito,
            'total_notas_credito' => $totalNotasCredito,
            'redondeo_invitaciones_sugerido' => $redondeoInvitacionesSugerido,
            'por_mozo' => array_values($porMozo),
            'por_medio_pago' => $porMedioGlobal,
        ];
    }

    /**
     * Facturación bruta (solo comprobantes de venta, sin NC) para bridge Anita rendg_total_x / rendg_total_z.
     * Las NC van aparte en rendg_tot_nc; Anita resta NC al consolidar ventas gastronomía.
     */
    public static function totalFacturasSinNotasCredito(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        ?Carbon $desdeHabilitacion = null,
        ?Carbon $hastaInclusive = null,
    ): float {
        $totales = self::calcular($identificadorPc, $empresaId, $fechaJornada, $desdeHabilitacion, $hastaInclusive);

        return round((float) ($totales['total_facturas'] ?? 0), 2);
    }

    /**
     * Notas de crédito del día por PC (valor positivo para rendg_tot_nc).
     */
    public static function totalNotasCreditoPorPc(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
    ): float {
        $totales = self::calcular($identificadorPc, $empresaId, $fechaJornada, null, null);

        return round(abs((float) ($totales['total_notas_credito'] ?? 0)), 2);
    }

    /**
     * Totales del día contable por punto de venta CAE (fecha de jornada, sin ventana de turno).
     *
     * @return array<string, mixed>
     */
    public static function totalesDiaPorPuntoventa(
        int $puntoventaId,
        int $empresaId,
        string $fechaJornada,
    ): array {
        $emisiones = self::emisionesDiaPorPuntoventa($puntoventaId, $empresaId, $fechaJornada);

        return self::calcularDesdeColeccionEmisiones($emisiones);
    }

    /**
     * Facturación bruta del día por PV CAE (sin NC), para rendg_total_z.
     */
    public static function totalFacturasSinNotasCreditoPorPuntoventa(
        int $puntoventaId,
        int $empresaId,
        string $fechaJornada,
    ): float {
        $totales = self::totalesDiaPorPuntoventa($puntoventaId, $empresaId, $fechaJornada);

        return round((float) ($totales['total_facturas'] ?? 0), 2);
    }

    /**
     * Notas de crédito del día por PV (valor positivo para rendg_tot_nc).
     */
    public static function totalNotasCreditoPorPuntoventa(
        int $puntoventaId,
        int $empresaId,
        string $fechaJornada,
    ): float {
        $totales = self::totalesDiaPorPuntoventa($puntoventaId, $empresaId, $fechaJornada);

        return round(abs((float) ($totales['total_notas_credito'] ?? 0)), 2);
    }

    /**
     * Totales de toda la empresa en la ventana de una jornada cerrada (presentación a caja).
     *
     * @return array<string, mixed>
     */
    public static function calcularPorJornada(JornadaGastronomia $jornada): array
    {
        if ($jornada->apertura_en === null || $jornada->cierre_en === null) {
            throw new \InvalidArgumentException('La jornada no tiene fechas de apertura o cierre.');
        }

        $empresaId = (int) $jornada->empresa_id;
        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d')
            ?? $jornada->cierre_en->format('Y-m-d');

        $emisiones = self::queryEmisionesJornadaEmpresa(
            $empresaId,
            $fechaJornada,
            Carbon::parse($jornada->apertura_en),
            Carbon::parse($jornada->cierre_en),
        )->get();

        return self::calcularDesdeColeccionEmisiones($emisiones);
    }

    /**
     * @return Builder<VentaGastronomiaEmision>
     */
    public static function queryEmisionesJornadaEmpresa(
        int $empresaId,
        string $fechaJornada,
        Carbon $desdeInclusive,
        Carbon $hastaInclusive,
    ): Builder {
        return VentaGastronomiaEmision::query()
            ->whereHas('venta', function ($v) use ($empresaId, $fechaJornada, $desdeInclusive, $hastaInclusive) {
                $v->where(function ($fecha) use ($fechaJornada) {
                    $fecha->whereDate('fechajornada', $fechaJornada)
                        ->orWhere(function ($legacy) use ($fechaJornada) {
                            $legacy->whereNull('fechajornada')
                                ->whereDate('fecha', $fechaJornada);
                        });
                })
                    ->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId))
                    ->where('created_at', '>=', $desdeInclusive)
                    ->where('created_at', '<=', $hastaInclusive);
            })
            ->with([
                'venta.cobranzasDirectas',
                'venta.caja_movimientos.cobranzas',
                'cuenta.mozo',
            ]);
    }

    /**
     * @param  Collection<int, VentaGastronomiaEmision>  $emisiones
     * @return array<string, mixed>
     */
    private static function calcularDesdeColeccionEmisiones(Collection $emisiones): array
    {
        $importeMinimo = GastronomiaFacturacionService::IMPORTE_MINIMO_FACTURA;

        $porMozo = [];
        $porMedioGlobal = [];
        $totalVentas = 0.0;
        $totalFacturas = 0.0;
        $totalInvitaciones = 0.0;
        $cantidadInvitaciones = 0;
        $totalCobrado = 0.0;
        $totalNotasCredito = 0.0;
        $cantidadNotasCredito = 0;
        $cantidadFacturas = 0;

        foreach ($emisiones as $em) {
            if (! $em->venta) {
                continue;
            }
            self::acumularEmisionEnTotales(
                $em,
                $importeMinimo,
                $porMozo,
                $porMedioGlobal,
                $totalVentas,
                $totalFacturas,
                $totalInvitaciones,
                $cantidadInvitaciones,
                $totalCobrado,
                $totalNotasCredito,
                $cantidadNotasCredito,
                $cantidadFacturas,
            );
        }

        $totalVentas = round($totalVentas, 2);
        $totalFacturas = round($totalFacturas, 2);
        $totalCobrado = round($totalCobrado, 2);
        $totalInvitaciones = round($totalInvitaciones, 2);
        $totalNotasCredito = round($totalNotasCredito, 2);
        $porMedioGlobal = self::normalizarMediosPago($porMedioGlobal);
        $totalVentasCobrables = round($totalVentas - $totalInvitaciones, 2);
        $diferencia = round($totalCobrado - $totalVentasCobrables, 2);
        $redondeoInvitacionesSugerido = self::redondeoInvitacionesSugerido($totalInvitaciones, $diferencia);

        foreach ($porMozo as &$row) {
            $row['total'] = round($row['total'], 2);
            $row['total_facturas'] = round($row['total_facturas'], 2);
            $row['total_cobrado'] = round($row['total_cobrado'], 2);
            $row['por_medio_pago'] = self::normalizarMediosPago($row['por_medio_pago']);
            $row['notas_credito']['total'] = round($row['notas_credito']['total'], 2);
            if (isset($row['invitaciones'])) {
                $row['invitaciones']['total'] = round($row['invitaciones']['total'], 2);
            }
        }
        unset($row);

        usort($porMozo, fn ($a, $b) => strcmp($a['mozo_nombre'], $b['mozo_nombre']));

        return [
            'total_general' => $totalVentas,
            'total_ventas' => $totalVentas,
            'total_facturas' => $totalFacturas,
            'total_cobrado' => $totalCobrado,
            'total_invitaciones' => $totalInvitaciones,
            'cantidad_invitaciones' => $cantidadInvitaciones,
            'total_ventas_cobrables' => $totalVentasCobrables,
            'diferencia_cobranza' => $diferencia,
            'conciliacion_ok' => abs($diferencia) < self::TOLERANCIA_CONCILIACION,
            'cantidad_comprobantes' => $emisiones->count(),
            'cantidad_facturas' => $cantidadFacturas,
            'cantidad_notas_credito' => $cantidadNotasCredito,
            'total_notas_credito' => $totalNotasCredito,
            'redondeo_invitaciones_sugerido' => $redondeoInvitacionesSugerido,
            'por_mozo' => array_values($porMozo),
            'por_medio_pago' => $porMedioGlobal,
        ];
    }

    /**
     * Invitaciones ($0,01) más el residual de conciliación dentro de tolerancia (p. ej. NC emitida en otra PC).
     */
    public static function redondeoInvitacionesSugerido(float $totalInvitaciones, float $diferenciaCobranza): float
    {
        $ajuste = 0.0;
        if (abs($diferenciaCobranza) > 0.001 && abs($diferenciaCobranza) <= self::TOLERANCIA_CONCILIACION) {
            $ajuste = $diferenciaCobranza;
        }

        return round($totalInvitaciones + $ajuste, 2);
    }

    /**
     * Valida que redondeo / sobrante-faltante absorban la diferencia de conciliación del turno.
     */
    public static function cierreCuadraConAjustesManuales(
        array $totalesTurno,
        float $redondeoInvitaciones,
        float $redondeoTurno,
        float $sobranteFaltante,
    ): bool {
        if (! empty($totalesTurno['conciliacion_ok'])) {
            return true;
        }

        $diferencia = round((float) ($totalesTurno['diferencia_cobranza'] ?? 0), 2);
        $baseInvitaciones = round((float) ($totalesTurno['total_invitaciones'] ?? 0), 2);
        $extraInvitaciones = round($redondeoInvitaciones - $baseInvitaciones, 2);
        $residual = round(
            $diferencia - $extraInvitaciones - round($redondeoTurno, 2) - round($sobranteFaltante, 2),
            2,
        );

        return abs($residual) < self::TOLERANCIA_CONCILIACION;
    }

    /**
     * Redondeo invitaciones sugerido y, si aún hay residual, lo imputa en sobrante/faltante.
     * Usado en cierre remoto (PC inoperativa): permite cerrar y corregir después vía anulación de cierre.
     *
     * @return array{
     *   redondeo_invitaciones:float,
     *   redondeo_turno:float,
     *   sobrante_faltante:float,
     *   sobrante_faltante_auto:bool
     * }
     */
    public static function resolverAjustesCierreConSobranteFaltanteResidual(
        array $totalesTurno,
        ?float $redondeoInvitaciones = null,
        ?float $redondeoTurno = null,
    ): array {
        $redondeoInvitaciones = $redondeoInvitaciones !== null
            ? round($redondeoInvitaciones, 2)
            : round((float) ($totalesTurno['redondeo_invitaciones_sugerido'] ?? 0), 2);
        $redondeoTurno = round($redondeoTurno ?? 0.0, 2);
        $sobranteFaltante = 0.0;
        $sobranteFaltanteAuto = false;

        if (! self::cierreCuadraConAjustesManuales(
            $totalesTurno,
            $redondeoInvitaciones,
            $redondeoTurno,
            $sobranteFaltante,
        )) {
            $diferencia = round((float) ($totalesTurno['diferencia_cobranza'] ?? 0), 2);
            $baseInvitaciones = round((float) ($totalesTurno['total_invitaciones'] ?? 0), 2);
            $extraInvitaciones = round($redondeoInvitaciones - $baseInvitaciones, 2);
            $sobranteFaltante = round($diferencia - $extraInvitaciones - $redondeoTurno, 2);
            $sobranteFaltanteAuto = abs($sobranteFaltante) >= 0.001;
        }

        return [
            'redondeo_invitaciones' => $redondeoInvitaciones,
            'redondeo_turno' => $redondeoTurno,
            'sobrante_faltante' => $sobranteFaltante,
            'sobrante_faltante_auto' => $sobranteFaltanteAuto,
        ];
    }

    /**
     * NC emitidas en otra terminal sobre facturas de esta PC (misma jornada / ventana de turno).
     *
     * @param  list<int>  $ventaIdsFacturasEnPc
     * @return Collection<int, VentaGastronomiaEmision>
     */
    private static function emisionesNotasCreditoOtraPc(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        ?Carbon $desdeHabilitacion,
        ?Carbon $hastaInclusive,
        array $ventaIdsFacturasEnPc,
    ): Collection {
        $ventaIdsFacturasEnPc = array_values(array_unique(array_filter($ventaIdsFacturasEnPc)));
        if ($ventaIdsFacturasEnPc === []) {
            return collect();
        }

        return VentaGastronomiaEmision::query()
            ->where('identificador_pc', '!=', $identificadorPc)
            ->whereIn('venta_factura_origen_id', $ventaIdsFacturasEnPc)
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
            ->with([
                'venta.cobranzasDirectas',
                'venta.caja_movimientos.cobranzas',
                'cuenta.mozo',
            ])
            ->get();
    }

    /**
     * @param  array<string, array<string, mixed>>  $porMozo
     * @param  array<int, array{cuentacaja_id:int, codigo:string, nombre:string, total:float}>  $porMedioGlobal
     */
    private static function acumularEmisionEnTotales(
        VentaGastronomiaEmision $em,
        float $importeMinimo,
        array &$porMozo,
        array &$porMedioGlobal,
        float &$totalVentas,
        float &$totalFacturas,
        float &$totalInvitaciones,
        int &$cantidadInvitaciones,
        float &$totalCobrado,
        float &$totalNotasCredito,
        int &$cantidadNotasCredito,
        int &$cantidadFacturas,
    ): void {
        $venta = $em->venta;
        if (! $venta) {
            return;
        }

        $montoVenta = round((float) $venta->total, 2);
        $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
        $medios = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
        $totalCobradoVenta = self::sumarMontosMedios($medios);
        $esNotaCredito = ($em->venta_factura_origen_id ?? null) !== null;

        $totalVentas += $montoVenta;
        $totalCobrado += $totalCobradoVenta;

        if ($esNotaCredito) {
            $totalNotasCredito += $montoVenta;
            $cantidadNotasCredito++;
        } else {
            $totalFacturas += $montoVenta;
            $cantidadFacturas++;
            if (self::esInvitacionSinCobranza($montoVenta, $totalCobradoVenta, $importeMinimo)) {
                $totalInvitaciones += $montoVenta;
                $cantidadInvitaciones++;
            }
        }

        $mozoId = $em->cuenta?->mozo_gastronomia_id;
        $key = $mozoId !== null ? (string) $mozoId : '0';
        if (! isset($porMozo[$key])) {
            $mozo = $em->cuenta?->mozo;
            $porMozo[$key] = [
                'mozo_id' => $mozoId ? (int) $mozoId : null,
                'mozo_codigo' => $mozo?->codigo,
                'mozo_nombre' => $mozo?->nombre ?? 'Sin mozo',
                'total' => 0.0,
                'total_facturas' => 0.0,
                'total_cobrado' => 0.0,
                'cantidad' => 0,
                'por_medio_pago' => [],
                'notas_credito' => [
                    'total' => 0.0,
                    'cantidad' => 0,
                ],
                'invitaciones' => [
                    'total' => 0.0,
                    'cantidad' => 0,
                ],
            ];
        }
        $porMozo[$key]['total'] += $montoVenta;
        $porMozo[$key]['total_cobrado'] += $totalCobradoVenta;
        $porMozo[$key]['cantidad']++;

        if ($esNotaCredito) {
            $porMozo[$key]['notas_credito']['total'] += $montoVenta;
            $porMozo[$key]['notas_credito']['cantidad']++;
        } else {
            $porMozo[$key]['total_facturas'] += $montoVenta;
            if (self::esInvitacionSinCobranza($montoVenta, $totalCobradoVenta, $importeMinimo)) {
                if (! isset($porMozo[$key]['invitaciones'])) {
                    $porMozo[$key]['invitaciones'] = ['total' => 0.0, 'cantidad' => 0];
                }
                $porMozo[$key]['invitaciones']['total'] += $montoVenta;
                $porMozo[$key]['invitaciones']['cantidad']++;
            }
        }
        // NC incluye cobranza negativa en su medio; rendvalor debe reflejar neto por cuenta.
        self::acumularMediosPago($medios, $porMozo[$key]['por_medio_pago']);
        self::acumularMediosPago($medios, $porMedioGlobal);
    }

    /**
     * @return Collection<int, VentaGastronomiaEmision>
     */
    private static function emisionesDiaPorPuntoventa(
        int $puntoventaId,
        int $empresaId,
        string $fechaJornada,
    ): Collection {
        if ($puntoventaId <= 0) {
            return collect();
        }

        return VentaGastronomiaEmision::query()
            ->whereHas('venta', function ($v) use ($empresaId, $fechaJornada, $puntoventaId) {
                $v->where('puntoventa_id', $puntoventaId)
                    ->where(function ($fecha) use ($fechaJornada) {
                        $fecha->whereDate('fechajornada', $fechaJornada)
                            ->orWhere(function ($legacy) use ($fechaJornada) {
                                $legacy->whereNull('fechajornada')
                                    ->whereDate('fecha', $fechaJornada);
                            });
                    })
                    ->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
            })
            ->with([
                'venta.cobranzasDirectas',
                'venta.caja_movimientos.cobranzas',
                'cuenta.mozo',
            ])
            ->get();
    }

    /**
     * @return Collection<int, VentaGastronomiaEmision>
     */
    private static function emisionesEnAlcance(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        ?Carbon $desdeHabilitacion,
        ?Carbon $hastaInclusive = null,
    ): Collection {
        return VentaGastronomiaEmision::query()
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
            ->with([
                'venta.cobranzasDirectas',
                'venta.caja_movimientos.cobranzas',
                'cuenta.mozo',
            ])
            ->get();
    }

    private static function esInvitacionSinCobranza(float $montoVenta, float $montoCobrado, float $importeMinimo): bool
    {
        if (abs($montoVenta - $importeMinimo) >= 0.001) {
            return false;
        }

        return $montoCobrado < 0.001;
    }

    /**
     * @param  array<int, list<object{cuentacaja_id?:int, codigo?:string, nombre?:string, cuenta?:string, monto?:float}>>  $medios
     */
    private static function sumarMontosMedios(array $medios): float
    {
        $total = 0.0;
        foreach ($medios as $lineas) {
            foreach ($lineas as $linea) {
                $total += (float) ($linea->monto ?? 0);
            }
        }

        return round($total, 2);
    }

    /**
     * @param  array<int, list<object{cuentacaja_id?:int, codigo?:string, nombre?:string, cuenta?:string, monto?:float}>>  $medios
     * @param  array<int, array{cuentacaja_id:int, codigo:string, nombre:string, total:float}>  $acum
     */
    private static function acumularMediosPago(array $medios, array &$acum): void
    {
        foreach ($medios as $lineas) {
            foreach ($lineas as $linea) {
                $ccId = (int) ($linea->cuentacaja_id ?? 0);
                if ($ccId <= 0) {
                    continue;
                }
                if (! isset($acum[$ccId])) {
                    $acum[$ccId] = [
                        'cuentacaja_id' => $ccId,
                        'codigo' => (string) ($linea->codigo ?? ''),
                        'nombre' => trim((string) ($linea->nombre ?? $linea->cuenta ?? '')),
                        'total' => 0.0,
                    ];
                }
                $acum[$ccId]['total'] += (float) $linea->monto;
            }
        }
    }

    /**
     * @param  array<int, array{cuentacaja_id:int, codigo:string, nombre:string, total:float}>  $mapa
     * @return list<array{cuentacaja_id:int, codigo:string, nombre:string, total:float}>
     */
    private static function normalizarMediosPago(array $mapa): array
    {
        foreach ($mapa as &$medio) {
            $medio['total'] = round($medio['total'], 2);
        }
        unset($medio);
        $lista = array_values($mapa);
        usort($lista, fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));

        return $lista;
    }

    public const CONCILIACION_FILAS_POR_PAGINA = 40;

    /**
     * Meta (page=0) o página de filas. No carga todas las facturas de una vez salvo filtro «solo diferencia».
     *
     * @return array{
     *   columnas_medios: list<array{cuentacaja_id:int, codigo:string, nombre:string}>,
     *   filas: list<array<string, mixed>>,
     *   totales: array<string, mixed>,
     *   total_filas: int,
     *   total_con_diferencia: int,
     *   paginacion: array{page:int, per_page:int, total:int, total_pages:int}|null
     * }
     */
    public static function grillaConciliacionRespuesta(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        ?Carbon $desdeHabilitacion,
        int $page = 0,
        int $perPage = self::CONCILIACION_FILAS_POR_PAGINA,
        bool $soloDiferencias = false,
        ?Carbon $hastaInclusive = null,
    ): array {
        $perPage = max(10, min(100, $perPage));
        $totales = self::calcular($identificadorPc, $empresaId, $fechaJornada, $desdeHabilitacion, $hastaInclusive);
        $columnas = self::columnasMediosConciliacion($totales);
        $conteo = self::contarFilasConciliacion($identificadorPc, $empresaId, $fechaJornada, $desdeHabilitacion, $hastaInclusive);

        $base = [
            'columnas_medios' => $columnas,
            'filas' => [],
            'totales' => $totales,
            'total_filas' => $conteo['total'],
            'total_con_diferencia' => $conteo['con_diferencia'],
            'paginacion' => null,
        ];

        if ($page < 1) {
            return $base;
        }

        if ($soloDiferencias) {
            $todas = self::construirFilasConciliacion(
                self::emisionesEnAlcance($identificadorPc, $empresaId, $fechaJornada, $desdeHabilitacion, $hastaInclusive),
            );
            $filtradas = array_values(array_filter(
                $todas,
                fn (array $f) => abs((float) $f['diferencia']) >= self::TOLERANCIA_CONCILIACION,
            ));
            $total = count($filtradas);
            $filas = array_slice($filtradas, ($page - 1) * $perPage, $perPage);
        } else {
            $query = self::queryEmisionesEnAlcance($identificadorPc, $empresaId, $fechaJornada, $desdeHabilitacion, $hastaInclusive);
            $total = (int) $query->count();
            $emisiones = $query
                ->forPage($page, $perPage)
                ->with([
                    'venta.cobranzasDirectas',
                    'venta.caja_movimientos.cobranzas',
                    'cuenta.mozo',
                ])
                ->get();
            $filas = self::construirFilasConciliacion($emisiones);
        }

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;

        $base['filas'] = $filas;
        $base['paginacion'] = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'solo_diferencias' => $soloDiferencias,
        ];

        return $base;
    }

    /**
     * @deprecated Use grillaConciliacionRespuesta()
     * @return array<string, mixed>
     */
    public static function grillaConciliacionTurno(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        ?Carbon $desdeHabilitacion = null,
    ): array {
        return self::grillaConciliacionRespuesta(
            $identificadorPc,
            $empresaId,
            $fechaJornada,
            $desdeHabilitacion,
            1,
            5000,
            false,
        );
    }

    /**
     * @param  array<string, mixed>  $totales
     * @return list<array{cuentacaja_id:int, codigo:string, nombre:string}>
     */
    private static function columnasMediosConciliacion(array $totales): array
    {
        $columnas = [];
        foreach ($totales['por_medio_pago'] ?? [] as $p) {
            $nombre = trim((string) ($p['nombre'] ?? ''));
            $codigo = trim((string) ($p['codigo'] ?? ''));
            $columnas[] = [
                'cuentacaja_id' => (int) $p['cuentacaja_id'],
                'codigo' => $codigo,
                'nombre' => $nombre !== '' ? $nombre : $codigo,
            ];
        }

        usort($columnas, fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));

        return $columnas;
    }

    /**
     * @return array{total:int, con_diferencia:int}
     */
    private static function contarFilasConciliacion(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        ?Carbon $desdeHabilitacion,
        ?Carbon $hastaInclusive = null,
    ): array {
        $total = (int) self::queryEmisionesEnAlcance($identificadorPc, $empresaId, $fechaJornada, $desdeHabilitacion, $hastaInclusive)->count();
        $conDiferencia = 0;
        $importeMinimo = GastronomiaFacturacionService::IMPORTE_MINIMO_FACTURA;

        self::queryEmisionesEnAlcance($identificadorPc, $empresaId, $fechaJornada, $desdeHabilitacion, $hastaInclusive)
            ->with(['venta.cobranzasDirectas', 'venta.caja_movimientos.cobranzas'])
            ->chunk(80, function ($emisiones) use (&$conDiferencia, $importeMinimo) {
                foreach ($emisiones as $em) {
                    $venta = $em->venta;
                    if (! $venta) {
                        continue;
                    }
                    $montoVenta = round((float) $venta->total, 2);
                    $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
                    $totalCobrado = self::sumarMontosMedios(GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas));
                    $esInvitacion = self::esInvitacionSinCobranza($montoVenta, $totalCobrado, $importeMinimo);
                    $diff = round($totalCobrado - ($esInvitacion ? 0 : $montoVenta), 2);
                    if (abs($diff) >= self::TOLERANCIA_CONCILIACION) {
                        $conDiferencia++;
                    }
                }
            });

        return ['total' => $total, 'con_diferencia' => $conDiferencia];
    }

    /**
     * @return Builder<VentaGastronomiaEmision>
     */
    private static function queryEmisionesEnAlcance(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        ?Carbon $desdeHabilitacion,
        ?Carbon $hastaInclusive = null,
    ): Builder {
        return VentaGastronomiaEmision::query()
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

    /**
     * @param  Collection<int, VentaGastronomiaEmision>  $emisiones
     * @return list<array<string, mixed>>
     */
    private static function construirFilasConciliacion(Collection $emisiones): array
    {
        $importeMinimo = GastronomiaFacturacionService::IMPORTE_MINIMO_FACTURA;
        $filas = [];

        foreach ($emisiones as $em) {
            $venta = $em->venta;
            if (! $venta) {
                continue;
            }

            $montoVenta = round((float) $venta->total, 2);
            $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
            $medios = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
            $totalCobradoVenta = self::sumarMontosMedios($medios);
            $esNotaCredito = ($em->venta_factura_origen_id ?? null) !== null;
            $esInvitacion = ! $esNotaCredito
                && self::esInvitacionSinCobranza($montoVenta, $totalCobradoVenta, $importeMinimo);

            $mediosFila = [];
            foreach ($medios as $lineas) {
                foreach ($lineas as $linea) {
                    $ccId = (int) ($linea->cuentacaja_id ?? 0);
                    if ($ccId <= 0) {
                        continue;
                    }
                    $mediosFila[$ccId] = round(($mediosFila[$ccId] ?? 0) + (float) $linea->monto, 2);
                }
            }

            $mozo = $em->cuenta?->mozo;
            $filas[] = [
                'venta_id' => (int) $venta->id,
                'codigo' => (string) ($venta->codigo ?? ''),
                'cliente' => (string) ($venta->nombre ?? ''),
                'mozo_id' => $em->cuenta?->mozo_gastronomia_id !== null ? (int) $em->cuenta->mozo_gastronomia_id : null,
                'mozo_nombre' => $mozo?->nombre ?? 'Sin mozo',
                'hora' => $venta->created_at?->format('H:i') ?? '',
                'total_facturado' => $montoVenta,
                'total_cobrado' => $totalCobradoVenta,
                'diferencia' => round($totalCobradoVenta - ($esInvitacion ? 0 : $montoVenta), 2),
                'medios' => $mediosFila,
                'es_invitacion' => $esInvitacion,
                'es_nota_credito' => $esNotaCredito,
                'venta_factura_origen_id' => $esNotaCredito ? (int) $em->venta_factura_origen_id : null,
            ];
        }

        return $filas;
    }

    /**
     * Facturas del turno que utilizaron un medio de pago (cuenta de caja).
     * Excluye notas de crédito (devoluciones) — para esas usar notasCreditoDelTurno().
     *
     * @return list<array{
     *   venta_id:int,
     *   codigo:string,
     *   cliente:string,
     *   mozo_nombre:string,
     *   mozo_id:?int,
     *   hora:string,
     *   total_facturado:float,
     *   monto_medio:float,
     *   total_cobrado:float,
     *   es_invitacion:bool
     * }>
     */
    public static function facturasPorMedioPago(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        int $cuentacajaId,
        ?Carbon $desdeHabilitacion = null,
        ?int $mozoId = null,
    ): array {
        if ($cuentacajaId <= 0) {
            return [];
        }

        $emisiones = self::emisionesEnAlcance($identificadorPc, $empresaId, $fechaJornada, $desdeHabilitacion);
        $filas = self::construirFilasConciliacion($emisiones);
        $out = [];

        foreach ($filas as $fila) {
            if (! empty($fila['es_nota_credito'])) {
                continue;
            }
            if ($mozoId !== null && (int) ($fila['mozo_id'] ?? 0) !== $mozoId) {
                continue;
            }
            $montoMedio = (float) ($fila['medios'][$cuentacajaId] ?? 0);
            if ($montoMedio < 0.001) {
                continue;
            }
            $out[] = [
                'venta_id' => $fila['venta_id'],
                'codigo' => $fila['codigo'],
                'cliente' => $fila['cliente'],
                'mozo_id' => $fila['mozo_id'] ?? null,
                'mozo_nombre' => $fila['mozo_nombre'],
                'hora' => $fila['hora'],
                'total_facturado' => $fila['total_facturado'],
                'monto_medio' => $montoMedio,
                'total_cobrado' => $fila['total_cobrado'],
                'es_invitacion' => $fila['es_invitacion'],
            ];
        }

        return $out;
    }

    /**
     * Notas de crédito del turno (opcionalmente filtradas por mozo).
     * Mismo formato que facturasPorMedioPago() para reutilizar el render del modal.
     * El campo `monto_medio` se reemplaza por:
     *   - `monto_nota_credito` (= total NC, negativo)
     *   - `factura_origen_codigo` (código del comprobante origen)
     *   - `factura_origen_id`
     *
     * @return list<array{
     *   venta_id:int,
     *   codigo:string,
     *   cliente:string,
     *   mozo_nombre:string,
     *   mozo_id:?int,
     *   hora:string,
     *   total_facturado:float,
     *   monto_nota_credito:float,
     *   total_cobrado:float,
     *   factura_origen_id:?int,
     *   factura_origen_codigo:string,
     *   es_invitacion:bool
     * }>
     */
    public static function notasCreditoDelTurno(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        ?Carbon $desdeHabilitacion = null,
        ?int $mozoId = null,
        ?Carbon $hastaInclusive = null,
    ): array {
        $emisiones = self::emisionesEnAlcance($identificadorPc, $empresaId, $fechaJornada, $desdeHabilitacion, $hastaInclusive);
        $filas = self::construirFilasConciliacion($emisiones);

        $ncFilas = array_values(array_filter($filas, function (array $f) use ($mozoId) {
            if (empty($f['es_nota_credito'])) {
                return false;
            }
            if ($mozoId !== null && (int) ($f['mozo_id'] ?? 0) !== $mozoId) {
                return false;
            }

            return true;
        }));

        if ($ncFilas === []) {
            return [];
        }

        $origenIds = array_values(array_unique(array_filter(array_map(
            fn (array $f) => $f['venta_factura_origen_id'] ?? null,
            $ncFilas
        ))));
        $origenCodigos = [];
        if ($origenIds !== []) {
            $origenCodigos = Venta::query()
                ->whereIn('id', $origenIds)
                ->pluck('codigo', 'id')
                ->all();
        }

        $out = [];
        foreach ($ncFilas as $fila) {
            $origenId = $fila['venta_factura_origen_id'] ?? null;
            $out[] = [
                'venta_id' => $fila['venta_id'],
                'codigo' => $fila['codigo'],
                'cliente' => $fila['cliente'],
                'mozo_id' => $fila['mozo_id'] ?? null,
                'mozo_nombre' => $fila['mozo_nombre'],
                'hora' => $fila['hora'],
                'total_facturado' => $fila['total_facturado'],
                'monto_nota_credito' => $fila['total_facturado'],
                'total_cobrado' => $fila['total_cobrado'],
                'factura_origen_id' => $origenId !== null ? (int) $origenId : null,
                'factura_origen_codigo' => $origenId !== null ? (string) ($origenCodigos[$origenId] ?? '') : '',
                'es_invitacion' => (bool) ($fila['es_invitacion'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * Facturas de cortesía / invitación ($0,01, sin cobranza) del turno.
     *
     * @return list<array{
     *   venta_id:int,
     *   codigo:string,
     *   cliente:string,
     *   mozo_nombre:string,
     *   hora:string,
     *   total_facturado:float,
     *   descuento_pct:float,
     *   descuento_codigo:string,
     *   descuento_nombre:string
     * }>
     */
    public static function invitacionesDelTurno(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        ?Carbon $desdeHabilitacion = null,
        ?Carbon $hastaInclusive = null,
        ?int $mozoId = null,
    ): array {
        $emisiones = self::emisionesEnAlcance(
            $identificadorPc,
            $empresaId,
            $fechaJornada,
            $desdeHabilitacion,
            $hastaInclusive,
        );
        $emisiones->loadMissing('cuenta.descuentoGastronomia');

        $filas = self::construirFilasConciliacion($emisiones);
        $invFilas = array_values(array_filter($filas, function (array $f) use ($mozoId) {
            if (empty($f['es_invitacion'])) {
                return false;
            }
            if ($mozoId !== null && (int) ($f['mozo_id'] ?? 0) !== $mozoId) {
                return false;
            }

            return true;
        }));

        if ($invFilas === []) {
            return [];
        }

        $emPorVentaId = $emisiones->keyBy('venta_id');
        $out = [];

        foreach ($invFilas as $fila) {
            $em = $emPorVentaId->get($fila['venta_id']);
            $desc = $em?->cuenta?->descuentoGastronomia;
            $venta = $em?->venta;

            $out[] = [
                'venta_id' => $fila['venta_id'],
                'codigo' => $fila['codigo'],
                'cliente' => $fila['cliente'],
                'mozo_id' => $fila['mozo_id'] ?? null,
                'mozo_nombre' => $fila['mozo_nombre'],
                'hora' => $fila['hora'],
                'total_facturado' => $fila['total_facturado'],
                'descuento_pct' => round((float) ($venta->descuento ?? 0), 2),
                'descuento_codigo' => (string) ($desc?->codigo ?? ''),
                'descuento_nombre' => (string) ($desc?->nombre ?? ''),
            ];
        }

        return $out;
    }
}
