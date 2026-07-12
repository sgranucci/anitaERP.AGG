@extends("theme.$theme.layout")
@section('titulo')
    Administración de Tickets
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ticket/administracion_ticket/filtro.js') }}" type="text/javascript"></script>
<script>
    function eliminarTicket(event) {
        if (!confirm('¿Desea eliminar el ticket?')) {
            event.preventDefault();
        }
    }
</script>
<style>
    .admin-ticket-alcance-wrap {
        background: #fff;
        border-radius: 4px;
        padding: 0.35rem 0.65rem;
        border: 1px solid rgba(0, 0, 0, 0.12);
    }
    .admin-ticket-alcance-wrap .custom-control-label {
        color: #212529 !important;
        font-weight: 600;
        cursor: pointer;
    }
</style>
@endsection

<?php
use App\Support\Ticket\AdministracionTicketListadoFiltros;
?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Administración de Tickets</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-administracion-ticket',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => AdministracionTicketListadoFiltros::tieneCriteriosUsuario($filtros ?? []),
                        'limpiarUrl' => route('consulta_administracion_ticket'),
                        'placeholder' => 'Búsqueda rápida (ID, título, comentario, técnico…)',
                        'toggleTarget' => '#panel-filtros-administracion-ticket',
                        'toggleId' => 'btn-toggle-filtros-administracion-ticket',
                        'inputId' => 'filtro_valor',
                    ])
                    <div class="admin-ticket-alcance-wrap ml-2 mb-0 align-self-center">
                        <div class="custom-control custom-checkbox mb-0">
                            <input type="hidden"
                                   name="ver_todos_tickets"
                                   value="0"
                                   form="form-filtros-administracion-ticket">
                            <input type="checkbox"
                                   class="custom-control-input"
                                   id="ver_todos_tickets"
                                   name="ver_todos_tickets"
                                   value="1"
                                   form="form-filtros-administracion-ticket"
                                   @checked($ver_todos_tickets ?? true)>
                            <label class="custom-control-label small text-nowrap"
                                   for="ver_todos_tickets"
                                   title="Desmarcado: solo tickets asignados a usted. Marcado: todos los del área Sistemas (CC {{ config('ticket.administracion_sistemas_centrocosto', '92') }})">
                                Ver todos los tickets
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <form method="get" action="{{ route('consulta_administracion_ticket') }}" id="form-filtros-administracion-ticket" class="mb-0">
                @include('ticket.administracion_ticket.partials.filtros_listado')
            </form>
            <div class="card-body table-responsive p-0">
                @if ($ver_todos_tickets ?? true)
                    <div class="alert alert-secondary py-2 mb-0 mx-3 mt-3 small">
                        <i class="fa fa-users"></i>
                        Mostrando <strong>todos los tickets</strong> del área Sistemas / Tecnología.
                    </div>
                @else
                    <div class="alert alert-light border py-2 mb-0 mx-3 mt-3 small text-muted">
                        <i class="fa fa-user"></i>
                        Mostrando tickets <strong>asignados a usted</strong>.
                        Marque «Ver todos los tickets» para ver el resto del equipo.
                    </div>
                @endif
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_administracion_ticket',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    @include('ticket.administracion_ticket.partials.tabla_datos', [
                        'ticket' => $ticket,
                        'mostrar_acciones' => true,
                        'puede_ver_ticket' => can('editar-ticket', false),
                        'retornoListadoQuery' => $retornoListadoQuery,
                    ])
                </table>
            </div>
            <div class="card-footer">
                {{ $ticket->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
