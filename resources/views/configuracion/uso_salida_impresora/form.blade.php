<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required maxlength="255"/>
    </div>
</div>
<div class="form-group row">
    <label for="descripcion" class="col-lg-3 col-form-label">Descripci&oacute;n</label>
    <div class="col-lg-8">
        <textarea name="descripcion" id="descripcion" class="form-control" rows="3" maxlength="2000">{{ old('descripcion', $data->descripcion ?? '') }}</textarea>
    </div>
</div>
<div class="form-group row">
    <label for="programas_destino" class="col-lg-3 col-form-label">Programas destino</label>
    <div class="col-lg-8">
        @php
            $selectedProgramas = old(
                'programas_destino',
                ($data->exists ?? false) ? (array) ($data->programas_destino ?? []) : []
            );
        @endphp
        <select name="programas_destino[]" id="programas_destino" data-placeholder="Programas donde filtra este uso" class="form-control" data-fouc multiple>
            @foreach ($programasSeteoOpciones ?? [] as $codigo => $etiqueta)
                @if (in_array($codigo, array_map('strval', (array) $selectedProgramas), true))
                    <option value="{{ $codigo }}" selected="selected">{{ $etiqueta }}</option>
                @else
                    <option value="{{ $codigo }}">{{ $etiqueta }}</option>
                @endif
            @endforeach
        </select>
        <small class="form-text text-muted">
            Define en qu&eacute; pantallas del ERP se ofrecen impresoras con este uso al configurar salida.
            Sin selecci&oacute;n el uso queda disponible en <strong>todos</strong> los programas.
        </small>
    </div>
</div>
