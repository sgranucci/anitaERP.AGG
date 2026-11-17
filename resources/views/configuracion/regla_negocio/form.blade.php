@extends("theme.$theme.layout")
@section('titulo')
{{ $regla->exists ? 'Editar regla de negocio' : 'Nueva regla de negocio' }}
@endsection

@section('styles')
<style>
.rn-flow { display: grid; grid-template-columns: 1fr; gap: 1rem; }
@media (min-width: 992px) {
  .rn-flow { grid-template-columns: 1fr 1fr; }
}
.rn-step {
  border: 1px solid #bcd4e6;
  border-radius: .35rem;
  background: #f8fbfe;
  min-height: 10rem;
}
.rn-step-h {
  background: #85C1E9;
  color: #17202A;
  font-weight: 700;
  padding: .5rem .75rem;
  border-bottom: 1px solid #9fc5de;
}
.rn-step-b { padding: .75rem; }
</style>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')

        @unless ($motor_vivo)
            <div class="alert alert-warning">
                El motor global está <strong>detenido</strong>. Podés armar la regla igual; recién opera cuando lo actives en
                <a href="{{ route('consultar_regla_negocio') }}">Reglas de negocio</a>.
            </div>
        @endunless

        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    {{ $regla->exists ? $regla->nombre : 'Nueva regla de negocio' }}
                </h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_regla_negocio') }}" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <form method="post" id="form-regla-negocio"
                action="{{ $regla->exists ? route('actualizar_regla_negocio', $regla->id) : route('guardar_regla_negocio') }}"
                data-enter-campos="1">
                @csrf
                @if ($regla->exists) @method('PUT') @endif
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="codigo">Código</label>
                            <input type="text" class="form-control" name="codigo" id="codigo" maxlength="80" required
                                value="{{ old('codigo', $regla->codigo) }}" @if($regla->exists) readonly @endif>
                        </div>
                        <div class="form-group col-md-5">
                            <label for="nombre">Nombre</label>
                            <input type="text" class="form-control" name="nombre" id="nombre" maxlength="160" required
                                value="{{ old('nombre', $regla->nombre) }}">
                        </div>
                        <div class="form-group col-md-2">
                            <label for="prioridad">Prioridad</label>
                            <input type="number" min="1" max="9999" class="form-control" name="prioridad" id="prioridad"
                                value="{{ old('prioridad', $regla->prioridad ?: 100) }}">
                            <small class="text-muted">Menor = primero</small>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="d-block">Estado</label>
                            <input type="hidden" name="activo" value="{{ ($regla->activo && !($puede_activar ?? false)) ? '1' : '0' }}">
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" name="activo" id="activo" value="1"
                                    @if(old('activo', $regla->activo)) checked @endif
                                    @unless($puede_activar ?? false) disabled @endunless>
                                <label class="form-check-label" for="activo">Regla activa</label>
                            </div>
                            @unless($puede_activar ?? false)
                                <small class="text-muted">Requiere permiso activar</small>
                            @endunless
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="punto">Punto de decisión</label>
                            @php $cableados = array_keys(config('regla_negocio.puntos_cableados', [])); @endphp
                            <select name="punto" id="punto" class="form-control" required>
                                @foreach ($puntos as $clave => $meta)
                                    <option value="{{ $clave }}" @if(old('punto', $regla->punto)===$clave) selected @endif>
                                        {{ in_array($clave, $cableados, true) ? '● ' : '○ ' }}{{ $meta['etiqueta'] ?? $clave }} ({{ $clave }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">● = cableado en código · ○ = solo catálogo</small>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="dominio">Dominio</label>
                            <input type="text" class="form-control" name="dominio" id="dominio" maxlength="40"
                                value="{{ old('dominio', $regla->dominio) }}"
                                placeholder="auto desde punto">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="d-block">Stop on match</label>
                            <input type="hidden" name="stop_on_match" value="0">
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" name="stop_on_match" id="stop_on_match" value="1"
                                    @if(old('stop_on_match', $regla->stop_on_match ?? true)) checked @endif>
                                <label class="form-check-label" for="stop_on_match">Detener tras esta regla</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <input type="text" class="form-control" name="descripcion" id="descripcion" maxlength="500"
                            value="{{ old('descripcion', $regla->descripcion) }}">
                    </div>

                    @php
                        $condJson = old('condiciones_json', json_encode($regla->condiciones ?: ['logic'=>'and','rules'=>[]], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
                        $accJson = old('acciones_json', json_encode($regla->acciones ?: [], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
                    @endphp
                    <input type="hidden" name="condiciones_json" id="condiciones_json" value="{{ $condJson }}">
                    <input type="hidden" name="acciones_json" id="acciones_json" value="{{ $accJson }}">

                    <div class="rn-flow mt-3">
                        <div class="rn-step">
                            <div class="rn-step-h">IF — Condiciones</div>
                            <div class="rn-step-b">
                                <div class="form-group">
                                    <label for="cond_logic">Lógica del grupo</label>
                                    <select id="cond_logic" class="form-control form-control-sm">
                                        <option value="and">TODAS (AND)</option>
                                        <option value="or">ALGUNA (OR)</option>
                                    </select>
                                </div>
                                <div id="cond-rows"></div>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-cond">+ Condición</button>
                                <p class="text-muted small mt-2 mb-0">Sin filas = siempre verdadero.</p>
                            </div>
                        </div>
                        <div class="rn-step">
                            <div class="rn-step-h">THEN — Acciones</div>
                            <div class="rn-step-b">
                                <div id="acc-rows"></div>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-acc">+ Acción</button>
                            </div>
                        </div>
                    </div>

                    <details class="mt-3">
                        <summary class="text-muted small">JSON avanzado (sincronizado con el diseñador)</summary>
                        <pre class="small bg-light border p-2 mt-2" id="preview-json" style="max-height:14rem;overflow:auto;"></pre>
                    </details>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary" @unless($puede_disenar ?? true) disabled @endunless>Guardar</button>
                </div>
            </form>
        </div>

        @if ($regla->exists)
            <div class="card card-outline card-secondary mt-3">
                <div class="card-header">
                    <h3 class="card-title mb-0">Probar (simulación sin efectos)</h3>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ route('probar_regla_negocio', $regla->id) }}" data-enter-campos="1">
                        @csrf
                        <div class="form-group">
                            <label for="contexto_json">Contexto JSON</label>
                            <textarea class="form-control font-monospace" name="contexto_json" id="contexto_json" rows="6"
                                style="font-size:.85rem;">{{ old('contexto_json', "{\n  \"monto\": 1500,\n  \"tipo\": \"TRA\"\n}") }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm" @unless($puede_disenar ?? false) disabled @endunless>Probar</button>
                    </form>
                </div>
            </div>

            <div class="card card-outline card-secondary mt-3">
                <div class="card-header">
                    <h3 class="card-title mb-0">Historial de versiones</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>#</th>
                                <th>Cuándo</th>
                                <th>Motivo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($versiones as $v)
                                <tr>
                                    <td>v{{ $v->nro_version }}</td>
                                    <td>{{ optional($v->created_at)->format('d/m/Y H:i:s') }}</td>
                                    <td><code class="small">{{ $v->motivo }}</code></td>
                                    <td class="text-right">
                                        @if ($puede_disenar ?? false)
                                            <form method="post" action="{{ route('restaurar_regla_negocio_version', [$regla->id, $v->id]) }}" class="d-inline"
                                                onsubmit="return confirm('¿Restaurar versión {{ $v->nro_version }}?');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Restaurar</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted p-3">Sin versiones aún (se crean al guardar).</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    var CAMPOS = @json($campos);
    var OPS = @json($operadores);
    var ACCIONES = @json($tipos_accion);

    var condRows = document.getElementById('cond-rows');
    var accRows = document.getElementById('acc-rows');
    var condLogic = document.getElementById('cond_logic');
    var hidCond = document.getElementById('condiciones_json');
    var hidAcc = document.getElementById('acciones_json');
    var preview = document.getElementById('preview-json');

    function parseHidden(el, fallback) {
        try { return JSON.parse(el.value || '') || fallback; } catch (e) { return fallback; }
    }

    function campoOptions(selected) {
        return Object.keys(CAMPOS).map(function (k) {
            var et = (CAMPOS[k] && CAMPOS[k].etiqueta) ? CAMPOS[k].etiqueta : k;
            return '<option value="'+k+'"'+(selected===k?' selected':'')+'>'+et+' ('+k+')</option>';
        }).join('');
    }

    function opOptions(selected) {
        return Object.keys(OPS).map(function (k) {
            return '<option value="'+k+'"'+(selected===k?' selected':'')+'>'+OPS[k]+'</option>';
        }).join('');
    }

    function esc(v) {
        return String(v == null ? '' : v).replace(/"/g, '&quot;');
    }

    function addCond(data) {
        data = data || {campo: Object.keys(CAMPOS)[0] || 'monto', op:'eq', valor:''};
        var row = document.createElement('div');
        row.className = 'form-row align-items-end mb-2 cond-row';
        row.innerHTML =
            '<div class="form-group col-md-4 mb-1"><label class="small mb-0">Campo</label>'+
            '<select class="form-control form-control-sm js-campo">'+campoOptions(data.campo)+'</select></div>'+
            '<div class="form-group col-md-3 mb-1"><label class="small mb-0">Op</label>'+
            '<select class="form-control form-control-sm js-op">'+opOptions(data.op || 'eq')+'</select></div>'+
            '<div class="form-group col-md-4 mb-1"><label class="small mb-0">Valor</label>'+
            '<input type="text" class="form-control form-control-sm js-valor" value="'+esc(data.valor)+'"></div>'+
            '<div class="form-group col-md-1 mb-1"><button type="button" class="btn btn-sm btn-outline-danger js-del">&times;</button></div>';
        condRows.appendChild(row);
        row.querySelector('.js-del').addEventListener('click', function () { row.remove(); sync(); });
        row.querySelectorAll('select,input').forEach(function (el) {
            el.addEventListener('change', sync); el.addEventListener('input', sync);
        });
    }

    function addAcc(data) {
        data = data || {tipo:'set_dato', clave:'', valor:'', mensaje:'', modulo:'', codigo:''};
        var row = document.createElement('div');
        row.className = 'border rounded p-2 mb-2 acc-row';
        var tipoOpts = Object.keys(ACCIONES).map(function (k) {
            return '<option value="'+k+'"'+(data.tipo===k?' selected':'')+'>'+ACCIONES[k]+'</option>';
        }).join('');
        row.innerHTML =
            '<div class="form-row">'+
            '<div class="form-group col-md-4 mb-1"><label class="small mb-0">Acción</label>'+
            '<select class="form-control form-control-sm js-tipo">'+tipoOpts+'</select></div>'+
            '<div class="form-group col-md-3 mb-1 js-box-clave"><label class="small mb-0">Clave</label>'+
            '<input type="text" class="form-control form-control-sm js-clave" value="'+esc(data.clave)+'" placeholder="requiere_aprobacion"></div>'+
            '<div class="form-group col-md-4 mb-1 js-box-valor"><label class="small mb-0">Valor</label>'+
            '<input type="text" class="form-control form-control-sm js-valor-acc" value="'+esc(data.valor)+'" placeholder="true / 3 / exportacion"></div>'+
            '<div class="form-group col-md-1 mb-1"><button type="button" class="btn btn-sm btn-outline-danger js-del mt-3">&times;</button></div>'+
            '</div>'+
            '<div class="form-group mb-1 js-box-mensaje d-none"><label class="small mb-0">Mensaje</label>'+
            '<input type="text" class="form-control form-control-sm js-mensaje" value="'+esc(data.mensaje)+'"></div>'+
            '<div class="form-row js-box-aviso d-none">'+
            '<div class="form-group col-md-6 mb-0"><label class="small mb-0">Módulo aviso</label>'+
            '<input type="text" class="form-control form-control-sm js-modulo" value="'+esc(data.modulo)+'"></div>'+
            '<div class="form-group col-md-6 mb-0"><label class="small mb-0">Código aviso</label>'+
            '<input type="text" class="form-control form-control-sm js-codigo-aviso" value="'+esc(data.codigo)+'"></div>'+
            '</div>';
        accRows.appendChild(row);

        function refreshBoxes() {
            var t = row.querySelector('.js-tipo').value;
            row.querySelector('.js-box-clave').classList.toggle('d-none', t !== 'set_dato');
            row.querySelector('.js-box-valor').classList.toggle('d-none', t !== 'set_dato');
            row.querySelector('.js-box-mensaje').classList.toggle('d-none', t !== 'deny' && t !== 'require_approval');
            row.querySelector('.js-box-aviso').classList.toggle('d-none', t !== 'enviar_aviso');
        }
        row.querySelector('.js-tipo').addEventListener('change', function () { refreshBoxes(); sync(); });
        row.querySelector('.js-del').addEventListener('click', function () { row.remove(); sync(); });
        row.querySelectorAll('select,input').forEach(function (el) {
            el.addEventListener('change', sync); el.addEventListener('input', sync);
        });
        refreshBoxes();
    }

    function coerceValor(raw) {
        var s = String(raw == null ? '' : raw).trim();
        if (s === 'true') return true;
        if (s === 'false') return false;
        if (s !== '' && !isNaN(s) && /^-?\d+(\.\d+)?$/.test(s)) return Number(s);
        return s;
    }

    function sync() {
        var rules = [];
        condRows.querySelectorAll('.cond-row').forEach(function (row) {
            rules.push({
                campo: row.querySelector('.js-campo').value,
                op: row.querySelector('.js-op').value,
                valor: coerceValor(row.querySelector('.js-valor').value)
            });
        });
        hidCond.value = JSON.stringify({ logic: condLogic.value, rules: rules }, null, 2);

        var acciones = [];
        accRows.querySelectorAll('.acc-row').forEach(function (row) {
            var tipo = row.querySelector('.js-tipo').value;
            var a = { tipo: tipo };
            if (tipo === 'set_dato') {
                a.clave = row.querySelector('.js-clave').value.trim();
                a.valor = coerceValor(row.querySelector('.js-valor-acc').value);
            } else if (tipo === 'deny' || tipo === 'require_approval') {
                a.mensaje = row.querySelector('.js-mensaje').value;
            } else if (tipo === 'enviar_aviso') {
                a.modulo = row.querySelector('.js-modulo').value.trim();
                a.codigo = row.querySelector('.js-codigo-aviso').value.trim();
            }
            acciones.push(a);
        });
        hidAcc.value = JSON.stringify(acciones, null, 2);
        if (preview) {
            preview.textContent = 'condiciones:\n'+hidCond.value+'\n\nacciones:\n'+hidAcc.value;
        }
    }

    var initialCond = parseHidden(hidCond, {logic:'and', rules:[]});
    condLogic.value = initialCond.logic || 'and';
    (initialCond.rules || []).forEach(function (r) {
        if (r && r.campo) addCond(r);
    });
    var initialAcc = parseHidden(hidAcc, []);
    if (Array.isArray(initialAcc)) initialAcc.forEach(addAcc);

    document.getElementById('btn-add-cond').addEventListener('click', function () { addCond(); sync(); });
    document.getElementById('btn-add-acc').addEventListener('click', function () { addAcc(); sync(); });
    condLogic.addEventListener('change', sync);
    document.getElementById('form-regla-negocio').addEventListener('submit', function () { sync(); });
    sync();
})();
</script>
@endsection
