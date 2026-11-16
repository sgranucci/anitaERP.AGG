<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Repositories\Stock\ArticuloRepositoryInterface;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Stock\ArticuloListadoColumnas;
use App\Support\Stock\ArticuloListadoFiltros;
use App\Support\Stock\ArticuloListadoPreferenciasUsuario;
use App\Support\Stock\ArticuloSaldosDepositoSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class ArticuloEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'articulo';
    }

    public function nombre(): string
    {
        return 'Artículos / saldos';
    }

    public function descripcion(): string
    {
        return 'Maestro de artículos con saldo del depósito de entrega.';
    }

    public function icono(): string
    {
        return 'fa-boxes';
    }

    public function modulo(): string
    {
        return 'Stock';
    }

    public function color(): string
    {
        return '#6C3483';
    }

    public function permisoListar(): string
    {
        return 'listar-articulos';
    }

    public function rutaNativa(): string
    {
        return 'articulo';
    }

    public function recurso(): string
    {
        return ArticuloListadoColumnas::RECURSO;
    }

    public function camposQbe(): array
    {
        return ArticuloListadoFiltros::camposQbeDisponibles();
    }

    public function columnasVisibles(): array
    {
        return ListadoGrillaConfigSupport::keysVisibles(
            ArticuloListadoPreferenciasUsuario::grillaEstandar()
        );
    }

    public function etiquetasColumnas(): array
    {
        return ListadoGrillaConfigSupport::etiquetasDesdeLayout(
            ArticuloListadoPreferenciasUsuario::grillaEstandar()
        );
    }

    public function valorCelda(object $row, string $key): string
    {
        return ArticuloListadoColumnas::valorCelda($row, $key);
    }

    public function resolverFiltros(Request $request): array
    {
        return ArticuloListadoFiltros::resolverDesdeRequest($request);
    }

    public function paraQueryString(array $filtros): array
    {
        return ArticuloListadoFiltros::paraQueryString($filtros);
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        /** @var ArticuloRepositoryInterface $repo */
        $repo = app(ArticuloRepositoryInterface::class);
        $filas = $repo->leeArticulo($filtros, $paginar);
        if (! ArticuloSaldosDepositoSupport::puedeConsultar()) {
            return $filas;
        }
        $coleccion = $filas instanceof LengthAwarePaginator
            ? $filas->getCollection()
            : collect($filas);
        $saldos = ArticuloSaldosDepositoSupport::saldosDepositoEntrega($coleccion);
        foreach ($coleccion as $row) {
            $id = (int) ($row->id ?? 0);
            $row->saldo = $saldos[$id] ?? null;
        }

        return $filas;
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-articulos', false)) {
            return null;
        }

        return route('editar_articulo', [
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
                'titulo' => 'Artículos activos',
                'descripcion' => 'Con stock consultable',
                'query' => ['consultar' => 1],
            ],
        ];
    }
}
