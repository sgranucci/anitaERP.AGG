@extends("theme.$theme.layout")
@section('titulo')
    Provincias
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/provincia/filtro.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/provincia/reporte_tasas.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Configuracion\ProvinciaListadoFiltros;
@endphp

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('provincia');
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Provincias</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <button type="button" class="btn btn-outline-light btn-sm mr-1" data-toggle="modal" data-target="#modal-reporte-tasas-iibb">
                        <i class="fa fa-file-alt"></i> Reporte tasas IIBB
                    </button>
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-provincia',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ProvinciaListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-provincia',
                        'toggleId' => 'btn-toggle-filtros-provincia',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_provincia', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-provincias',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('provincia') }}" id="form-filtros-provincia" class="mb-0">
                @include('configuracion.provincia.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_provincias',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th>Abrev.</th>
                            <th>Juris.</th>
                            <th>Código</th>
                            <th>País</th>
                            <th>Mínimo Coef. CM05</th>
                            <th>Tasas por Condición IIBB</th>
                            <th>Cuentas Contables</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->abreviatura }}</td>
                            <td>{{ $data->jurisdiccion }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->paises->nombre ?? '' }}</td>
                            <td>{{ $data->minimocoeficientecm05 }}</td>
                            <td>
                                @if (($data->provincia_tasaiibbs ?? collect())->isNotEmpty())
                                    <ul class="mb-0 pl-3">
                                        @foreach($data->provincia_tasaiibbs as $tasa)
                                            <li>
                                                {{ $tasa->condicioniibbs->nombre ?? '' }}
                                                {{ number_format((float) $tasa->tasa, 2, ',', '.') }} %
                                                Min.Neto {{ number_format((float) $tasa->minimoneto, 2, ',', '.') }}
                                                Min.Perc. {{ number_format((float) $tasa->minimopercepcion, 2, ',', '.') }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                            <td>
                                @if (($data->provincia_cuentacontableiibbs ?? collect())->isNotEmpty())
                                    <ul class="mb-0 pl-3">
                                        @foreach($data->provincia_cuentacontableiibbs as $cuentacontable)
                                            <li>
                                                {{ $cuentacontable->empresas->nombre ?? '' }}
                                                {{ $cuentacontable->cuentacontables->codigo ?? '' }}-{{ $cuentacontable->cuentacontables->nombre ?? '' }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                            <td>
                                @if (can('editar-provincias', false))
                                    <a href="{{ route('editar_provincia', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-provincias', false))
                                <form action="{{ route('eliminar_provincia', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
@include('configuracion.provincia.partials.modal_reporte_tasas')
@endsection
