@extends("theme.$theme.layout")
@section('titulo')
    Asientos contables
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/asiento/filtro.js")}}" type="text/javascript"></script>
<script>
    function eliminarAsiento(event) {
        if (!confirm('¿Desea eliminar el asiento?')) {
            event.preventDefault();
        }
    }
</script>
@endsection

@section('contenido')
@php
    use App\Support\Contable\AsientoListadoFiltros;
    use App\Support\Listado\QueryRetornoListado;
    $retornoListadoQuery = QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('asiento', AsientoListadoFiltros::paraQueryStringEmpresa($filtros ?? []));
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Asientos Contables</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('crear-asiento', false))
                        <a href="{{ route('crear_importacion_asiento') }}" class="btn btn-outline-success btn-sm mr-1" title="Importar asientos desde Excel">
                            <i class="fa fa-fw fa-file-excel"></i> Importar Excel
                        </a>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-asiento',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => AsientoListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (número, tipo, fecha…)…',
                        'toggleTarget' => '#panel-filtros-asiento',
                        'toggleId' => 'btn-toggle-filtros-asiento',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_asiento', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-asiento',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('asiento') }}" id="form-filtros-asiento" class="mb-0">
                @include('contable.asiento.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            @include('contable.asiento.partials.filtros_externos')
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_asiento',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>Empresa</th>
                            <th>Número</th>
                            <th>Fecha</th>
                            <th>Tipo de asiento</th>
                            <th>Estado</th>
                            <th>Observaciones</th>
                            <th>Monto</th>
                            <th>Movimientos</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($asientos as $data)
                        <tr>
                            <td>{{$data->id}}</td>
                            <td>{{$data->nombreempresa}}</td>
                            <td>{{$data->numeroasiento}}</td>
                            <td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
                            <td>{{$data->nombretipoasiento}}</td>
                            <td>
                                @include('contable.asiento.partials.estado_aprobacion_badge', [
                                    'estado' => $data->estado_aprobacion ?? 'confirmado',
                                ])
                            </td>
                            <td>{{$data->observacion ?? ''}}</td>
                            <td>
                                @php $totalAsiento = 0; @endphp
                                @foreach($data->asiento_movimientos as $movimiento)
                                    @php $totalAsiento += ($movimiento->monto > 0 ? $movimiento->monto : 0); @endphp
                                @endforeach
                                {{number_format($totalAsiento,2)}}
                            </td>
                            <td>
                                <ul class="mb-0 pl-3">
                                @foreach($data->asiento_movimientos as $movimiento)
                                    <li>{{ $movimiento->cuentacontables->nombre }} {{ $movimiento->monto > 0 ? number_format($movimiento->monto,2) : '' }} {{ $movimiento->monto < 0 ? number_format($movimiento->monto,2) : ''}}</li>
                                @endforeach
                                </ul>
                            </td>
                            <td>
                       			@if (($data->estado_aprobacion ?? 'confirmado') === 'pendiente')
                                    @if (can('listar-aprobacion-asiento', false) || can('aprobar-asiento-pendiente', false))
                                        <a href="{{ route('ver_aprobacion_asiento', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Revisar aprobación">
                                            <i class="fa fa-search text-info"></i>
                                        </a>
                                    @endif
                                    @if (can('aprobar-asiento-pendiente', false))
                                        <form action="{{ route('aprobar_asiento_pendiente', ['id' => $data->id]) }}" class="d-inline" method="POST" onsubmit="return confirm('¿Aprobar y sincronizar este asiento con contabilidad?');">
                                            @csrf
                                            <input type="hidden" name="redirect" value="asiento">
                                            <button type="submit" class="btn-accion-tabla tooltipsC" title="Aprobar asiento">
                                                <i class="fa fa-check text-success"></i>
                                            </button>
                                        </form>
                                    @endif
                                @endif
                       			@if (can('editar-asiento', false) && ($data->estado_aprobacion ?? 'confirmado') !== 'pendiente')
                                	<a href="{{route('editar_asiento', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-asiento', false) && ($data->estado_aprobacion ?? 'confirmado') !== 'pendiente')
                                <form action="{{route('eliminar_asiento', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" onclick="eliminarAsiento(event)" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
								@endif
                       			@if (can('listar-asiento', false) || can('editar-asiento', false))
                                	<a href="{{ route('imprimir_pdf_asiento', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Emitir asiento en PDF" target="_blank" rel="noopener noreferrer">
                                    <i class="fas fa-file-pdf text-danger"></i>
                                	</a>
                                	<a href="{{ route('imprimir_excel_asiento', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Emitir asiento en Excel" target="_blank" rel="noopener noreferrer">
                                    <i class="fas fa-file-excel text-success"></i>
                                	</a>
								@endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $asientos->appends($filtrosQuery ?? [])->links() }}
@endsection
