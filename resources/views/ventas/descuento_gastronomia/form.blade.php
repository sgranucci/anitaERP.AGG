<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required maxlength="255"/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">Código Anita</label>
    <div class="col-lg-8">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{ old('codigo', $data->codigo ?? '') }}" maxlength="50"/>
        <small class="form-text text-muted">Corresponde a dto_codigo en Anita (clave de sincronización).</small>
    </div>
</div>
<div class="form-group row">
    <label for="tipovalor" class="col-lg-3 col-form-label requerido">Tipo de valor</label>
    <div class="col-lg-8">
        <select name="tipovalor" id="tipovalor" class="form-control" required>
            <option value="">Seleccione…</option>
            @foreach ($tiposValor as $clave => $etiqueta)
                <option value="{{ $clave }}" {{ old('tipovalor', $data->tipovalor ?? '') === $clave ? 'selected' : '' }}>
                    {{ $etiqueta }} ({{ $clave }})
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="valor" class="col-lg-3 col-form-label requerido">Valor</label>
    <div class="col-lg-8">
        <input type="number" name="valor" id="valor" class="form-control" value="{{ old('valor', $data->valor ?? '') }}" required step="any"/>
    </div>
</div>
<div class="form-group row">
    <label for="cliente_id" class="col-lg-3 col-form-label">Cliente consumo interno</label>
    <div class="col-lg-8 d-flex align-items-center flex-wrap">
        <input type="text" class="form-control col-lg-2 mb-1 mb-lg-0" id="cliente_id" name="cliente_id" value="{{ old('cliente_id', $data->cliente_id ?? '') }}" placeholder="ID">
        <button type="button" title="Consulta clientes" class="btn-accion-tabla consultacliente tooltipsC mx-1">
            <i class="fa fa-search text-primary"></i>
        </button>
        <input type="text" class="form-control col-lg-7" id="nombrecliente" name="nombrecliente" value="{{ old('nombrecliente', $data->cliente->nombre ?? '') }}" placeholder="Nombre / razón social" readonly>
        <small class="form-text text-muted w-100">Cliente al que se aplica el descuento o invitación (Anita: dto_cliente). Distinto del cliente de facturación.</small>
    </div>
</div>
