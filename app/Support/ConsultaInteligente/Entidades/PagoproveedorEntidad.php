<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Repositories\Compras\PagoproveedorRepositoryInterface;
use App\Support\Compras\PagoproveedorListadoColumnas;
use App\Support\Compras\PagoproveedorListadoFiltros;
use App\Support\Compras\PagoproveedorListadoPreferenciasUsuario;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoGrillaConfigSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class PagoproveedorEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'pagoproveedor';
    }

    public function nombre(): string
    {
        return 'Pagos a proveedores';
    }

    public function descripcion(): string
    {
        return 'Órdenes de pago, montos, facturas aplicadas y seguimiento de envío.';
    }

    public function icono(): string
    {
        return 'fa-hand-holding-usd';
    }

    public function modulo(): string
    {
        return 'Compras';
    }

    public function color(): string
    {
        return '#117A65';
    }

    public function permisoListar(): string
    {
        return 'listar-pagoproveedor';
    }

    public function rutaNativa(): string
    {
        return 'pagoproveedor';
    }

    public function recurso(): string
    {
        return PagoproveedorListadoColumnas::RECURSO;
    }

    public function camposQbe(): array
    {
        return PagoproveedorListadoFiltros::camposQbeDisponibles();
    }

    public function columnasVisibles(): array
    {
        return ListadoGrillaConfigSupport::keysVisibles(
            PagoproveedorListadoPreferenciasUsuario::grillaEstandar()
        );
    }

    public function etiquetasColumnas(): array
    {
        $layout = PagoproveedorListadoPreferenciasUsuario::grillaEstandar();

        return ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);
    }

    public function valorCelda(object $row, string $key): string
    {
        return PagoproveedorListadoColumnas::valorCelda($row, $key);
    }

    public function resolverFiltros(Request $request): array
    {
        return PagoproveedorListadoFiltros::resolverDesdeRequest($request);
    }

    public function paraQueryString(array $filtros): array
    {
        return PagoproveedorListadoFiltros::paraQueryString($filtros);
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        /** @var PagoproveedorRepositoryInterface $repo */
        $repo = app(PagoproveedorRepositoryInterface::class);

        return $repo->leePagoproveedor($filtros, $paginar);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-pagoproveedor', false)) {
            return null;
        }

        return route('editar_pagoproveedor', [
            'id' => $id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    }

    public function plantillas(): array
    {
        return [
            [
                'clave' => 'este_mes',
                'titulo' => 'Pagos del mes',
                'descripcion' => 'Órdenes de pago del mes en curso',
                'query' => [
                    'consultar' => 1,
                    'filtro_modo' => 'qbe',
                    'qbe' => [
                        'entre_grupos' => 'and',
                        'grupos' => [[
                            'logic' => 'and',
                            'not' => 0,
                            'criterios' => [[
                                'campo' => 'fecha',
                                'op' => 'este_mes',
                                'valor' => '',
                                'valor_hasta' => '',
                            ]],
                        ]],
                    ],
                ],
            ],
        ];
    }
}
