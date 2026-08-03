@extends("theme.$theme.layout")
@section('titulo')
    Novedades de liquidaci&oacute;n
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/sueldos/novedad/filtro.js")}}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Sueldos\NovedadSueldosListadoFiltros;
    use App\Support\Sueldos\NovedadSueldosCatalogo;
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
                <h3 class="card-title">Novedades de liquidaci&oacute;n</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('crear-novedad-sueldos', false))
                        <form action="{{ route('sincronizar_novedad_sueldos') }}" method="POST" class="d-inline mr-1"
                              onsubmit="return confirm('¿Sincronizar novedades desde Anita? Solo se agregarán las que falten.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                <i class="fa fa-fw fa-refresh"></i> Sincronizar desde Anita
                            </button>
                        </form>
                        <a href="{{ route('importar_novedad_sueldos') }}" class="btn btn-outline-secondary btn-sm mr-1">
                            <i class="fa fa-fw fa-file-excel-o"></i> Importar Excel
                        </a>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-novedad-sueldos',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => NovedadSueldosListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_novedad_sueldos'),
                        'placeholder' => 'Búsqueda rápida (legajo, empleado, concepto)…',
                        'toggleTarget' => '#panel-filtros-novedad-sueldos',
                        'toggleId' => 'btn-toggle-filtros-novedad-sueldos',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_novedad_sueldos', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-novedad-sueldos',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_novedad_sueldos') }}" id="form-filtros-novedad-sueldos" class="mb-0">
                @include('sueldos.novedad.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_novedad_sueldos'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_novedad_sueldos',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Corrida</th>
                            <th>Per&iacute;odo</th>
                            <th>Legajo</th>
                            <th>Empleado</th>
                            <th>Concepto</th>
                            <th class="text-right">Valor 1</th>
                            <th class="text-right">Valor 2</th>
                            <th>Estado</th>
                            <th>Vigencia</th>
                            <th>Origen</th>
                            <th class="text-nowrap" style="width:70px" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ optional($data->liquidacion)->numero }}</td>
                            <td>{{ $data->periodo }}</td>
                            <td>{{ optional($data->empleado)->legajo }}</td>
                            <td>{{ optional($data->empleado)->nombre }}</td>
                            <td>{{ optional($data->concepto)->codigo }} — {{ optional($data->concepto)->descripcion }}</td>
                            <td class="text-right">{{ number_format((float) $data->valor1, 2, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $data->valor2, 2, ',', '.') }}</td>
                            <td>{{ NovedadSueldosCatalogo::etiquetaEstado($data->estado) }}</td>
                            <td class="small">
                                @if ($data->fecha_desde)
                                    {{ \Illuminate\Support\Carbon::parse($data->fecha_desde)->format('d/m/Y') }}
                                    —
                                    {{ $data->fecha_hasta ? \Illuminate\Support\Carbon::parse($data->fecha_hasta)->format('d/m/Y') : '∞' }}
                                @else
                                    <span class="text-muted">one-shot</span>
                                @endif
                            </td>
                            <td>{{ NovedadSueldosCatalogo::etiquetaOrigen($data->origen) }}</td>
                            <td class="text-nowrap align-middle">
                                @if (can('editar-novedad-sueldos', false))
                                    <a href="{{route('editar_novedad_sueldos', ['id' => $data->id] + $retornoListadoQuery)}}" class="btn-accion-tabla tooltipsC" title="Editar">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-novedad-sueldos', false))
                                    <form action="{{route('eliminar_novedad_sueldos', ['id' => $data->id])}}" class="d-inline form-eliminar" method="POST">
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
        </div>
    </div>
</div>
{{ $datas->appends($filtrosQuery ?? [])->links() }}
@endsection
