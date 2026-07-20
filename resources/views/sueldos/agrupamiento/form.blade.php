@php
    $falloActual = old('fallo_tipo', $data->fallo_tipo ?? '');
@endphp
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">C&oacute;digo</label>
    <div class="col-lg-3">
        @if (isset($data))
            <input type="text" id="codigo" class="form-control" value="{{ $data->codigo }}" readonly/>
        @else
            <input type="number" name="codigo" id="codigo" class="form-control" min="1"
                   value="{{ old('codigo') }}"
                   placeholder="Autom&aacute;tico si se deja vac&iacute;o"/>
        @endif
    </div>
</div>
<div class="form-group row">
    <label for="descripcion" class="col-lg-3 col-form-label requerido">Descripci&oacute;n</label>
    <div class="col-lg-6">
        <input type="text" name="descripcion" id="descripcion" class="form-control" maxlength="30" required
               value="{{ old('descripcion', $data->descripcion ?? '') }}"/>
    </div>
</div>
<div class="form-group row">
    <label for="fallo_tipo" class="col-lg-3 col-form-label">Fallo de caja aplicado</label>
    <div class="col-lg-3">
        <select name="fallo_tipo" id="fallo_tipo" class="form-control"
                data-fallos='@json($fallosPorTipo ?? [])'>
            <option value="">(Sin fallo)</option>
            @foreach ($tipos as $t)
                <option value="{{ $t }}" {{ $falloActual === $t ? 'selected' : '' }}>{{ $t }}</option>
            @endforeach
        </select>
        <small class="form-text text-muted">Tabla de sanciones que aplica a este agrupamiento.</small>
    </div>
    <div class="col-lg-6">
        <div id="fallo-detalle-panel" class="border rounded bg-light p-2 small text-muted" style="display:none">
            <div class="font-weight-bold mb-1"><i class="fa fa-gavel"></i> Tramos de sanción <span id="fallo-detalle-tipo"></span></div>
            <ul class="mb-0 pl-3" id="fallo-detalle-lista"></ul>
        </div>
    </div>
</div>

<script>
(function () {
    var sel = document.getElementById('fallo_tipo');
    if (!sel) { return; }
    var fallos = {};
    try { fallos = JSON.parse(sel.getAttribute('data-fallos') || '{}'); } catch (e) { fallos = {}; }

    var panel = document.getElementById('fallo-detalle-panel');
    var lista = document.getElementById('fallo-detalle-lista');
    var tituloTipo = document.getElementById('fallo-detalle-tipo');

    function render() {
        var tipo = sel.value;
        var tramos = tipo && fallos[tipo] ? fallos[tipo] : [];
        lista.innerHTML = '';
        if (!tipo || !tramos.length) {
            panel.style.display = 'none';
            return;
        }
        tituloTipo.textContent = '· ' + tipo;
        tramos.forEach(function (t) {
            var li = document.createElement('li');
            li.textContent = t.linea;
            lista.appendChild(li);
        });
        panel.style.display = '';
    }

    sel.addEventListener('change', render);
    render();
})();
</script>
