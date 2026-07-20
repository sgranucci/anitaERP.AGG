@extends("theme.$theme.layout")
@section('titulo')
    Par&aacute;metros flash
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/flash/parametro/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Caja\Flash\FlashParametroListadoFiltros;
    use App\Support\Caja\Flash\FlashParametroPeriodoSupport;
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
                <h3 class="card-title">Par&aacute;metros flash (mensuales)</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-flash-parametro',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => FlashParametroListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('flash_parametro'),
                        'placeholder' => 'Búsqueda rápida (período, empresa)…',
                        'toggleTarget' => '#panel-filtros-flash-parametro',
                        'toggleId' => 'btn-toggle-filtros-flash-parametro',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_flash_parametro', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-flash-parametro',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('flash_parametro') }}" id="form-filtros-flash-parametro" class="mb-0">
                @include('caja.flash.parametro.partials.filtros_listado', [
                    'limpiarUrl' => route('flash_parametro'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_flash_parametro',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th class="width120">Per&iacute;odo</th>
                            <th>Empresa</th>
                            <th class="width110 text-right">Budget total</th>
                            <th class="width100 text-right">Slots</th>
                            <th class="width100 text-right">Bingo</th>
                            <th class="width100 text-right">F&amp;B</th>
                            <th class="width100 text-right">Estac.</th>
                            <th class="width80 text-right">POS</th>
                            <th class="width100 text-center">D&iacute;as</th>
                            <th class="width120 text-center text-nowrap" data-orderable="false">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ FlashParametroPeriodoSupport::labelPeriodo((string) $data->periodo) }}</td>
                            <td>{{ $data->empresa->nombre ?? '' }}</td>
                            <td class="text-right">{{ number_format((float) $data->budget_total, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $data->budget_slot, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $data->budget_bingo, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $data->budget_f_b, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $data->budget_estac, 2, ',', '.') }}</td>
                            <td class="text-right">{{ $data->budget_pos }}</td>
                            <td class="text-center">{{ $data->indices->count() }}</td>
                            <td class="text-center text-nowrap">
                                @if (can('editar-flash-parametro', false))
                                    <a href="{{ route('editar_flash_parametro', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-flash-parametro', false))
                                <form action="{{ route('eliminar_flash_parametro', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
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
            @if (method_exists($datas, 'links'))
                <div class="card-footer clearfix">
                    {{ $datas->appends($filtrosQuery ?? [])->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
