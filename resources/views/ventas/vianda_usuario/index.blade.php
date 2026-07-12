@extends("theme.$theme.layout")
@section('titulo')
    Usuarios de vianda
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/vianda_usuario/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Ventas\ViandaUsuarioListadoFiltros;
use App\Support\Ventas\ViandaUsuarioTipoSupport; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (! empty($sinRegistros ?? false))
        <div class="alert alert-info">
            No hay usuarios de vianda en el ERP. Para importar desde Anita ejecute
            <code>php artisan vianda:sincronizar-usuarios-anita</code>
            o cree registros con <strong>Nuevo registro</strong>.
        </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Usuarios de vianda</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-vianda-usuario',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ViandaUsuarioListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_vianda_usuario_gastronomia'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-vianda-usuario',
                        'toggleId' => 'btn-toggle-filtros-vianda-usuario',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_vianda_usuario_gastronomia', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-vianda-usuario-gastronomia',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_vianda_usuario_gastronomia') }}" id="form-filtros-vianda-usuario" class="mb-0">
                @include('ventas.vianda_usuario.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_vianda_usuario_gastronomia'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_vianda_usuario',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>C&oacute;digo</th>
                            <th>Empresa</th>
                            <th>Nombre</th>
                            <th>Centro costo</th>
                            <th>Tipo</th>
                            <th>Tipo men&uacute;</th>
                            <th class="width80">Estado</th>
                            <th class="width120 text-nowrap" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->codigo_usuario }}</td>
                            <td>{{ optional($data->empresa)->nombre ?: '—' }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>
                                @if ($data->centrocosto)
                                    {{ $data->centrocosto->codigo }} — {{ $data->centrocosto->nombre }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ ViandaUsuarioTipoSupport::etiqueta($data->tipo_usuario) }}</td>
                            <td>{{ optional($data->tipoMenu)->nombre ?: '—' }}</td>
                            <td>{{ $data->etiquetaEstado() }}</td>
                            <td class="text-nowrap">
                                <span class="d-inline-flex flex-nowrap align-items-center">
                                @if (can('editar-vianda-usuario-gastronomia', false))
                                    <a href="{{ route('editar_vianda_usuario_gastronomia', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-vianda-usuario-gastronomia', false))
                                <form action="{{ route('eliminar_vianda_usuario_gastronomia', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
                                @endif
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-2">
            @if ($datas->total() > 0)
                <small class="text-muted">
                    {{ $datas->firstItem() }}–{{ $datas->lastItem() }} de {{ $datas->total() }} registros
                </small>
            @endif
            {{ $datas->appends($filtrosQuery ?? [])->links() }}
        </div>
    </div>
</div>
@endsection
