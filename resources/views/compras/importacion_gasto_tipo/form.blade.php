@include('includes.form-error')
@include('includes.mensaje')

@php $esEdicion = isset($data) && $data->id; @endphp

<form method="POST" data-enter-campos="1" class="form-horizontal"
      action="{{ $esEdicion ? route('actualizar_importacion_gasto_tipo', $data->id) : route('guardar_importacion_gasto_tipo') }}">
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <div class="form-group row">
        <label class="col-lg-4 control-label text-right pr-2 requerido">Código</label>
        <div class="col-lg-7">
            <input type="text" name="codigo" class="form-control" required maxlength="30"
                   value="{{ old('codigo', $data->codigo ?? '') }}">
        </div>
    </div>
    <div class="form-group row">
        <label class="col-lg-4 control-label text-right pr-2 requerido">Nombre</label>
        <div class="col-lg-7">
            <input type="text" name="nombre" class="form-control" required maxlength="120"
                   value="{{ old('nombre', $data->nombre ?? '') }}">
        </div>
    </div>
    <div class="form-group row">
        <label class="col-lg-4 control-label text-right pr-2">Tratamiento</label>
        <div class="col-lg-7">
            <select name="tratamiento" class="form-control" required>
                @foreach ($tratamientos as $id => $nombre)
                    <option value="{{ $id }}" @if (old('tratamiento', $data->tratamiento ?? '') === $id) selected @endif>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="form-group row">
        <label class="col-lg-4 control-label text-right pr-2">Base prorrateo</label>
        <div class="col-lg-7">
            <select name="base_prorrateo" class="form-control" required>
                @foreach ($bases as $id => $nombre)
                    <option value="{{ $id }}" @if (old('base_prorrateo', $data->base_prorrateo ?? '') === $id) selected @endif>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="form-group row">
        <label class="col-lg-4 control-label text-right pr-2">Cuenta contable (ID)</label>
        <div class="col-lg-7">
            <input type="number" name="cuentacontable_id" class="form-control"
                   value="{{ old('cuentacontable_id', $data->cuentacontable_id ?? '') }}">
            <small class="text-muted">Opcional. Si vacío, usa cuentas automáticas de importación.</small>
        </div>
    </div>
    <div class="form-group row">
        <label class="col-lg-4 control-label text-right pr-2">Orden</label>
        <div class="col-lg-3">
            <input type="number" name="orden" class="form-control" value="{{ old('orden', $data->orden ?? 100) }}">
        </div>
        <div class="col-lg-4">
            <div class="form-check mt-2">
                <input type="checkbox" class="form-check-input" name="activo" id="activo" value="1"
                       @if (old('activo', $data->activo ?? true)) checked @endif>
                <label class="form-check-label" for="activo">Activo</label>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-success">{{ $esEdicion ? 'Actualizar' : 'Guardar' }}</button>
</form>
