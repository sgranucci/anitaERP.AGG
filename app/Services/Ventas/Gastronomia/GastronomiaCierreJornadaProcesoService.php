<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\JornadaGastronomia;
use Carbon\Carbon;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Support\Ventas\Gastronomia\CierreJornadaFacturadoAnitaSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosPreviewSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoClasificacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoConfigSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoRedistribucionSupport;
use App\Support\Ventas\GastronomiaCuentacajaTotem;
use App\Support\Ventas\Gastronomia\VentaGastronomiaEmisionWaitrySupport;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use InvalidArgumentException;

/**
 * Proceso de cierre de jornada gastronomía: conciliación Waitry, redistribución QR/efectivo y preview de asientos.
 */
final class GastronomiaCierreJornadaProcesoService
{
    public const GRUPO_ANITA_JORNADA = 'anita_jornada';

    public function __construct(
        private readonly GastronomiaCierreTotemJornadaService $cierreTotemJornadaService,
    ) {
    }

    public function habilitado(): bool
    {
        return $this->cierreTotemJornadaService->habilitado();
    }

    /**
     * @return array<string, mixed>
     */
    public function analizarPorEmpresaYFecha(int $empresaId, string $fechaJornada): array
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Debe seleccionar una empresa.');
        }

        $fecha = $this->normalizarFecha($fechaJornada);

        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fecha)
            ->orderByDesc('id')
            ->first();

        if ($jornada === null) {
            throw new InvalidArgumentException(
                'No hay jornada gastronomía registrada para esta empresa y fecha. '
                .'Abra o cierre la jornada en Ventas → Gastronomía → Jornada antes de ejecutar el proceso.'
            );
        }

        return $this->analizarJornada((int) $jornada->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function recalcularPorEmpresaYFecha(int $empresaId, string $fechaJornada, float $porcentaje): array
    {
        $jornada = $this->resolverJornadaPorEmpresaFecha($empresaId, $fechaJornada);

        return $this->recalcular((int) $jornada->id, $porcentaje);
    }

    /**
     * @return array<string, mixed>
     */
    public function movimientosGrupoPorEmpresaYFecha(
        int $empresaId,
        string $fechaJornada,
        string $grupo,
        int $pagina = 1,
        int $porPagina = 50,
    ): array {
        $jornada = $this->resolverJornadaPorEmpresaFecha($empresaId, $fechaJornada);

        return $this->movimientosGrupo((int) $jornada->id, $grupo, $pagina, $porPagina);
    }

    /**
     * @return array<string, mixed>
     */
    public function analizarJornada(int $jornadaId): array
    {
        $jornada = $this->resolverJornada($jornadaId);
        $empresaId = (int) $jornada->empresa_id;

        $cargado = $this->cierreTotemJornadaService->movimientosParaJornada($jornada);
        $lineas = $this->enriquecerLineasConCobranzaAnita($cargado['lineas'], $empresaId);
        $anitaJornada = CierreJornadaFacturadoAnitaSupport::totalesJornadaEmpresa(
            $empresaId,
            $jornada->fecha_jornada?->format('Y-m-d') ?? '',
        );

        $clasificacion = CierreJornadaProcesoClasificacionSupport::clasificar($lineas, $empresaId, $anitaJornada);
        $config = CierreJornadaProcesoConfigSupport::paraEmpresa($empresaId);

        return [
            'ok' => true,
            'jornada' => $this->resumenJornada($jornada),
            'meta' => $cargado['meta'],
            'grilla' => $clasificacion['grilla'],
            'cuadro_filas' => $clasificacion['cuadro_filas'],
            'total_facturacion' => $clasificacion['total_facturacion'],
            'total_pendiente_facturar' => $clasificacion['total_pendiente_facturar'],
            'total_impago_waitry' => $clasificacion['total_impago_waitry'],
            'total_cuadro' => $clasificacion['total_cuadro'],
            'conteos' => $clasificacion['conteos'],
            'grupos_resumen' => $this->resumenGrupos($clasificacion['grupos'], $jornada, $empresaId),
            'config_contable' => $config,
            'config_faltante' => CierreJornadaProcesoConfigSupport::faltantes($config),
            'notas' => $this->notasProceso(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recalcular(int $jornadaId, float $porcentaje): array
    {
        $jornada = $this->resolverJornada($jornadaId);
        $empresaId = (int) $jornada->empresa_id;

        $cargado = $this->cierreTotemJornadaService->movimientosParaJornada($jornada);
        $lineas = $this->enriquecerLineasConCobranzaAnita($cargado['lineas'], $empresaId);
        $anitaJornada = CierreJornadaFacturadoAnitaSupport::totalesJornadaEmpresa(
            $empresaId,
            $jornada->fecha_jornada?->format('Y-m-d') ?? '',
        );
        $clasificacionBase = CierreJornadaProcesoClasificacionSupport::clasificar($lineas, $empresaId, $anitaJornada);

        $redistribucion = CierreJornadaProcesoRedistribucionSupport::aplicar(
            $clasificacionBase['movimientos'],
            $clasificacionBase['total_facturacion'],
            $porcentaje,
        );

        $empresaId = (int) $jornada->empresa_id;
        $clasificacion = CierreJornadaProcesoClasificacionSupport::clasificar(
            $redistribucion['movimientos'],
            $empresaId,
            $anitaJornada,
        );

        $config = CierreJornadaProcesoConfigSupport::paraEmpresa($empresaId);
        $previewAsientos = CierreJornadaProcesoAsientosPreviewSupport::generar(
            $clasificacion['movimientos'],
            $empresaId,
            $config,
        );

        return [
            'ok' => true,
            'porcentaje' => $redistribucion['porcentaje'],
            'objetivo_importe' => $redistribucion['objetivo_importe'],
            'redistribucion' => [
                'asignado_sin_facturar_a_efectivo' => $redistribucion['asignado_sin_facturar_a_efectivo'],
                'asignado_facturado_efectivo_a_qr' => $redistribucion['asignado_facturado_efectivo_a_qr'],
                'ajustes' => $redistribucion['ajustes'],
            ],
            'grilla' => $clasificacion['grilla'],
            'cuadro_filas' => $clasificacion['cuadro_filas'],
            'total_facturacion' => $clasificacion['total_facturacion'],
            'total_pendiente_facturar' => $clasificacion['total_pendiente_facturar'],
            'total_impago_waitry' => $clasificacion['total_impago_waitry'],
            'total_cuadro' => $clasificacion['total_cuadro'],
            'movimientos' => $this->compactarMovimientosParaCliente($clasificacion['movimientos']),
            'preview_asientos' => $previewAsientos,
        ];
    }

    /**
     * Detalle paginado de un grupo (evita enviar miles de filas en el análisis inicial).
     *
     * @return array<string, mixed>
     */
    public function movimientosGrupo(int $jornadaId, string $grupo, int $pagina = 1, int $porPagina = 50): array
    {
        $jornada = $this->resolverJornada($jornadaId);
        $empresaId = (int) $jornada->empresa_id;
        $porPagina = max(10, min(200, $porPagina));
        $pagina = max(1, $pagina);

        if ($grupo === self::GRUPO_ANITA_JORNADA) {
            return $this->movimientosAnitaJornada($jornada, $empresaId, $pagina, $porPagina);
        }

        $cargado = $this->cierreTotemJornadaService->movimientosParaJornada($jornada);
        $lineas = $this->enriquecerLineasConCobranzaAnita($cargado['lineas'], $empresaId);
        $anitaJornada = CierreJornadaFacturadoAnitaSupport::totalesJornadaEmpresa(
            $empresaId,
            $jornada->fecha_jornada?->format('Y-m-d') ?? '',
        );
        $clasificacion = CierreJornadaProcesoClasificacionSupport::clasificar($lineas, $empresaId, $anitaJornada);
        $items = $clasificacion['grupos'][$grupo] ?? [];
        $total = count($items);
        $offset = ($pagina - 1) * $porPagina;
        $slice = array_slice($items, $offset, $porPagina);

        return [
            'ok' => true,
            'grupo' => $grupo,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total' => $total,
            'total_paginas' => (int) max(1, ceil($total / $porPagina)),
            'items' => $this->compactarMovimientosParaCliente($slice),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function guardarConfig(int $empresaId, array $data): array
    {
        $cfg = CierreJornadaProcesoConfigSupport::guardar($empresaId, $data);

        return ['ok' => true, 'config' => $cfg];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function enriquecerLineasConCobranzaAnita(array $lineas, int $empresaId): array
    {
        $waitryIds = [];
        foreach ($lineas as $ln) {
            $wid = (int) ($ln['waitry_order_id'] ?? 0);
            if ($wid > 0 && ! empty($ln['facturada_erp'])) {
                $waitryIds[] = $wid;
            }
        }
        if ($waitryIds === []) {
            return $lineas;
        }

        $totemId = (int) (GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId)['id'] ?? 0);

        $emisiones = VentaGastronomiaEmision::query()
            ->with(['venta.cobranzasDirectas', 'venta.caja_movimientos.cobranzas', 'cuenta', 'waitryComandaEnvio'])
            ->whereHas('venta', fn ($q) => $q->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId)))
            ->orderByDesc('venta_id')
            ->get();

        $mapEmisiones = [];
        foreach ($emisiones as $emision) {
            $wid = VentaGastronomiaEmisionWaitrySupport::resolverOrderId($emision);
            if ($wid > 0 && in_array($wid, $waitryIds, true) && ! isset($mapEmisiones[$wid])) {
                $mapEmisiones[$wid] = $emision;
            }
        }

        $out = [];
        foreach ($lineas as $ln) {
            $wid = (int) ($ln['waitry_order_id'] ?? 0);
            $emision = $mapEmisiones[$wid] ?? null;
            if ($emision !== null) {
                $ln['venta_id'] = $emision->venta_id;
                $ln['venta_codigo'] = $emision->venta?->codigo ?? ($ln['venta_codigo'] ?? '');
                $ln['impuesto_interno'] = $this->sumarImpuestoInternoVenta($emision->venta);

                $medio = $this->primerMedioCobranza($emision, $empresaId);
                if ($medio !== null) {
                    $ln['anita_cuentacaja_id'] = $medio['cuentacaja_id'];
                    $ln['anita_cuentacaja_label'] = $medio['label'];
                    $ln['anita_es_totem'] = $totemId > 0 && (int) $medio['cuentacaja_id'] === $totemId;
                } else {
                    $ln['anita_es_totem'] = (bool) ($ln['waitry_cobro_totem'] ?? false);
                }
            } else {
                $ln['anita_es_totem'] = (bool) ($ln['waitry_cobro_totem'] ?? false);
            }
            $out[] = $ln;
        }

        return $out;
    }

    private function sumarImpuestoInternoVenta($venta): float
    {
        if ($venta === null) {
            return 0.;
        }
        $venta->loadMissing('venta_impuestos');
        $total = 0.;
        foreach ($venta->venta_impuestos ?? [] as $vi) {
            $concepto = mb_strtolower((string) ($vi->concepto ?? ''));
            if (str_contains($concepto, 'intern')) {
                $total += (float) ($vi->importe ?? 0);
            }
        }

        return round($total, 2);
    }

    /**
     * @return array{cuentacaja_id:int,label:string}|null
     */
    private function primerMedioCobranza(VentaGastronomiaEmision $emision, int $empresaId): ?array
    {
        $venta = $emision->venta;
        if ($venta === null) {
            return null;
        }

        $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
        $medios = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
        foreach ($medios as $lineas) {
            foreach ($lineas as $medio) {
                $ccId = (int) ($medio->cuentacaja_id ?? 0);
                if ($ccId <= 0) {
                    continue;
                }
                $codigo = trim((string) ($medio->codigo ?? ''));
                $nombre = trim((string) ($medio->nombre ?? ''));
                $label = $codigo !== '' && $nombre !== ''
                    ? $codigo.' — '.$nombre
                    : ($codigo !== '' ? $codigo : ($nombre !== '' ? $nombre : '#'.$ccId));

                return ['cuentacaja_id' => $ccId, 'label' => $label];
            }
        }

        return null;
    }

    private function resolverJornada(int $jornadaId): JornadaGastronomia
    {
        if ($jornadaId <= 0) {
            throw new InvalidArgumentException('Jornada inválida.');
        }

        $jornada = JornadaGastronomia::query()->find($jornadaId);
        if ($jornada === null) {
            throw new InvalidArgumentException('Jornada no encontrada.');
        }

        return $jornada;
    }

    private function resolverJornadaPorEmpresaFecha(int $empresaId, string $fechaJornada): JornadaGastronomia
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Debe seleccionar una empresa.');
        }

        $fecha = $this->normalizarFecha($fechaJornada);
        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fecha)
            ->orderByDesc('id')
            ->first();

        if ($jornada === null) {
            throw new InvalidArgumentException('No hay jornada gastronomía para esta empresa y fecha.');
        }

        return $jornada;
    }

    private function normalizarFecha(string $fecha): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            throw new InvalidArgumentException('Debe indicar la fecha de jornada.');
        }

        try {
            return Carbon::parse($fecha)->format('Y-m-d');
        } catch (\Throwable) {
            throw new InvalidArgumentException('Fecha de jornada inválida.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenJornada(JornadaGastronomia $jornada): array
    {
        return [
            'id' => (int) $jornada->id,
            'empresa_id' => (int) $jornada->empresa_id,
            'estado' => (string) $jornada->estado,
            'fecha_jornada' => $jornada->fecha_jornada?->format('Y-m-d'),
            'fecha_jornada_fmt' => $jornada->fecha_jornada?->format('d/m/Y'),
            'apertura_en' => $jornada->apertura_en?->format('d/m/Y H:i'),
            'cierre_en' => $jornada->cierre_en?->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function movimientosAnitaJornada(
        JornadaGastronomia $jornada,
        int $empresaId,
        int $pagina,
        int $porPagina,
    ): array {
        $fecha = $jornada->fecha_jornada?->format('Y-m-d');
        if ($fecha === null || $fecha === '') {
            return [
                'ok' => true,
                'grupo' => self::GRUPO_ANITA_JORNADA,
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total' => 0,
                'total_paginas' => 1,
                'items' => [],
            ];
        }

        $query = VentaGastronomiaEmision::query()
            ->with(['venta', 'cuenta', 'waitryComandaEnvio'])
            ->whereHas('venta', function ($q) use ($empresaId, $fecha) {
                $q->whereDate('fechajornada', $fecha)
                    ->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
            })
            ->orderByDesc('venta_id');

        $total = (int) $query->count();
        $offset = ($pagina - 1) * $porPagina;
        $emisiones = $query->skip($offset)->take($porPagina)->get();

        return [
            'ok' => true,
            'grupo' => self::GRUPO_ANITA_JORNADA,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total' => $total,
            'total_paginas' => (int) max(1, ceil($total / $porPagina)),
            'items' => $this->compactarEmisionesAnitaParaCliente($emisiones, $empresaId),
        ];
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $grupos
     * @return list<array<string, mixed>>
     */
    private function resumenGrupos(array $grupos, JornadaGastronomia $jornada, int $empresaId): array
    {
        $mapa = [
            self::GRUPO_ANITA_JORNADA => 'Facturado Anita (jornada — todas las emisiones)',
            CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_MEDIO_REAL => 'Facturados — medio de pago real en Anita',
            CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_TOTEM => 'Facturados — cobro TOTEM (medio real Waitry)',
            CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR => 'Sin facturar — QR (Totalcoin)',
            CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_OTRO => 'Sin facturar — otros medios',
            CierreJornadaProcesoClasificacionSupport::GRUPO_WAITRY_CASH_NO_FACTURAR => 'Waitry efectivo — no se factura',
            CierreJornadaProcesoClasificacionSupport::GRUPO_HUECO_AUDITORIA => 'Huecos de secuencia (auditoría)',
        ];

        $out = [];
        foreach ($mapa as $clave => $titulo) {
            if ($clave === self::GRUPO_ANITA_JORNADA) {
                $totales = CierreJornadaFacturadoAnitaSupport::totalesJornadaEmpresa(
                    $empresaId,
                    $jornada->fecha_jornada?->format('Y-m-d') ?? '',
                );
                $cantidad = ($totales['cantidad_facturas'] ?? 0) + ($totales['cantidad_notas_credito'] ?? 0);
                $out[] = [
                    'clave' => $clave,
                    'titulo' => $titulo,
                    'cantidad' => $cantidad,
                    'total' => $totales['total'],
                ];
                continue;
            }

            $items = $grupos[$clave] ?? [];
            $total = round(array_sum(array_map(fn (array $m) => (float) ($m['total'] ?? 0), $items)), 2);
            $out[] = [
                'clave' => $clave,
                'titulo' => $titulo,
                'cantidad' => count($items),
                'total' => $total,
            ];
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, VentaGastronomiaEmision>  $emisiones
     * @return list<array<string, mixed>>
     */
    private function compactarEmisionesAnitaParaCliente($emisiones, int $empresaId): array
    {
        $totemId = (int) (GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId)['id'] ?? 0);
        $out = [];
        foreach ($emisiones as $emision) {
            $venta = $emision->venta;
            $esNc = ($emision->venta_factura_origen_id ?? null) !== null;
            $waitryId = VentaGastronomiaEmisionWaitrySupport::resolverOrderId($emision);
            $medio = $this->primerMedioCobranza($emision, $empresaId);
            $waitryTipo = $emision->cuenta?->waitry_tipo_pago;
            $medioLabel = $medio['label'] ?? '';
            if ($medioLabel === '' && $waitryTipo !== null && $waitryTipo !== '') {
                $medioLabel = \App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport::etiquetaTipo($waitryTipo);
            }

            $out[] = [
                'waitry_order_id' => $waitryId > 0 ? $waitryId : null,
                'display_id' => $waitryId > 0 ? '#'.$waitryId : '—',
                'total' => round((float) ($venta?->total ?? 0), 2),
                'venta_codigo' => (string) ($venta?->codigo ?? ''),
                'waitry_medio_label' => $medioLabel,
                'es_nota_credito' => $esNc,
                'anita_es_totem' => $medio !== null
                    ? ($totemId > 0 && (int) $medio['cuentacaja_id'] === $totemId)
                    : (bool) ($emision->cuenta?->waitry_cobro_totem ?? false),
            ];
        }

        return $out;
    }

    /**
     * Reduce payload JSON para el navegador (detalle por grupo vía paginación).
     *
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array<string, mixed>>
     */
    private function compactarMovimientosParaCliente(array $movimientos): array
    {
        $out = [];
        foreach ($movimientos as $m) {
            $out[] = [
                'waitry_order_id' => $m['waitry_order_id'] ?? null,
                'display_id' => $m['display_id'] ?? '',
                'placed_at_fmt' => $m['placed_at_fmt'] ?? '',
                'total' => round((float) ($m['total'] ?? 0), 2),
                'grupo' => $m['grupo'] ?? '',
                'facturada_erp' => ! empty($m['facturada_erp']),
                'venta_codigo' => $m['venta_codigo'] ?? '',
                'waitry_medio_label' => $m['waitry_medio_label'] ?? '',
                'medio_anita_clave' => $m['medio_anita_clave'] ?? null,
                'medio_waitry_clave' => $m['medio_waitry_clave'] ?? null,
                'anita_es_totem' => ! empty($m['anita_es_totem']),
                'medio_pago_planificado' => $m['medio_pago_planificado'] ?? null,
                'medios_pago_planificados' => $m['medios_pago_planificados'] ?? null,
                'discrepancia_gap' => ! empty($m['discrepancia_gap']),
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function notasProceso(): array
    {
        return [
            'Los movimientos incluyen órdenes Waitry desde el último ticket del cierre anterior hasta el cierre de esta jornada.',
            'El cuadro parte del total facturado en Anita (fechajornada), suma lo cobrado en Waitry sin facturar (candidatos a facturación) e incluye impagos Waitry solo como referencia.',
            'El efectivo registrado en Waitry (cash) no se facturará; queda en fila aparte.',
            'Las facturas cobradas con TOTEM en Anita generan asiento puente (Debe medio real / Haber TOTEM); el QR de esas facturas entra en el cupo de redistribución a efectivo.',
            'El porcentaje se aplica sobre el total facturado Anita de la jornada y define cuánto mover de QR a efectivo (y viceversa en facturas ya emitidas).',
            'Revise el preview de asientos antes de confirmar la facturación masiva (próximo paso).',
        ];
    }
}
