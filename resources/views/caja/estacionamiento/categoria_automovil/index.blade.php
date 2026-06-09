@extends("theme.$theme.layout")
@section('titulo')
    Categor&iacute;as de autom&oacute;viles
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/estacionamiento/categoria_automovil/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Caja\Estacionamiento\CategoriaAutomovilListadoFiltros;
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Categor&iacute;as de autom&oacute;viles</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-estacionamiento-categoria-automovil',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => CategoriaAutomovilListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('estacionamiento_categoria_automovil'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-estacionamiento-categoria-automovil',
                        'toggleId' => 'btn-toggle-filtros-estacionamiento-categoria-automovil',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_estacionamiento_categoria_automovil'),
                        'nuevoRegistroCan' => 'crear-estacionamiento-categoria-automovil',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('estacionamiento_categoria_automovil') }}" id="form-filtros-estacionamiento-categoria-automovil" class="mb-0">
                @include('caja.estacionamiento.categoria_automovil.partials.filtros_listado', [
                    'limpiarUrl' => route('estacionamiento_categoria_automovil'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_estacionamiento_categoria_automovil',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>
                                @if (can('editar-estacionamiento-categoria-automovil', false))
                                    <a href="{{ route('editar_estacionamiento_categoria_automovil', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-estacionamiento-categoria-automovil', false))
                                <form action="{{ route('eliminar_estacionamiento_categoria_automovil', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
