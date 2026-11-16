<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Models\Ventas\Remito;
use App\Repositories\Ventas\VendedorRepositoryInterface;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoQbeSupport;
use App\Support\Ventas\RemitoListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RemitoEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'remito';
    }

    public function nombre(): string
    {
        return 'Remitos';
    }

    public function descripcion(): string
    {
        return 'Remitos de venta: cliente, estado, reparto y fechas.';
    }

    public function icono(): string
    {
        return 'fa-truck';
    }

    public function modulo(): string
    {
        return 'Ventas';
    }

    public function color(): string
    {
        return '#117A65';
    }

    public function permisoListar(): string
    {
        return 'listar-remitos';
    }

    public function rutaNativa(): string
    {
        return 'remito';
    }

    public function recurso(): string
    {
        return 'ventas.remito';
    }

    public function camposQbe(): array
    {
        $out = [];
        foreach (RemitoListadoFiltros::CAMPOS as $key => $meta) {
            $out[$key] = [
                'label' => html_entity_decode((string) ($meta['label'] ?? $key), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'type' => $meta['type'] ?? 'texto',
                'column' => $meta['column'] ?? $key,
            ];
        }
        $out['venta_id'] = [
            'label' => 'ID venta',
            'type' => 'entero',
            'column' => 'remito.venta_id',
        ];

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
            'codigo' => 'Código',
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
        $f = RemitoListadoFiltros::resolverDesdeRequest($request);
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
        $out = RemitoListadoFiltros::paraQueryString($filtros);
        if (! empty($filtros['qbe']) && is_array($filtros['qbe'])) {
            $out['qbe'] = $filtros['qbe'];
            $out['filtro_modo'] = 'qbe';
        }

        return $out;
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        $vendedores = app(VendedorRepositoryInterface::class)->leeVendedoresAsociados();

        $query = Remito::query()
            ->select([
                'remito.id as id',
                'remito.fecha as fecha',
                'remito.fechaentrega as fechaentrega',
                'remito.cliente_id as cliente_id',
                'cliente.nombre as nombrecliente',
                'remito.codigo as codigo',
                'remito.estadoremito as estado',
                'remito.venta_id as venta_id',
                'remito.transporte_id as transporte_id',
                'transporte.nombre as nombretransporte',
                'transporte.codigo as codigotransporte',
                DB::raw("'".addslashes((string) config('app.empresa'))."' as nombreempresa"),
            ])
            ->join('cliente', 'cliente.id', '=', 'remito.cliente_id')
            ->leftJoin('transporte', 'transporte.id', '=', 'remito.transporte_id')
            ->orderByDesc('remito.id');

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
                if ($type === 'estado' && $op === 'igual' && $valor !== '') {
                    $q->where($column, $valor);

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
            RemitoListadoFiltros::aplicar($query, $filtros);
        }

        if ($paginar) {
            return $query->paginate(10);
        }

        return $query->limit(2000)->get();
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-remitos', false)) {
            return null;
        }

        return route('editar_remito', [
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
                'titulo' => 'Remitos este mes',
                'descripcion' => 'Fecha del mes en curso',
                'query' => \App\Support\Ai\AiConsultaDolorEntidadesSupport::queryQbeCampo(
                    'fecha',
                    'este_mes'
                ),
            ],
            [
                'clave' => 'pendientes',
                'titulo' => 'Remitos pendientes',
                'descripcion' => 'Estado Pendiente',
                'query' => \App\Support\Ai\AiConsultaDolorEntidadesSupport::queryQbeCampo(
                    'estadoremito',
                    'contiene',
                    'Pendiente'
                ),
            ],
        ];
    }
}
