<div class="form-group row">
    <label for="tipo_bien" class="col-lg-3 col-form-label requerido">Tipo de bien</label>
    <div class="col-lg-3">
        <select id="tipo_bien" name="tipo_bien" class="form-control" required>
            <option value="">-- Elija tipo --</option>
            @foreach($tipo_bien_enum as $item)
                @if ($item['valor'] == old('tipo_bien', $data->tipo_bien ?? ''))
                    <option value="{{ $item['valor'] }}" selected>{{ $item['nombre'] }}</option>
                @else
                    <option value="{{ $item['valor'] }}">{{ $item['nombre'] }}</option>
                @endif
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row campos-bien-pc">
    <label for="codigo_inventario" class="col-lg-3 col-form-label">C&oacute;d. inventario</label>
    <div class="col-lg-3">
        <input type="number" name="codigo_inventario" id="codigo_inventario" class="form-control" min="1"
               value="{{ old('codigo_inventario', $data->codigo_inventario ?? '') }}">
    </div>
</div>

<div class="form-group row campos-bien-maquina">
    <label for="uid" class="col-lg-3 col-form-label requerido">UID</label>
    <div class="col-lg-3">
        <input type="text" name="uid" id="uid" class="form-control" maxlength="20"
               value="{{ old('uid', $data->uid ?? '') }}" placeholder="021-0238"
               title="Identificador &uacute;nico de la m&aacute;quina tragamonedas">
        <small class="form-text text-muted">Identificador &uacute;nico de tragamonedas (ej. 021-0238).</small>
    </div>
</div>

<div class="form-group row campos-bien-maquina">
    <label for="empresa_id" class="col-lg-3 col-form-label requerido">Empresa</label>
    <div class="col-lg-6">
        @include('includes.form-empresa-asignada', [
            'empresa_query' => $empresa_query ?? collect(),
            'empresa_id' => old('empresa_id', $data->empresa_id ?? null),
            'solo_lectura' => false,
        ])
    </div>
</div>

<div class="form-group row campos-bien-pc">
    <label for="hostname" class="col-lg-3 col-form-label requerido">Hostname</label>
    <div class="col-lg-6">
        <input type="text" name="hostname" id="hostname" class="form-control"
               value="{{ old('hostname', $data->hostname ?? '') }}">
    </div>
</div>

<div class="form-group row campos-bien-pc">
    <label for="ip" class="col-lg-3 col-form-label">IP</label>
    <div class="col-lg-4">
        <input type="text" name="ip" id="ip" class="form-control"
               value="{{ old('ip', $data->ip ?? '') }}">
    </div>
</div>

<div class="form-group row">
    <label for="modelo" class="col-lg-3 col-form-label">Modelo</label>
    <div class="col-lg-6">
        <input type="text" name="modelo" id="modelo" class="form-control"
               value="{{ old('modelo', $data->modelo ?? '') }}">
    </div>
</div>

<div class="form-group row campos-bien-maquina">
    <label for="vendor" class="col-lg-3 col-form-label">Vendor / fabricante</label>
    <div class="col-lg-6">
        <input type="text" name="vendor" id="vendor" class="form-control"
               value="{{ old('vendor', $data->vendor ?? '') }}">
    </div>
</div>

<div class="form-group row campos-bien-maquina">
    <label for="tema" class="col-lg-3 col-form-label">Tema / juego</label>
    <div class="col-lg-6">
        <input type="text" name="tema" id="tema" class="form-control"
               value="{{ old('tema', $data->tema ?? '') }}">
    </div>
</div>

<div class="form-group row">
    <label for="numero_serie" class="col-lg-3 col-form-label">N&uacute;mero de serie</label>
    <div class="col-lg-4">
        <input type="text" name="numero_serie" id="numero_serie" class="form-control"
               value="{{ old('numero_serie', $data->numero_serie ?? '') }}">
    </div>
</div>

<div class="form-group row">
    <label for="estado" class="col-lg-3 col-form-label requerido">Estado</label>
    <div class="col-lg-3">
        <select id="estado" name="estado" class="form-control" required>
            <option value="">-- Elija estado --</option>
            @foreach($estado_enum as $item)
                @if ($item['valor'] == old('estado', $data->estado ?? 'A'))
                    <option value="{{ $item['valor'] }}" selected>{{ $item['nombre'] }}</option>
                @else
                    <option value="{{ $item['valor'] }}">{{ $item['nombre'] }}</option>
                @endif
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row">
    <label for="centrocosto_id" class="col-lg-3 col-form-label requerido">Centro de costo</label>
    <div class="col-lg-6">
        <select id="centrocosto_id" name="centrocosto_id" class="form-control" required>
            <option value="">-- Elija centro de costo --</option>
            @foreach($centrocosto_opciones as $cc)
                @if ((int) $cc->id === (int) old('centrocosto_id', $data->centrocosto_id ?? ($centrocosto_opciones->first()->id ?? 0)))
                    <option value="{{ $cc->id }}" selected>{{ $cc->codigo }} — {{ $cc->nombre }}</option>
                @else
                    <option value="{{ $cc->id }}">{{ $cc->codigo }} — {{ $cc->nombre }}</option>
                @endif
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row">
    <label for="observaciones" class="col-lg-3 col-form-label">Observaciones</label>
    <div class="col-lg-8">
        <textarea name="observaciones" id="observaciones" class="form-control" rows="3">{{ old('observaciones', $data->observaciones ?? '') }}</textarea>
    </div>
</div>

<script>
(function () {
    function actualizarCamposBienUso() {
        var tipo = document.getElementById('tipo_bien').value;
        var esMaquina = tipo === 'M' || tipo === 'I';
        var esPc = tipo === 'P';

        document.querySelectorAll('.campos-bien-maquina').forEach(function (el) {
            el.style.display = esMaquina ? '' : 'none';
        });
        document.querySelectorAll('.campos-bien-pc').forEach(function (el) {
            el.style.display = esPc ? '' : 'none';
        });

        var hostname = document.getElementById('hostname');
        var uid = document.getElementById('uid');
        if (hostname) {
            hostname.required = esPc;
        }
        if (uid) {
            uid.required = tipo === 'M';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var select = document.getElementById('tipo_bien');
        if (!select) {
            return;
        }
        select.addEventListener('change', actualizarCamposBienUso);
        actualizarCamposBienUso();
    });
})();
</script>
