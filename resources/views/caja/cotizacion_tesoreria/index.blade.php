@extends("theme.$theme.layout")
@section('titulo')
    Cotización tesorería
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/cotizacion_tesoreria/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Caja\CotizacionTesoreriaListadoFiltros;
@endphp

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('cotizacion_tesoreria', CotizacionTesoreriaListadoFiltros::paraQueryStringEmpresa($filtros ?? []));
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cotización tesorería</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @if (can('crear-cotizacion-tesoreria', false))
                        <form action="{{ route('sincronizar_cotizacion_tesoreria_anita') }}" method="POST" class="d-inline mr-1"
                              onsubmit="return confirm('¿Importar cotizaciones desde Anita (Biyemas, Kandiko y Rebisco)? Puede demorar varios minutos.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm" title="Importar desde Anita cotiz_tes">
                                <i class="fa fa-sync"></i> Sincronizar Anita
                            </button>
                        </form>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cotizacion-tesoreria',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => CotizacionTesoreriaListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Fecha (d/m/Y), año o ID…',
                        'toggleTarget' => '#panel-filtros-cotizacion-tesoreria',
                        'toggleId' => 'btn-toggle-filtros-cotizacion-tesoreria',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_cotizacion_tesoreria', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-cotizacion-tesoreria',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('cotizacion_tesoreria') }}" id="form-filtros-cotizacion-tesoreria" class="mb-0">
                @include('caja.cotizacion_tesoreria.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            @include('caja.cotizacion_tesoreria.partials.filtros_externos')
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_cotizacion_tesoreria',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                @include('caja.cotizacion_tesoreria.partials.tabla_datos', [
                    'datas' => $datas,
                    'monedasColumnas' => $monedasColumnas,
                    'mostrarAcciones' => true,
                    'retornoListadoQuery' => $retornoListadoQuery,
                ])
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
