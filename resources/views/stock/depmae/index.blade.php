@extends("theme.$theme.layout")
@section('titulo')
Dep&oacute;sitos
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/filtro.js') }}" type="text/javascript"></script>
@endsection

<?php
use App\Models\Stock\Depmae;
use App\Support\Stock\DepmaeListadoFiltros;
?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Dep&oacute;sitos</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-depmae',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => DepmaeListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('depmae'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-depmae',
                        'toggleId' => 'btn-toggle-filtros-depmae',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_depmae'),
                        'nuevoRegistroCan' => 'crear-depositos',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('depmae') }}" id="form-filtros-depmae" class="mb-0">
                @include('stock.depmae.partials.filtros_listado', [
                    'limpiarUrl' => route('depmae'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_depmae',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Descripci&oacute;n</th>
                            <th>Empresa</th>
                            <th>Tipo de dep&oacute;sito</th>
                            <th>C&oacute;digo ANITA</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ optional($data->empresas)->nombre ?? '—' }}</td>
                            <td>{{ Depmae::etiquetaTipoDeposito($data->tipodeposito) ?: '—' }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>
                       			@if (can('editar-depositos', false))
                                	<a href="{{ route('editar_depmae', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
                       			@if (can('borrar-depositos', false))
                                <form action="{{ route('eliminar_depmae', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <input type="hidden" name="codigo" value="{{ $data->codigo }}">
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
