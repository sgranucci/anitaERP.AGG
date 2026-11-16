<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Models\Ventas\Pedido;
use App\Repositories\Ventas\VendedorRepositoryInterface;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoQbeSupport;
use App\Support\Ventas\PedidoListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PedidoEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'pedido';
    }

    public function nombre(): string
    {
        return 'Pedidos';
    }

    public function descripcion(): string
    {
        return 'Pedidos de venta: cliente, estado, reparto y fechas.';
    }

    public function icono(): string
    {
        return 'fa-clipboard-list';
    }

    public function modulo(): string
    {
        return 'Ventas';
    }

    public function color(): string
    {
        return '#6C3483';
    }

    public function permisoListar(): string
    {
        return 'listar-pedidos';
    }

    public function rutaNativa(): string
    {
        return 'pedido';
    }

    public function recurso(): string
    {
        return 'ventas.pedido';
    }

    public function camposQbe(): array
    {
        $out = [];
        foreach (PedidoListadoFiltros::CAMPOS as $key => $meta) {
            $out[$key] = [
                'label' => html_entity_decode((string) ($meta['label'] ?? $key), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'type' => $meta['type'] ?? 'texto',
                'column' => $meta['column'] ?? $key,
            ];
        }

        return $out;
    }

    public function columnasVisibles(): array
    {
        return ['id', 'codigo', 'fecha', 'nombrecliente', 'estado', 'nombretransporte', 'nombreempresa'];
    }

    public function etiquetasColumnas(): array
    {
        return [
            'id' => 'ID',
            'codigo' => 'Número',
            'fecha' => 'Fecha',
            'nombrecliente' => 'Cliente',
            'estado' => 'Estado',
            'nombretransporte' => 'Reparto',
            'nombreempresa' => 'Empresa',
            'fechaentrega' => 'Entrega',
        ];
    }

    public function valorCelda(object $row, string $key): string
    {
        $v = $row->{$key} ?? '';
        if (in_array($key, ['fecha', 'fechaentrega'], true) && $v) {
            return e(date('d/m/Y', strtotime((string) $v)));
        }

        return e((string) $v);
    }

    public function resolverFiltros(Request $request): array
    {
        $f = PedidoListadoFiltros::resolverDesdeRequest($request);
        $qbe = ListadoQbeSupport::resolverDesdeRequest(
            $request,
            $this->camposQbe(),
            static fn (string $op, string $campo): string => $op
        );
        if (ListadoQbeSupport::tieneCriterios($qbe)) {
            $f['qbe'] = $qbe;
            $f['filtro_modo'] = 'qbe';
        }
        $f['filtro_valor'] = trim((string) ($f['valor'] ?? $f['busqueda'] ?? $request->input('filtro_valor', '')));

        return $f;
    }

    public function paraQueryString(array $filtros): array
    {
        $out = PedidoListadoFiltros::paraQueryString($filtros);
        if (! empty($filtros['qbe']) && is_array($filtros['qbe'])) {
            $out['qbe'] = $filtros['qbe'];
            $out['filtro_modo'] = 'qbe';
        }

        return $out;
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        $vendedores = app(VendedorRepositoryInterface::class)->leeVendedoresAsociados();

        $query = Pedido::query()
            ->select([
                'pedido.id as id',
                'pedido.fecha as fecha',
                'pedido.fechaentrega as fechaentrega',
                'pedido.cliente_id as cliente_id',
                'cliente.nombre as nombrecliente',
                'pedido.codigo as codigo',
                'pedido.estadopedido as estado',
                'pedido.transporte_id as transporte_id',
                'transporte.nombre as nombretransporte',
                'transporte.codigo as codigotransporte',
                DB::raw("'".addslashes((string) config('app.empresa'))."' as nombreempresa"),
            ])
            ->join('cliente', 'cliente.id', '=', 'pedido.cliente_id')
            ->leftJoin('transporte', 'transporte.id', '=', 'pedido.transporte_id')
            ->orderByDesc('pedido.id');

        if (count($vendedores) > 0) {
            $query->whereIn('cliente.vendedor_id', $vendedores);
        }

        if (ListadoQbeSupport::tieneCriterios(is_array($filtros['qbe'] ?? null) ? $filtros['qbe'] : [])) {
            $campos = $this->camposQbe();
            ListadoQbeSupport::aplicar($query, $filtros['qbe'], function (Builder $q, array $criterio) use ($campos): void {
                $campo = (string) ($criterio['campo'] ?? '');
                $meta = $campos[$campo] ?? null;
                if (! $meta) {
                    return;
                }
                $column = (string) $meta['column'];
                $op = (string) ($criterio['op'] ?? 'contiene');
                $valor = (string) ($criterio['valor'] ?? '');
                $hasta = (string) ($criterio['valor_hasta'] ?? '');
                $type = (string) ($meta['type'] ?? 'texto');
                if ($type === 'fecha') {
                    ListadoQbeSupport::aplicarFecha($q, $column, $op, $valor, $hasta);

                    return;
                }
                if ($type === 'entero') {
                    if ($op === 'igual' && $valor !== '') {
                        $q->where($column, (int) $valor);
                    }

                    return;
                }
                if ($type === 'estado') {
                    if ($op === 'igual' && $valor !== '') {
                        $q->where($column, $valor);
                    } elseif ($valor !== '') {
                        $q->where($column, 'like', '%'.$valor.'%');
                    }

                    return;
                }
                if ($op === 'igual') {
                    $q->where($column, $valor);
                } elseif ($op === 'empieza') {
                    $q->where($column, 'like', $valor.'%');
                } elseif ($op === 'termina') {
                    $q->where($column, 'like', '%'.$valor);
                } else {
                    $q->where($column, 'like', '%'.$valor.'%');
                }
            });
        } else {
            PedidoListadoFiltros::aplicar($query, $filtros);
        }

        if ($paginar) {
            return $query->paginate(10);
        }

        return $query->limit(2000)->get();
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-pedidos', false)) {
            return null;
        }

        return route('editar_pedido', [
            'id' => $id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    }

    public function plantillas(): array
    {
        return [
            [
                'clave' => 'pendientes_mes',
                'titulo' => 'Pendientes este mes',
                'descripcion' => 'Pedidos abiertos del mes',
                'query' => [
                    'consultar' => 1,
                    'filtro_modo' => 'qbe',
                    'qbe' => [
                        'entre_grupos' => 'and',
                        'grupos' => [[
                            'logic' => 'and',
                            'not' => 0,
                            'criterios' => [
                                [
                                    'campo' => 'fecha',
                                    'op' => 'este_mes',
                                    'valor' => '',
                                    'valor_hasta' => '',
                                ],
                                [
                                    'campo' => 'estadopedido',
                                    'op' => 'contiene',
                                    'valor' => 'pendiente',
                                    'valor_hasta' => '',
                                ],
                            ],
                        ]],
                    ],
                ],
            ],
        ];
    }
}
