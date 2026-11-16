<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Repositories\Ventas\ClienteRepositoryInterface;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Ventas\ClienteListadoColumnas;
use App\Support\Ventas\ClienteListadoFiltros;
use App\Support\Ventas\ClienteListadoPreferenciasUsuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class ClienteEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'cliente';
    }

    public function nombre(): string
    {
        return 'Clientes';
    }

    public function descripcion(): string
    {
        return 'Maestro de clientes: razón social, documento, estado y datos comerciales.';
    }

    public function icono(): string
    {
        return 'fa-user-friends';
    }

    public function modulo(): string
    {
        return 'Ventas';
    }

    public function color(): string
    {
        return '#1B4F72';
    }

    public function permisoListar(): string
    {
        return 'listar-clientes';
    }

    public function rutaNativa(): string
    {
        return 'cliente';
    }

    public function recurso(): string
    {
        return ClienteListadoColumnas::RECURSO;
    }

    public function camposQbe(): array
    {
        return ClienteListadoFiltros::camposQbeDisponibles();
    }

    public function columnasVisibles(): array
    {
        return ListadoGrillaConfigSupport::keysVisibles(
            ClienteListadoPreferenciasUsuario::grillaEstandar()
        );
    }

    public function etiquetasColumnas(): array
    {
        return ListadoGrillaConfigSupport::etiquetasDesdeLayout(
            ClienteListadoPreferenciasUsuario::grillaEstandar()
        );
    }

    public function valorCelda(object $row, string $key): string
    {
        return ClienteListadoColumnas::valorCelda($row, $key);
    }

    public function resolverFiltros(Request $request): array
    {
        return ClienteListadoFiltros::resolverDesdeRequest($request);
    }

    public function paraQueryString(array $filtros): array
    {
        return ClienteListadoFiltros::paraQueryString($filtros);
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        /** @var ClienteRepositoryInterface $repo */
        $repo = app(ClienteRepositoryInterface::class);

        return $repo->leeCliente($filtros, $paginar);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-clientes', false)) {
            return null;
        }

        return route('editar_cliente', [
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
                'titulo' => 'Todos los clientes',
                'descripcion' => 'Abrir el maestro con consulta lista',
                'query' => ['consultar' => 1],
            ],
        ];
    }
}
