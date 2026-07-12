@extends("theme.$theme.layout")
@section('titulo')
    Conceptos de rendici&oacute;n de bingo
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/bingo/concepto_rendicion/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Caja\Bingo\BingoConceptoRendicionListadoFiltros;
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
                <h3 class="card-title">Conceptos de rendici&oacute;n</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-bingo-concepto-rendicion',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => BingoConceptoRendicionListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('bingo_concepto_rendicion'),
                        'placeholder' => 'B&uacute;squeda r&aacute;pida (tolera errores de tipeo)&hellip;',
                        'toggleTarget' => '#panel-filtros-bingo-concepto-rendicion',
                        'toggleId' => 'btn-toggle-filtros-bingo-concepto-rendicion',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_bingo_concepto_rendicion', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-bingo-concepto-rendicion',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('bingo_concepto_rendicion') }}" id="form-filtros-bingo-concepto-rendicion" class="mb-0">
                @include('caja.bingo.concepto_rendicion.partials.filtros_listado', [
                    'limpiarUrl' => route('bingo_concepto_rendicion'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_bingo_concepto_rendicion',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th class="width100">C&oacute;digo</th>
                            <th>Detalle</th>
                            <th class="width80 text-center">Signo</th>
                            <th class="width90 text-right">Porcentaje</th>
                            <th>Base c&aacute;lculo</th>
                            <th>Empresa</th>
                            <th class="width120">Estado</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->detalle }}</td>
                            <td class="text-center">{{ $data->signo }}</td>
                            <td class="text-right">
                                @if ($data->porcentaje !== null && $data->porcentaje !== '')
                                    {{ number_format((float) $data->porcentaje, 4, ',', '.') }}%
                                @endif
                            </td>
                            <td>{{ $data->base_calculo_label }}</td>
                            <td>{{ $data->empresa->nombre ?? '' }}</td>
                            <td>{{ $data->estado_label }}</td>
                            <td>
                                @if (can('editar-bingo-concepto-rendicion', false))
                                    <a href="{{ route('editar_bingo_concepto_rendicion', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-bingo-concepto-rendicion', false))
                                <form action="{{ route('eliminar_bingo_concepto_rendicion', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
