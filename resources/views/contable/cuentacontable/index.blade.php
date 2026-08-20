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
    cursor: pointer; border-left: 3px solid transparent;
}
.pc-nodo__row:hover { background: #f4f9fc; }
.pc-nodo--hit > .pc-nodo__row { background: #d6eaf8; }
.pc-nodo--total > .pc-nodo__row { opacity: .72; }
.pc-nodo__row.is-selected { background: #d6eaf8; border-left-color: #2471A3; }
.pc-nodo__toggle {
    width: 22px; height: 22px; padding: 0; border: 0; background: transparent;
    color: #2471A3; line-height: 1; cursor: pointer;
}
.pc-nodo__toggle--empty { display: inline-block; width: 22px; }
.pc-nodo__codigo { font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 12px; color: #1B4F72; min-width: 88px; }
.pc-nodo__nombre { flex: 1; min-width: 0; }
.pc-nodo__tipo { font-size: 10px; text-transform: uppercase; letter-spacing: .02em; }
.pc-nodo__meta { font-size: 11px; white-space: nowrap; }
.pc-nodo__acciones { white-space: nowrap; }
.pc-nodo__acciones a { cursor: pointer; }
.pc-arbol-toolbar { background: #fbfcfd; }
.pc-workbench {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.75fr);
    align-items: stretch;
}
@media (max-width: 991px) {
    .pc-workbench { grid-template-columns: 1fr; }
    .pc-inspector { border-left: 0; border-top: 1px solid #d6eaf8; }
}
.pc-workbench__arbol { min-width: 0; max-height: calc(100vh - 260px); overflow: auto; }
.pc-inspector {
    border-left: 1px solid #d6eaf8;
    background: #fbfcfd;
    padding: 1rem 1.1rem 1.25rem;
    position: sticky;
    top: 0;
    align-self: start;
    max-height: calc(100vh - 180px);
    overflow: auto;
}
.pc-inspector[hidden] { display: none !important; }
.pc-inspector__head { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; margin-bottom: .5rem; }
.pc-inspector__titulo { font-size: 1.05rem; color: #1B4F72; }
.pc-inspector__hint { font-size: 12px; color: #5d6d7e; }
.pc-inspector__vacio { padding: 1.5rem .25rem; color: #7f8c8d; font-size: 13px; }
.pc-niveles { display: flex; gap: 4px; }
.pc-nivel {
    flex: 1; border: 1px solid #bdd7ea; background: #fff; color: #1B4F72;
    padding: 6px 2px 5px; border-radius: 4px; cursor: pointer; line-height: 1.1;
}
.pc-nivel:disabled { cursor: default; opacity: .7; }
.pc-nivel__n { display: block; font-size: 11px; font-weight: 700; }
.pc-nivel__bar { display: block; height: 5px; background: #d6eaf8; margin: 5px auto 0; border-radius: 2px; }
.pc-nivel[data-nivel="1"] .pc-nivel__bar { width: 22%; }
.pc-nivel[data-nivel="2"] .pc-nivel__bar { width: 38%; }
.pc-nivel[data-nivel="3"] .pc-nivel__bar { width: 54%; }
.pc-nivel[data-nivel="4"] .pc-nivel__bar { width: 70%; }
.pc-nivel[data-nivel="5"] .pc-nivel__bar { width: 86%; }
.pc-nivel.is-on { border-color: #5dade2; background: #eaf4fb; }
.pc-nivel.is-current { background: #2471A3; border-color: #1B4F72; color: #fff; }
.pc-nivel.is-current .pc-nivel__bar { background: #fff; }
.pc-preview {
    margin-top: .75rem; border: 1px solid #d6eaf8; border-radius: 4px;
    background: #fff; padding: .65rem .75rem;
}
.pc-preview__label { font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #7f8c8d; margin-bottom: .35rem; }
.pc-preview__arbol { list-style: none; padding: 0; font-size: 12px; }
.pc-preview__linea {
    display: flex; align-items: baseline; gap: .4rem;
    padding: .18rem .25rem; border-radius: 3px; color: #5d6d7e;
}
.pc-preview__linea.is-self { background: #d6eaf8; color: #1B4F72; font-weight: 700; }
.pc-preview__linea.is-child { color: #34495e; }
.pc-preview__n { font-size: 10px; color: #2471A3; min-width: 1.4rem; }
.pc-preview__cod { font-family: SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 11px; color: #1B4F72; }
</style>
@endsection

@section("scripts")
@if ($vistaArbol ?? false)
@php
    $pcUrlInspector = str_replace('999999991', '__ID__', route('actualizar_inspector_cuentacontable', ['id' => 999999991]));
    $pcUrlFicha = str_replace('999999991', '__ID__', route('editar_cuentacontable', ['id' => 999999991] + ($filtrosQuery ?? [])));
@endphp
<script>
window.pcWorkbench = {
    puedeEditar: @json((bool) ($puedeEditarArbol ?? false)),
    urlInspector: @json($pcUrlInspector),
    urlFicha: @json($pcUrlFicha),
    cuentaInicial: @json((int) request('cuenta', 0))
};
</script>
@endif
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
                <div class="pc-workbench">
                    <div class="pc-workbench__arbol">
                        @include('contable.cuentacontable.partials.arbol')
                    </div>
                    @include('contable.cuentacontable.partials.inspector')
                </div>
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
