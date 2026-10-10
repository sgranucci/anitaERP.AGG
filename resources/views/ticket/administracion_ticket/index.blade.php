@extends("theme.$theme.layout")
@section('titulo')
    Administración de Tickets
@endsection

@section("styles")
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ticket/administracion_ticket/filtro.js') }}" type="text/javascript"></script>
@php
    $qbeGruposJs = public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js');
    $ordenJs = public_path('assets/pages/scripts/listado/workbench-orden.js');
    $agruparJs = public_path('assets/pages/scripts/listado/workbench-agrupar.js');
    $disenadorJs = public_path('assets/pages/scripts/listado/workbench-disenador-preview.js');
    $vistaGuardarJs = public_path('assets/pages/scripts/listado/workbench-vista-guardar.js');
    $ticketWorkbenchJs = public_path('assets/pages/scripts/ticket/administracion_ticket/workbench.js');
@endphp
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ file_exists($qbeGruposJs) ? filemtime($qbeGruposJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ file_exists($ordenJs) ? filemtime($ordenJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-agrupar.js') }}?v={{ file_exists($agruparJs) ? filemtime($agruparJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-disenador-preview.js') }}?v={{ file_exists($disenadorJs) ? filemtime($disenadorJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-vista-guardar.js') }}?v={{ file_exists($vistaGuardarJs) ? filemtime($vistaGuardarJs) : time() }}"></script>
<script src="{{ asset('assets/pages/scripts/ticket/administracion_ticket/workbench.js') }}?v={{ file_exists($ticketWorkbenchJs) ? filemtime($ticketWorkbenchJs) : time() }}"></script>
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
    $columnasVisibles = $columnasVisibles ?? [];
    $camposOrdenablesThead = AdministracionTicketListadoFiltros::camposOrdenables();
    $ordenActualThead = \App\Support\Listado\ListadoOrdenamientoSupport::normalizar(
        $filtros['sort'] ?? [],
        $camposOrdenablesThead
    );
    $qsQuitarOrden = $filtrosQuery ?? [];
    unset($qsQuitarOrden['sort']);
    $qsQuitarOrden['quitar_orden'] = 1;
    $urlQuitarOrden = route('consulta_administracion_ticket', $qsQuitarOrden);
    $qbeAbierto = \App\Support\Listado\ListadoQbeSupport::tieneCriterios($filtros['qbe'] ?? []);
    $puedeVerTicket = can('editar-ticket', false);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info lw-workbench shadow-sm">
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
            @if (! ($workbenchListo ?? false))
                <div class="alert alert-warning lw-aviso-migracion mb-0">
                    <strong>Migración pendiente.</strong>
                    Para vistas y configuración de grilla hace falta la tabla <code>listado_vista</code>.
                </div>
            @endif
            <form method="get" action="{{ route('consulta_administracion_ticket') }}" id="form-filtros-administracion-ticket" class="mb-0">
                @include('ticket.administracion_ticket.partials.filtros_listado')
                <input type="hidden" name="columnas" id="lw_columnas_csv" value="{{ implode(',', $columnasVisibles) }}">
                @if ($vistaActiva ?? null)
                    <input type="hidden" name="vista_id" value="{{ $vistaActiva->id }}">
                @endif
                <div class="lw-toolbar">
                    <div class="lw-toolbar-left">
                        <select id="lw-vista-select" class="form-control form-control-sm lw-vista-select"
                                data-base-url="{{ route('consulta_administracion_ticket') }}"
                                title="Vistas guardadas"
                                @if (! ($workbenchListo ?? false)) disabled @endif>
                            <option value="">Vista estándar</option>
                            @foreach (($vistasListado ?? []) as $vista)
                                <option value="{{ $vista->id }}" @if (($vistaActiva ?? null) && (int) $vistaActiva->id === (int) $vista->id) selected @endif>
                                    {{ $vista->nombre }}
                                    @if ($vista->es_default) ★ @endif
                                    @if ($vista->compartida) (compartida) @endif
                                </option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modal-lw-grilla"
                                @if (! ($workbenchListo ?? false)) disabled title="Requiere migración" @endif>
                            <i class="fa fa-th"></i> Diseñar vista
                        </button>
                        <button type="button" class="btn btn-sm {{ $qbeAbierto ? 'btn-info' : 'btn-outline-info' }} {{ $qbeAbierto ? '' : 'collapsed' }}" data-toggle="collapse" data-target="#lw-qbe-panel" aria-expanded="{{ $qbeAbierto ? 'true' : 'false' }}" aria-controls="lw-qbe-panel" title="Consulta avanzada">
                            <i class="fa fa-filter"></i> Consulta avanzada
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-info collapsed" data-toggle="collapse" data-target="#lw-analisis-panel" aria-expanded="false" title="Gráfico, color de fila y columnas calculadas">
                            <i class="fa fa-bar-chart"></i> Visual
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary collapsed" data-toggle="collapse" data-target="#lw-mail-panel" aria-expanded="false" aria-controls="lw-mail-panel" title="Enviar este listado por correo">
                            <i class="fa fa-envelope"></i> Enviar por mail
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#modal-lw-etiquetas"
                                @if (! ($workbenchListo ?? false)) disabled title="Requiere migración" @endif>
                            <i class="fa fa-font"></i> Defaults instalación
                        </button>
                    </div>
                    <div class="lw-toolbar-right">
                        @if ($ordenActualThead !== [])
                            <a href="{{ $urlQuitarOrden }}" class="btn btn-sm btn-outline-secondary" title="Vuelve al orden por ID descendente y lo saca de la vista">
                                <i class="fa fa-sort"></i> Quitar orden
                            </a>
                        @endif
                    </div>
                </div>
                @include('ticket.administracion_ticket.partials.workbench_qbe')
                @php
                    $camposDelVisual = AdministracionTicketListadoFiltros::camposVisual();
                    $ejesVisual = $camposDelVisual;
                    $medidasVisual = [];
                    foreach (\App\Support\Listado\ListadoMedidaSupport::catalogo($camposDelVisual) as $keyMedida => $metaMedida) {
                        $medidasVisual[$keyMedida] = $metaMedida['label'];
                    }
                    $lienzoVisual = true;
                    $calculadasVisual = true;
                @endphp
                @include('includes.listado.workbench_visual')
            </form>
            @php
                $mailListado = trim((string) (auth()->user()->email ?? ''));
            @endphp
            <div class="collapse" id="lw-mail-panel">
                <form method="post" action="{{ route('enviar_listado_administracion_ticket', $filtrosQuery ?? []) }}" class="px-3 pb-2 mb-0">
                    @csrf
                    <div class="border rounded p-2" style="background:#f8fbfd;">
                        <div class="d-flex flex-wrap align-items-center" style="gap:.5rem;">
                            <label class="small mb-0" for="email-listado-ticket">Enviar este listado</label>
                            <input type="email" name="email" id="email-listado-ticket" class="form-control form-control-sm" required
                                   style="max-width:22rem;" value="{{ $mailListado }}" placeholder="correo@empresa.com">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                <i class="fa fa-envelope"></i> Mandar Excel ahora
                            </button>
                            <select name="programar" class="form-control form-control-sm" style="max-width:11rem;">
                                <option value="">Solo ahora</option>
                                <option value="diaria">Todos los días</option>
                                <option value="semanal">Cada lunes</option>
                            </select>
                            <span class="small text-muted">Sale con el filtro y el alcance de esta pantalla. Si hay más de 2.000 filas, el archivo corta ahí. Lo programado se manda a las 07:05.</span>
                        </div>
                    </div>
                    @if (($enviosProgramados ?? collect())->isNotEmpty())
                        <ul class="small mb-0 mt-2 pl-3">
                            @foreach ($enviosProgramados as $envio)
                                <li>
                                    {{ $envio->email }} · {{ $envio->frecuencia === 'semanal' ? 'cada lunes' : 'todos los días' }}
                                    <button type="submit" class="btn btn-link btn-sm p-0 align-baseline" form="form-baja-envio-ticket-{{ $envio->id }}">Dar de baja</button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </form>
                @foreach (($enviosProgramados ?? collect()) as $envio)
                    <form id="form-baja-envio-ticket-{{ $envio->id }}" method="post" action="{{ route('baja_envio_administracion_ticket', $envio->id) }}" class="d-none">
                        @csrf
                    </form>
                @endforeach
            </div>
            @include('includes.listado.workbench_grafico', [
                'recursoVisual' => \App\Support\Ticket\AdministracionTicketListadoColumnas::RECURSO,
                'rutaListadoVisual' => 'consulta_administracion_ticket',
            ])
            <div class="card-body py-2 border-bottom bg-white d-flex flex-wrap align-items-center justify-content-between">
                <div class="mb-1 mb-md-0">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'lista_administracion_ticket',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                </div>
                <div class="mb-1 mb-md-0 ml-auto">
                    @include('ticket.administracion_ticket.partials.filtros_externos')
                </div>
            </div>
            <div class="px-3 pt-2">
                @include('includes.listado.workbench_cortes', ['cortes' => $cortes ?? []])
            </div>
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
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @foreach ($columnasVisibles as $keyColumna)
                                @include('ticket.administracion_ticket.partials.workbench_th', ['key' => $keyColumna])
                            @endforeach
                            @foreach (($filtros['calculadas'] ?? []) as $calc)
                                <th data-orderable="false">{{ $calc['etiqueta'] ?? '' }}</th>
                            @endforeach
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ticket as $data)
                            @php
                                $tonoFila = \App\Support\Listado\ListadoVisualSupport::tonoFila(
                                    $data,
                                    $filtros['formato'] ?? [],
                                    static fn ($row, $key) => \App\Support\Ticket\AdministracionTicketListadoColumnas::valorCelda($row, $key)
                                );
                            @endphp
                            <tr @class(['lw-tono-danger' => $tonoFila === 'danger', 'lw-tono-warning' => $tonoFila === 'warning', 'lw-tono-success' => $tonoFila === 'success'])>
                                @foreach ($columnasVisibles as $keyColumna)
                                    @include('ticket.administracion_ticket.partials.workbench_celda', [
                                        'key' => $keyColumna,
                                        'data' => $data,
                                        'puedeVerTicket' => $puedeVerTicket,
                                    ])
                                @endforeach
                                @foreach (($filtros['calculadas'] ?? []) as $iCalc => $calc)
                                    <td>{{ $data->{'calc_'.$iCalc} ?? '' }}</td>
                                @endforeach
                                <td>
                                    @if ($puedeVerTicket)
                                        <a href="{{ route('edita_administracion_ticket', ['id' => $data->id] + ($retornoListadoQuery ?? [])) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('borrar-ticket', false))
                                        <form action="{{ route('elimina_administracion_ticket', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                            @csrf @method('delete')
                                            <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro" onclick="eliminarTicket(event)">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columnasVisibles) + count($filtros['calculadas'] ?? []) + 1 }}" class="text-center text-muted py-4">
                                    No hay tickets con los filtros aplicados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $ticket->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@include('caja.ingresoegreso.partials.workbench_modales', [
    'rutaGuardarColumnas' => 'guardar_columnas_listado_administracion_ticket',
    'rutaGuardarVista' => 'guardar_vista_listado_administracion_ticket',
    'rutaEliminarVista' => 'eliminar_vista_listado_administracion_ticket',
    'rutaPreview' => 'preview_workbench_administracion_ticket',
    'rutaGuardarEtiquetas' => 'guardar_etiquetas_listado_administracion_ticket',
    'catalogo' => $catalogoColumnas ?? \App\Support\Ticket\AdministracionTicketListadoColumnas::catalogoActivo(),
])
@endsection
