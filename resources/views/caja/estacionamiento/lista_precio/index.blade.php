@extends("theme.$theme.layout")
@section('titulo')
    Listas de precios de estacionamiento
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/estacionamiento/lista_precio/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Caja\Estacionamiento\ListaPrecioEstacionamientoListadoFiltros;
    $fechaReferencia = $filtros['fecha_referencia'] ?? date('Y-m-d');
@endphp

@section('contenido')
<style>
    #tabla-paginada .col-precios-detalle {
        min-width: 320px;
        max-width: 480px;
    }
    #tabla-paginada .tabla-precios-vigentes-detalle {
        font-size: 0.8rem;
        background: #fff;
    }
    #tabla-paginada .tabla-precios-vigentes-detalle th,
    #tabla-paginada .tabla-precios-vigentes-detalle td {
        padding: 0.2rem 0.35rem;
        vertical-align: middle;
    }
</style>
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    Listas de precios
                    <small class="text-muted d-block d-md-inline ml-md-2">
                        Precios vigentes al {{ \Carbon\Carbon::parse($fechaReferencia)->format('d/m/Y') }}
                    </small>
                </h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-estacionamiento-lista-precio',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ListaPrecioEstacionamientoListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('estacionamiento_lista_precio'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-estacionamiento-lista-precio',
                        'toggleId' => 'btn-toggle-filtros-estacionamiento-lista-precio',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_estacionamiento_lista_precio'),
                        'nuevoRegistroCan' => 'crear-estacionamiento-lista-precio',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('estacionamiento_lista_precio') }}" id="form-filtros-estacionamiento-lista-precio" class="mb-0">
                @include('caja.estacionamiento.lista_precio.partials.filtros_listado', [
                    'limpiarUrl' => route('estacionamiento_lista_precio'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_estacionamiento_lista_precio',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Empresa</th>
                            <th>Categor&iacute;a</th>
                            <th class="width70">Mon.</th>
                            <th class="width80 text-center">Vigentes</th>
                            <th class="width90">&Uacute;lt. vigencia</th>
                            <th class="col-precios-detalle">Precios vigentes por &iacute;tem</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        @php
                            $moneda = $data->moneda->abreviatura ?? ($data->moneda->nombre ?? '');
                            $lineas = $data->precios_vigentes_detalle ?? collect();
                        @endphp
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->empresa->nombre ?? '' }}</td>
                            <td>{{ $data->categoriaAutomovil->nombre ?? '' }}</td>
                            <td>{{ $moneda }}</td>
                            <td class="text-center">{{ $data->precios_vigentes_count ?? 0 }}</td>
                            <td class="text-nowrap">
                                @if (!empty($data->ultima_vigencia))
                                    {{ \Carbon\Carbon::parse($data->ultima_vigencia)->format('d/m/Y') }}
                                @endif
                            </td>
                            <td class="col-precios-detalle align-top">
                                @include('caja.estacionamiento.lista_precio.partials.precios_vigentes_detalle', [
                                    'lineas' => $lineas,
                                    'moneda' => $moneda,
                                    'modo' => 'tabla',
                                ])
                            </td>
                            <td class="text-nowrap">
                                @if (can('editar-estacionamiento-lista-precio', false))
                                    <a href="{{ route('editar_estacionamiento_lista_precio', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar precios y vigencias">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-estacionamiento-lista-precio', false))
                                <form action="{{ route('eliminar_estacionamiento_lista_precio', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar lista completa">
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
