<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Repositories\Caja\ChequeRepositoryInterface;
use App\Support\Caja\ChequeListadoColumnas;
use App\Support\Caja\ChequeListadoFiltros;
use App\Support\Caja\ChequeListadoPreferenciasUsuario;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoGrillaConfigSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class ChequeEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'cheque';
    }

    public function nombre(): string
    {
        return 'Cheques';
    }

    public function descripcion(): string
    {
        return 'Cartera, emitidos, para depositar, rechazados y montos por estado.';
    }

    public function icono(): string
    {
        return 'fa-money-check-alt';
    }

    public function modulo(): string
    {
        return 'Caja';
    }

    public function color(): string
    {
        return '#1A5276';
    }

    public function permisoListar(): string
    {
        return 'listar-cheque';
    }

    public function rutaNativa(): string
    {
        return 'cheque';
    }

    public function recurso(): string
    {
        return ChequeListadoColumnas::RECURSO;
    }

    public function camposQbe(): array
    {
        return ChequeListadoFiltros::camposQbeDisponibles();
    }

    public function columnasVisibles(): array
    {
        return ListadoGrillaConfigSupport::keysVisibles(ChequeListadoPreferenciasUsuario::grillaEstandar());
    }

    public function etiquetasColumnas(): array
    {
        $layout = ChequeListadoPreferenciasUsuario::grillaEstandar();

        return ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);
    }

    public function valorCelda(object $row, string $key): string
    {
        return ChequeListadoColumnas::valorCelda($row, $key);
    }

    public function resolverFiltros(Request $request): array
    {
        return ChequeListadoFiltros::resolverDesdeRequest($request);
    }

    public function paraQueryString(array $filtros): array
    {
        return ChequeListadoFiltros::paraQueryString($filtros);
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        /** @var ChequeRepositoryInterface $repo */
        $repo = app(ChequeRepositoryInterface::class);

        return $repo->leeCheque($filtros, $paginar);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-cheque', false)) {
            return null;
        }

        return route('editar_cheque', [
            'id' => $id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    }

    public function plantillas(): array
    {
        return [
            [
                'clave' => 'para_depositar',
                'titulo' => 'Para depositar',
                'descripcion' => 'Cheques en condición de depósito',
                'query' => ['para_depositar' => 1, 'consultar' => 1],
            ],
            [
                'clave' => 'cartera',
                'titulo' => 'En cartera',
                'descripcion' => 'Recibidos disponibles',
                'query' => ['cartera' => 1, 'consultar' => 1],
            ],
            [
                'clave' => 'emitidos',
                'titulo' => 'Emitidos',
                'descripcion' => 'Cheques propios',
                'query' => ['origen' => 'E', 'consultar' => 1],
            ],
            [
                'clave' => 'rechazados',
                'titulo' => 'Rechazados',
                'descripcion' => 'Con rechazo / ND',
                'query' => ['rechazados' => 1, 'consultar' => 1],
            ],
        ];
    }
}
