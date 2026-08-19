@extends("theme.$theme.layout")
@section('titulo')
    Plan de cuentas
@endsection

@section('styles')
<style>
.pc-arbol { list-style: none; margin: 0; padding: 0 0 1rem; }
.pc-nodo { list-style: none; }
.pc-nodo__hijos { list-style: none; margin: 0; padding: 0 0 0 1.15rem; border-left: 1px solid #d6eaf8; }
.pc-nodo__row {
    display: flex; align-items: center; gap: .45rem;
    padding: .32rem .75rem; border-bottom: 1px solid #f0f3f5;
}
.pc-nodo__row:hover { background: #f4f9fc; }
.pc-nodo--hit > .pc-nodo__row { background: #d6eaf8; }
.pc-nodo--total > .pc-nodo__row { opacity: .72; }
.pc-nodo__toggle {
    width: 22px; height: 22px; padding: 0; border: 0; background: transparent;
    color: #2471A3; line-height: 1;
}
.pc-nodo__toggle--empty { display: inline-block; width: 22px; }
.pc-nodo__codigo { font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 12px; color: #1B4F72; min-width: 88px; }
.pc-nodo__nombre { flex: 1; min-width: 0; }
.pc-nodo__tipo { font-size: 10px; text-transform: uppercase; letter-spacing: .02em; }
.pc-nodo__meta { font-size: 11px; white-space: nowrap; }
.pc-nodo__acciones { white-space: nowrap; }
.pc-arbol-toolbar { background: #fbfcfd; }
</style>
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    use App\Support\Contable\CuentacontableListadoFiltros;
    use App\Support\Listado\QueryRetornoListado;
    $retornoListadoQuery = QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('cuentacontable', CuentacontableListadoFiltros::paraQueryStringEmpresa($filtros ?? []));
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Plan de cuentas</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cuentacontable',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => CuentacontableListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Buscar código o nombre…',
                        'toggleTarget' => '#panel-filtros-cuentacontable',
                        'toggleId' => 'btn-toggle-filtros-cuentacontable',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_cuentacontable', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-cuentas-contables',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('cuentacontable') }}" id="form-filtros-cuentacontable" class="mb-0">
                @include('contable.cuentacontable.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            @include('contable.cuentacontable.partials.filtros_externos')
            @if ($vistaArbol ?? false)
                @include('contable.cuentacontable.partials.arbol')
            @else
                @include('contable.cuentacontable.partials.tabla_lista')
            @endif
        </div>
    </div>
</div>
@if (! ($vistaArbol ?? false) && isset($cuentacontables) && method_exists($cuentacontables, 'links'))
    {{ $cuentacontables->appends($filtrosQuery ?? [])->links() }}
@endif
@endsection
