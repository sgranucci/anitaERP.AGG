@extends("theme.$theme.layout")
@section('titulo')
    Pago a proveedores
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/pagoproveedor/filtro.js")}}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Compras\PagoproveedorListadoFiltros;
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('pagoproveedor');
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Órdenes de pago</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-pagoproveedor',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => PagoproveedorListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-pagoproveedor',
                        'toggleId' => 'btn-toggle-filtros-pagoproveedor',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_pagoproveedor', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-pagoproveedor',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('pagoproveedor') }}" id="form-filtros-pagoproveedor" class="mb-0">
                @include('compras.pagoproveedor.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_pagoproveedor',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Fecha</th>
                            <th>OP</th>
                            <th>Empresa</th>
                            <th>Proveedor</th>
                            <th class="text-right">Monto</th>
                            <th>Estado</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($coleccion as $fila)
                            <tr>
                                <td>{{ optional($fila->fecha)->format('d/m/Y') }}</td>
                                <td>{{ $fila->etiquetaComprobante() }}</td>
                                <td>{{ $fila->empresas->nombre ?? '' }}</td>
                                <td>{{ $fila->proveedores->nombre ?? '' }}</td>
                                <td class="text-right">{{ number_format((float)$fila->monto, 2, ',', '.') }} {{ $fila->monedas->abreviatura ?? '' }}</td>
                                <td>{{ $fila->estado }}</td>
                                <td class="text-nowrap">
                                    @if (can('editar-pagoproveedor', false))
                                        <a href="{{ route('editar_pagoproveedor', ['id' => $fila->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    <a class="btn-accion-tabla tooltipsC" target="_blank" rel="noopener" href="{{ route('imprimir_pagoproveedor', $fila->id) }}" title="Imprimir">
                                        <i class="fa fa-print"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted">Sin órdenes de pago</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer clearfix">
                {{ $coleccion->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
