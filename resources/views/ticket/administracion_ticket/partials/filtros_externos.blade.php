@php
    use App\Support\Ticket\AdministracionTicketListadoFiltros;

    $estadoActual = AdministracionTicketListadoFiltros::normalizarFiltroEstado(
        (string) ($filtros['filtro_estado'] ?? AdministracionTicketListadoFiltros::FILTRO_ESTADO_EN_CURSO)
    );
    $baseQ = $filtrosQuery ?? [];
    $rutaIndex = 'consulta_administracion_ticket';

    $urlEstado = function (string $cod) use ($baseQ, $rutaIndex) {
        $q = $baseQ;
        unset($q['filtro_estado'], $q['estado']);
        if ($cod === AdministracionTicketListadoFiltros::FILTRO_ESTADO_TODOS) {
            $q['filtro_estado'] = AdministracionTicketListadoFiltros::FILTRO_ESTADO_TODOS;
        } elseif ($cod !== AdministracionTicketListadoFiltros::FILTRO_ESTADO_EN_CURSO) {
            $q['filtro_estado'] = $cod;
        }

        return route($rutaIndex, $q);
    };

    $claseBoton = function (string $cod) use ($estadoActual): string {
        $activo = $estadoActual === $cod;
        if ($cod === AdministracionTicketListadoFiltros::FILTRO_ESTADO_EN_CURSO) {
            return $activo ? 'btn-info' : 'btn-outline-info';
        }
        if ($cod === 'Finalizado') {
            return $activo ? 'btn-success' : 'btn-outline-success';
        }
        if ($cod === 'Suspendido') {
            return $activo ? 'btn-secondary' : 'btn-outline-secondary';
        }
        if ($cod === AdministracionTicketListadoFiltros::FILTRO_ESTADO_TODOS) {
            return $activo ? 'btn-primary' : 'btn-outline-primary';
        }

        return $activo ? 'btn-warning' : 'btn-outline-warning';
    };
@endphp
<div class="d-flex flex-wrap align-items-center justify-content-end">
    <span class="text-muted small mr-2 mb-0"><i class="fa fa-filter"></i> Estado:</span>
    <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Filtro de estado">
        @foreach (AdministracionTicketListadoFiltros::opcionesFiltroEstadoExterno() as $opcion)
            <a href="{{ $urlEstado($opcion['valor']) }}"
               class="btn {{ $claseBoton($opcion['valor']) }}"
               title="{{ AdministracionTicketListadoFiltros::etiquetaFiltroEstado($opcion['valor']) }}">
                {{ $opcion['label'] }}
            </a>
        @endforeach
    </div>
</div>
