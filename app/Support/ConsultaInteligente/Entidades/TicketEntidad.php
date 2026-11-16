<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Queries\Ticket\TicketQuery;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Ticket\AdministracionTicketListadoColumnas;
use App\Support\Ticket\AdministracionTicketListadoFiltros;
use App\Support\Ticket\AdministracionTicketListadoPreferenciasUsuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class TicketEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'ticket';
    }

    public function nombre(): string
    {
        return 'Tickets';
    }

    public function descripcion(): string
    {
        return 'Administración de tickets: estado, sector, minutos y responsables.';
    }

    public function icono(): string
    {
        return 'fa-ticket-alt';
    }

    public function modulo(): string
    {
        return 'Tickets';
    }

    public function color(): string
    {
        return '#922B21';
    }

    public function permisoListar(): string
    {
        return 'listar-ticket';
    }

    public function rutaNativa(): string
    {
        return 'consulta_administracion_ticket';
    }

    public function recurso(): string
    {
        return AdministracionTicketListadoColumnas::RECURSO;
    }

    public function camposQbe(): array
    {
        return AdministracionTicketListadoFiltros::camposQbeDisponibles();
    }

    public function columnasVisibles(): array
    {
        return ListadoGrillaConfigSupport::keysVisibles(
            AdministracionTicketListadoPreferenciasUsuario::grillaEstandar()
        );
    }

    public function etiquetasColumnas(): array
    {
        return ListadoGrillaConfigSupport::etiquetasDesdeLayout(
            AdministracionTicketListadoPreferenciasUsuario::grillaEstandar()
        );
    }

    public function valorCelda(object $row, string $key): string
    {
        return AdministracionTicketListadoColumnas::valorCelda($row, $key);
    }

    public function resolverFiltros(Request $request): array
    {
        return AdministracionTicketListadoFiltros::resolverDesdeRequest($request);
    }

    public function paraQueryString(array $filtros): array
    {
        return AdministracionTicketListadoFiltros::paraQueryString($filtros);
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        return app(TicketQuery::class)->leeTicketAdministracion($filtros, $paginar);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-ticket', false)) {
            return null;
        }

        return route('edita_administracion_ticket', [
            'id' => $id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    }

    public function plantillas(): array
    {
        return [
            [
                'clave' => 'abiertos',
                'titulo' => 'Tickets abiertos',
                'descripcion' => 'Administración con consulta lista',
                'query' => ['consultar' => 1],
            ],
        ];
    }
}
