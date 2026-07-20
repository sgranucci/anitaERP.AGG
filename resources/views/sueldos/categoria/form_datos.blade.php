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
    <label for="origen_bases" class="col-lg-3 col-form-label requerido">Origen de las bases</label>
    <div class="col-lg-6">
        @php
            $origenActual = old('origen_bases', $data->origen_bases ?? \App\Support\Sueldos\CategoriaOrigenBases::TABLA);
        @endphp
        <select name="origen_bases" id="origen_bases" class="form-control" required>
            @foreach ($origenLabels as $valor => $etiqueta)
                <option value="{{ $valor }}" {{ $origenActual === $valor ? 'selected' : '' }}>{{ $etiqueta }}</option>
            @endforeach
        </select>
        <small class="form-text text-muted">
            «Desde la categor&iacute;a» carga las bases en la solapa de esta pantalla. «Desde cada empleado» carga las bases en cada legajo.
        </small>
    </div>
</div>
