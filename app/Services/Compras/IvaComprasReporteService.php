<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\IvaCompras\IvaComprasColumnasSupport;
use App\Support\Compras\IvaCompras\IvaComprasDesgloseSupport;
use App\Support\Compras\IvaComprasListadoFiltros;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasAnitaArmadoSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;

final class IvaComprasReporteService
{
    public function __construct(
        private readonly IvaComprasConciliacionContableService $conciliacionContableService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generarDesdeFiltros(array $filtros): array
    {
        $comprobantes = $this->queryComprobantes($filtros)->get();
        $filas = [];
        $totalesGeneral = IvaComprasColumnasSupport::montosVacios();
        $monedaReporteId = (int) ($filtros['moneda_id'] ?? 1);
        $soloMonedaOrigen = ! empty($filtros['solo_moneda_origen']);
        $excluidasTipo = 0;
        $excluidasSubdiario = 0;
        $excluidasMoneda = 0;
        $excluidasSignoNulo = 0;

        foreach ($comprobantes as $cp) {
            $motivo = $this->motivoExclusion($cp, $filtros);
            if ($motivo !== null) {
                match ($motivo) {
                    'tipo' => $excluidasTipo++,
                    'subdiario' => $excluidasSubdiario++,
                    'signo' => $excluidasSignoNulo++,
                    default => null,
                };
                continue;
            }

            $coef = IvaComprasDesgloseSupport::coeficienteMoneda($cp, $monedaReporteId, $soloMonedaOrigen);
            if ($coef === null) {
                $excluidasMoneda++;
                continue;
            }

            $desglose = IvaComprasDesgloseSupport::desgloseDesdeComprobante($cp, $coef);
            $columnas = $desglose['columnas'];
            $tipo = strtoupper(trim((string) ($cp->tipotransaccion_compras->abreviatura ?? '')));
            $proveedor = $cp->proveedores;
            $fechaIva = $cp->fechaiva?->format('Y-m-d') ?? '';
            $fechaMov = $cp->fechacomprobante?->format('Y-m-d') ?? $fechaIva;

            $fila = [
                'tipo_fila' => 'detalle',
                'proveedor_id' => (int) ($cp->proveedor_id ?? 0),
                'proveedor_codigo' => (string) ($proveedor->codigo ?? ''),
                'proveedor_nombre' => trim((string) ($proveedor->nombre ?? $cp->proveedor_nombre_eventual ?? '')),
                'cuit' => $this->cuitProveedor($cp),
                'fecha_mov' => $fechaMov !== '' ? date('d/m/Y', strtotime($fechaMov)) : '',
                'fecha_iva' => $fechaIva !== '' ? date('d/m/Y', strtotime($fechaIva)) : '',
                'fecha_orden' => $fechaIva !== '' ? $fechaIva : $fechaMov,
                'tipo' => $tipo,
                'tipo_orden' => $tipo,
                'tipotransaccion_compra_id' => (int) ($cp->tipotransaccion_compra_id ?? 0),
                'comprobante' => $this->formatearComprobante($cp),
                'letra' => strtoupper(trim((string) ($cp->letra ?? ''))),
                'sucursal' => (int) ($cp->sucursal ?? 0),
                'numerocomprobante' => (int) ($cp->numerocomprobante ?? 0),
                'nro_interno' => (int) ($cp->anita_nro_interno ?? 0),
                'cae' => (string) ($cp->numerocae ?? ''),
                'columnas' => $columnas,
                'rubros' => $desglose['rubros'],
                'comprobante_proveedor_id' => (int) $cp->id,
                'asiento_id' => (int) ($cp->asiento_id ?? 0),
                'es_fce' => (bool) ($cp->es_fce ?? false),
                'moneda_codigo' => (string) ($cp->monedas->abreviatura ?? $cp->monedas->codigo ?? ''),
                'cotizacion' => (float) ($cp->cotizacion ?? 1),
                'nombreempresa' => (string) ($cp->empresas->nombre ?? ''),
            ];

            $filas[] = $fila;
            IvaComprasColumnasSupport::acumular($totalesGeneral, $columnas);
        }

        $filas = $this->ordenarFilas($filas, $filtros);

        foreach ($totalesGeneral as $k => $v) {
            $totalesGeneral[$k] = round($v, 2);
        }

        $resultado = [
            'titulo' => 'IVA COMPRAS',
            'columnas' => IvaComprasColumnasSupport::columnas(),
            'filas' => $filas,
            'filas_display' => $filas,
            'totales_general' => $totalesGeneral,
            'stats' => [
                'comprobantes' => count($filas),
                'excluidas_tipo' => $excluidasTipo,
                'excluidas_subdiario' => $excluidasSubdiario,
                'excluidas_moneda' => $excluidasMoneda,
                'excluidas_signo' => $excluidasSignoNulo,
                'periodo' => $comprobantes->count(),
            ],
        ];

        $resultado['conciliacion_contable'] = ! empty($filtros['conciliar_contable'])
            ? $this->conciliacionContableService->conciliar($filtros, $resultado)
            : ['habilitada' => false];

        return $resultado;
    }

    /**
     * @param  list<array<string, mixed>>|iterable  $filas
     */
    public function paginarFilas(iterable $filas, int $perPage, int $page): LengthAwarePaginator
    {
        $items = is_array($filas) ? $filas : iterator_to_array($filas);
        $total = count($items);
        $offset = max(0, ($page - 1) * $perPage);

        return new PaginatorImpl(
            array_slice($items, $offset, $perPage),
            $total,
            $perPage,
            $page,
            ['path' => PaginatorImpl::resolveCurrentPath()],
        );
    }

    private function queryComprobantes(array $filtros): Builder
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');

        $query = LibroIvaDigitalComprasAnitaArmadoSupport::restringirQueryTiposInformables(
            Comprobante_Proveedor::query()
                ->with([
                    'proveedores:id,codigo,nombre,nroinscripcion',
                    'tipotransaccion_compras:id,abreviatura,nombre,signo,subdiario,codigoafip',
                    'monedas:id,codigo,abreviatura,nombre',
                    'empresas:id,nombre',
                    'comprobante_proveedor_conceptos.concepto_ivacompras:id,codigo,nombre,tipoconcepto,columna_ivacompra_id,impuesto_id',
                ])
                ->where('comprobante_proveedor.empresa_id', $empresaId)
                ->whereBetween('comprobante_proveedor.fechaiva', [$desde, $hasta])
                ->where('comprobante_proveedor.estado', '<>', ComprobanteProveedorEstados::ANULADO)
        );

        $subdiario = (string) ($filtros['subdiario'] ?? IvaComprasListadoFiltros::SUBDIARIO_TODOS);
        if ($subdiario === IvaComprasListadoFiltros::SUBDIARIO_COMPRAS) {
            $query->whereHas('tipotransaccion_compras', static function (Builder $q): void {
                $q->where('subdiario', 'C');
            });
        } elseif ($subdiario === IvaComprasListadoFiltros::SUBDIARIO_FCE) {
            $query->where('comprobante_proveedor.es_fce', true);
        }

        return $query;
    }

