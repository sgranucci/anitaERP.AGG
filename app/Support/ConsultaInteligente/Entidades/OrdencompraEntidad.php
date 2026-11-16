<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Repositories\Compras\OrdencompraRepositoryInterface;
use App\Support\Compras\OrdencompraListadoFiltros;
use App\Support\Compras\OrdencompraSectorVisibilidadSupport;
use App\Support\ConsultaInteligente\EntidadSemantica;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class OrdencompraEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'ordencompra';
    }

    public function nombre(): string
    {
        return 'Órdenes de compra';
    }

    public function descripcion(): string
    {
        return 'OC: proveedor, estado, fechas, recepción y factura.';
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
        return '#1A5276';
    }

    public function permisoListar(): string
    {
        return 'listar-ordencompra';
    }

    public function rutaNativa(): string
    {
        return 'consultar_ordencompra';
    }

    public function recurso(): string
    {
        return 'compras.ordencompra';
    }

    public function camposQbe(): array
    {
        return OrdencompraListadoFiltros::CAMPOS;
    }

    public function columnasVisibles(): array
    {
        return [
            'numeroordencompra', 'fecha', 'nombreproveedor', 'nombreempresa',
            'estadoordencompra', 'nombreusuario', 'recepcion_numero', 'factura_numero',
        ];
    }

    public function etiquetasColumnas(): array
    {
        $out = [];
        foreach (OrdencompraListadoFiltros::CAMPOS as $key => $meta) {
            $out[$key] = (string) ($meta['label'] ?? $key);
        }

        return $out;
    }

    public function valorCelda(object $row, string $key): string
    {
        $v = $row->{$key} ?? '';
        if (in_array($key, ['fecha', 'fechaentrega'], true) && $v) {
            return e(date('d/m/Y', strtotime((string) $v)));
        }

        return e((string) $v);
    }

    public function resolverFiltros(Request $request): array
    {
        return OrdencompraListadoFiltros::resolverDesdeRequest($request);
    }

    public function paraQueryString(array $filtros): array
    {
        return OrdencompraListadoFiltros::paraQueryString($filtros);
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        /** @var OrdencompraRepositoryInterface $repo */
        $repo = app(OrdencompraRepositoryInterface::class);
        $sectorId = OrdencompraSectorVisibilidadSupport::idSectorParaFiltro();

        return $repo->listadoIndex($filtros, $sectorId, $paginar);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-ordencompra', false)) {
            return null;
        }

        return route('editar_ordencompra', [
            'id' => $id,
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ]);
    }

    public function plantillas(): array
    {
        return [
            [
                'clave' => 'todas',
                'titulo' => 'Órdenes de compra',
                'descripcion' => 'Listado de OC',
                'query' => ['consultar' => 1],
            ],
            [
                'clave' => 'pendientes',
                'titulo' => 'OC pendientes',
                'descripcion' => 'Estado PENDIENTE',
                'query' => \App\Support\Ai\AiConsultaDolorEntidadesSupport::queryQbeCampo(
                    'estadoordencompra',
                    'igual',
                    'PENDIENTE'
                ),
            ],
            [
                'clave' => 'aprobadas',
                'titulo' => 'OC aprobadas',
                'descripcion' => 'Estado APROBADA',
                'query' => \App\Support\Ai\AiConsultaDolorEntidadesSupport::queryQbeCampo(
                    'estadoordencompra',
                    'igual',
                    'APROBADA'
                ),
            ],
        ];
    }
}
