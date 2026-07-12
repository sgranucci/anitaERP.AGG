@extends("theme.$theme.layout")
@section('titulo')
    Cartones de bingo
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/bingo/carton/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Caja\Bingo\BingoCartonListadoFiltros;
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
                <h3 class="card-title">Cartones</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-bingo-carton',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => BingoCartonListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('bingo_carton'),
                        'placeholder' => 'B&uacute;squeda r&aacute;pida (tolera errores de tipeo)&hellip;',
                        'toggleTarget' => '#panel-filtros-bingo-carton',
                        'toggleId' => 'btn-toggle-filtros-bingo-carton',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_bingo_carton', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-bingo-carton',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('bingo_carton') }}" id="form-filtros-bingo-carton" class="mb-0">
                @include('caja.bingo.carton.partials.filtros_listado', [
                    'limpiarUrl' => route('bingo_carton'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_bingo_carton',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th class="width100">C&oacute;digo</th>
                            <th>Nombre</th>
                            <th class="width100 text-right">Precio</th>
                            <th class="width60 text-center">L&iacute;neas</th>
                            <th>Empresa</th>
                            <th class="width120">Estado</th>
                            <th class="width90 text-center">Vista previa</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td class="text-right">{{ number_format((float) $data->precio_unitario, 2, ',', '.') }}</td>
                            <td class="text-center">{{ $data->lineas }}</td>
                            <td>{{ $data->empresa->nombre ?? '' }}</td>
                            <td>{{ $data->estado_label }}</td>
                            <td class="text-center p-1">
                                @include('caja.bingo.carton.partials.vista_previa_carton', [
                                    'data' => $data,
                                    'mini' => true,
                                ])
                            </td>
                            <td>
                                @if (can('editar-bingo-carton', false))
                                    <a href="{{ route('editar_bingo_carton', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-bingo-carton', false))
                                <form action="{{ route('eliminar_bingo_carton', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
