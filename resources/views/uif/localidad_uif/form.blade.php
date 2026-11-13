<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="codigopostal" class="col-lg-3 col-form-label">C&oacute;digo postal</label>
    <div class="col-lg-2">
        <input type="text" name="codigopostal" id="codigopostal" class="form-control" value="{{old('codigopostal', $data->codigopostal ?? '')}}">
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">C&oacute;digo Anita</label>
    <div class="col-lg-2">
        @if (isset($codigoSugerido) && ! isset($data))
            <input type="text" name="codigo" id="codigo" class="form-control" value="{{ old('codigo', $codigoSugerido) }}" readonly>
            <small class="form-text text-muted">Se asigna autom&aacute;tico (m&aacute;ximo + 1).</small>
        @else
            <input type="text" name="codigo" id="codigo" class="form-control" value="{{ old('codigo', $data->codigo ?? '') }}" readonly>
        @endif
    </div>
</div>
<div class="form-group row">
    <label for="provincia_uif_id" class="col-lg-3 col-form-label">Provincia</label>
    <div class="col-lg-8">
        <select name="provincia_uif_id" id="provincia_uif_id" class="form-control">
            <option value="">-- Elija provincia --</option>
            @foreach ($provincia_uif_query as $provincia)
                <option value="{{ $provincia->id }}"
                    @if ((string) old('provincia_uif_id', $data->provincia_uif_id ?? '') === (string) $provincia->id) selected @endif
                    >{{ $provincia->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
