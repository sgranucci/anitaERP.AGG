<div class="col-sm-6">
    <div class="form-group row">
        <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
        <div class="col-lg-8">
            <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
        </div>
    </div>
    <div class="form-group row">
        <label for="estado" class="col-lg-3 col-form-label">Estado</label>
        <input type="text" name="estado" id="estado" class="col-lg-4 form-control" value="{{old('estado', $data->estado ?? 'ACTIVO')}}" readonly>
    </div>   
</div>
<div class="col-sm-6">
    <!-- textarea -->
    <div class="form-group">
        <label>Código de la etiqueta</label>
        <textarea name="codigoetiqueta" id="codigoetiqueta" class="form-control" rows="20" placeholder="Código fuente ...">{{old('codigoetiqueta', $data->codigoetiqueta ?? '')}}</textarea>
    </div>
</div>
<input type="hidden" id="creousuario_id" name="creousuario_id" value="{{ $data->creousuario_id ?? auth()->id() }}" />
<input type="hidden" id="modeloetiqueta_id" name="modeloetiqueta_id" value="{{ $data->id ?? '' }}" />