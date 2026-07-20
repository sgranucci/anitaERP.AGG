@extends("theme.$theme.layout")
@section('titulo')
    Días feriados
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/feriado/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Configuracion\FeriadoListadoFiltros;
@endphp

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $puedeImportar = can('importar-feriado', false);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Días feriados</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-feriado',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => FeriadoListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('feriado'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-feriado',
                        'toggleId' => 'btn-toggle-filtros-feriado',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_feriado', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-feriado',
                    ])
                </div>
            </div>

            @if ($puedeImportar)
            <div class="card-body border-bottom py-2 bg-light">
                <form action="{{ route('importar_feriado', $filtrosQuery ?? []) }}" method="POST" class="form-inline"
                      id="form-importar-feriado"
                      onsubmit="return confirm('¿Importar los feriados de Argentina del año indicado? Se agregarán los que aún no existan.');">
                    @csrf
                    <label for="anio" class="mr-2 mb-0"><i class="fa fa-cloud-download-alt"></i> Importar feriados de Argentina</label>
                    <input type="number" name="anio" id="anio" class="form-control form-control-sm mr-2" style="width: 110px;"
                           min="1900" max="2100" value="{{ $anioSugerido ?? date('Y') }}" required>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="fa fa-download"></i> Importar del año
                    </button>
                    <small class="text-muted ml-3">Fuente pública argentinadatos.com. No duplica fechas ya cargadas.</small>
                </form>
            </div>
            @endif

            <form method="get" action="{{ route('feriado') }}" id="form-filtros-feriado" class="mb-0">
                @include('configuracion.feriado.partials.filtros_listado', [
                    'limpiarUrl' => route('feriado'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_feriado',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th style="width: 55%;">Nombre</th>
                            <th class="text-nowrap" style="width: 110px;">Fecha</th>
                            <th style="width: 15%;">Tipo</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td class="text-nowrap">{{ $data->nombre }}</td>
                            <td class="text-nowrap">{{ $data->fecha ? \Illuminate\Support\Carbon::parse($data->fecha)->format('d/m/Y') : '' }}</td>
                            <td>{{ $data->tipo }}</td>
                            <td>
                                @if (can('editar-feriado', false))
                                    <a href="{{ route('editar_feriado', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-feriado', false))
                                <form action="{{ route('eliminar_feriado', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
