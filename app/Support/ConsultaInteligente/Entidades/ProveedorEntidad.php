<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Support\Compras\ProveedorListadoColumnas;
use App\Support\Compras\ProveedorListadoFiltros;
use App\Support\Compras\ProveedorListadoPreferenciasUsuario;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoGrillaConfigSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class ProveedorEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'proveedor';
    }

    public function nombre(): string
    {
        return 'Proveedores';
    }

    public function descripcion(): string
    {
        return 'Maestro de proveedores: razón social, CUIT, estado y contactos.';
    }

    public function icono(): string
    {
        return 'fa-truck';
    }

    public function modulo(): string
    {
        return 'Compras';
    }

    public function color(): string
    {
        return '#6C3483';
    }

    public function permisoListar(): string
    {
        return 'listar-proveedor';
    }

    public function rutaNativa(): string
    {
        return 'proveedor';
    }

    public function recurso(): string
    {
        return ProveedorListadoColumnas::RECURSO;
    }

    public function camposQbe(): array
    {
        return ProveedorListadoFiltros::camposQbeDisponibles();
    }

    public function columnasVisibles(): array
    {
        return ListadoGrillaConfigSupport::keysVisibles(
            ProveedorListadoPreferenciasUsuario::grillaEstandar()
        );
    }

    public function etiquetasColumnas(): array
    {
        $layout = ProveedorListadoPreferenciasUsuario::grillaEstandar();

        return ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);
    }

    public function valorCelda(object $row, string $key): string
    {
        return ProveedorListadoColumnas::valorCelda($row, $key);
    }

    public function resolverFiltros(Request $request): array
    {
        return ProveedorListadoFiltros::resolverDesdeRequest($request);
    }

    public function paraQueryString(array $filtros): array
    {
        return ProveedorListadoFiltros::paraQueryString($filtros);
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        /** @var ProveedorRepositoryInterface $repo */
        $repo = app(ProveedorRepositoryInterface::class);

        return $repo->leeProveedor($filtros, $paginar);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-proveedor', false)) {
            return null;
        }

        return route('editar_proveedor', [
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
                'titulo' => 'Todos los proveedores',
                'descripcion' => 'Abrir el maestro con consulta lista',
                'query' => ['consultar' => 1],
            ],
        ];
    }
}
