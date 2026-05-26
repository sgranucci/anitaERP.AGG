<div class="form-group row">
    <label class="col-lg-3 col-form-label requerido">Depósito</label>
    <div class="col-lg-6">
        <select name="deposito_id" class="form-control" required>
            <option value="">-- Seleccionar --</option>
            @foreach ($depositos as $d)
                <option value="{{ $d->id }}" @if ((int) old('deposito_id', $data->deposito_id ?? 0) === (int) $d->id) selected @endif>
                    {{ $d->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 col-form-label requerido">Usuario</label>
    <div class="col-lg-6">
        <select name="usuario_id" class="form-control" required>
            <option value="">-- Seleccionar --</option>
            @foreach ($usuarios as $u)
                <option value="{{ $u->id }}" @if ((int) old('usuario_id', $data->usuario_id ?? 0) === (int) $u->id) selected @endif>
                    {{ $u->nombre }}
                    @if (! empty($u->email))
                        ({{ $u->email }})
                    @endif
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <div class="col-lg-3"></div>
    <div class="col-lg-6">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" name="principal" id="principal" value="1"
                @if (old('principal', $data->principal ?? false)) checked @endif>
            <label class="form-check-label" for="principal">Administrador principal</label>
        </div>
        <div class="form-check">
            <input type="checkbox" class="form-check-input" name="recibe_avisos" id="recibe_avisos" value="1"
                @if (old('recibe_avisos', $data->recibe_avisos ?? true)) checked @endif>
            <label class="form-check-label" for="recibe_avisos">Recibe avisos por correo</label>
        </div>
        <div class="form-check">
            <input type="checkbox" class="form-check-input" name="aprueba_recepcion" id="aprueba_recepcion" value="1"
                @if (old('aprueba_recepcion', $data->aprueba_recepcion ?? true)) checked @endif>
            <label class="form-check-label" for="aprueba_recepcion">Puede aprobar recepción de préstamos</label>
        </div>
    </div>
</div>
