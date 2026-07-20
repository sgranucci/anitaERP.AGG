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
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-6">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="255" required
               value="{{ old('nombre', $data->nombre ?? '') }}"/>
    </div>
</div>
