<?php

namespace App\Support\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Models\Caja\Estacionamiento\TurnoOperativoEstacionamiento;
use App\Models\Caja\Estacionamiento\VentaEstacionamientoEmision;
use App\Models\Ventas\Venta;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Totales de comprobantes estacionamiento por PC: ventas y cobranzas leídas de cada factura emitida.
 * Sin desglose por mozo (estacionamiento no usa mozos).
 */
final class EstacionamientoTurnoOperativoTotalesSupport
{
    public const IMPORTE_MINIMO = 0.01;

    private const TOLERANCIA_CONCILIACION = 0.02;

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
        int $jornadaEstacionamientoId,
    ): array {
        $ventanas = TurnoOperativoEstacionamiento::query()
            ->where('identificador_pc', $identificadorPc)
            ->where('jornada_estacionamiento_id', $jornadaEstacionamientoId)
            ->whereIn('estado', [
                TurnoOperativoEstacionamiento::ESTADO_CERRADO,
                TurnoOperativoEstacionamiento::ESTADO_HABILITADO,
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

    /**
     * @return array{cantidad:int, ejemplos:list<array{venta_id:int, codigo:string, hora:string}>}
     */
    public static function facturasHuerfanasDelDia(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        int $jornadaEstacionamientoId,
    ): array {
        $huerfanas = self::listarFacturasHuerfanasDelDia(
            $identificadorPc,
            $empresaId,
            $fechaJornada,
            $jornadaEstacionamientoId,
        );

        return [
            'cantidad' => count($huerfanas),
            'ejemplos' => array_slice($huerfanas, 0, 8),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function calcular(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        ?Carbon $desdeHabilitacion = null,
        ?Carbon $hastaInclusive = null,
    ): array {
        $emisiones = self::emisionesEnAlcance($identificadorPc, $empresaId, $fechaJornada, $desdeHabilitacion, $hastaInclusive);

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
            'por_mozo' => [],
            'por_medio_pago' => self::normalizarMediosPago($porMedioGlobal),
        ];
    }

    public static function calcularPorJornada(JornadaEstacionamiento $jornada): array
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

    public static function redondeoInvitacionesSugerido(float $totalInvitaciones, float $diferenciaCobranza): float
    {
        $ajuste = 0.0;
        if (abs($diferenciaCobranza) > 0.001 && abs($diferenciaCobranza) <= self::TOLERANCIA_CONCILIACION) {
            $ajuste = $diferenciaCobranza;
        }

        return round($totalInvitaciones + $ajuste, 2);
    }

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

    public const CONCILIACION_FILAS_POR_PAGINA = 40;

    /**
     * @return array<string, mixed>
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
     * @return Builder<VentaEstacionamientoEmision>
     */
    public static function queryEmisionesJornadaEmpresa(
        int $empresaId,
        string $fechaJornada,
        Carbon $desdeInclusive,
        Carbon $hastaInclusive,
    ): Builder {
        return VentaEstacionamientoEmision::query()
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
            ]);
    }

    /**
     * @param  Collection<int, VentaEstacionamientoEmision>  $emisiones
     * @return array<string, mixed>
     */
    private static function calcularDesdeColeccionEmisiones(Collection $emisiones): array
    {
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
        $totalVentasCobrables = round($totalVentas - $totalInvitaciones, 2);
        $diferencia = round($totalCobrado - $totalVentasCobrables, 2);

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
            'redondeo_invitaciones_sugerido' => self::redondeoInvitacionesSugerido($totalInvitaciones, $diferencia),
            'por_mozo' => [],
            'por_medio_pago' => self::normalizarMediosPago($porMedioGlobal),
        ];
    }

    /**
     * @param  list<int>  $ventaIdsFacturasEnPc
     * @return Collection<int, VentaEstacionamientoEmision>
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

        return VentaEstacionamientoEmision::query()
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
            ])
            ->get();
    }

    /**
     * @param  array<int, array{cuentacaja_id:int, codigo:string, nombre:string, total:float}>  $porMedioGlobal
     */
    private static function acumularEmisionEnTotales(
        VentaEstacionamientoEmision $em,
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
            if (self::esInvitacionSinCobranza($montoVenta, $totalCobradoVenta)) {
                $totalInvitaciones += $montoVenta;
                $cantidadInvitaciones++;
            }
            self::acumularMediosPago($medios, $porMedioGlobal);
        }
    }

    /**
     * @return Collection<int, VentaEstacionamientoEmision>
     */
    private static function emisionesEnAlcance(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        ?Carbon $desdeHabilitacion,
        ?Carbon $hastaInclusive = null,
    ): Collection {
        return self::queryEmisionesEnAlcance($identificadorPc, $empresaId, $fechaJornada, $desdeHabilitacion, $hastaInclusive)
            ->with([
                'venta.cobranzasDirectas',
                'venta.caja_movimientos.cobranzas',
            ])
            ->get();
    }

    private static function esInvitacionSinCobranza(float $montoVenta, float $montoCobrado): bool
    {
        if (abs($montoVenta - self::IMPORTE_MINIMO) >= 0.001) {
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

        self::queryEmisionesEnAlcance($identificadorPc, $empresaId, $fechaJornada, $desdeHabilitacion, $hastaInclusive)
            ->with(['venta.cobranzasDirectas', 'venta.caja_movimientos.cobranzas'])
            ->chunk(80, function ($emisiones) use (&$conDiferencia) {
                foreach ($emisiones as $em) {
                    $venta = $em->venta;
                    if (! $venta) {
                        continue;
                    }
                    $montoVenta = round((float) $venta->total, 2);
                    $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
                    $totalCobrado = self::sumarMontosMedios(GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas));
                    $esInvitacion = self::esInvitacionSinCobranza($montoVenta, $totalCobrado);
                    $diff = round($totalCobrado - ($esInvitacion ? 0 : $montoVenta), 2);
                    if (abs($diff) >= self::TOLERANCIA_CONCILIACION) {
                        $conDiferencia++;
                    }
                }
            });

        return ['total' => $total, 'con_diferencia' => $conDiferencia];
    }

    /**
     * @return Builder<VentaEstacionamientoEmision>
     */
    private static function queryEmisionesEnAlcance(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        ?Carbon $desdeHabilitacion,
        ?Carbon $hastaInclusive = null,
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

    /**
     * @param  Collection<int, VentaEstacionamientoEmision>  $emisiones
     * @return list<array<string, mixed>>
     */
    private static function construirFilasConciliacion(Collection $emisiones): array
    {
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
                && self::esInvitacionSinCobranza($montoVenta, $totalCobradoVenta);

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

            $filas[] = [
                'venta_id' => (int) $venta->id,
                'codigo' => (string) ($venta->codigo ?? ''),
                'cliente' => (string) ($venta->nombre ?? ''),
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
     * @return list<array<string, mixed>>
     */
    public static function facturasPorMedioPago(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        int $cuentacajaId,
        ?Carbon $desdeHabilitacion = null,
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
            $montoMedio = (float) ($fila['medios'][$cuentacajaId] ?? 0);
            if ($montoMedio < 0.001) {
                continue;
            }
            $out[] = [
                'venta_id' => $fila['venta_id'],
                'codigo' => $fila['codigo'],
                'cliente' => $fila['cliente'],
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
     * @return list<array<string, mixed>>
     */
    public static function notasCreditoDelTurno(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        ?Carbon $desdeHabilitacion = null,
        ?Carbon $hastaInclusive = null,
    ): array {
        $emisiones = self::emisionesEnAlcance($identificadorPc, $empresaId, $fechaJornada, $desdeHabilitacion, $hastaInclusive);
        $filas = self::construirFilasConciliacion($emisiones);

        $ncFilas = array_values(array_filter($filas, fn (array $f) => ! empty($f['es_nota_credito'])));

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
     * @return list<array{
     *   venta_id:int,
     *   codigo:string,
     *   cliente:string,
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
    ): array {
        $emisiones = self::emisionesEnAlcance(
            $identificadorPc,
            $empresaId,
            $fechaJornada,
            $desdeHabilitacion,
            $hastaInclusive,
        );

        $filas = self::construirFilasConciliacion($emisiones);
        $invFilas = array_values(array_filter($filas, fn (array $f) => ! empty($f['es_invitacion'])));

        if ($invFilas === []) {
            return [];
        }

        $out = [];
        foreach ($invFilas as $fila) {
            $out[] = [
                'venta_id' => $fila['venta_id'],
                'codigo' => $fila['codigo'],
                'cliente' => $fila['cliente'],
                'hora' => $fila['hora'],
                'total_facturado' => $fila['total_facturado'],
                'descuento_pct' => 0.0,
                'descuento_codigo' => '',
                'descuento_nombre' => '',
            ];
        }

        return $out;
    }
}
