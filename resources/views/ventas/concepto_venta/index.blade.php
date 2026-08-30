@extends("theme.$theme.layout")
@section('titulo')
    Conceptos de venta
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/concepto_venta/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Ventas\ConceptoVentaListadoFiltros;
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
                <h3 class="card-title">Conceptos de venta</h3>
                <div class="card-tools d-flex flex-nowrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-concepto-venta',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ConceptoVentaListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('concepto_venta'),
                        'placeholder' => 'Búsqueda rápida…',
                        'toggleTarget' => '#panel-filtros-concepto-venta',
                        'toggleId' => 'btn-toggle-filtros-concepto-venta',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_concepto_venta', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-conceptos-venta',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('concepto_venta') }}" id="form-filtros-concepto-venta" class="mb-0">
                @include('ventas.concepto_venta.partials.filtros_listado', [
                    'limpiarUrl' => route('concepto_venta'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                <div class="px-2 pt-1 d-flex flex-nowrap align-items-center">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'lista_concepto_venta',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                </div>
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>GTIN</th>
                            <th>Alícuota</th>
                            <th>Unidad</th>
                            <th>Cuentas contables</th>
                            <th>Activo</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->codigo_gtin }}</td>
                            <td>{{ $data->impuesto->nombre ?? '' }}</td>
                            <td>{{ $data->unidadmedida->abreviatura ?? '' }}</td>
                            <td>
                                @forelse ($data->cuentas as $cuenta)
                                    <div class="small mb-0 text-nowrap">
                                        <strong>{{ $cuenta->empresas->nombre ?? '' }}</strong>
                                        {{ $cuenta->cuentacontables->codigo ?? '' }}{{ !empty($cuenta->cuentacontables->nombre) ? '-'.$cuenta->cuentacontables->nombre : '' }}
                                        @if (!empty($cuenta->tipotransaccion->abreviatura))
                                            <span class="text-muted">{{ $cuenta->tipotransaccion->abreviatura }}</span>
                                        @endif
                                        @if ($cuenta->vigencia_desde || $cuenta->vigencia_hasta)
                                            <span class="text-muted">{{ $cuenta->vigencia_desde?->format('d/m/y') ?: '…' }}–{{ $cuenta->vigencia_hasta?->format('d/m/y') ?: '…' }}</span>
                                        @endif
                                        @if (!empty($cuenta->centrocosto->codigo))
                                            <span class="text-muted">CC {{ $cuenta->centrocosto->codigo }}</span>
                                        @endif
                                    </div>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </td>
                            <td>{{ $data->activo ? 'Sí' : 'No' }}</td>
                            <td class="text-nowrap">
                                @if (can('editar-conceptos-venta', false))
                                    <a href="{{ route('editar_concepto_venta', ['id' => $data->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-conceptos-venta', false))
                                <form action="{{ route('eliminar_concepto_venta', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
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
