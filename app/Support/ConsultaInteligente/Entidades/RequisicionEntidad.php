<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Queries\Compras\RequisicionQuery;
use App\Support\Compras\RequisicionListadoFiltros;
use App\Support\ConsultaInteligente\EntidadSemantica;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class RequisicionEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'requisicion';
    }

    public function nombre(): string
    {
        return 'Requisiciones';
    }

    public function descripcion(): string
    {
        return 'Requisiciones de compra: estado, proveedor, fechas y circuito.';
    }

    public function icono(): string
    {
        return 'fa-clipboard-list';
    }

    public function modulo(): string
    {
        return 'Compras';
    }

    public function color(): string
    {
        return '#5D6D7E';
    }

    public function permisoListar(): string
    {
        return 'listar-requisicion';
    }

    public function rutaNativa(): string
    {
        return 'consultar_requisicion';
    }

    public function recurso(): string
    {
        return 'compras.requisicion';
    }

    public function camposQbe(): array
    {
        return RequisicionListadoFiltros::CAMPOS;
    }

    public function columnasVisibles(): array
    {
        return [
            'numerorequisicion', 'fecha', 'nombreproveedor', 'nombreempresa',
            'estado', 'nombreusuario', 'ordencompra_numero',
        ];
    }

    public function etiquetasColumnas(): array
    {
        $out = [];
        foreach (RequisicionListadoFiltros::CAMPOS as $key => $meta) {
            $out[$key] = (string) ($meta['label'] ?? $key);
        }

        return $out;
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
        return RequisicionListadoFiltros::resolverDesdeRequest($request);
    }

    public function paraQueryString(array $filtros): array
    {
        return RequisicionListadoFiltros::paraQueryString($filtros);
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        /** @var RequisicionQuery $query */
        $query = app(RequisicionQuery::class);

        return $query->leeRequisicion($filtros, $paginar);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-requisicion', false)) {
            return null;
        }

        return route('editar_requisicion', [
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
                'titulo' => 'Requisiciones',
                'descripcion' => 'Listado de requisiciones',
                'query' => ['consultar' => 1],
            ],
            [
                'clave' => 'pendientes',
                'titulo' => 'Requisiciones pendientes',
                'descripcion' => 'Estado pendiente',
                'query' => \App\Support\Ai\AiConsultaDolorEntidadesSupport::queryQbeCampo(
                    'estado',
                    'contiene',
                    'PENDIENTE'
                ),
            ],
        ];
    }
}
