@extends("theme.$theme.layout")
@section('titulo')
{{ $regla->exists ? 'Editar regla' : 'Nueva regla' }}
@endsection

@section('styles')
<style>
.aviso-regla-flow { display: grid; grid-template-columns: 1fr; gap: 1rem; }
@media (min-width: 992px) {
  .aviso-regla-flow { grid-template-columns: 1fr 1.2fr 1.2fr; }
}
.aviso-regla-step {
  border: 1px solid #bcd4e6;
  border-radius: .35rem;
  background: #f8fbfe;
  min-height: 12rem;
}
.aviso-regla-step-h {
  background: #85C1E9;
  color: #17202A;
  font-weight: 700;
  padding: .5rem .75rem;
  border-bottom: 1px solid #9fc5de;
}
.aviso-regla-step-b { padding: .75rem; }
.aviso-regla-chip {
  display: inline-block;
  background: #d6eaf8;
  border-radius: .25rem;
  padding: .15rem .45rem;
  font-size: .8rem;
  margin: .15rem .15rem 0 0;
}
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
                <a href="{{ route('consultar_aviso_regla') }}">Reglas de aviso</a>.
            </div>
        @endunless

        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    {{ $regla->exists ? $regla->nombre : 'Nueva regla de aviso' }}
                </h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_aviso_regla') }}" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <form method="post"
                action="{{ $regla->exists ? route('actualizar_aviso_regla', $regla->id) : route('guardar_aviso_regla') }}"
                data-enter-campos="1" id="form-aviso-regla">
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
                            <input type="hidden" name="activo" value="0">
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" name="activo" id="activo" value="1"
                                    @if(old('activo', $regla->activo)) checked @endif>
                                <label class="form-check-label" for="activo">Regla activa</label>
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

                    <div class="aviso-regla-flow mt-3">
                        <div class="aviso-regla-step">
                            <div class="aviso-regla-step-h">1 · WHEN — Evento</div>
                            <div class="aviso-regla-step-b">
                                <label for="evento">Al ocurrir</label>
                                <select name="evento" id="evento" class="form-control" required>
                                    @foreach ($catalogo as $clave => $etiqueta)
                                        <option value="{{ $clave }}" @if(old('evento', $regla->evento)===$clave) selected @endif>
                                            {{ $etiqueta }} ({{ $clave }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-muted small mt-2 mb-0">
                                    El ERP debe emitir este evento con <code>ModuloAvisoEventBus::emit</code>.
                                </p>
                            </div>
                        </div>

                        <div class="aviso-regla-step">
                            <div class="aviso-regla-step-h">2 · IF — Condiciones</div>
                            <div class="aviso-regla-step-b">
                                <div class="form-group">
                                    <label for="cond_logic">Lógica del grupo</label>
                                    <select id="cond_logic" class="form-control form-control-sm">
                                        <option value="and">TODAS (AND)</option>
                                        <option value="or">ALGUNA (OR)</option>
                                    </select>
                                </div>
                                <div id="cond-rows"></div>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-cond">+ Condición</button>
                                <p class="text-muted small mt-2 mb-0">Sin filas = siempre verdadero (solo el evento dispara).</p>
                            </div>
                        </div>

                        <div class="aviso-regla-step">
                            <div class="aviso-regla-step-h">3 · THEN — Acciones</div>
                            <div class="aviso-regla-step-b">
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
                    <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                    <a href="{{ route('consultar_aviso_regla') }}" class="btn btn-outline-info btn-sm">Cancelar</a>
                </div>
            </form>
        </div>

        @if ($regla->exists)
            <div class="card card-outline card-info mt-3">
                <div class="card-header"><h3 class="card-title mb-0">Probar condiciones (sin enviar)</h3></div>
                <form method="post" action="{{ route('probar_aviso_regla', $regla->id) }}" data-enter-campos="1">
                    @csrf
                    <div class="card-body">
                        <label for="contexto_json">Contexto de ejemplo (JSON del emit)</label>
                        <textarea class="form-control" name="contexto_json" id="contexto_json" rows="5">{{ old('contexto_json', "{\n  \"entity_id\": 1,\n  \"empresa_id\": 1,\n  \"monto\": 1500000\n}") }}</textarea>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-outline-primary btn-sm">Simular</button>
                    </div>
                </form>
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
    var TIPOS_AVISO = @json($tipos_aviso);
    var ROLES = @json($roles);
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

    function optionsHtml(map, selected) {
        return Object.keys(map).map(function (k) {
            return '<option value="'+k+'"'+(String(selected)===String(k)?' selected':'')+'>'+map[k]+(typeof map[k]==='object'?(map[k].etiqueta||k):'')+'</option>';
        }).join('');
    }

    function campoOptions(selected) {
        return Object.keys(CAMPOS).map(function (k) {
            var et = CAMPOS[k].etiqueta || k;
            return '<option value="'+k+'"'+(selected===k?' selected':'')+'>'+et+'</option>';
        }).join('');
    }

    function opOptions(selected) {
        return Object.keys(OPS).map(function (k) {
            return '<option value="'+k+'"'+(selected===k?' selected':'')+'>'+OPS[k]+'</option>';
        }).join('');
    }

    function addCond(data) {
        data = data || {campo:'empresa_id', op:'eq', valor:''};
        var row = document.createElement('div');
        row.className = 'form-row align-items-end mb-2 cond-row';
        row.innerHTML =
            '<div class="form-group col-md-4 mb-1"><label class="small mb-0">Campo</label>'+
            '<select class="form-control form-control-sm js-campo">'+campoOptions(data.campo)+'</select></div>'+
            '<div class="form-group col-md-3 mb-1"><label class="small mb-0">Op</label>'+
            '<select class="form-control form-control-sm js-op">'+opOptions(data.op)+'</select></div>'+
            '<div class="form-group col-md-4 mb-1"><label class="small mb-0">Valor</label>'+
            '<input type="text" class="form-control form-control-sm js-valor" value="'+(data.valor!=null?String(data.valor).replace(/"/g,'&quot;'):'')+'"></div>'+
            '<div class="form-group col-md-1 mb-1"><button type="button" class="btn btn-sm btn-outline-danger js-del">&times;</button></div>';
        condRows.appendChild(row);
        row.querySelector('.js-del').addEventListener('click', function () { row.remove(); sync(); });
        row.querySelectorAll('select,input').forEach(function (el) { el.addEventListener('change', sync); el.addEventListener('input', sync); });
    }

    function addAcc(data) {
        data = data || {tipo:'enviar_aviso', modulo:'', codigo:'', usuario_ids:'', rol_ids:'', titulo:'', cuerpo:''};
        var row = document.createElement('div');
        row.className = 'border rounded p-2 mb-2 acc-row';
        var avisosOpts = TIPOS_AVISO.map(function (t) {
            var v = t.modulo+'/'+t.codigo;
            var sel = (data.modulo===t.modulo && data.codigo===t.codigo) ? ' selected' : '';
            return '<option value="'+v+'"'+sel+'>'+v+' — '+t.nombre+'</option>';
        }).join('');
        var rolesOpts = ROLES.map(function (r) {
            var ids = String(data.rol_ids||'').split(/[,\s]+/);
            var sel = ids.indexOf(String(r.id)) >= 0 ? ' selected' : '';
            return '<option value="'+r.id+'"'+sel+'>'+r.nombre+'</option>';
        }).join('');
        var tipoOpts = Object.keys(ACCIONES).map(function (k) {
            return '<option value="'+k+'"'+(data.tipo===k?' selected':'')+'>'+ACCIONES[k]+'</option>';
        }).join('');
        row.innerHTML =
            '<div class="form-row">'+
            '<div class="form-group col-md-5 mb-1"><label class="small mb-0">Acción</label>'+
            '<select class="form-control form-control-sm js-tipo">'+tipoOpts+'</select></div>'+
            '<div class="form-group col-md-6 mb-1 js-box-aviso"><label class="small mb-0">Tipo aviso</label>'+
            '<select class="form-control form-control-sm js-aviso"><option value="">—</option>'+avisosOpts+'</select></div>'+
            '<div class="form-group col-md-1 mb-1"><button type="button" class="btn btn-sm btn-outline-danger js-del mt-3">&times;</button></div>'+
            '</div>'+
            '<div class="form-row js-box-users d-none">'+
            '<div class="form-group col-md-6 mb-1"><label class="small mb-0">Usuario IDs (coma)</label>'+
            '<input type="text" class="form-control form-control-sm js-uids" value="'+(data.usuario_ids||'')+'"></div>'+
            '<div class="form-group col-md-6 mb-1"><label class="small mb-0">Título</label>'+
            '<input type="text" class="form-control form-control-sm js-titulo" value="'+(data.titulo||'')+'"></div>'+
            '</div>'+
            '<div class="form-row js-box-roles d-none">'+
            '<div class="form-group col-md-6 mb-1"><label class="small mb-0">Roles</label>'+
            '<select class="form-control form-control-sm js-roles" multiple size="3">'+rolesOpts+'</select></div>'+
            '<div class="form-group col-md-6 mb-1"><label class="small mb-0">Título</label>'+
            '<input type="text" class="form-control form-control-sm js-titulo2" value="'+(data.titulo||'')+'"></div>'+
            '</div>'+
            '<div class="form-row js-box-webhook d-none">'+
            '<div class="form-group col-md-8 mb-1"><label class="small mb-0">Clave allowlist</label>'+
            '<input type="text" class="form-control form-control-sm js-webhook-clave" value="'+(data.clave||'')+'" placeholder="ops_aprobaciones / ops_stock / ops_general"></div>'+
            '</div>'+
            '<div class="form-group mb-0 js-box-cuerpo d-none"><label class="small mb-0">Cuerpo (placeholders {empresa_id} {monto}…)</label>'+
            '<input type="text" class="form-control form-control-sm js-cuerpo" value="'+(data.cuerpo||'')+'"></div>';
        accRows.appendChild(row);

        function refreshBoxes() {
            var t = row.querySelector('.js-tipo').value;
            row.querySelector('.js-box-aviso').classList.toggle('d-none', t !== 'enviar_aviso');
            row.querySelector('.js-box-users').classList.toggle('d-none', t !== 'notificar_usuarios');
            row.querySelector('.js-box-roles').classList.toggle('d-none', t !== 'notificar_roles');
            row.querySelector('.js-box-webhook').classList.toggle('d-none', t !== 'webhook_allowlist');
            row.querySelector('.js-box-cuerpo').classList.toggle('d-none', t === 'enviar_aviso' || t === 'webhook_allowlist');
        }
        row.querySelector('.js-tipo').addEventListener('change', function () { refreshBoxes(); sync(); });
        row.querySelector('.js-del').addEventListener('click', function () { row.remove(); sync(); });
        row.querySelectorAll('select,input').forEach(function (el) { el.addEventListener('change', sync); el.addEventListener('input', sync); });
        if (data.modulo && data.codigo) {
            row.querySelector('.js-aviso').value = data.modulo+'/'+data.codigo;
        }
        refreshBoxes();
    }

    function sync() {
        var rules = [];
        condRows.querySelectorAll('.cond-row').forEach(function (row) {
            rules.push({
                campo: row.querySelector('.js-campo').value,
                op: row.querySelector('.js-op').value,
                valor: row.querySelector('.js-valor').value
            });
        });
        var condiciones = { logic: condLogic.value, rules: rules };
        hidCond.value = JSON.stringify(condiciones, null, 2);

        var acciones = [];
        accRows.querySelectorAll('.acc-row').forEach(function (row) {
            var tipo = row.querySelector('.js-tipo').value;
            var a = { tipo: tipo };
            if (tipo === 'enviar_aviso') {
                var v = (row.querySelector('.js-aviso').value || '').split('/');
                a.modulo = v[0] || '';
                a.codigo = v[1] || '';
            } else if (tipo === 'notificar_usuarios') {
                a.usuario_ids = row.querySelector('.js-uids').value;
                a.titulo = row.querySelector('.js-titulo').value;
                a.cuerpo = row.querySelector('.js-cuerpo').value;
            } else if (tipo === 'notificar_roles') {
                var sel = row.querySelector('.js-roles');
                a.rol_ids = Array.prototype.slice.call(sel.selectedOptions).map(function (o) { return o.value; }).join(',');
                a.titulo = row.querySelector('.js-titulo2').value;
                a.cuerpo = row.querySelector('.js-cuerpo').value;
            } else if (tipo === 'webhook_allowlist') {
                a.clave = (row.querySelector('.js-webhook-clave').value || '').trim();
            }
            acciones.push(a);
        });
        hidAcc.value = JSON.stringify(acciones, null, 2);
        if (preview) {
            preview.textContent = 'condiciones:\n'+hidCond.value+'\n\nacciones:\n'+hidAcc.value;
        }
    }

    // bootstrap from hidden
    var initialCond = parseHidden(hidCond, {logic:'and', rules:[]});
    condLogic.value = initialCond.logic || 'and';
    (initialCond.rules || []).forEach(addCond);
    var initialAcc = parseHidden(hidAcc, []);
    if (Array.isArray(initialAcc)) initialAcc.forEach(addAcc);

    document.getElementById('btn-add-cond').addEventListener('click', function () { addCond(); sync(); });
    document.getElementById('btn-add-acc').addEventListener('click', function () { addAcc(); sync(); });
    condLogic.addEventListener('change', sync);

    document.getElementById('form-aviso-regla').addEventListener('submit', function () { sync(); });
    sync();
})();
</script>
@endsection
