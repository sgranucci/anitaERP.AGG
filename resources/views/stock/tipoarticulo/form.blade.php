<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="abreviatura" class="col-lg-3 col-form-label requerido">Abreviatura</label>
    <div class="col-lg-8">
    <input type="text" name="abreviatura" id="abreviatura" class="form-control" value="{{old('abreviatura', $data->abreviatura ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 col-form-label">Control contable cigarrillos</label>
    <div class="col-lg-8">
        <div class="custom-control custom-checkbox mt-2">
            <input type="checkbox"
                   class="custom-control-input"
                   id="usa_control_contable_cigarrillos"
                   name="usa_control_contable_cigarrillos"
                   value="1"
                   @checked(old('usa_control_contable_cigarrillos', $data->usa_control_contable_cigarrillos ?? false))>
            <label class="custom-control-label" for="usa_control_contable_cigarrillos">
                Habilita conciliación Contaduría (planilla cigarrillos vs mayor Anita) en el reporte de insumos gastronomía por día
            </label>
        </div>
    </div>
</div>
