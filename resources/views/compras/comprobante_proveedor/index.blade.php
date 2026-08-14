@extends("theme.$theme.layout")
@section('titulo')
    Comprobantes de proveedor
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/comprobante_proveedor/filtro.js') }}" type="text/javascript"></script>
@endsection

<?php
use App\Support\Compras\ComprobanteProveedorListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
?>

@section('contenido')
@php
    $retornoListadoQuery = QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('comprobante_proveedor', ComprobanteProveedorListadoFiltros::paraQueryStringEmpresa($filtros ?? []));
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Comprobantes de proveedor</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('editar-configuracion-comprobante-proveedor', false))
                    <a href="{{ route('configuracion_comprobante_proveedor') }}" class="btn btn-outline-secondary btn-sm mr-1">
                        <i class="fa fa-cog"></i> Configuración
                    </a>
                    @endif
                    @if (can('crear-comprobante-proveedor', false) || can('listar-precarga-proveedores', false))
                    <a href="{{ route('comprobante_proveedor_opciones_carga') }}" class="btn btn-outline-success btn-sm mr-1">
                        <i class="fa fa-fw fa-plus-circle"></i> Cargar factura
                    </a>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-comprobante-proveedor',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ComprobanteProveedorListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-comprobante-proveedor',
                        'toggleId' => 'btn-toggle-filtros-comprobante-proveedor',
                        'inputId' => 'filtro_valor',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('comprobante_proveedor') }}" id="form-filtros-comprobante-proveedor" class="mb-0">
                @include('compras.comprobante_proveedor.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            @include('compras.comprobante_proveedor.partials.filtros_externos')
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_comprobante_proveedor',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>ID</th>
                            <th>Empresa</th>
                            <th>Proveedor</th>
                            <th>Tipo</th>
                            <th>Número</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Origen</th>
                            <th class="width120" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td><small>{{ $row->empresas->nombre ?? '' }}</small></td>
                            <td><small>{{ $row->proveedores->nombre ?? '' }}</small></td>
                            <td><small>{{ trim(($row->tipotransaccion_compras->abreviatura ?? '').' '.($row->tipotransaccion_compras->nombre ?? '')) }}</small></td>
                            <td><small>{{ $row->letra }}{{ $row->sucursal }}-{{ $row->numerocomprobante }}</small></td>
                            <td><small>{{ $row->fechacomprobante ? $row->fechacomprobante->format('d/m/Y') : '' }}</small></td>
                            <td><small>{{ number_format((float) $row->total, 2, ',', '.') }}</small></td>
                            <td><small>{{ $row->estado }}</small></td>
                            <td><small>{{ \App\Support\Compras\ComprobanteProveedorOrigenEntrada::etiqueta($row->origen_entrada ?? '') }}</small></td>
                            <td class="text-nowrap">
                                @if (can('editar-comprobante-proveedor', false))
                                <a href="{{ route('editar_comprobante_proveedor', ['id' => $row->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                    <i class="fa fa-edit"></i>
                                </a>
                                @endif
                                @if (($row->estado ?? '') !== \App\Support\Compras\ComprobanteProveedorEstados::CONTABILIZADO
                                    && ($row->estado ?? '') !== \App\Support\Compras\ComprobanteProveedorEstados::ANULADO
                                    && can('contabilizar-comprobante-proveedor', false))
                                <form action="{{ route('contabilizar_comprobante_proveedor', ['id' => $row->id]) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('¿Confirmar / contabilizar el comprobante #{{ $row->id }}?');">
                                    @csrf
                                    <button type="submit" class="btn-accion-tabla tooltipsC text-success" title="Confirmar / Contabilizar">
                                        <i class="fa fa-check"></i>
                                    </button>
                                </form>
                                @endif
                                @if (can('borrar-comprobante-proveedor', false))
                                <form action="{{ route('eliminar_comprobante_proveedor', ['id' => $row->id]) }}" method="POST" class="d-inline form-eliminar">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Borrar factura (ERP + Anita)">
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
