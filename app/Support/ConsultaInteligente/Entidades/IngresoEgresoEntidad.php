<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Queries\Caja\Caja_MovimientoQueryInterface;
use App\Support\Caja\IngresoEgresoListadoColumnas;
use App\Support\Caja\IngresoEgresoListadoFiltros;
use App\Support\Caja\IngresoEgresoListadoPreferenciasUsuario;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoGrillaConfigSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class IngresoEgresoEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'ingresoegreso';
    }

    public function nombre(): string
    {
        return 'Ingresos y egresos';
    }

    public function descripcion(): string
    {
        return 'Movimientos de caja, OPP/OPA, importes y cuentas asociadas.';
    }

    public function icono(): string
    {
        return 'fa-exchange-alt';
    }

    public function modulo(): string
    {
        return 'Caja';
    }

    public function color(): string
    {
        return '#0E6655';
    }

    public function permisoListar(): string
    {
        return 'listar-ingresos-egresos-caja';
    }

    public function rutaNativa(): string
    {
        return 'ingresoegreso';
    }

    public function recurso(): string
    {
        return IngresoEgresoListadoColumnas::RECURSO;
    }

    public function camposQbe(): array
    {
        return IngresoEgresoListadoFiltros::camposQbeDisponibles();
    }

    public function columnasVisibles(): array
    {
        return ListadoGrillaConfigSupport::keysVisibles(
            IngresoEgresoListadoPreferenciasUsuario::grillaEstandar()
        );
    }

    public function etiquetasColumnas(): array
    {
        return ListadoGrillaConfigSupport::etiquetasDesdeLayout(
            IngresoEgresoListadoPreferenciasUsuario::grillaEstandar()
        );
    }

    public function valorCelda(object $row, string $key): string
    {
        return IngresoEgresoListadoColumnas::valorCelda($row, $key);
    }

    public function resolverFiltros(Request $request): array
    {
        return IngresoEgresoListadoFiltros::resolverDesdeRequest($request);
    }

    public function paraQueryString(array $filtros): array
    {
        return IngresoEgresoListadoFiltros::paraQueryString($filtros);
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        /** @var Caja_MovimientoQueryInterface $query */
        $query = app(Caja_MovimientoQueryInterface::class);

        return $query->leeCaja_Movimiento($filtros, 0, $paginar);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-ingresos-egresos-caja', false)) {
            return null;
        }

        return route('editar_ingresoegreso', [
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
                'titulo' => 'Todos los IE',
                'descripcion' => 'Listado de ingresos y egresos',
                'query' => ['consultar' => 1],
            ],
            [
                'clave' => 'este_mes',
                'titulo' => 'IE este mes',
                'descripcion' => 'Ingresos y egresos del mes en curso',
                'query' => \App\Support\Ai\AiConsultaDolorEntidadesSupport::queryQbeCampo(
                    'fecha',
                    'este_mes'
                ),
            ],
        ];
    }
}
