@extends("theme.$theme.layout")
@section('titulo')
    Programas de impresión
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/programa_impresion/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Ventas\ProgramaImpresionListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('consultar_programa_impresion');
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Programas de impresión</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-programa-impresion',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ProgramaImpresionListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-programa-impresion',
                        'toggleId' => 'btn-toggle-filtros-programa-impresion',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_programa_impresion', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-programa-impresion',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_programa_impresion') }}" id="form-filtros-programa-impresion" class="mb-0">
                @include('ventas.programa_impresion.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            <div class="card-body table-responsive p-0">
                <div class="p-3 pb-0">
                    @include('ventas.programa_impresion.partials.ayuda_precedencia')
                </div>
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_programa_impresion',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Empresa</th>
                            <th>Formularios</th>
                            <th>Reglas</th>
                            <th>Disparo al grabar</th>
                            <th class="width160 text-nowrap" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->empresa->nombre ?? 'Todas' }}</td>
                            <td>{{ $data->formularios_count }}</td>
                            <td>{{ $data->reglas_count }}</td>
                            <td>{{ $data->permite_disparo_al_grabar ? 'Sí' : 'No' }}</td>
                            <td class="width160 text-nowrap">
                                @if (can('editar-programa-impresion', false))
                                <a href="{{ route('editar_programa_impresion', array_merge(['id' => $data->id], $retornoListadoQuery)) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                </a>
                                @endif
                                @if (can('borrar-programa-impresion', false))
                                <form action="{{ route('eliminar_programa_impresion', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
            @if(method_exists($datas, 'links'))
            <div class="card-footer">
                {{ $datas->appends($filtrosQuery ?? [])->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
