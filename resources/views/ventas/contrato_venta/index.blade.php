@extends("theme.$theme.layout")
@section('titulo')
    Abonos / contratos de venta
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/contrato_venta/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Ventas\ContratoVentaListadoFiltros;
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
                <h3 class="card-title">Abonos / contratos de venta</h3>
                <div class="card-tools d-flex flex-nowrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-contrato-venta',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ContratoVentaListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('contrato_venta'),
                        'placeholder' => 'Búsqueda rápida…',
                        'toggleTarget' => '#panel-filtros-contrato-venta',
                        'toggleId' => 'btn-toggle-filtros-contrato-venta',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_contrato_venta', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-contratos-venta',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('contrato_venta') }}" id="form-filtros-contrato-venta" class="mb-0">
                @include('ventas.contrato_venta.partials.filtros_listado', [
                    'limpiarUrl' => route('contrato_venta'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                <div class="px-2 pt-1 d-flex flex-nowrap align-items-center">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'lista_contrato_venta',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                </div>
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>Código</th>
                            <th>Cliente</th>
                            <th>Concepto</th>
                            <th>Estado</th>
                            <th>Vigencia</th>
                            <th>Periodicidad</th>
                            <th>Precio</th>
                            <th>Empresa</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->cliente->nombre ?? '' }}</td>
                            <td>{{ $data->conceptoVenta->codigo ?? '' }} — {{ $data->conceptoVenta->nombre ?? '' }}</td>
                            <td>{{ $data->estado }}</td>
                            <td>
                                {{ $data->vigencia_desde?->format('d/m/Y') }}
                                @if ($data->vigencia_hasta)
                                    – {{ $data->vigencia_hasta->format('d/m/Y') }}
                                @endif
                            </td>
                            <td>{{ $data->periodicidad }}</td>
                            <td class="text-right">{{ $data->precio !== null ? number_format((float) $data->precio, 2, ',', '.') : '' }}</td>
                            <td>{{ $data->empresa->nombre ?? '' }}</td>
                            <td class="text-nowrap">
                                @if (can('editar-contratos-venta', false))
                                    <a href="{{ route('editar_contrato_venta', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-contratos-venta', false))
                                <form action="{{ route('eliminar_contrato_venta', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
