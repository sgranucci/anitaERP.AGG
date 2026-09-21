@extends("theme.$theme.layout")
@section('titulo')
    Turnos Facturación Local
@endsection
@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/turno_local/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Ventas\TurnoLocalListadoFiltros;
    $limpiarUrl = route('facturacion_local_turno');
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Turnos</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-turno-local',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => TurnoLocalListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-turno-local',
                        'toggleId' => 'btn-toggle-filtros-turno-local',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_turno_local'),
                        'nuevoRegistroCan' => 'crear-turno-local',
                    ])
                    <a href="{{ route('facturacion_local_turnos') }}" class="btn btn-outline-info btn-sm ml-1">
                        Cierres
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('facturacion_local_turno') }}" id="form-filtros-turno-local" class="mb-0">
                @include('ventas.facturacion_local.turno_local.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_turno_local',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>ID</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Empresa</th>
                            <th>Horario</th>
                            <th>Orden</th>
                            <th>Activo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->codigo ?? '—' }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->empresa->nombre ?? '' }}</td>
                            <td>{{ $data->etiquetaHorario() }}</td>
                            <td>{{ $data->orden }}</td>
                            <td>{{ $data->activo ? 'Sí' : 'No' }}</td>
                            <td class="text-nowrap">
                                @if (can('editar-turno-local', false))
                                    <a href="{{ route('editar_turno_local', $data->id) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-turno-local', false))
                                    <a href="{{ route('eliminar_turno_local', $data->id) }}" class="btn-accion-tabla tooltipsC eliminar-registro" title="Eliminar">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center p-4">
                                No hay turnos. Use <strong>Nuevo registro</strong> para crear Mañana / Tarde / Noche.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $datas->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
