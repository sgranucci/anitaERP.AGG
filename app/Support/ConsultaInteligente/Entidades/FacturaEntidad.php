<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Services\Ventas\FacturacionService;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoQbeSupport;
use App\Support\Ventas\FacturaListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class FacturaEntidad implements EntidadSemantica
{
    /** @var array<string, array{label: string, type: string, column: string}> */
    private const CAMPOS_QBE = [
        'id' => ['label' => 'ID', 'type' => 'entero', 'column' => 'venta.id'],
        'numerocomprobante' => ['label' => 'Nº comprobante', 'type' => 'texto', 'column' => 'venta.numerocomprobante'],
        'fecha' => ['label' => 'Fecha', 'type' => 'fecha', 'column' => 'venta.fecha'],
        'cliente' => ['label' => 'Cliente', 'type' => 'texto', 'column' => 'cliente.nombre'],
        'total' => ['label' => 'Total', 'type' => 'decimal', 'column' => 'venta.total'],
    ];

    public function clave(): string
    {
        return 'factura';
    }

    public function nombre(): string
    {
        return 'Facturas de venta';
    }

    public function descripcion(): string
    {
        return 'Comprobantes de venta: cliente, tipo, total, fechas y reparto.';
    }

    public function icono(): string
    {
        return 'fa-file-invoice-dollar';
    }

    public function modulo(): string
    {
        return 'Ventas';
    }

    public function color(): string
    {
        return '#1A5276';
    }

    public function permisoListar(): string
    {
        return 'listar-factura';
    }

    public function rutaNativa(): string
    {
        return 'factura';
    }

    public function recurso(): string
    {
        return 'ventas.factura';
    }

    public function camposQbe(): array
    {
        return self::CAMPOS_QBE;
    }

    public function columnasVisibles(): array
    {
        return ['id', 'fecha', 'numerocomprobante', 'nombrecliente', 'nombretipo', 'total', 'nombreempresa'];
    }

    public function etiquetasColumnas(): array
    {
        return [
            'id' => 'ID',
            'fecha' => 'Fecha',
            'numerocomprobante' => 'Comprobante',
            'nombrecliente' => 'Cliente',
            'nombretipo' => 'Tipo',
            'total' => 'Total',
            'nombreempresa' => 'Empresa',
        ];
    }

    public function valorCelda(object $row, string $key): string
    {
        if ($key === 'nombrecliente') {
            return e((string) (data_get($row, 'clientes.nombre') ?? $row->nombrecliente ?? ''));
        }
        if ($key === 'nombretipo') {
            return e((string) (data_get($row, 'tipotransacciones.nombre') ?? $row->nombretipo ?? ''));
        }
        if ($key === 'nombreempresa') {
            return e((string) (data_get($row, 'puntoventas.empresas.nombre') ?? $row->nombreempresa ?? ''));
        }
        if ($key === 'numerocomprobante') {
            $pv = (string) (data_get($row, 'puntoventas.codigo') ?? '');
            $nro = (string) ($row->numerocomprobante ?? '');

            return e($pv !== '' ? $pv.'-'.$nro : $nro);
        }
        $v = $row->{$key} ?? '';
        if ($key === 'fecha' && $v) {
            return e(date('d/m/Y', strtotime((string) $v)));
        }
        if ($key === 'total' && $v !== '' && $v !== null) {
            return e(number_format((float) $v, 2, ',', '.'));
        }

        return e((string) $v);
    }

    public function resolverFiltros(Request $request): array
    {
        // En CI, sin chip de empresa → todas las asignadas (no forzar empresa 1 del index nativo).
        if (! $request->filled('empresa_id') && ! $request->boolean('empresa_todas') && ! $request->filled('empresa_scope')) {
            $request->merge(['empresa_todas' => 1]);
        }

        $f = FacturaListadoFiltros::resolverDesdeRequest($request);
        $qbe = ListadoQbeSupport::resolverDesdeRequest(
            $request,
            $this->camposQbe(),
            static fn (string $op, string $campo): string => $op
        );
        if (ListadoQbeSupport::tieneCriterios($qbe)) {
            $f['qbe'] = $qbe;
            $f['filtro_modo'] = 'qbe';
            $this->aplicarQbeSobreFiltrosFactura($f, $qbe);
        }
        $f['filtro_valor'] = trim((string) ($f['valor'] ?? $f['busqueda'] ?? $request->input('filtro_valor', '')));

        return $f;
    }

    public function paraQueryString(array $filtros): array
    {
        $out = FacturaListadoFiltros::paraQueryString($filtros);
        if (! empty($filtros['qbe']) && is_array($filtros['qbe'])) {
            $out['qbe'] = $filtros['qbe'];
            $out['filtro_modo'] = 'qbe';
        }

        return $out;
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        if (ListadoQbeSupport::tieneCriterios(is_array($filtros['qbe'] ?? null) ? $filtros['qbe'] : [])) {
            $this->aplicarQbeSobreFiltrosFactura($filtros, $filtros['qbe']);
        }

        /** @var FacturacionService $svc */
        $svc = app(FacturacionService::class);

        return $paginar
            ? $svc->leePaginando($filtros)
            : $svc->leeSinPaginar($filtros);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-factura', false)) {
            return null;
        }

        return route('editar_factura', [
            'id' => $id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    }

    public function plantillas(): array
    {
        return [
            [
                'clave' => 'este_mes',
                'titulo' => 'Facturas este mes',
                'descripcion' => 'Comprobantes del mes en curso',
                'query' => [
                    'consultar' => 1,
                    'empresa_todas' => 1,
                    'filtro_modo' => 'qbe',
                    'qbe' => [
                        'entre_grupos' => 'and',
                        'grupos' => [[
                            'logic' => 'and',
                            'not' => 0,
                            'criterios' => [[
                                'campo' => 'fecha',
                                'op' => 'este_mes',
                                'valor' => '',
                                'valor_hasta' => '',
                            ]],
                        ]],
                    ],
                ],
            ],
            [
                'clave' => 'sin_remito',
                'titulo' => 'Sin remito',
                'descripcion' => 'Facturas pendientes de remito',
                'query' => [
                    'consultar' => 1,
                    'empresa_todas' => 1,
                    'solo_sin_remito' => 1,
                ],
            ],
        ];
    }

    /**
     * Traduce QBE (fecha / texto / id) a los filtros nativos del index de facturas.
     *
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $qbe
     */
    private function aplicarQbeSobreFiltrosFactura(array &$filtros, array $qbe): void
    {
        foreach ($qbe['grupos'] ?? [] as $grupo) {
            foreach ($grupo['criterios'] ?? [] as $criterio) {
                if (! is_array($criterio)) {
                    continue;
                }
                $campo = (string) ($criterio['campo'] ?? '');
                $op = (string) ($criterio['op'] ?? '');
                $valor = trim((string) ($criterio['valor'] ?? ''));
                $hasta = trim((string) ($criterio['valor_hasta'] ?? ''));

                if ($campo === 'fecha') {
                    if (isset(ListadoQbeSupport::OPERADORES_PERIODO[$op])) {
                        $rango = ListadoQbeSupport::rangoPeriodo($op);
                        if ($rango !== null) {
                            $filtros['fecha_desde'] = $rango[0];
                            $filtros['fecha_hasta'] = $rango[1];
                        }
                    } elseif ($op === 'entre') {
                        if ($valor !== '') {
                            $filtros['fecha_desde'] = $valor;
                        }
                        if ($hasta !== '') {
                            $filtros['fecha_hasta'] = $hasta;
                        }
                    } elseif ($op === 'desde' && $valor !== '') {
                        $filtros['fecha_desde'] = $valor;
                    } elseif ($op === 'hasta' && $valor !== '') {
                        $filtros['fecha_hasta'] = $valor;
                    } elseif (in_array($op, ['igual', 'es_el_dia'], true) && $valor !== '') {
                        $filtros['fecha_desde'] = $valor;
                        $filtros['fecha_hasta'] = $valor;
                    }
                } elseif ($campo === 'cliente' && $valor !== '') {
                    $filtros['modo'] = FacturaListadoFiltros::MODO_CAMPO;
                    $filtros['campo'] = 'cliente';
                    $filtros['operador'] = $op !== '' ? $op : 'contiene';
                    $filtros['valor'] = $valor;
                    $filtros['busqueda'] = $valor;
                } elseif ($campo === 'numerocomprobante' && $valor !== '') {
                    $filtros['modo'] = FacturaListadoFiltros::MODO_CAMPO;
                    $filtros['campo'] = 'numerocomprobante';
                    $filtros['operador'] = $op !== '' ? $op : 'contiene';
                    $filtros['valor'] = $valor;
                    $filtros['busqueda'] = $valor;
                } elseif ($campo === 'id' && $valor !== '') {
                    $filtros['modo'] = FacturaListadoFiltros::MODO_CAMPO;
                    $filtros['campo'] = 'id';
                    $filtros['operador'] = 'igual';
                    $filtros['valor'] = $valor;
                    $filtros['busqueda'] = $valor;
                }
            }
        }
    }
}
