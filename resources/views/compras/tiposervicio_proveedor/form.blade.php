<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="controla_unicidad_cuit" class="col-lg-3 col-form-label requerido">Unicidad CUIT</label>
    <div class="col-lg-8">
        @php
            $ctrlCuit = old('controla_unicidad_cuit', isset($data) && $data ? ($data->controla_unicidad_cuit ?? 'CONTROLA') : 'CONTROLA');
        @endphp
        <select name="controla_unicidad_cuit" id="controla_unicidad_cuit" class="form-control" required>
            <option value="CONTROLA" @if($ctrlCuit === 'CONTROLA') selected @endif>CONTROLA</option>
            <option value="NO CONTROLA" @if($ctrlCuit === 'NO CONTROLA') selected @endif>NO CONTROLA</option>
        </select>
        <small class="form-text text-muted">Si es CONTROLA, en el ABM de proveedores no se permitirá repetir el mismo CUIT que otro proveedor activo.</small>
    </div>
</div>

