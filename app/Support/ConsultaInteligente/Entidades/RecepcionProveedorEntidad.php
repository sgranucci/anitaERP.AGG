<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Repositories\Stock\Recepcion_ProveedorRepositoryInterface;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoQbeSupport;
use App\Support\Stock\RecepcionProveedorListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class RecepcionProveedorEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'recepcion_proveedor';
    }

    public function nombre(): string
    {
        return 'Recepciones de proveedor';
    }

    public function descripcion(): string
    {
        return 'COM / recepciones: OC, proveedor, estado y factura vinculada.';
    }

    public function icono(): string
    {
        return 'fa-dolly';
    }

    public function modulo(): string
    {
        return 'Stock';
    }

    public function color(): string
    {
        return '#7D3C98';
    }

    public function permisoListar(): string
    {
        return 'listar-recepcion-proveedor';
    }

    public function rutaNativa(): string
    {
        return 'recepcion_proveedor';
    }

    public function recurso(): string
    {
        return 'stock.recepcion_proveedor';
    }

    public function camposQbe(): array
    {
        $out = [];
        foreach (RecepcionProveedorListadoFiltros::camposOrdenables() as $key => $meta) {
            $out[$key] = [
                'label' => $meta['label'] ?? $key,
                'type' => $meta['type'] ?? 'texto',
                'column' => $meta['column'] ?? $key,
            ];
        }

        return $out;
    }

    public function columnasVisibles(): array
    {
        return ['id', 'numerorecepcion', 'fecha', 'nombreproveedor', 'numeroordencompra', 'nombreempresa', 'estado', 'tipo'];
    }

    public function etiquetasColumnas(): array
    {
        return [
            'id' => 'ID',
            'numerorecepcion' => 'Nº recepción',
            'fecha' => 'Fecha',
            'nombreproveedor' => 'Proveedor',
            'numeroordencompra' => 'OC',
            'nombreempresa' => 'Empresa',
            'estado' => 'Estado',
            'tipo' => 'Tipo',
        ];
    }

    public function valorCelda(object $row, string $key): string
    {
        $v = $row->{$key} ?? '';
        if ($key === 'fecha' && $v) {
            return e(date('d/m/Y', strtotime((string) $v)));
        }

        return e((string) $v);
    }

    public function resolverFiltros(Request $request): array
    {
        $f = RecepcionProveedorListadoFiltros::resolverDesdeRequest($request);
        $qbe = ListadoQbeSupport::resolverDesdeRequest(
            $request,
            $this->camposQbe(),
            static fn (string $op, string $campo): string => $op
        );
        if (ListadoQbeSupport::tieneCriterios($qbe)) {
            $f['qbe'] = $qbe;
            $f['filtro_modo'] = 'qbe';
        }

        return $f;
    }

    public function paraQueryString(array $filtros): array
    {
        $out = RecepcionProveedorListadoFiltros::paraQueryString($filtros);
        if (! empty($filtros['qbe']) && is_array($filtros['qbe'])) {
            $out['qbe'] = $filtros['qbe'];
            $out['filtro_modo'] = 'qbe';
        }

        return $out;
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        return app(Recepcion_ProveedorRepositoryInterface::class)->leeRecepciones($filtros, $paginar);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-recepcion-proveedor', false)) {
            return null;
        }

        return route('editar_recepcion_proveedor', [
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
                'titulo' => 'Todas las recepciones',
                'descripcion' => 'Abrir el listado con consulta lista',
                'query' => ['consultar' => 1],
            ],
            [
                'clave' => 'borradores',
                'titulo' => 'Recepciones en borrador',
                'descripcion' => 'Estado BORRADOR',
                'query' => \App\Support\Ai\AiConsultaDolorEntidadesSupport::queryQbeCampo(
                    'estado',
                    'igual',
                    'BORRADOR'
                ),
            ],
        ];
    }
}
