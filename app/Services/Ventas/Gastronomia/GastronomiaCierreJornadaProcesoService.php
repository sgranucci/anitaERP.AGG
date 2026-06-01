<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\JornadaGastronomia;
use Carbon\Carbon;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosPreviewSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoClasificacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoConfigSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoRedistribucionSupport;
use App\Support\Ventas\GastronomiaCuentacajaTotem;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use InvalidArgumentException;

/**
 * Proceso de cierre de jornada gastronomía: conciliación Waitry, redistribución QR/efectivo y preview de asientos.
 */
final class GastronomiaCierreJornadaProcesoService
{
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

        $clasificacion = CierreJornadaProcesoClasificacionSupport::clasificar($lineas, $empresaId);
        $config = CierreJornadaProcesoConfigSupport::paraEmpresa($empresaId);

        return [
            'ok' => true,
            'jornada' => $this->resumenJornada($jornada),
            'meta' => $cargado['meta'],
            'grilla' => $clasificacion['grilla'],
            'total_facturacion' => $clasificacion['total_facturacion'],
            'conteos' => $clasificacion['conteos'],
            'grupos_resumen' => $this->resumenGrupos($clasificacion['grupos']),
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
        $clasificacionBase = CierreJornadaProcesoClasificacionSupport::clasificar($lineas, $empresaId);

        $redistribucion = CierreJornadaProcesoRedistribucionSupport::aplicar(
            $clasificacionBase['movimientos'],
            $clasificacionBase['total_facturacion'],
            $porcentaje,
        );

        $empresaId = (int) $jornada->empresa_id;
        $clasificacion = CierreJornadaProcesoClasificacionSupport::clasificar(
            $redistribucion['movimientos'],
            $empresaId,
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

        $cargado = $this->cierreTotemJornadaService->movimientosParaJornada($jornada);
        $lineas = $this->enriquecerLineasConCobranzaAnita($cargado['lineas'], $empresaId);
        $clasificacion = CierreJornadaProcesoClasificacionSupport::clasificar($lineas, $empresaId);
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
            ->with(['venta.cobranzasDirectas', 'venta.caja_movimientos.cobranzas'])
            ->whereIn('waitry_order_id', array_unique($waitryIds))
            ->whereHas('venta', fn ($q) => $q->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId)))
            ->orderByDesc('venta_id')
            ->get()
            ->unique('waitry_order_id')
            ->keyBy(fn (VentaGastronomiaEmision $e) => (int) $e->waitry_order_id);

        $out = [];
        foreach ($lineas as $ln) {
            $wid = (int) ($ln['waitry_order_id'] ?? 0);
            $emision = $emisiones->get($wid);
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
     * @param  array<string, list<array<string, mixed>>>  $grupos
     * @return list<array<string, mixed>>
     */
    private function resumenGrupos(array $grupos): array
    {
        $mapa = [
            CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_MEDIO_REAL => 'Facturados — medio de pago real en Anita',
            CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_TOTEM => 'Facturados — cobro TOTEM (medio real Waitry)',
            CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR => 'Sin facturar — QR (Totalcoin)',
            CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_OTRO => 'Sin facturar — otros medios',
            CierreJornadaProcesoClasificacionSupport::GRUPO_WAITRY_CASH_NO_FACTURAR => 'Waitry efectivo — no se factura',
            CierreJornadaProcesoClasificacionSupport::GRUPO_HUECO_AUDITORIA => 'Huecos de secuencia (auditoría)',
        ];

        $out = [];
        foreach ($mapa as $clave => $titulo) {
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
            'Las facturas cobradas con TOTEM en Anita implican un medio de pago real en Waitry (MP, QR, etc.).',
            'El efectivo registrado en Waitry (cash) no se facturará en este proceso; queda solo como referencia.',
            'El porcentaje se aplica sobre el total facturado de la jornada y define el importe a mover de QR a efectivo (y viceversa en facturas ya emitidas).',
            'Revise el preview de asientos antes de confirmar la facturación masiva (próximo paso).',
        ];
    }
}
