<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Queries\Presupuesto\CapexQueryInterface;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoQbeSupport;
use App\Support\Presupuesto\CapexListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class CapexEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'capex';
    }

    public function nombre(): string
    {
        return 'Capex';
    }

    public function descripcion(): string
    {
        return 'Proyectos de inversión: presupuesto, centro de costo, estado y código.';
    }

    public function icono(): string
    {
        return 'fa-project-diagram';
    }

    public function modulo(): string
    {
        return 'Presupuesto';
    }

    public function color(): string
    {
        return '#6C3483';
    }

    public function permisoListar(): string
    {
        return 'listar-capex';
    }

    public function rutaNativa(): string
    {
        return 'consultar_capex';
    }

    public function recurso(): string
    {
        return 'presupuesto.capex';
    }

    public function camposQbe(): array
    {
        $out = [];
        foreach (CapexListadoFiltros::camposOrdenables() as $key => $meta) {
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
        return ['id', 'codigo', 'nombre', 'nombrepresupuesto', 'nombrecentrocosto', 'nombreempresa', 'estado'];
    }

    public function etiquetasColumnas(): array
    {
        return [
            'id' => 'ID',
            'codigo' => 'Nro.',
            'nombre' => 'Nombre',
            'nombrepresupuesto' => 'Presupuesto',
            'nombrecentrocosto' => 'Centro costo',
            'nombreempresa' => 'Empresa',
            'estado' => 'Estado',
            'codigoproyecto' => 'Cód. proyecto',
        ];
    }

    public function valorCelda(object $row, string $key): string
    {
        return e((string) ($row->{$key} ?? ''));
    }

    public function resolverFiltros(Request $request): array
    {
        $f = CapexListadoFiltros::resolverDesdeRequest($request);
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
        $out = CapexListadoFiltros::paraQueryString($filtros);
        if (! empty($filtros['qbe']) && is_array($filtros['qbe'])) {
            $out['qbe'] = $filtros['qbe'];
            $out['filtro_modo'] = 'qbe';
        }

        return $out;
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        return app(CapexQueryInterface::class)->leeCapex($filtros, $paginar);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-capex', false)) {
            return null;
        }

        return route('editar_capex', [
            'id' => $id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    }

    public function plantillas(): array
    {
        return [
            [
                'clave' => 'todos',
                'titulo' => 'Todos los Capex',
                'descripcion' => 'Abrir el listado con consulta lista',
                'query' => ['consultar' => 1],
            ],
            [
                'clave' => 'activos',
                'titulo' => 'Capex activos',
                'descripcion' => 'Estado ACTIVO',
                'query' => \App\Support\Ai\AiConsultaDolorEntidadesSupport::queryQbeCampo(
                    'estado',
                    'igual',
                    'ACTIVO'
                ),
            ],
        ];
    }
}
