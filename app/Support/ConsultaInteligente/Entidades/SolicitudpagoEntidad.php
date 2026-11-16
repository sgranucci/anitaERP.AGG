<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente\Entidades;

use App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface;
use App\Support\ConsultaInteligente\EntidadSemantica;
use App\Support\Listado\ListadoGrillaConfigSupport;
use App\Support\Solicitudpago\SolicitudpagoListadoColumnas;
use App\Support\Solicitudpago\SolicitudpagoListadoFiltros;
use App\Support\Solicitudpago\SolicitudpagoListadoPreferenciasUsuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class SolicitudpagoEntidad implements EntidadSemantica
{
    public function clave(): string
    {
        return 'solicitudpago';
    }

    public function nombre(): string
    {
        return 'Solicitudes de pago';
    }

    public function descripcion(): string
    {
        return 'Solicitudes de pago: estado, concepto, importe y circuito de aprobación.';
    }

    public function icono(): string
    {
        return 'fa-hand-holding-usd';
    }

    public function modulo(): string
    {
        return 'Solicitud de pago';
    }

    public function color(): string
    {
        return '#196F3D';
    }

    public function permisoListar(): string
    {
        return 'listar-solicitud-pago';
    }

    public function rutaNativa(): string
    {
        return 'consultar_solicitudpago';
    }

    public function recurso(): string
    {
        return SolicitudpagoListadoColumnas::RECURSO;
    }

    public function camposQbe(): array
    {
        return SolicitudpagoListadoFiltros::camposQbeDisponibles();
    }

    public function columnasVisibles(): array
    {
        return ListadoGrillaConfigSupport::keysVisibles(
            SolicitudpagoListadoPreferenciasUsuario::grillaEstandar()
        );
    }

    public function etiquetasColumnas(): array
    {
        return ListadoGrillaConfigSupport::etiquetasDesdeLayout(
            SolicitudpagoListadoPreferenciasUsuario::grillaEstandar()
        );
    }

    public function valorCelda(object $row, string $key): string
    {
        return SolicitudpagoListadoColumnas::valorCelda($row, $key);
    }

    public function resolverFiltros(Request $request): array
    {
        return SolicitudpagoListadoFiltros::resolverDesdeRequest($request);
    }

    public function paraQueryString(array $filtros): array
    {
        return SolicitudpagoListadoFiltros::paraQueryString($filtros);
    }

    public function consultar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        return app(SolicitudpagoRepositoryInterface::class)->leeSolicitudpago($filtros, $paginar);
    }

    public function urlDetalle(object $row): ?string
    {
        $id = (int) ($row->id ?? 0);
        if ($id < 1 || ! can('editar-solicitud-pago', false)) {
            return null;
        }

        return route('editar_solicitudpago', [
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
                'titulo' => 'Todas las solicitudes',
                'descripcion' => 'Abrir el listado con consulta lista',
                'query' => ['consultar' => 1],
            ],
            [
                'clave' => 'emitidas',
                'titulo' => 'Solicitudes emitidas',
                'descripcion' => 'Circuito: EMITIDA (pendientes de control)',
                'query' => \App\Support\Ai\AiConsultaDolorEntidadesSupport::queryQbeCampo(
                    'estado',
                    'igual',
                    'EMITIDA'
                ),
            ],
            [
                'clave' => 'controladas',
                'titulo' => 'Solicitudes controladas',
                'descripcion' => 'Circuito: CONTROLADA (pendientes de autorización)',
                'query' => \App\Support\Ai\AiConsultaDolorEntidadesSupport::queryQbeCampo(
                    'estado',
                    'igual',
                    'CONTROLADA'
                ),
            ],
        ];
    }
}
