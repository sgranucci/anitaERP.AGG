<div class="form1">
    <div class="form-group row">
        <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
        <div class="col-lg-4">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
        </div>
    </div>
    <div class="form-group row">
        <!-- textarea -->
        <label class="col-lg-3 col-form-label requerido">Observaciones</label>
        <div class="col-lg-5">
            <textarea id="detalle" name="observacion" class="form-control" rows="3" required placeholder="Detalle ...">{{old('observacion', $data->observacion ?? '')}}</textarea>
        </div>
    </div>
    <input type="hidden" name="creousuario_id" class="form-control" value="{{ auth()->id() }}"/>
</div>