    private function motivoExclusion(Comprobante_Proveedor $cp, array $filtros): ?string
    {
        $tipo = $cp->tipotransaccion_compras;
        $letra = strtoupper(trim((string) ($cp->letra ?? 'A')));

        if (! LibroIvaDigitalComprasAnitaArmadoSupport::esTipoInformableIvaCompras($tipo, $letra)) {
            return 'tipo';
        }

        $signo = (int) ($tipo?->getRawOriginal('signo') ?? 1);
        if ($signo === 0) {
            return 'signo';
        }

        $subdiario = (string) ($filtros['subdiario'] ?? IvaComprasListadoFiltros::SUBDIARIO_TODOS);
        if ($subdiario === IvaComprasListadoFiltros::SUBDIARIO_COMPRAS) {
            $sub = strtoupper(trim((string) ($tipo?->getRawOriginal('subdiario') ?? $tipo?->subdiario ?? '')));
            if ($sub !== 'C') {
                return 'subdiario';
            }
        }
        if ($subdiario === IvaComprasListadoFiltros::SUBDIARIO_FCE && ! $cp->es_fce) {
            return 'subdiario';
        }

        return null;
    }

    private function formatearComprobante(Comprobante_Proveedor $cp): string
    {
        $letra = strtoupper(trim((string) ($cp->letra ?? '')));
        $suc = str_pad((string) ((int) $cp->sucursal), 4, '0', STR_PAD_LEFT);
        $nro = str_pad((string) ((int) $cp->numerocomprobante), 8, '0', STR_PAD_LEFT);

        return $letra.$suc.'-'.$nro;
    }

    private function cuitProveedor(Comprobante_Proveedor $cp): string
    {
        $cuit = preg_replace('/\D+/', '', (string) ($cp->proveedores->nroinscripcion
            ?? $cp->identificacion_proveedor_cuit
            ?? $cp->proveedor_documento_eventual
            ?? '')) ?? '';

        if (strlen($cuit) === 11) {
            return substr($cuit, 0, 2).'-'.substr($cuit, 2, 8).'-'.substr($cuit, 10, 1);
        }

        return $cuit;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function ordenarFilas(array $filas, array $filtros): array
    {
        $orden = (string) ($filtros['orden'] ?? IvaComprasListadoFiltros::ORDEN_FECHA_IVA);

        usort($filas, static function (array $a, array $b) use ($orden): int {
            $cmp = match ($orden) {
                IvaComprasListadoFiltros::ORDEN_PROVEEDOR => strcasecmp(
                    (string) ($a['proveedor_codigo'] ?? ''),
                    (string) ($b['proveedor_codigo'] ?? ''),
                ),
                IvaComprasListadoFiltros::ORDEN_TIPO => strcasecmp(
                    (string) ($a['tipo_orden'] ?? ''),
                    (string) ($b['tipo_orden'] ?? ''),
                ),
                IvaComprasListadoFiltros::ORDEN_FECHA_COMP => strcmp(
                    (string) ($a['fecha_mov'] ?? ''),
                    (string) ($b['fecha_mov'] ?? ''),
                ),
                default => strcmp(
                    (string) ($a['fecha_orden'] ?? ''),
                    (string) ($b['fecha_orden'] ?? ''),
                ),
            };
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['comprobante'] ?? ''), (string) ($b['comprobante'] ?? ''));
        });

        return $filas;
    }
}
