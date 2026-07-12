@extends("theme.$theme.layout")
@section('titulo')
    Bienes de uso
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/bien_uso/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Contable\BienUsoListadoFiltros;
use App\Models\Contable\BienUso; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Bienes de uso</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-bien-uso',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => BienUsoListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('bien_uso'),
                        'placeholder' => 'Búsqueda rápida (UID, hostname, IP, modelo, vendor, tema, serie…)…',
                        'toggleTarget' => '#panel-filtros-bien-uso',
                        'toggleId' => 'btn-toggle-filtros-bien-uso',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_bien_uso', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-bien-uso',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('bien_uso') }}" id="form-filtros-bien-uso" class="mb-0">
                @include('contable.bien_uso.partials.filtros_listado', [
                    'limpiarUrl' => route('bien_uso'),
                    'centrocosto_opciones' => $centrocosto_opciones ?? collect(),
                    'filtro_centrocosto_restringido' => $filtro_centrocosto_restringido ?? false,
                    'alcance_centro_costo' => $alcance_centro_costo ?? null,
                ])
            </form>
            @if(!empty($alcance_centro_costo))
                <div class="px-3 py-2 border-bottom bg-white text-muted small">
                    <i class="fa fa-filter"></i>
                    Mostrando bienes del pool: <strong>{{ $alcance_centro_costo }}</strong>
                </div>
            @endif
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_bien_uso',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>UID</th>
                            <th>C&oacute;d. inv.</th>
                            <th>Empresa</th>
                            <th>Hostname</th>
                            <th>IP</th>
                            <th>Modelo</th>
                            <th>Vendor</th>
                            <th>Tema</th>
                            <th>N&ordm; serie</th>
                            <th>Estado</th>
                            <th>C. costo</th>
                            <th>Tipo bien</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>
                                @if($puede_ver_bien_uso ?? false)
                                    <a href="{{ route('editar_bien_uso', ['id' => $data->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                       class="text-primary" target="_blank" rel="noopener">{{ $data->id }}</a>
                                @else
                                    {{ $data->id }}
                                @endif
                            </td>
                            <td>{{ $data->uid }}</td>
                            <td>{{ $data->codigo_inventario }}</td>
                            <td>{{ $data->empresa->nombre ?? '' }}</td>
                            <td>{{ $data->hostname }}</td>
                            <td>{{ $data->ip }}</td>
                            <td>{{ $data->modelo }}</td>
                            <td>{{ $data->vendor }}</td>
                            <td>{{ $data->tema }}</td>
                            <td>{{ $data->numero_serie }}</td>
                            <td>{{ BienUso::labelEstado($data->estado) }}</td>
                            <td>{{ $data->centrocostos->codigo ?? '' }} — {{ $data->centrocostos->nombre ?? '' }}</td>
                            <td>{{ BienUso::labelTipoBien($data->tipo_bien) }}</td>
                            <td>
                                @if (can('editar-bien-uso', false))
                                    <a href="{{ route('editar_bien_uso', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-bien-uso', false))
                                <form action="{{ route('eliminar_bien_uso', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
        </div>
    </div>
</div>
{{ $datas->appends($filtrosQuery ?? [])->links() }}
@endsection
