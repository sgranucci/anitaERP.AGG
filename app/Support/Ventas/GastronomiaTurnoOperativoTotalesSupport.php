<?php

namespace App\Support\Ventas;

use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\GastronomiaFacturacionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Totales de comprobantes gastronomía por PC: ventas y cobranzas leídas de cada factura emitida.
 */
final class GastronomiaTurnoOperativoTotalesSupport
{
    private const TOLERANCIA_CONCILIACION = 0.02;

    /**
     * @return array{
     *   total_general: float,
     *   total_ventas: float,
     *   total_cobrado: float,
     *   total_invitaciones: float,
     *   cantidad_invitaciones: int,
     *   total_ventas_cobrables: float,
     *   diferencia_cobranza: float,
     *   conciliacion_ok: bool,
     *   cantidad_comprobantes: int,
     *   redondeo_invitaciones_sugerido: float,
     *   por_mozo: list<array{
     *     mozo_id:?int,
     *     mozo_codigo:?string,
     *     mozo_nombre:string,
     *     total:float,
     *     total_cobrado:float,
     *     cantidad:int,
     *     por_medio_pago: list<array{cuentacaja_id:int, codigo:string, nombre:string, total:float}>
     *   }>,
     *   por_medio_pago: list<array{cuentacaja_id:int, codigo:string, nombre:string, total:float}>
     * }
     */
    /**
     * Comprobantes del día en esta PC que no caen dentro de ningún turno operativo cerrado de la jornada.
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
            ->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO)
            ->whereNotNull('habilitacion_en')
            ->whereNotNull('cierre_en')
            ->get(['habilitacion_en', 'cierre_en']);

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
                if ($ts >= $ventana->habilitacion_en && $ts <= $ventana->cierre_en) {
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
        $totalInvitaciones = 0.0;
        $cantidadInvitaciones = 0;
        $totalCobrado = 0.0;

        foreach ($emisiones as $em) {
            $venta = $em->venta;
            if (! $venta) {
                continue;
            }

            $montoVenta = round((float) $venta->total, 2);
            $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
            $medios = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
            $totalCobradoVenta = self::sumarMontosMedios($medios);
            $totalVentas += $montoVenta;
            $totalCobrado += $totalCobradoVenta;

            if (self::esInvitacionSinCobranza($montoVenta, $totalCobradoVenta, $importeMinimo)) {
                $totalInvitaciones += $montoVenta;
                $cantidadInvitaciones++;
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
                    'total_cobrado' => 0.0,
                    'cantidad' => 0,
                    'por_medio_pago' => [],
                ];
            }
            $porMozo[$key]['total'] += $montoVenta;
            $porMozo[$key]['total_cobrado'] += $totalCobradoVenta;
            $porMozo[$key]['cantidad']++;

            self::acumularMediosPago($medios, $porMozo[$key]['por_medio_pago']);
            self::acumularMediosPago($medios, $porMedioGlobal);
        }

        $totalVentas = round($totalVentas, 2);
        $totalInvitaciones = round($totalInvitaciones, 2);
        $totalCobrado = round($totalCobrado, 2);
        $totalVentasCobrables = round($totalVentas - $totalInvitaciones, 2);
        $diferencia = round($totalCobrado - $totalVentasCobrables, 2);

        $porMedioGlobal = self::normalizarMediosPago($porMedioGlobal);

        foreach ($porMozo as &$row) {
            $row['total'] = round($row['total'], 2);
            $row['total_cobrado'] = round($row['total_cobrado'], 2);
            $row['por_medio_pago'] = self::normalizarMediosPago($row['por_medio_pago']);
        }
        unset($row);

        usort($porMozo, fn ($a, $b) => strcmp($a['mozo_nombre'], $b['mozo_nombre']));

        return [
            'total_general' => $totalVentas,
            'total_ventas' => $totalVentas,
            'total_cobrado' => $totalCobrado,
            'total_invitaciones' => $totalInvitaciones,
            'cantidad_invitaciones' => $cantidadInvitaciones,
            'total_ventas_cobrables' => $totalVentasCobrables,
            'diferencia_cobranza' => $diferencia,
            'conciliacion_ok' => abs($diferencia) < self::TOLERANCIA_CONCILIACION,
            'cantidad_comprobantes' => $emisiones->count(),
            'redondeo_invitaciones_sugerido' => $totalInvitaciones,
            'por_mozo' => array_values($porMozo),
            'por_medio_pago' => $porMedioGlobal,
        ];
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
            $esInvitacion = self::esInvitacionSinCobranza($montoVenta, $totalCobradoVenta, $importeMinimo);

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
                'mozo_nombre' => $mozo?->nombre ?? 'Sin mozo',
                'hora' => $venta->created_at?->format('H:i') ?? '',
                'total_facturado' => $montoVenta,
                'total_cobrado' => $totalCobradoVenta,
                'diferencia' => round($totalCobradoVenta - ($esInvitacion ? 0 : $montoVenta), 2),
                'medios' => $mediosFila,
                'es_invitacion' => $esInvitacion,
            ];
        }

        return $filas;
    }

    /**
     * Facturas del turno que utilizaron un medio de pago (cuenta de caja).
     *
     * @return list<array{
     *   venta_id:int,
     *   codigo:string,
     *   cliente:string,
     *   mozo_nombre:string,
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
    ): array {
        if ($cuentacajaId <= 0) {
            return [];
        }

        $emisiones = self::emisionesEnAlcance($identificadorPc, $empresaId, $fechaJornada, $desdeHabilitacion);
        $filas = self::construirFilasConciliacion($emisiones);
        $out = [];

        foreach ($filas as $fila) {
            $montoMedio = (float) ($fila['medios'][$cuentacajaId] ?? 0);
            if ($montoMedio < 0.001) {
                continue;
            }
            $out[] = [
                'venta_id' => $fila['venta_id'],
                'codigo' => $fila['codigo'],
                'cliente' => $fila['cliente'],
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
}
