@extends("theme.$theme.layout")
@section('titulo')
    Cotizaciones
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/cotizacion/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Configuracion\CotizacionListadoFiltros;
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
                <h3 class="card-title">Cotizaciones</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cotizacion',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => CotizacionListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('cotizacion'),
                        'placeholder' => 'Fecha (d/m/Y), año o ID…',
                        'toggleTarget' => '#panel-filtros-cotizacion',
                        'toggleId' => 'btn-toggle-filtros-cotizacion',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_cotizacion', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-cotizacion',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('cotizacion') }}" id="form-filtros-cotizacion" class="mb-0">
                @include('configuracion.cotizacion.partials.filtros_listado', [
                    'limpiarUrl' => route('cotizacion'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_cotizacion',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                @include('configuracion.cotizacion.partials.tabla_datos', [
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
