@extends("theme.$theme.layout")
@section('titulo')
    Regímenes de percepción
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/regimen_percepcion/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Configuracion\RegimenPercepcionListadoFiltros;
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
                <h3 class="card-title">Regímenes de percepción</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-regimen-percepcion',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => RegimenPercepcionListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('regimen_percepcion'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-regimen-percepcion',
                        'toggleId' => 'btn-toggle-filtros-regimen-percepcion',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_regimen_percepcion', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-regimen-percepcion',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('regimen_percepcion') }}" id="form-filtros-regimen-percepcion" class="mb-0">
                @include('configuracion.regimen_percepcion.partials.filtros_listado', [
                    'limpiarUrl' => route('regimen_percepcion'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_regimen_percepcion',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <p class="px-3 pt-2 text-muted small mb-0">
                    Parámetros de percepción IVA nacional (RG 5329 y RG 2126) para facturación de administración
                    (mostrador, pedido, remito). Gastronomía, estacionamiento y POS no usan este catálogo.
                </p>
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Agente</th>
                            <th>Alícuota</th>
                            <th>Mín. gravado</th>
                            <th>Mín. percepción</th>
                            <th>Vigencia</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->habilitado ? 'Sí' : 'No' }}</td>
                            <td>{{ number_format((float) $data->tasa, 2, ',', '.') }}%</td>
                            <td>{{ number_format((float) $data->minimo_base, 2, ',', '.') }}</td>
                            <td>{{ number_format((float) $data->minimo_importe, 2, ',', '.') }}</td>
                            <td>
                                @if ($data->vigencia_desde)
                                    {{ $data->vigencia_desde->format('d/m/Y') }}
                                @endif
                                @if ($data->vigencia_hasta)
                                    — {{ $data->vigencia_hasta->format('d/m/Y') }}
                                @endif
                            </td>
                            <td>
                                @if (can('editar-regimen-percepcion', false))
                                    <a href="{{ route('editar_regimen_percepcion', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-regimen-percepcion', false) && ! $data->esCodigoSistema())
                                <form action="{{ route('eliminar_regimen_percepcion', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
