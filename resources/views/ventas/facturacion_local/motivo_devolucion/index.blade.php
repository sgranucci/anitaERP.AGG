@extends("theme.$theme.layout")
@section('titulo')
    Motivos de devolución
@endsection
@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/motivo_devolucion/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Ventas\FacturacionLocal\MotivoDevolucionListadoFiltros;
    $limpiarUrl = route('facturacion_local_motivos_devolucion');
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Motivos de devolución</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-motivo-devolucion',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => MotivoDevolucionListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-motivo-devolucion',
                        'toggleId' => 'btn-toggle-filtros-motivo-devolucion',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_motivo_devolucion'),
                        'nuevoRegistroCan' => 'crear-motivo-devolucion-facturacion-local',
                    ])
                    <a href="{{ route('facturacion_local_historial_devoluciones') }}" class="btn btn-outline-info btn-sm ml-1">
                        Historial
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('facturacion_local_motivos_devolucion') }}" id="form-filtros-motivo-devolucion" class="mb-0">
                @include('ventas.facturacion_local.motivo_devolucion.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_motivo_devolucion',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    @include('ventas.facturacion_local.motivo_devolucion.partials.tabla_datos', [
                        'presentacion' => 'pantalla',
                        'datas' => $datas,
                    ])
                </table>
            </div>
            <div class="card-footer">
                {{ $datas->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
