<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Repositories\Compras\Comprobante_ProveedorRepositoryInterface;
use App\Support\Compras\ComprobanteProveedorListadoColumnas;
use App\Support\Compras\ComprobanteProveedorListadoFiltros;
use App\Support\Compras\ComprobanteProveedorListadoPreferenciasUsuario;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoGrillaConfigSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class ComprobanteProveedorEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'comprobante_proveedor';
    }

    public function nombre(): string
    {
        return 'Facturas de proveedor';
    }

    public function descripcion(): string
    {
        return 'Comprobantes de compra, totales, OC vinculadas y control contable.';
    }

    public function icono(): string
    {
        return 'fa-file-invoice';
    }

    public function modulo(): string
    {
        return 'Compras';
    }

    public function color(): string
    {
        return '#1A365D';
    }

    public function permisoListar(): string
    {
        return 'listar-comprobante-proveedor';
    }

    public function rutaNativa(): string
    {
        return 'comprobante_proveedor';
    }

    public function recurso(): string
    {
        return ComprobanteProveedorListadoColumnas::RECURSO;
    }

    public function camposQbe(): array
    {
        return ComprobanteProveedorListadoFiltros::camposQbeDisponibles();
    }

    public function columnasVisibles(): array
    {
        return ListadoGrillaConfigSupport::keysVisibles(
            ComprobanteProveedorListadoPreferenciasUsuario::grillaEstandar()
        );
    }

    public function etiquetasColumnas(): array
    {
        $layout = ComprobanteProveedorListadoPreferenciasUsuario::grillaEstandar();

        return ListadoGrillaConfigSupport::etiquetasDesdeLayout($layout);
    }

    public function valorCelda(object $row, string $key): string
    {
        return ComprobanteProveedorListadoColumnas::valorCelda($row, $key);
    }

    public function resolverFiltros(Request $request): array
    {
        return ComprobanteProveedorListadoFiltros::resolverDesdeRequest($request);
    }

    public function paraQueryString(array $filtros): array
    {
        return ComprobanteProveedorListadoFiltros::paraQueryString($filtros);
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        /** @var Comprobante_ProveedorRepositoryInterface $repo */
        $repo = app(Comprobante_ProveedorRepositoryInterface::class);

        return $repo->leeComprobanteProveedor($filtros, $paginar);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-comprobante-proveedor', false)) {
            return null;
        }

        return route('editar_comprobante_proveedor', [
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
                'titulo' => 'Este mes',
                'descripcion' => 'Facturas con fecha del mes en curso',
                'query' => [
                    'consultar' => 1,
                    'filtro_modo' => 'qbe',
                    'qbe' => [
                        'entre_grupos' => 'and',
                        'grupos' => [[
                            'logic' => 'and',
                            'not' => 0,
                            'criterios' => [[
                                'campo' => 'fechacomprobante',
                                'op' => 'este_mes',
                                'valor' => '',
                                'valor_hasta' => '',
                            ]],
                        ]],
                    ],
                ],
            ],
            [
                'clave' => 'mes_anterior',
                'titulo' => 'Mes anterior',
                'descripcion' => 'Facturas del mes previo',
                'query' => [
                    'consultar' => 1,
                    'filtro_modo' => 'qbe',
                    'qbe' => [
                        'entre_grupos' => 'and',
                        'grupos' => [[
                            'logic' => 'and',
                            'not' => 0,
                            'criterios' => [[
                                'campo' => 'fechacomprobante',
                                'op' => 'mes_anterior',
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
