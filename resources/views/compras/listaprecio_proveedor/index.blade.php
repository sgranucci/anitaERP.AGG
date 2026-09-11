@extends("theme.$theme.layout")
@section('titulo')
Listas de precio — proveedores
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/compras/listaprecio-proveedor-ui.css') }}?v={{ @filemtime(public_path('assets/css/compras/listaprecio-proveedor-ui.css')) ?: time() }}">
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/listaprecio_proveedor/filtro.js') }}" type="text/javascript"></script>
@endsection

<?php use App\Support\Compras\ListaprecioProveedorListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('consultar_listaprecio_proveedor', ListaprecioProveedorListadoFiltros::paraQueryString(array_merge(
        ListaprecioProveedorListadoFiltros::filtrosVacios(),
        ['estado' => $filtros['estado'] ?? '']
    )));
@endphp
<div class="row lp-ui">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="lp-header">
            <h1><i class="fa fa-tags"></i> Listas de precio de proveedores</h1>
            <div class="lp-header-acciones">
                @include('includes.compras.boton-manual')
                @include('includes.listado.filtros_toolbar', [
                    'formId' => 'form-filtros-listaprecio-proveedor',
                    'filtroValor' => $filtros['valor'] ?? '',
                    'tieneCriterios' => ListaprecioProveedorListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                    'limpiarUrl' => $limpiarUrl,
                    'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                    'toggleTarget' => '#panel-filtros-listaprecio-proveedor',
                    'toggleId' => 'btn-toggle-filtros-listaprecio-proveedor',
                    'inputId' => 'filtro_valor',
                    'nuevoRegistroUrl' => route('crear_listaprecio_proveedor', $retornoListadoQuery),
                    'nuevoRegistroCan' => 'crear-listaprecio-proveedor',
                    'nuevoRegistroLabel' => 'Nueva lista',
                ])
            </div>
        </div>

        <div class="lp-panel">
            <p class="lp-intro">
                Condiciones vigentes por proveedor: nombre, moneda y precios por artículo. Filtre por estado o texto; abra la lista para editar renglones o importar un Excel.
            </p>

            <form method="get" action="{{ route('consultar_listaprecio_proveedor') }}" id="form-filtros-listaprecio-proveedor" class="mb-0">
                @include('compras.listaprecio_proveedor.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>

            @include('compras.listaprecio_proveedor.partials.filtros_externos')

            <div class="table-responsive p-0">
                <table class="table table-hover lp-grilla mb-0" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Nombre</th>
                            <th>Proveedor</th>
                            <th>Moneda</th>
                            <th>Ítems</th>
                            <th>Estado</th>
                            <th>Usuario</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($listas as $data)
                            @php
                                $esInactiva = strtoupper(trim((string) ($data->estado ?? ''))) === 'INACTIVA';
                            @endphp
                            <tr @if($esInactiva) class="lp-fila-inactiva" @endif>
                                <td><span class="lp-numero">{{ $data->id }}</span></td>
                                <td class="text-nowrap">{{ $data->fecha ? date('d/m/Y', strtotime($data->fecha)) : '—' }}</td>
                                <td>
                                    {{ $data->nombre }}
                                    @if (!empty($data->observaciones))
                                        <span class="lp-meta">{{ \Illuminate\Support\Str::limit($data->observaciones, 80) }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $data->nombreproveedor ?: '—' }}
                                    @if (!empty($data->codigoproveedor))
                                        <span class="lp-meta">Cód. {{ $data->codigoproveedor }}</span>
                                    @endif
                                </td>
                                <td>{{ $data->abreviaturamoneda ?: ($data->nombremoneda ?: '—') }}</td>
                                <td class="text-nowrap">{{ number_format((int) ($data->listaprecio_proveedor_articulos_count ?? 0), 0, ',', '.') }}</td>
                                <td>
                                    @include('compras.listaprecio_proveedor.partials.estado_badge', ['estado' => $data->estado ?? ''])
                                </td>
                                <td>{{ $data->nombreusuario ?: '—' }}</td>
                                <td class="text-nowrap">
                                    @include('compras.listaprecio_proveedor.partials.botones_exportar', [
                                        'listaId' => $data->id,
                                        'variant' => 'row',
                                    ])
                                    @if (can('editar-listaprecio-proveedor', false))
                                        <a href="{{ route('editar_listaprecio_proveedor', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('editar-listaprecio-proveedor', false) && can('actualizar-listaprecio-proveedor', false))
                                        <a href="{{ route('editar_listaprecio_proveedor', ['id' => $data->id] + $retornoListadoQuery) }}#importar-excel" class="btn-accion-tabla tooltipsC text-success" title="Importar precios desde Excel">
                                            <i class="fa fa-file-excel-o"></i>
                                        </a>
                                    @endif
                                    @if (can('actualizar-listaprecio-proveedor', false))
                                        <form action="{{ route('cambiar_estado_listaprecio_proveedor', ['id' => $data->id] + $retornoListadoQuery) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Cambiar estado entre ACTIVA e INACTIVA?');">
                                            @csrf
                                            <button type="submit" class="btn-accion-tabla tooltipsC text-warning" title="Cambiar estado ACTIVA / INACTIVA">
                                                <i class="fa fa-toggle-on"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if (can('borrar-listaprecio-proveedor', false))
                                        <form action="{{ route('eliminar_listaprecio_proveedor', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                            @csrf @method("delete")
                                            <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="lp-vacio">
                                    <i class="fa fa-inbox"></i>
                                    <div class="lp-vacio-titulo">No hay listas para este filtro</div>
                                    Ajuste el estado o el texto de búsqueda, o cree una lista nueva.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (method_exists($listas, 'links'))
            <div class="lp-footer">
                {{ $listas->appends($filtrosQuery ?? [])->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
