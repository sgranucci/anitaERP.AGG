<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Support\Ventas\IvaVentas\IvaVentasAuditoriaCorrelatividadSupport;
use App\Support\Ventas\IvaVentas\IvaVentasColumnasSupport;
use App\Support\Ventas\IvaVentas\IvaVentasDesgloseSupport;
use App\Support\Ventas\IvaVentas\IvaVentasUnidadNegocioSupport;
use App\Support\Ventas\IvaVentasListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;
use Illuminate\Support\Collection;

final class IvaVentasReporteService
{
    public function __construct(
        private readonly IvaVentasConciliacionContableService $conciliacionContableService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   titulo: string,
     *   columnas: list<array{key: string, label: string}>,
     *   filas: list<array<string, mixed>>,
     *   totales_por_puntoventa: list<array<string, mixed>>,
     *   totales_general: array<string, float>,
     *   stats: array<string, int|float>
     * }
     */
    public function generarDesdeFiltros(array $filtros): array
    {
        $ventas = $this->queryVentas($filtros)->get();
        $filas = [];
        $totalesPorPv = [];
        $totalesGeneral = IvaVentasColumnasSupport::montosVacios();
        $monedaReporteId = (int) ($filtros['moneda_id'] ?? 1);
        $soloMonedaOrigen = ! empty($filtros['solo_moneda_origen']);
        $clasificarHost = ! empty($filtros['clasificar_por_host']);
        $vendingPvIds = IvaVentasUnidadNegocioSupport::vendingPuntoventaIds((int) ($filtros['empresa_id'] ?? 0));
        $excluidasPre = 0;
        $excluidasSubdiario = 0;
        $excluidasMoneda = 0;

        foreach ($ventas as $venta) {
            $motivoExclusion = $this->motivoExclusionNegocio($venta, $filtros);
            if ($motivoExclusion !== null) {
                if ($motivoExclusion === 'pre') {
                    $excluidasPre++;
                } elseif ($motivoExclusion === 'subdiario') {
                    $excluidasSubdiario++;
                }
                continue;
            }

            $coef = $this->coeficienteMoneda($venta, $monedaReporteId, $soloMonedaOrigen);
            if ($coef === null) {
                $excluidasMoneda++;
                continue;
            }

            $columnas = IvaVentasDesgloseSupport::columnasDesdeVenta($venta, $coef);
            $seccion = $this->seccionVenta($venta);
            $host = $this->hostVenta($venta);
            $unidadNegocio = IvaVentasUnidadNegocioSupport::clasificar($venta, $vendingPvIds);
            $pv = $venta->puntoventas;
            $pvId = (int) ($venta->puntoventa_id ?? 0);
            $pvCodigo = (string) ($pv->codigo ?? '');
            $pvNombre = trim((string) ($pv->nombre ?? $pvCodigo));
            $sucursal = $this->sucursalDesdeCodigoPuntoventa($pvCodigo);
            $ordenFecha = $this->fechaOrden($venta, $filtros);
            $tipo = strtoupper(trim((string) ($venta->tipotransacciones->abreviatura ?? '')));
            if ($tipo === '') {
                $tipo = $this->tipoDesdeCodigo((string) $venta->codigo);
            }

            $fila = [
                'tipo_fila' => 'detalle',
                'seccion' => $seccion,
                'seccion_label' => $seccion === 'administracion' ? 'Facturas de administración' : 'Operación',
                'host' => $host,
                'unidad_negocio' => $unidadNegocio,
                'unidad_negocio_label' => IvaVentasUnidadNegocioSupport::label($unidadNegocio),
                'puntoventa_id' => $pvId,
                'puntoventa_codigo' => $pvCodigo,
                'puntoventa_nombre' => $pvNombre,
                'sucursal' => $sucursal,
                'cliente_codigo' => $this->clienteCodigo($venta),
                'cliente_nombre' => $this->clienteNombre($venta),
                'cliente_id' => (int) ($venta->cliente_id ?? 0),
                'cuit' => $this->cuitCliente($venta),
                'fecha_mov' => date('d/m/Y', strtotime((string) $venta->fecha)),
                'fecha_orden' => $ordenFecha,
                'tipo' => $tipo,
                'tipo_orden' => $tipo,
                'tipotransaccion_id' => (int) ($venta->tipotransaccion_id ?? 0),
                'comprobante' => $this->formatearComprobante($venta),
                'numerocomprobante' => (int) $venta->numerocomprobante,
                'letra' => IvaVentasDesgloseSupport::letra($venta),
                'columnas' => $columnas,
                'venta_id' => (int) $venta->id,
                'anulada' => IvaVentasDesgloseSupport::esAnulada($venta),
                'nombreempresa' => (string) ($pv->empresas->nombre ?? ''),
            ];

            $filas[] = $fila;

            $clavePv = $seccion.'|'.$pvId;
            if (! isset($totalesPorPv[$clavePv])) {
                $totalesPorPv[$clavePv] = [
                    'seccion' => $seccion,
                    'seccion_label' => $fila['seccion_label'],
                    'puntoventa_id' => $pvId,
                    'puntoventa_codigo' => $pvCodigo,
                    'puntoventa_nombre' => $pvNombre,
                    'sucursal' => $sucursal,
                    'cantidad' => 0,
                    'columnas' => IvaVentasColumnasSupport::montosVacios(),
                ];
            }
            $totalesPorPv[$clavePv]['cantidad']++;
            IvaVentasColumnasSupport::acumular($totalesPorPv[$clavePv]['columnas'], $columnas);
            IvaVentasColumnasSupport::acumular($totalesGeneral, $columnas);
        }

        $filas = $this->ordenarFilas($filas, $filtros, $clasificarHost);
        $totalesPorPvLista = $this->ordenarTotalesPv(array_values($totalesPorPv));

        // Vista del listado: opcionalmente colapsa las Facturas B en un resumen por día + PV + tipo.
        // El detalle completo ($filas) se conserva para conciliación y auditoría de correlatividad.
        $filasDisplay = ! empty($filtros['agrupar_b_por_dia'])
            ? $this->agruparFacturasBPorDia($filas, $filtros, $clasificarHost)
            : $filas;

        foreach ($totalesGeneral as $k => $v) {
            $totalesGeneral[$k] = round($v, 2);
        }
        foreach ($totalesPorPvLista as &$totPv) {
            foreach ($totPv['columnas'] as $k => $v) {
                $totPv['columnas'][$k] = round((float) $v, 2);
            }
        }
        unset($totPv);

        return [
            'titulo' => 'IVA VENTAS',
            'columnas' => IvaVentasColumnasSupport::COLUMNAS,
            'filas' => $filas,
            'filas_display' => $filasDisplay,
            'agrupado_b_por_dia' => ! empty($filtros['agrupar_b_por_dia']),
            'totales_por_puntoventa' => $totalesPorPvLista,
            'totales_general' => $totalesGeneral,
            'stats' => [
                'ventas' => count($filas),
                'puntoventa' => count($totalesPorPvLista),
                'excluidas_pre' => $excluidasPre,
                'excluidas_subdiario' => $excluidasSubdiario,
                'excluidas_moneda' => $excluidasMoneda,
                'ventas_periodo' => $ventas->count(),
            ],
            'conciliacion_contable' => ! empty($filtros['conciliar_contable'])
                ? $this->conciliacionContableService->conciliar($filtros, [
                    'totales_general' => $totalesGeneral,
                    'totales_por_puntoventa' => $totalesPorPvLista,
                    'filas' => $filas,
                ])
                : ['habilitada' => false],
            'auditoria_correlatividad' => IvaVentasAuditoriaCorrelatividadSupport::auditar($filas, $filtros),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginarFilas(array $filas, int $perPage, int $page = 1): LengthAwarePaginator
    {
        $perPage = max(10, min(500, $perPage));
        $page = max(1, $page);
        $total = count($filas);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($filas, $offset, $perPage);

        return new PaginatorImpl(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => PaginatorImpl::resolveCurrentPath()],
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryVentas(array $filtros): Builder
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $campoFecha = ($filtros['orden_fecha'] ?? IvaVentasListadoFiltros::ORDEN_FECHA_JORNADA) === IvaVentasListadoFiltros::ORDEN_FECHA
            ? 'fecha'
            : 'fechajornada';

        return Venta::query()
            ->whereHas('puntoventas', fn (Builder $q) => $q->where('empresa_id', $empresaId))
            ->whereDate($campoFecha, '>=', $filtros['fecha_desde'])
            ->whereDate($campoFecha, '<=', $filtros['fecha_hasta'])
            ->with([
                'venta_impuestos',
                'tipotransacciones',
                'puntoventas.empresas',
                'clientes',
                'monedas',
                'gastronomiaEmision.configuracionPuntoventa',
                'estacionamientoEmision.configuracionPuntoventa',
            ])
            ->orderBy($campoFecha)
            ->orderBy('puntoventa_id')
            ->orderBy('tipotransaccion_id')
            ->orderBy('numerocomprobante');
    }

    private function motivoExclusionNegocio(Venta $venta, array $filtros): ?string
    {
        $abrev = strtoupper(trim((string) ($venta->tipotransacciones->abreviatura ?? '')));
        if ($abrev === 'PRE') {
            return 'pre';
        }

        $letra = IvaVentasDesgloseSupport::letra($venta);
        $subdiario = (string) ($filtros['subdiario'] ?? IvaVentasListadoFiltros::SUBDIARIO_VENTAS_A_B);
        if (! IvaVentasListadoFiltros::pasaSubdiario($letra, $subdiario)) {
            return 'subdiario';
        }

        return null;
    }

    private function coeficienteMoneda(Venta $venta, int $monedaReporteId, bool $soloMonedaOrigen): ?float
    {
        $monedaVentaId = (int) ($venta->moneda_id ?? 0);
        if ($soloMonedaOrigen && $monedaVentaId !== $monedaReporteId) {
            $cotiz = (float) ($venta->cotizacion ?? 0);

            return $cotiz > 0.01 ? $cotiz : null;
        }

        if ($monedaVentaId === $monedaReporteId) {
            return 1.0;
        }

        $cotiz = (float) ($venta->cotizacion ?? 0);

        return $cotiz > 0.01 ? $cotiz : 1.0;
    }

    private function seccionVenta(Venta $venta): string
    {
        $modo = strtoupper(trim((string) ($venta->puntoventas->modofacturacion ?? '')));
        if ($modo === 'M') {
            return 'administracion';
        }

        $venta->loadMissing(['gastronomiaEmision', 'estacionamientoEmision']);

        if ($venta->gastronomiaEmision !== null || $venta->estacionamientoEmision !== null) {
            return 'operacion';
        }

        return 'administracion';
    }

    private function hostVenta(Venta $venta): string
    {
        $emision = $venta->gastronomiaEmision;
        if ($emision !== null) {
            $pc = trim((string) ($emision->identificador_pc ?? ''));
            if ($pc !== '') {
                return $pc;
            }
            $cfg = $emision->configuracionPuntoventa;
            if ($cfg !== null) {
                $pcCfg = trim((string) ($cfg->identificador_pc ?? ''));
                if ($pcCfg !== '') {
                    return $pcCfg;
                }
                $desc = trim((string) ($cfg->descripcion ?? ''));

                return $desc !== '' ? $desc : '—';
            }
        }

        $emisionEst = $venta->estacionamientoEmision;
        if ($emisionEst !== null) {
            $pc = trim((string) ($emisionEst->identificador_pc ?? ''));
            if ($pc !== '') {
                return $pc;
            }
            $cfg = $emisionEst->configuracionPuntoventa;
            if ($cfg !== null) {
                $pcCfg = trim((string) ($cfg->identificador_pc ?? ''));
                if ($pcCfg !== '') {
                    return $pcCfg;
                }
                $desc = trim((string) ($cfg->descripcion ?? ''));

                return $desc !== '' ? $desc : 'Estacionamiento';
            }

            return 'Estacionamiento';
        }

        return 'Administración';
    }

    private function fechaOrden(Venta $venta, array $filtros): string
    {
        $campo = ($filtros['orden_fecha'] ?? IvaVentasListadoFiltros::ORDEN_FECHA_JORNADA) === IvaVentasListadoFiltros::ORDEN_FECHA
            ? (string) $venta->fecha
            : (string) ($venta->fechajornada ?? $venta->fecha);

        return $campo;
    }

    private function clienteCodigo(Venta $venta): string
    {
        $codigo = trim((string) ($venta->clientes->codigo ?? ''));
        if ($codigo !== '') {
            return str_pad($codigo, 6, '0', STR_PAD_LEFT);
        }

        $id = (int) ($venta->cliente_id ?? 0);

        return $id > 0 ? str_pad((string) $id, 6, '0', STR_PAD_LEFT) : '000000';
    }

    private function clienteNombre(Venta $venta): string
    {
        $nombre = trim((string) $venta->nombre);
        if ($nombre !== '') {
            return $nombre;
        }

        return trim((string) ($venta->clientes->nombre ?? ''));
    }

    private function cuitCliente(Venta $venta): string
    {
        $cuit = trim((string) ($venta->nroinscripcion ?? ''));
        if ($cuit !== '') {
            return $cuit;
        }

        return trim((string) ($venta->clientes->numerodocumento ?? ''));
    }

    private function formatearComprobante(Venta $venta): string
    {
        $codigo = trim((string) $venta->codigo);
        if ($codigo !== '') {
            if (preg_match('/^[A-Z]{3}\s+([A-Z])-(\d+)-(\d+)/i', $codigo, $m)) {
                return sprintf('%s%04d-%08d', strtoupper($m[1]), (int) $m[2], (int) $m[3]);
            }

            return $codigo;
        }

        $letra = IvaVentasDesgloseSupport::letra($venta);
        $pv = (string) ($venta->puntoventas->codigo ?? '0');
        $sucursal = $this->sucursalDesdeCodigoPuntoventa($pv);
        $nro = (int) $venta->numerocomprobante;

        return sprintf('%s%04d-%08d', $letra, $sucursal, $nro);
    }

    private function tipoDesdeCodigo(string $codigo): string
    {
        if (preg_match('/^([A-Z]{2,4})\s/i', $codigo, $m)) {
            return strtoupper($m[1]);
        }

        return '';
    }

    private function sucursalDesdeCodigoPuntoventa(string $codigo): int
    {
        return (int) preg_replace('/\D+/', '', trim($codigo));
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function ordenarFilas(array $filas, array $filtros, bool $clasificarHost): array
    {
        usort($filas, function (array $a, array $b) use ($clasificarHost): int {
            $secOrder = ['operacion' => 0, 'administracion' => 1];
            $sa = $secOrder[$a['seccion'] ?? ''] ?? 9;
            $sb = $secOrder[$b['seccion'] ?? ''] ?? 9;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }

            if ($clasificarHost) {
                $ha = (string) ($a['host'] ?? '');
                $hb = (string) ($b['host'] ?? '');
                if ($ha !== $hb) {
                    return strcmp($ha, $hb);
                }
            }

            $fa = (string) ($a['fecha_orden'] ?? '');
            $fb = (string) ($b['fecha_orden'] ?? '');
            if ($fa !== $fb) {
                return strcmp($fa, $fb);
            }

            $ta = (string) ($a['tipo_orden'] ?? '');
            $tb = (string) ($b['tipo_orden'] ?? '');
            if ($ta !== $tb) {
                return strcmp($ta, $tb);
            }

            return ((int) ($a['venta_id'] ?? 0)) <=> ((int) ($b['venta_id'] ?? 0));
        });

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $totales
     * @return list<array<string, mixed>>
     */
    private function ordenarTotalesPv(array $totales): array
    {
        usort($totales, function (array $a, array $b): int {
            $secOrder = ['operacion' => 0, 'administracion' => 1];
            $sa = $secOrder[$a['seccion'] ?? ''] ?? 9;
            $sb = $secOrder[$b['seccion'] ?? ''] ?? 9;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }

            return strcmp((string) ($a['puntoventa_codigo'] ?? ''), (string) ($b['puntoventa_codigo'] ?? ''));
        });

        return $totales;
    }

    /**
     * Colapsa las Facturas B (letra B) en un resumen por día + punto de venta + tipo de comprobante.
     * Cada grupo muestra el rango "desde comprobante a comprobante" con los totales acumulados.
     * Las demás filas (A, C, notas de crédito de otras letras, etc.) se dejan sin agrupar.
     *
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function agruparFacturasBPorDia(array $filas, array $filtros, bool $clasificarHost): array
    {
        $grupos = [];
        $otras = [];

        foreach ($filas as $fila) {
            if (strtoupper(trim((string) ($fila['letra'] ?? ''))) !== 'B') {
                $otras[] = $fila;

                continue;
            }

            $clave = implode('|', [
                (string) ($fila['seccion'] ?? ''),
                (int) ($fila['puntoventa_id'] ?? 0),
                (string) ($fila['fecha_orden'] ?? ''),
                (int) ($fila['tipotransaccion_id'] ?? 0),
                $clasificarHost ? (string) ($fila['host'] ?? '') : '',
            ]);

            if (! isset($grupos[$clave])) {
                $grupos[$clave] = [
                    'base' => $fila,
                    'columnas' => IvaVentasColumnasSupport::montosVacios(),
                    'cantidad' => 0,
                    'nro_min' => null,
                    'nro_max' => null,
                    'comprobante_min' => '',
                    'comprobante_max' => '',
                ];
            }

            $grupo = &$grupos[$clave];
            IvaVentasColumnasSupport::acumular($grupo['columnas'], $fila['columnas'] ?? []);
            $grupo['cantidad']++;

            $nro = (int) ($fila['numerocomprobante'] ?? 0);
            $comprobante = (string) ($fila['comprobante'] ?? '');
            if ($grupo['nro_min'] === null || $nro < $grupo['nro_min']) {
                $grupo['nro_min'] = $nro;
                $grupo['comprobante_min'] = $comprobante;
            }
            if ($grupo['nro_max'] === null || $nro > $grupo['nro_max']) {
                $grupo['nro_max'] = $nro;
                $grupo['comprobante_max'] = $comprobante;
            }
            unset($grupo);
        }

        $resumenes = [];
        foreach ($grupos as $grupo) {
            $base = $grupo['base'];
            $columnas = $grupo['columnas'];
            foreach ($columnas as $k => $v) {
                $columnas[$k] = round((float) $v, 2);
            }

            $rango = (string) $grupo['comprobante_min'];
            if ($grupo['comprobante_max'] !== '' && $grupo['comprobante_max'] !== $grupo['comprobante_min']) {
                $rango .= ' a '.$grupo['comprobante_max'];
            }

            $resumenes[] = array_merge($base, [
                'tipo_fila' => 'resumen_b',
                'cliente_codigo' => '',
                'cliente_nombre' => 'Consumidor final ('.(int) $grupo['cantidad'].' comprobantes)',
                'cliente_id' => 0,
                'cuit' => '',
                'comprobante' => $rango,
                'numerocomprobante' => (int) ($grupo['nro_min'] ?? 0),
                'columnas' => $columnas,
                'venta_id' => 0,
                'anulada' => false,
                'cantidad_comprobantes' => (int) $grupo['cantidad'],
            ]);
        }

        return $this->ordenarFilas(array_merge($otras, $resumenes), $filtros, $clasificarHost);
    }
}
