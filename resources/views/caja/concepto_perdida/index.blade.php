@extends("theme.$theme.layout")
@section('titulo')
    Conceptos de p&eacute;rdida
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/concepto_perdida/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Caja\ConceptoPerdidaListadoFiltros;
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
                <h3 class="card-title">Conceptos de p&eacute;rdida</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-concepto-perdida',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ConceptoPerdidaListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('concepto_perdida'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-concepto-perdida',
                        'toggleId' => 'btn-toggle-filtros-concepto-perdida',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_concepto_perdida', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-concepto-perdida',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('concepto_perdida') }}" id="form-filtros-concepto-perdida" class="mb-0">
                @include('caja.concepto_perdida.partials.filtros_listado', [
                    'limpiarUrl' => route('concepto_perdida'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_concepto_perdida',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th class="width80">C&oacute;digo</th>
                            <th>Nombre</th>
                            <th class="width120 text-nowrap" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td class="text-nowrap">
                                @if (can('editar-concepto-perdida', false))
                                    <a href="{{ route('editar_concepto_perdida', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-concepto-perdida', false))
                                <form action="{{ route('eliminar_concepto_perdida', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
