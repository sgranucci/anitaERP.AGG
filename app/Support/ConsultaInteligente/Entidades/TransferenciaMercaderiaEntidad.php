<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Models\Stock\Transferencia_Mercaderia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoQbeSupport;
use App\Support\Stock\TransferenciaMercaderiaListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class TransferenciaMercaderiaEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'transferencia_mercaderia';
    }

    public function nombre(): string
    {
        return 'Transferencias de mercadería';
    }

    public function descripcion(): string
    {
        return 'TM entre depósitos: estado, origen/destino, tipo y usuario.';
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
        return '#148F77';
    }

    public function permisoListar(): string
    {
        return 'crear-transferencia-mercaderia';
    }

    public function rutaNativa(): string
    {
        return 'transferencia_mercaderia';
    }

    public function recurso(): string
    {
        return 'stock.transferencia_mercaderia';
    }

    public function camposQbe(): array
    {
        $out = [];
        foreach (TransferenciaMercaderiaListadoFiltros::CAMPOS as $key => $meta) {
            $out[$key] = [
                'label' => $meta['label'] ?? $key,
                'type' => $meta['type'] ?? 'texto',
                'column' => $meta['column'] ?? $key,
            ];
        }

        return $out;
    }

    public function columnasVisibles(): array
    {
        return [
            'id', 'codigo', 'fecha', 'estado', 'nombreempresa',
            'deposito_origen', 'deposito_destino', 'nombretipo', 'nombreusuario',
        ];
    }

    public function etiquetasColumnas(): array
    {
        return [
            'id' => 'ID',
            'codigo' => 'Número',
            'fecha' => 'Fecha',
            'estado' => 'Estado',
            'nombreempresa' => 'Empresa',
            'deposito_origen' => 'Origen',
            'deposito_destino' => 'Destino',
            'nombretipo' => 'Tipo',
            'nombreusuario' => 'Usuario',
            'lote' => 'Lote',
        ];
    }

    public function valorCelda(object $row, string $key): string
    {
        $v = $row->{$key} ?? '';
        if ($key === 'fecha' && $v) {
            return e(date('d/m/Y', strtotime((string) $v)));
        }

        return e((string) $v);
    }

    public function resolverFiltros(Request $request): array
    {
        $f = TransferenciaMercaderiaListadoFiltros::resolverDesdeRequest($request);
        $qbe = ListadoQbeSupport::resolverDesdeRequest(
            $request,
            $this->camposQbe(),
            static fn (string $op, string $campo): string => $op
        );
        if (ListadoQbeSupport::tieneCriterios($qbe)) {
            $f['qbe'] = $qbe;
            $f['filtro_modo'] = 'qbe';
        }

        return $f;
    }

    public function paraQueryString(array $filtros): array
    {
        $out = TransferenciaMercaderiaListadoFiltros::paraQueryString($filtros);
        if (! empty($filtros['qbe']) && is_array($filtros['qbe'])) {
            $out['qbe'] = $filtros['qbe'];
            $out['filtro_modo'] = 'qbe';
        }

        return $out;
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        $query = Transferencia_Mercaderia::query()
            ->select([
                'transferencia_mercaderia.id as id',
                'transferencia_mercaderia.codigo as codigo',
                'transferencia_mercaderia.fecha as fecha',
                'transferencia_mercaderia.estado as estado',
                'transferencia_mercaderia.lote as lote',
                'transferencia_mercaderia.observacion as observacion',
                'empresa.nombre as nombreempresa',
                'dep_o.nombre as deposito_origen',
                'dep_d.nombre as deposito_destino',
                'tts.nombre as nombretipo',
                'u_orig.nombre as nombreusuario',
            ])
            ->leftJoin('empresa', 'empresa.id', '=', 'transferencia_mercaderia.empresa_id')
            ->leftJoin('depmae as dep_o', 'dep_o.id', '=', 'transferencia_mercaderia.deposito_origen_id')
            ->leftJoin('depmae as dep_d', 'dep_d.id', '=', 'transferencia_mercaderia.deposito_destino_id')
            ->leftJoin('tipotransaccion_stock as tts', 'tts.id', '=', 'transferencia_mercaderia.tipotransaccion_stock_id')
            ->leftJoin('usuario as u_orig', 'u_orig.id', '=', 'transferencia_mercaderia.usuario_origen_id')
            ->orderByDesc('transferencia_mercaderia.id');

        if (ListadoQbeSupport::tieneCriterios(is_array($filtros['qbe'] ?? null) ? $filtros['qbe'] : [])) {
            ListadoQbeSupport::aplicar($query, $filtros['qbe'], function (Builder $q, array $criterio): void {
                $campo = (string) ($criterio['campo'] ?? '');
                $meta = TransferenciaMercaderiaListadoFiltros::CAMPOS[$campo] ?? null;
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
            TransferenciaMercaderiaListadoFiltros::aplicar($query, $filtros);
        }

        app(EmpresaRepositoryInterface::class)
            ->aplicarFiltroEmpresasAsignadas($query, 'transferencia_mercaderia.empresa_id');

        return $paginar ? $query->paginate(10) : $query->get();
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1) {
            return null;
        }

        return route('consultar_transferencia_movimientostock', [
            'id' => $id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    }

    public function plantillas(): array
    {
        return [
            [
                'clave' => 'todas',
                'titulo' => 'Todas las TM',
                'descripcion' => 'Listado con consulta lista',
                'query' => ['consultar' => 1],
            ],
            [
                'clave' => 'pendientes',
                'titulo' => 'Pendientes de recepción',
                'descripcion' => 'Estado PENDIENTE_RECEPCION',
                'query' => [
                    'consultar' => 1,
                    'filtro_modo' => 'qbe',
                    'qbe' => [
                        'entre_grupos' => 'and',
                        'grupos' => [[
                            'logic' => 'and',
                            'not' => 0,
                            'criterios' => [[
                                'campo' => 'estado',
                                'op' => 'igual',
                                'valor' => 'PENDIENTE_RECEPCION',
                            ]],
                        ]],
                    ],
                ],
            ],
        ];
    }
}
