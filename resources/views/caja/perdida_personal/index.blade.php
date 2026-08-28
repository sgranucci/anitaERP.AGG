@extends("theme.$theme.layout")
@section('titulo')
    P&eacute;rdidas de personal
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/perdida_personal/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Caja\PerdidaPersonalListadoFiltros;
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
                <h3 class="card-title">P&eacute;rdidas de personal</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-perdida-personal',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => PerdidaPersonalListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('perdida_personal'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-perdida-personal',
                        'toggleId' => 'btn-toggle-filtros-perdida-personal',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_perdida_personal', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-perdida-personal',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('perdida_personal') }}" id="form-filtros-perdida-personal" class="mb-0">
                @include('caja.perdida_personal.partials.filtros_listado', [
                    'limpiarUrl' => route('perdida_personal'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_perdida_personal',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>N&uacute;mero</th>
                            <th>Fecha</th>
                            <th>Empresa</th>
                            <th>Empleado</th>
                            <th>Concepto</th>
                            <th>Imputaci&oacute;n</th>
                            <th>Turno</th>
                            <th class="text-right">Importe</th>
                            <th>Estado</th>
                            <th class="width120 text-nowrap" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->numero }}</td>
                            <td>{{ optional($data->fecha)->format('d/m/Y') }}</td>
                            <td>{{ $data->empresa->nombre ?? '' }}</td>
                            <td>
                                @if ($data->empleado)
                                    {{ $data->empleado->legajo }} — {{ $data->empleado->nombre }}
                                @endif
                            </td>
                            <td>
                                @if ($data->conceptoPerdida)
                                    {{ $data->conceptoPerdida->codigo }} — {{ $data->conceptoPerdida->nombre }}
                                @endif
                            </td>
                            <td>
                                @if ($data->imputacionPerdida)
                                    {{ $data->imputacionPerdida->codigo }} — {{ $data->imputacionPerdida->nombre }}
                                @endif
                            </td>
                            <td>{{ $data->turno_label }}</td>
                            <td class="text-right">{{ number_format((float) $data->importe, 2, ',', '.') }}</td>
                            <td>{{ $data->estado_label }}</td>
                            <td class="text-nowrap">
                                @if (can('listar-perdida-personal', false) || can('editar-perdida-personal', false))
                                    <a href="{{ route('imprimir_pdf_perdida_personal', $data->id) }}"
                                       class="btn-accion-tabla tooltipsC"
                                       title="Imprimir constancia PDF para firma del empleado"
                                       target="_blank" rel="noopener noreferrer">
                                        <i class="fas fa-file-pdf text-danger"></i>
                                    </a>
                                @endif
                                @if (can('editar-perdida-personal', false))
                                    <a href="{{ route('editar_perdida_personal', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-perdida-personal', false))
                                <form action="{{ route('eliminar_perdida_personal', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
