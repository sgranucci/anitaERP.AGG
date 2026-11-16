<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Services\Stock\MovimientoStockService;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoQbeSupport;
use App\Support\Stock\MovimientoStockListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class MovimientoStockEntidad implements EntidadSemantica
{
    /** @var array<string, array{label: string, type: string, column: string}> */
    private const CAMPOS_QBE_EXTRA = [
        'fecha' => ['label' => 'Fecha', 'type' => 'fecha', 'column' => 'movimientostock.fecha'],
    ];

    public function clave(): string
    {
        return 'movimientostock';
    }

    public function nombre(): string
    {
        return 'Movimientos de stock';
    }

    public function descripcion(): string
    {
        return 'Entradas/salidas de stock: tipo, depósito, empresa y fechas.';
    }

    public function icono(): string
    {
        return 'fa-exchange-alt';
    }

    public function modulo(): string
    {
        return 'Stock';
    }

    public function color(): string
    {
        return '#1B4F72';
    }

    public function permisoListar(): string
    {
        return 'listar-movimientos-de-stock';
    }

    public function rutaNativa(): string
    {
        return 'movimientostock';
    }

    public function recurso(): string
    {
        return 'stock.movimientostock';
    }

    public function camposQbe(): array
    {
        $out = [];
        foreach (MovimientoStockListadoFiltros::CAMPOS as $key => $meta) {
            $out[$key] = [
                'label' => html_entity_decode((string) ($meta['label'] ?? $key), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'type' => $key === 'fecha' ? 'fecha' : ($meta['type'] ?? 'texto'),
                'column' => $meta['column'] ?? $key,
            ];
        }
        $out['fecha'] = self::CAMPOS_QBE_EXTRA['fecha'];

        return $out;
    }

    public function columnasVisibles(): array
    {
        return ['id', 'codigo', 'fecha', 'nombretipo', 'nombredeposito', 'nombreempresa', 'estado'];
    }

    public function etiquetasColumnas(): array
    {
        return [
            'id' => 'ID',
            'codigo' => 'Número',
            'fecha' => 'Fecha',
            'nombretipo' => 'Tipo',
            'nombredeposito' => 'Depósito',
            'nombreempresa' => 'Empresa',
            'estado' => 'Estado',
            'leyenda' => 'Leyenda',
        ];
    }

    public function valorCelda(object $row, string $key): string
    {
        if ($key === 'nombretipo') {
            return e((string) (data_get($row, 'tipotransaccion_stocks.nombre') ?? $row->nombretipo ?? $row->tipo ?? ''));
        }
        if ($key === 'nombredeposito') {
            return e((string) (data_get($row, 'depmaes.nombre') ?? $row->nombredeposito ?? $row->deposito ?? ''));
        }
        if ($key === 'nombreempresa') {
            return e((string) (data_get($row, 'empresas.nombre') ?? $row->nombreempresa ?? ''));
        }
        $v = $row->{$key} ?? '';
        if ($key === 'fecha' && $v) {
            return e(date('d/m/Y', strtotime((string) $v)));
        }

        return e((string) $v);
    }

    public function resolverFiltros(Request $request): array
    {
        if (! $request->filled('empresa_id') && ! $request->boolean('empresa_todas') && ! $request->filled('empresa_scope')) {
            $request->merge(['empresa_todas' => 1]);
        }
        $f = MovimientoStockListadoFiltros::resolverDesdeRequest($request);
        $qbe = ListadoQbeSupport::resolverDesdeRequest(
            $request,
            $this->camposQbe(),
            static fn (string $op, string $campo): string => $op
        );
        if (ListadoQbeSupport::tieneCriterios($qbe)) {
            $f['qbe'] = $qbe;
            $f['filtro_modo'] = 'qbe';
            $this->aplicarQbeSobreFiltros($f, $qbe);
        }
        $f['filtro_valor'] = trim((string) ($f['valor'] ?? $f['busqueda'] ?? $request->input('filtro_valor', '')));

        return $f;
    }

    public function paraQueryString(array $filtros): array
    {
        $out = MovimientoStockListadoFiltros::paraQueryString($filtros);
        if (! empty($filtros['qbe']) && is_array($filtros['qbe'])) {
            $out['qbe'] = $filtros['qbe'];
            $out['filtro_modo'] = 'qbe';
        }

        return $out;
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        if (ListadoQbeSupport::tieneCriterios(is_array($filtros['qbe'] ?? null) ? $filtros['qbe'] : [])) {
            $this->aplicarQbeSobreFiltros($filtros, $filtros['qbe']);
        }

        return app(MovimientoStockService::class)->leeMovimientoStockListado($filtros, $paginar);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-movimientos-de-stock', false)) {
            return null;
        }

        return route('editar_movimientostock', [
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
                'titulo' => 'Movimientos este mes',
                'descripcion' => 'Stock del mes en curso',
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
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $qbe
     */
    private function aplicarQbeSobreFiltros(array &$filtros, array $qbe): void
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
                    // El index nativo trata fecha como texto: usamos contiene/igual sobre Y-m / Y-m-d.
                    $filtros['modo'] = MovimientoStockListadoFiltros::MODO_CAMPO;
                    $filtros['campo'] = 'fecha';
                    if (isset(ListadoQbeSupport::OPERADORES_PERIODO[$op])) {
                        $rango = ListadoQbeSupport::rangoPeriodo($op);
                        if ($rango === null) {
                            continue;
                        }
                        if (in_array($op, ['hoy', 'ayer'], true)) {
                            $filtros['operador'] = 'igual';
                            $filtros['valor'] = $rango[0];
                        } elseif ($op === 'este_anio') {
                            $filtros['operador'] = 'empieza';
                            $filtros['valor'] = substr($rango[0], 0, 4);
                        } else {
                            $filtros['operador'] = 'contiene';
                            $filtros['valor'] = substr($rango[0], 0, 7); // Y-m
                        }
                        $filtros['busqueda'] = $filtros['valor'];
                        $filtros['valor_hasta'] = '';
                    } elseif ($op === 'entre' && $valor !== '') {
                        $filtros['operador'] = 'contiene';
                        $filtros['valor'] = substr($valor, 0, 7);
                        $filtros['busqueda'] = $filtros['valor'];
                    } elseif ($valor !== '') {
                        $filtros['operador'] = $op !== '' ? $op : 'contiene';
                        $filtros['valor'] = $valor;
                        $filtros['busqueda'] = $valor;
                    }
                } elseif ($campo === 'deposito' && $valor !== '') {
                    $filtros['modo'] = MovimientoStockListadoFiltros::MODO_CAMPO;
                    $filtros['campo'] = 'deposito';
                    $filtros['operador'] = $op !== '' ? $op : 'contiene';
                    $filtros['valor'] = $valor;
                    $filtros['busqueda'] = $valor;
                } elseif ($campo === 'codigo' && $valor !== '') {
                    $filtros['modo'] = MovimientoStockListadoFiltros::MODO_CAMPO;
                    $filtros['campo'] = 'codigo';
                    $filtros['operador'] = $op !== '' ? $op : 'contiene';
                    $filtros['valor'] = $valor;
                    $filtros['busqueda'] = $valor;
                }
            }
        }
    }
}
