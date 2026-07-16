<div class="row">
    <div class="col-lg-8">
        <div class="form-group row">
            <label for="codigo" class="col-lg-3 col-form-label">C&oacute;digo</label>
            <div class="col-lg-3">
                @if (isset($data))
                    <input type="text" id="codigo" class="form-control" value="{{ $data->codigo }}" readonly/>
                @else
                    <input type="number" name="codigo" id="codigo" class="form-control" min="1"
                           value="{{ old('codigo') }}"
                           placeholder="Autom&aacute;tico"/>
                @endif
            </div>
        </div>
        <div class="form-group row">
            <label for="nombre" class="col-lg-3 col-form-label requerido">Descripci&oacute;n</label>
            <div class="col-lg-9">
                <input type="text" name="nombre" id="nombre" class="form-control" maxlength="50" required
                       value="{{ old('nombre', $data->nombre ?? '') }}"/>
            </div>
        </div>
        <div class="form-group row">
            <label for="sector_solicitudpago_id" class="col-lg-3 col-form-label">Sector</label>
            <div class="col-lg-6">
                <select name="sector_solicitudpago_id" id="sector_solicitudpago_id" class="form-control">
                    <option value="">-- Sin sector --</option>
                    @foreach ($sector_query as $sector)
                        @php
                            $sel = (int) old('sector_solicitudpago_id', $data->sector_solicitudpago_id ?? 0) === (int) $sector->id;
                        @endphp
                        <option value="{{ $sector->id }}" {{ $sel ? 'selected' : '' }}>
                            {{ $sector->codigo }} — {{ $sector->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="forma_pago" class="col-lg-3 col-form-label requerido">Forma de pago</label>
            <div class="col-lg-4">
                <select name="forma_pago" id="forma_pago" class="form-control" required>
                    @foreach ($forma_pago_enum as $opt)
                        @php
                            $sel = old('forma_pago', $data->forma_pago ?? 'SIN_CUOTAS') === $opt['valor'];
                        @endphp
                        <option value="{{ $opt['valor'] }}" {{ $sel ? 'selected' : '' }}>{{ $opt['nombre'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="estado" class="col-lg-3 col-form-label requerido">Estado</label>
            <div class="col-lg-4">
                <select name="estado" id="estado" class="form-control" required>
                    @foreach ($estado_enum as $opt)
                        @php
                            $sel = old('estado', $data->estado ?? 'ACTIVO') === $opt['valor'];
                        @endphp
                        <option value="{{ $opt['valor'] }}" {{ $sel ? 'selected' : '' }}>{{ $opt['nombre'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>
