@extends("theme.$theme.layout")
@section('titulo')
Diseñador del árbol
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/arbolaprobacion.css') }}?v={{ @filemtime(public_path('assets/css/arbolaprobacion.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/css/arbolaprobacion-disenador.css') }}?v={{ @filemtime(public_path('assets/css/arbolaprobacion-disenador.css')) ?: time() }}">
@endsection

@section('contenido')
<div class="anita-arbol anita-arbol-diseno">
    @include('includes.mensaje')

    <div class="anita-arbol-hero">
        <div class="anita-arbol-hero-row">
            <div>
                <p class="anita-arbol-brand">Configuración · Circuito visual</p>
                <h1 class="anita-arbol-title">Diseñador del árbol</h1>
                <p class="anita-arbol-sub">
                    {{ $grafo['arbol']['nombre'] }} · {{ $grafo['arbol']['tipoarbol'] }}
                    @if (!empty($grafo['arbol']['empresa'])) · {{ $grafo['arbol']['empresa'] }} @endif
                    · {{ count($grafo['nodes']) }} nodos · {{ count($grafo['lanes']) }} carriles
                </p>
            </div>
            <div class="anita-arbol-hero-actions">
                <button type="button" class="btn btn-outline-light btn-sm" id="btn-auto-layout">Auto layout</button>
                <button type="button" class="btn btn-light btn-sm" id="btn-guardar-layout">Guardar posiciones</button>
                <a href="{{ route('edita_arbolaprobacion', $arbol->id) }}" class="btn btn-outline-light btn-sm">
                    <i class="fa fa-edit"></i> ABM completo
                </a>
                <a href="{{ route('consulta_arbolaprobacion') }}" class="btn btn-outline-light btn-sm">
                    <i class="fa fa-reply"></i> Listado
                </a>
            </div>
        </div>
    </div>

    <div class="anita-diseno-shell">
        <div class="anita-diseno-canvas-wrap">
            <div class="anita-diseno-toolbar">
                <span class="text-muted small">Arrastrá nodos · clic para editar · rueda = zoom</span>
                <span id="anita-diseno-status" class="anita-diseno-status"></span>
            </div>
            <div id="anita-diseno-viewport" class="anita-diseno-viewport">
                <svg id="anita-diseno-edges" class="anita-diseno-edges" xmlns="http://www.w3.org/2000/svg"></svg>
                <div id="anita-diseno-stage" class="anita-diseno-stage"></div>
            </div>
        </div>

        <aside class="anita-diseno-panel card card-outline card-info mb-0">
            <div class="card-header py-2">
                <h3 class="card-title mb-0">Nodo</h3>
            </div>
            <div class="card-body" id="anita-diseno-panel-empty">
                <p class="text-muted small mb-0">Elegí un nivel en el lienzo para editar firmante, montos o estado.</p>
                <p class="text-muted small mt-2 mb-0">
                    La lógica del circuito (quién firma según monto/CC) no cambia por mover cajas:
                    las flechas siguen el orden de nivel dentro de cada carril (CC + rama).
                </p>
            </div>
            <form id="anita-diseno-panel-form" class="card-body d-none" data-enter-campos="1" autocomplete="off">
                <input type="hidden" id="panel_nivel_id">
                <div class="form-group">
                    <label for="panel_nivel">Nivel</label>
                    <input type="number" min="1" max="99" class="form-control form-control-sm" id="panel_nivel">
                </div>
                <div class="form-group tm-usuario-campo">
                    <label>Firmante (vacío = auto)</label>
                    <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
                        <input type="hidden" id="panel_usuario_id" class="usuario_id" value="">
                        <input type="text" class="form-control form-control-sm usuario_codigo" id="panel_usuario_codigo"
                            style="flex:0 0 96px;width:96px;" placeholder="Código" title="Login o ID; F1 / lupa" autocomplete="off">
                        <button type="button" class="btn btn-sm btn-accion-tabla consultausuario" title="Consulta usuarios">
                            <i class="fa fa-search text-primary"></i>
                        </button>
                        <input type="text" class="form-control form-control-sm nombreusuario" id="panel_usuario_nombre"
                            style="flex:1 1 auto;min-width:0;" readonly placeholder="(opcional / auto)">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="panel_limpiar_usuario" title="Auto">Auto</button>
                    </div>
                    <small class="form-text text-muted" id="panel_usuario_label"></small>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label for="panel_desde">Desde</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="panel_desde">
                    </div>
                    <div class="form-group col-6">
                        <label for="panel_hasta">Hasta</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="panel_hasta">
                    </div>
                </div>
                @if (!empty($grafo['meta']['usa_estado_doc']))
                    <div class="form-group">
                        <label for="panel_estado_doc">Estado doc. al aprobar</label>
                        <select class="form-control form-control-sm" id="panel_estado_doc">
                            <option value="">—</option>
                            @foreach (($grafo['meta']['estados_doc'] ?? []) as $est)
                                @php
                                    $lab = is_array($est) ? (string) ($est['nombre'] ?? '') : (string) $est;
                                @endphp
                                @if ($lab !== '')
                                    <option value="{{ $lab }}">{{ $lab }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="panel_doble">
                    <label class="form-check-label" for="panel_doble">2ª firma</label>
                </div>
                <button type="submit" class="btn btn-primary btn-sm btn-block">Aplicar al nivel</button>
            </form>
        </aside>
    </div>
</div>
@include('includes.admin.modalconsultausuario')
@endsection

@section('scripts')
<script>
window.AnitaArbolDiseno = {
    arbolId: {{ (int) $arbol->id }},
    grafo: @json($grafo),
    urls: {
        layout: @json(route('guardar_layout_arbolaprobacion', $arbol->id)),
        nodo: @json(url('configuracion/arbolaprobacion/'.$arbol->id.'/disenador/nodo')),
        csrf: @json(csrf_token()),
    }
};
</script>
<script src="{{ asset('assets/pages/scripts/admin/usuario/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/admin/usuario/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/arbolaprobacion/disenador.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/configuracion/arbolaprobacion/disenador.js')) ?: time() }}"></script>
<script>
if (typeof window.activa_eventos_consultausuario === 'function') {
    window.activa_eventos_consultausuario();
}
</script>
@endsection
