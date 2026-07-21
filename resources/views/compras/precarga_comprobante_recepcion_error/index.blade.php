@extends("theme.$theme.layout")
@section('titulo')
    Errores recepción precarga
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/precarga_comprobante_recepcion_error/filtro.js') }}" type="text/javascript"></script>
@endsection

<?php
use App\Support\Compras\PrecargaRecepcionErrorListadoFiltros;
use App\Support\Compras\PrecargaRecepcionErrorRegistrar;
?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Errores de recepción precarga (API / PDF+IA)</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('precarga_comprobante_proveedor') }}" class="btn btn-outline-secondary btn-sm mr-1">
                        <i class="fa fa-arrow-left"></i> Volver a precargas
                    </a>
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-precarga-recepcion-error',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => PrecargaRecepcionErrorListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('precarga_comprobante_recepcion_error'),
                        'placeholder' => 'Búsqueda rápida (mensaje, OC, CUIT…)…',
                        'toggleTarget' => '#panel-filtros-precarga-recepcion-error',
                        'toggleId' => 'btn-toggle-filtros-precarga-recepcion-error',
                        'inputId' => 'filtro_valor',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('precarga_comprobante_recepcion_error') }}" id="form-filtros-precarga-recepcion-error" class="mb-0">
                @include('compras.precarga_comprobante_recepcion_error.partials.filtros_listado', [
                    'limpiarUrl' => route('precarga_comprobante_recepcion_error'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_precarga_comprobante_recepcion_error',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A">
                        <tr>
                            <th class="width20">ID</th>
                            <th>Fecha</th>
                            <th>Origen</th>
                            <th>Fase</th>
                            <th>Nº OC</th>
                            <th>CUIT proveedor</th>
                            <th>HTTP</th>
                            <th>Mensaje</th>
                            <th>Usuario</th>
                            <th>Archivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td><small>{{ optional($data->created_at)->format('d/m/Y H:i:s') }}</small></td>
                            <td><small>{{ PrecargaRecepcionErrorRegistrar::etiquetaOrigen($data->origen) }}</small></td>
                            <td><small>{{ $data->fase }}</small></td>
                            <td>{{ $data->numero_oc }}</td>
                            <td>{{ $data->cuit_proveedor }}</td>
                            <td>{{ $data->http_status }}</td>
                            <td style="max-width:420px;white-space:normal">{{ $data->mensaje }}</td>
                            <td><small>{{ $data->usuario->nombre ?? '—' }}</small></td>
                            <td><small>{{ $data->archivo_nombre }}</small></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">Sin errores registrados.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists($datas, 'links'))
            <div class="card-footer clearfix">
                <div class="float-left">
                    @if ($datas->total() > 0)
                        Mostrando {{ $datas->firstItem() }}–{{ $datas->lastItem() }} de {{ $datas->total() }}
                    @endif
                </div>
                <div class="float-right">
                    {{ $datas->appends($filtrosQuery ?? [])->links() }}
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
