@extends("theme.$theme.layout")

@section('titulo')
    Diccionario NL · Consulta Inteligente
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/consulta-inteligente.css') }}?v={{ filemtime(public_path('assets/css/consulta-inteligente.css')) }}">
@endsection

@section('contenido')
<div class="container-fluid ci-wrap">
    <div class="ci-hero ci-explorar-hero">
        <div>
            <div class="ci-hero-kicker">Consulta Inteligente</div>
            <h1>Diccionario de lenguaje natural</h1>
            <p>
                Sinónimos editables que tienen prioridad sobre el diccionario fijo del código.
                Ejemplo: frase <em>valores en cartera</em> → entidad cheques.
                Opcional: asociá una <strong>métrica</strong> (aging, suma, promedio, conteo).
            </p>
        </div>
        <div class="ci-toolbar">
            <a href="{{ route('consulta_inteligente') }}" class="btn btn-outline-light btn-sm">
                <i class="fa fa-reply-all"></i> Catálogo
            </a>
            <a href="{{ route('configuracion_consulta_inteligente') }}" class="btn btn-outline-light btn-sm">
                <i class="fa fa-cog"></i> Alertas
            </a>
        </div>
    </div>

    @include('includes.mensaje')

    @if (! $tablaLista)
        <div class="alert alert-warning">
            Falta la tabla <code>consulta_inteligente_nl_sinonimo</code>.
            Hay que correr la migración <code>2026_11_15_194500_consulta_inteligente_nl_snapshot_auditoria</code>.
        </div>
    @else
        <form method="post" action="{{ route('guardar_diccionario_consulta_inteligente') }}"
              class="card card-primary shadow-sm mb-3" data-enter-campos="1">
            @csrf
            <div class="card-header"><strong>Agregar / actualizar sinónimo</strong></div>
            <div class="card-body">
                <input type="hidden" name="id" id="ci-nl-id" value="">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label class="small text-muted" for="ci-nl-frase">Frase</label>
                        <input type="text" name="frase" id="ci-nl-frase" class="form-control" maxlength="120" required
                               placeholder="ej. valores en cartera">
                    </div>
                    <div class="form-group col-md-3">
                        <label class="small text-muted" for="ci-nl-entidad">Entidad</label>
                        <select name="entidad" id="ci-nl-entidad" class="form-control" required>
                            <option value="">Elegí…</option>
                            @foreach ($entidades as $ent)
                                <option value="{{ $ent->clave() }}">{{ $ent->nombre() }} ({{ $ent->clave() }})</option>
                            @endforeach
                        </select>
                    </div>
                    @if (! empty($tieneMetrica))
                        <div class="form-group col-md-2">
                            <label class="small text-muted" for="ci-nl-metrica">Métrica</label>
                            <select name="metrica" id="ci-nl-metrica" class="form-control">
                                <option value="">(ninguna)</option>
                                @foreach (($metricas ?? []) as $m)
                                    <option value="{{ $m }}">{{ $m }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="form-group col-md-1">
                        <label class="small text-muted" for="ci-nl-prio">Prioridad</label>
                        <input type="number" name="prioridad" id="ci-nl-prio" class="form-control" min="1" max="999" value="100">
                    </div>
                    <div class="form-group col-md-2">
                        <label class="small text-muted d-block">&nbsp;</label>
                        <div class="custom-control custom-switch mt-2">
                            <input type="hidden" name="activo" value="0">
                            <input type="checkbox" class="custom-control-input" id="ci-nl-activo" name="activo" value="1" checked>
                            <label class="custom-control-label" for="ci-nl-activo">Activo</label>
                        </div>
                    </div>
                    <div class="form-group col-md-1">
                        <label class="small text-muted d-block">&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fa fa-save"></i>
                        </button>
                    </div>
                </div>
                <div class="small text-muted">Menor prioridad = se evalúa primero si empatan en largo de frase.</div>
            </div>
        </form>

        @if (! empty($metricasFijas))
            <div class="alert alert-light border mb-3">
                <strong class="d-block mb-1">Sinónimos fijos de métrica (código)</strong>
                @foreach ($metricasFijas as $metrica => $frases)
                    <div class="small mb-1">
                        <code>{{ $metrica }}</code>:
                        {{ implode(' · ', $frases) }}
                    </div>
                @endforeach
            </div>
        @endif

        <div class="card card-info shadow-sm">
            <div class="card-header"><strong>Sinónimos cargados</strong></div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Frase</th>
                            <th>Entidad</th>
                            @if (! empty($tieneMetrica))
                                <th>Métrica</th>
                            @endif
                            <th>Prioridad</th>
                            <th>Activo</th>
                            <th style="width:7rem;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sinonimos as $row)
                            <tr>
                                <td>{{ $row->frase }}</td>
                                <td><code>{{ $row->entidad }}</code></td>
                                @if (! empty($tieneMetrica))
                                    <td>{{ $row->metrica ?: '—' }}</td>
                                @endif
                                <td>{{ $row->prioridad }}</td>
                                <td>{{ $row->activo ? 'Sí' : 'No' }}</td>
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-accion-tabla js-ci-nl-edit"
                                            data-id="{{ $row->id }}"
                                            data-frase="{{ e($row->frase) }}"
                                            data-entidad="{{ e($row->entidad) }}"
                                            data-metrica="{{ e($row->metrica ?? '') }}"
                                            data-prioridad="{{ $row->prioridad }}"
                                            data-activo="{{ $row->activo ? '1' : '0' }}"
                                            title="Editar">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    <form method="post" action="{{ route('eliminar_diccionario_consulta_inteligente', $row->id) }}"
                                          class="d-inline" onsubmit="return confirm('¿Eliminar este sinónimo?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-accion-tabla" title="Eliminar">
                                            <i class="fa fa-times-circle text-danger"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ ! empty($tieneMetrica) ? 6 : 5 }}" class="text-muted text-center py-3">Todavía no hay sinónimos.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
document.querySelectorAll('.js-ci-nl-edit').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('ci-nl-id').value = btn.getAttribute('data-id') || '';
        document.getElementById('ci-nl-frase').value = btn.getAttribute('data-frase') || '';
        document.getElementById('ci-nl-entidad').value = btn.getAttribute('data-entidad') || '';
        var met = document.getElementById('ci-nl-metrica');
        if (met) { met.value = btn.getAttribute('data-metrica') || ''; }
        document.getElementById('ci-nl-prio').value = btn.getAttribute('data-prioridad') || '100';
        document.getElementById('ci-nl-activo').checked = btn.getAttribute('data-activo') === '1';
        document.getElementById('ci-nl-frase').focus();
    });
});
</script>
@endsection
