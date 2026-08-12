@extends("theme.$theme.layout")
@section('titulo')
    Numeradores del sistema
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/sistema_numerador/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Configuracion\SistemaNumeradorListadoFiltros;
@endphp

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Numeradores del sistema</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-sistema-numerador',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => SistemaNumeradorListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('sistema_numerador'),
                        'placeholder' => 'Búsqueda rápida (código, nombre, módulo)…',
                        'toggleTarget' => '#panel-filtros-sistema-numerador',
                        'toggleId' => 'btn-toggle-filtros-sistema-numerador',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_sistema_numerador', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-sistema-numerador',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('sistema_numerador') }}" id="form-filtros-sistema-numerador" class="mb-0">
                @include('configuracion.sistema_numerador.partials.filtros_listado', [
                    'limpiarUrl' => route('sistema_numerador'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_sistema_numerador',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Empresa</th>
                            <th>Módulo</th>
                            <th class="text-right">Último nro</th>
                            <th>Anita</th>
                            <th>Activo</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->empresa->nombre ?? $data->empresa_id }}</td>
                            <td>{{ $data->modulo }}</td>
                            <td class="text-right">{{ number_format((int) $data->ultimo_numero, 0, ',', '.') }}</td>
                            <td>
                                @if($data->anita_clave)
                                    {{ $data->anita_sistema }}/{{ $data->anita_clave }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $data->activo ? 'Sí' : 'No' }}</td>
                            <td>
                                @if (can('editar-sistema-numerador', false))
                                    <a href="{{ route('editar_sistema_numerador', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-sistema-numerador', false))
                                <form action="{{ route('eliminar_sistema_numerador', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
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
            @if (method_exists($datas, 'links'))
                <div class="card-footer clearfix">
                    {{ $datas->appends($filtrosQuery ?? [])->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
