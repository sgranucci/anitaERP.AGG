<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Repositories\Compras\ImportacionRepositoryInterface;
use App\Support\Compras\ImportacionListadoColumnas;
use App\Support\Compras\ImportacionListadoFiltros;
use App\Support\Compras\ImportacionListadoPreferenciasUsuario;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoGrillaConfigSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class ImportacionEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'importacion';
    }

    public function nombre(): string
    {
        return 'Importaciones';
    }

    public function descripcion(): string
    {
        return 'Carpetas de importación: proveedor, ETA, nacionalización y gastos.';
    }

    public function icono(): string
    {
        return 'fa-ship';
    }

    public function modulo(): string
    {
        return 'Compras';
    }

    public function color(): string
    {
        return '#154360';
    }

    public function permisoListar(): string
    {
        return 'listar-importacion';
    }

    public function rutaNativa(): string
    {
        return 'importacion';
    }

    public function recurso(): string
    {
        return ImportacionListadoColumnas::RECURSO;
    }

    public function camposQbe(): array
    {
        return ImportacionListadoFiltros::camposQbeDisponibles();
    }

    public function columnasVisibles(): array
    {
        return ListadoGrillaConfigSupport::keysVisibles(
            ImportacionListadoPreferenciasUsuario::grillaEstandar()
        );
    }

    public function etiquetasColumnas(): array
    {
        return ListadoGrillaConfigSupport::etiquetasDesdeLayout(
            ImportacionListadoPreferenciasUsuario::grillaEstandar()
        );
    }

    public function valorCelda(object $row, string $key): string
    {
        return ImportacionListadoColumnas::valorCelda($row, $key);
    }

    public function resolverFiltros(Request $request): array
    {
        return ImportacionListadoFiltros::resolverDesdeRequest($request);
    }

    public function paraQueryString(array $filtros): array
    {
        return ImportacionListadoFiltros::paraQueryString($filtros);
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        return app(ImportacionRepositoryInterface::class)->leeImportacion($filtros, $paginar);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-importacion', false)) {
            return null;
        }

        return route('editar_importacion', [
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
                'titulo' => 'Todas las importaciones',
                'descripcion' => 'Abrir el listado con consulta lista',
                'query' => ['consultar' => 1],
            ],
            [
                'clave' => 'abiertas',
                'titulo' => 'Importaciones abiertas',
                'descripcion' => 'Estado abierta',
                'query' => \App\Support\Ai\AiConsultaDolorEntidadesSupport::queryQbeCampo(
                    'estado',
                    'igual',
                    'abierta'
                ),
            ],
            [
                'clave' => 'en_transito',
                'titulo' => 'Importaciones en tránsito',
                'descripcion' => 'Estado en_transito',
                'query' => \App\Support\Ai\AiConsultaDolorEntidadesSupport::queryQbeCampo(
                    'estado',
                    'igual',
                    'en_transito'
                ),
            ],
        ];
    }
}
