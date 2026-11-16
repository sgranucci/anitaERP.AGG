<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Repositories\Sueldos\Empleado_SueldosRepositoryInterface;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Sueldos\EmpleadoSueldosListadoColumnas;
use App\Support\Sueldos\EmpleadoSueldosListadoFiltros;
use App\Support\Sueldos\EmpleadoSueldosListadoPreferenciasUsuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class EmpleadoSueldosEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'empleado_sueldos';
    }

    public function nombre(): string
    {
        return 'Empleados (sueldos)';
    }

    public function descripcion(): string
    {
        return 'Legajos de sueldos: estado, categoría, ingreso y datos laborales.';
    }

    public function icono(): string
    {
        return 'fa-id-card';
    }

    public function modulo(): string
    {
        return 'Sueldos';
    }

    public function color(): string
    {
        return '#1B4F72';
    }

    public function permisoListar(): string
    {
        return 'listar-empleado-sueldos';
    }

    public function rutaNativa(): string
    {
        return 'consultar_empleado_sueldos';
    }

    public function recurso(): string
    {
        return EmpleadoSueldosListadoColumnas::RECURSO;
    }

    public function camposQbe(): array
    {
        return EmpleadoSueldosListadoFiltros::camposQbeDisponibles();
    }

    public function columnasVisibles(): array
    {
        return ListadoGrillaConfigSupport::keysVisibles(
            EmpleadoSueldosListadoPreferenciasUsuario::grillaEstandar()
        );
    }

    public function etiquetasColumnas(): array
    {
        return ListadoGrillaConfigSupport::etiquetasDesdeLayout(
            EmpleadoSueldosListadoPreferenciasUsuario::grillaEstandar()
        );
    }

    public function valorCelda(object $row, string $key): string
    {
        return EmpleadoSueldosListadoColumnas::valorCelda($row, $key);
    }

    public function resolverFiltros(Request $request): array
    {
        return EmpleadoSueldosListadoFiltros::resolverDesdeRequest($request);
    }

    public function paraQueryString(array $filtros): array
    {
        return EmpleadoSueldosListadoFiltros::paraQueryString($filtros);
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        return app(Empleado_SueldosRepositoryInterface::class)->leeEmpleado($filtros, $paginar);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-empleado-sueldos', false)) {
            return null;
        }

        return route('editar_empleado_sueldos', [
            'id' => $id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    }

    public function plantillas(): array
    {
        return [
            [
                'clave' => 'activos',
                'titulo' => 'Empleados activos',
                'descripcion' => 'Estado activos (chip default del índice)',
                'query' => ['consultar' => 1],
            ],
        ];
    }
}
