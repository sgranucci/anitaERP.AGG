{{-- Inventario IT: PC / m&aacute;quina / instalaciones --}}
<div class="form-group row">
    <label for="tipo_bien" class="col-lg-4 control-label text-right pr-2 requerido">Tipo de bien</label>
    <div class="col-lg-3">
        <select id="tipo_bien" name="tipo_bien" class="form-control" required>
            <option value="">-- Elija tipo --</option>
            @foreach ($tipo_bien_enum as $item)
                <option value="{{ $item['valor'] }}"
                    {{ (string) old('tipo_bien', $data->tipo_bien ?? '') === $item['valor'] ? 'selected' : '' }}>
                    {{ $item['nombre'] }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="form-group row campos-bien-pc">
    <label for="codigo_inventario" class="col-lg-4 control-label text-right pr-2">C&oacute;d. inventario</label>
    <div class="col-lg-3">
        <input type="number" name="codigo_inventario" id="codigo_inventario" class="form-control" min="1"
               value="{{ old('codigo_inventario', $data->codigo_inventario ?? '') }}">
    </div>
</div>

<div class="form-group row campos-bien-maquina">
    <label for="uid" class="col-lg-4 control-label text-right pr-2 requerido">UID</label>
    <div class="col-lg-3">
        <input type="text" name="uid" id="uid" class="form-control" maxlength="20"
               value="{{ old('uid', $data->uid ?? '') }}" placeholder="021-0238"
               title="Identificador único de la máquina tragamonedas">
        <small class="form-text text-muted">Identificador &uacute;nico de tragamonedas (ej. 021-0238).</small>
    </div>
</div>

<div class="campos-bien-maquina">
    @include('includes.form-empresa-asignada', [
        'empresa_query' => $empresa_query ?? collect(),
        'empresa_id' => old('empresa_id', $data->empresa_id ?? null),
        'solo_lectura' => false,
        'col_label' => 'col-lg-4 control-label text-right pr-2',
        'col_input' => 'col-lg-7',
    ])
</div>

<div class="form-group row campos-bien-pc">
    <label for="hostname" class="col-lg-4 control-label text-right pr-2 requerido">Hostname</label>
    <div class="col-lg-6">
        <input type="text" name="hostname" id="hostname" class="form-control"
               value="{{ old('hostname', $data->hostname ?? '') }}">
    </div>
</div>

<div class="form-group row campos-bien-pc">
    <label for="ip" class="col-lg-4 control-label text-right pr-2">IP</label>
    <div class="col-lg-4">
        <input type="text" name="ip" id="ip" class="form-control"
               value="{{ old('ip', $data->ip ?? '') }}">
    </div>
</div>

<div class="form-group row">
    <label for="modelo" class="col-lg-4 control-label text-right pr-2">Modelo</label>
    <div class="col-lg-6">
        <input type="text" name="modelo" id="modelo" class="form-control"
               value="{{ old('modelo', $data->modelo ?? '') }}">
    </div>
</div>

<div class="form-group row campos-bien-maquina">
    <label for="vendor" class="col-lg-4 control-label text-right pr-2">Vendor / fabricante</label>
    <div class="col-lg-6">
        <input type="text" name="vendor" id="vendor" class="form-control"
               value="{{ old('vendor', $data->vendor ?? '') }}">
    </div>
</div>

<div class="form-group row campos-bien-maquina">
    <label for="tema" class="col-lg-4 control-label text-right pr-2">Tema / juego</label>
    <div class="col-lg-6">
        <input type="text" name="tema" id="tema" class="form-control"
               value="{{ old('tema', $data->tema ?? '') }}">
    </div>
</div>

<div class="form-group row">
    <label for="numero_serie" class="col-lg-4 control-label text-right pr-2">N&uacute;mero de serie</label>
    <div class="col-lg-4">
        <input type="text" name="numero_serie" id="numero_serie" class="form-control"
               value="{{ old('numero_serie', $data->numero_serie ?? '') }}">
    </div>
</div>
