<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Queries\Sala\RequisicionSalaQueryInterface;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Sala\RequisicionSalaListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class RequisicionSalaEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'requisicion_sala';
    }

    public function nombre(): string
    {
        return 'Requisiciones de sala';
    }

    public function descripcion(): string
    {
        return 'Requisiciones de sala: estado cabecera / ítem, zona, depósito y prioridad.';
    }

    public function icono(): string
    {
        return 'fa-door-open';
    }

    public function modulo(): string
    {
        return 'Sala';
    }

    public function color(): string
    {
        return '#117A65';
    }

    public function permisoListar(): string
    {
        return 'listar-requisicion-sala';
    }

    public function rutaNativa(): string
    {
        return 'consultar_requisicion_sala';
    }

    public function recurso(): string
    {
        return 'sala.requisicion_sala';
    }

    public function camposQbe(): array
    {
        $out = [];
        foreach (RequisicionSalaListadoFiltros::CAMPOS as $key => $meta) {
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
            'id', 'numerorequisicion', 'fecha', 'nombreusuario', 'nombreempresa',
            'nombrecentrocosto', 'nombredeposito', 'nombrezona', 'estado',
        ];
    }

    public function etiquetasColumnas(): array
    {
        return [
            'id' => 'ID',
            'numerorequisicion' => 'Número',
            'fecha' => 'Fecha',
            'nombreusuario' => 'Solicitante',
            'nombreempresa' => 'Empresa',
            'nombrecentrocosto' => 'CC',
            'nombredeposito' => 'Depósito',
            'nombrezona' => 'Zona',
            'nombreprioridad' => 'Prioridad',
            'estado' => 'Estado',
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
        return RequisicionSalaListadoFiltros::resolverDesdeRequest($request);
    }

    public function paraQueryString(array $filtros): array
    {
        return RequisicionSalaListadoFiltros::paraQueryString($filtros);
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        return app(RequisicionSalaQueryInterface::class)->leeRequisicionSala($filtros, $paginar, false);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-requisicion-sala', false)) {
            return null;
        }

        return route('editar_requisicion_sala', [
            'id' => $id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    }

    public function plantillas(): array
    {
        return [
            [
                'clave' => 'pendientes_item',
                'titulo' => 'Ítems pendientes',
                'descripcion' => 'Con líneas en estado pendiente',
                'query' => [
                    'consultar' => 1,
                    'empresa_todas' => 1,
                    'estado_linea' => RequisicionSalaListadoFiltros::ESTADO_LINEA_PENDIENTE,
                ],
            ],
            [
                'clave' => 'pendientes',
                'titulo' => 'Cabecera pendiente',
                'descripcion' => 'Estado cabecera PENDIENTE',
                'query' => [
                    'consultar' => 1,
                    'empresa_todas' => 1,
                    'filtro_modo' => 'campo',
                    'filtro_campo' => 'estado',
                    'filtro_operador' => 'igual',
                    'filtro_valor' => 'PENDIENTE',
                ],
            ],
            [
                'clave' => 'aprobadas',
                'titulo' => 'Aprobadas',
                'descripcion' => 'Estado cabecera APROBADA',
                'query' => [
                    'consultar' => 1,
                    'empresa_todas' => 1,
                    'filtro_modo' => 'campo',
                    'filtro_campo' => 'estado',
                    'filtro_operador' => 'igual',
                    'filtro_valor' => 'APROBADA',
                ],
            ],
        ];
    }
}
