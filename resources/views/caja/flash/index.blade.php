@extends("theme.$theme.layout")
@section('titulo')
    Flash diario
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/flash/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Caja\Flash\FlashCajaListadoFiltros;
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
                <h3 class="card-title">Flash diario</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('exportar-reporte-flash-caja', false))
                        <a href="{{ route('flash_caja_reporte_historico') }}" class="btn btn-outline-primary btn-sm mr-2">
                            <i class="fa fa-line-chart"></i> Reporte hist&oacute;rico
                        </a>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-flash-caja',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => FlashCajaListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => route('flash_caja', FlashCajaListadoFiltros::paraQueryStringEmpresa($filtros ?? [])),
                        'placeholder' => 'B&uacute;squeda r&aacute;pida (fecha, empresa, comentario)&hellip;',
                        'toggleTarget' => '#panel-filtros-flash-caja',
                        'toggleId' => 'btn-toggle-filtros-flash-caja',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_flash_caja', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-flash-caja',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('flash_caja') }}" id="form-filtros-flash-caja" class="mb-0">
                @include('caja.flash.partials.filtros_listado')
            </form>
            @include('caja.flash.partials.filtros_externos')
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_flash_caja',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr style="background:#85C1E9;color:#17202A;">
                            <th class="width90">Fecha</th>
                            <th class="width20">ID</th>
                            <th>Empresa</th>
                            <th class="width100 text-right">AyB</th>
                            <th class="width100 text-right">Estac.</th>
                            <th class="width100 text-right">Vending</th>
                            <th class="width100 text-right">Bingo</th>
                            <th class="width100 text-right">Win OL slots</th>
                            <th class="width100 text-right">Win OL ruletas</th>
                            <th class="width100 text-right">Gaming</th>
                            <th class="width100 text-right">Revenues</th>
                            <th class="width120">Comentario</th>
                            <th class="width160 text-nowrap" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->fecha?->format('d/m/Y') }}</td>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->empresa->nombre ?? '' }}</td>
                            <td class="text-right">{{ number_format((float) $data->ayb, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $data->estac, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $data->vending, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $data->bingo_total_venta, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $data->win_ol_slot, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $data->win_ol_rul, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format($data->total_gaming, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format($data->total_revenues, 2, ',', '.') }}</td>
                            <td>{{ $data->comentario }}</td>
                            <td class="text-nowrap">
                                @if (can('exportar-reporte-flash-caja', false))
                                    <a href="{{ route('flash_caja_reporte', ['id' => $data->id, 'formato' => 'PDF']) }}" class="btn-accion-tabla tooltipsC" title="Reporte PDF" target="_blank" rel="noopener">
                                        <i class="fa fa-file-pdf-o text-danger"></i>
                                    </a>
                                    <a href="{{ route('flash_caja_reporte', ['id' => $data->id, 'formato' => 'EXCEL']) }}" class="btn-accion-tabla tooltipsC" title="Reporte Excel">
                                        <i class="fa fa-file-excel-o text-success"></i>
                                    </a>
                                @endif
                                @if (can('editar-flash-caja', false))
                                    <a href="{{ route('editar_flash_caja', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-flash-caja', false))
                                <form action="{{ route('eliminar_flash_caja', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
