@extends("theme.$theme.layout")
@section('titulo')
Requisiciones de sala
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sala/requisicion_sala/filtro.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Requisiciones de sala</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-requisicion-sala',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => \App\Support\Sala\RequisicionSalaListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_requisicion_sala'),
                        'placeholder' => 'Búsqueda rápida…',
                        'toggleTarget' => '#panel-filtros-requisicion-sala',
                        'toggleId' => 'btn-toggle-filtros-requisicion-sala',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_requisicion_sala'),
                        'nuevoRegistroCan' => 'crear-requisicion-sala',
                        'nuevoRegistroLabel' => 'Nuevo registro',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_requisicion_sala') }}" id="form-filtros-requisicion-sala" class="mb-0">
                @include('sala.requisicion_sala.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_requisicion_sala'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_requisicion_sala',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Fecha</th>
                            <th>Empresa</th>
                            <th>Centro costo</th>
                            <th>Depósito</th>
                            <th>Zona</th>
                            <th>Prioridad</th>
                            <th>Estado</th>
                            <th>Items</th>
                            <th class="width40"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requisicion_sala as $data)
                        <tr>
                            <td>{{ $data->numerorequisicion }}</td>
                            <td>{{ date('d/m/Y', strtotime($data->fecha)) }}</td>
                            <td>{{ $data->nombreempresa }}</td>
                            <td><small>{{ $data->nombrecentrocosto }}</small></td>
                            <td><small>{{ $data->nombredeposito }}</small></td>
                            <td><small>{{ $data->nombrezona }}</small></td>
                            <td><small>{{ $data->nombreprioridad }}</small></td>
                            <td>
                                @include('sala.requisicion_sala.partials.estado_badge', ['estado' => $data->estado ?? ''])
                                @if(($data->estado ?? '') === ($estado_rechazada ?? 'RECHAZADA'))
                                    @php $motivoRechazo = \App\Support\Sala\RequisicionSalaMotivoRechazoSupport::textoVisible($data->motivo_rechazo ?? null); @endphp
                                    @if($motivoRechazo !== '')
                                    <div class="small text-danger mt-1" title="{{ $motivoRechazo }}">
                                        <i class="fa fa-comment-o" aria-hidden="true"></i>
                                        {{ \Illuminate\Support\Str::limit($motivoRechazo, 100) }}
                                    </div>
                                    @endif
                                @endif
                            </td>
                            <td>
                                @foreach ($data->requisicion_sala_articulos as $item)
                                    <small>{{ $item->articulos->sku ?? '' }}-{{ $item->articulos->descripcion ?? '' }}-Cant.:{{ $item->cantidad }}</small><br>
                                @endforeach
                            </td>
                            <td class="text-nowrap">
                                @if (can('listar-requisicion-sala', false) || can('editar-requisicion-sala', false))
                                <a href="{{ route('imprimir_pdf_requisicion_sala', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="PDF emisión" target="_blank" rel="noopener noreferrer">
                                    <i class="fa fa-file-pdf-o text-danger"></i>
                                </a>
                                @endif
                                @if (can('editar-requisicion-sala', false) || can('actualizar-requisicion-sala', false))
                                <a href="{{ route('editar_requisicion_sala', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                    <i class="fa fa-edit"></i>
                                </a>
                                @endif
                                @if (can('borrar-requisicion-sala', false) && ($data->estado ?? '') === ($estado_pendiente ?? 'PENDIENTE'))
                                <form action="{{ route('eliminar_requisicion_sala', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf
                                    @method('delete')
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer clearfix">
                {{ $requisicion_sala->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
