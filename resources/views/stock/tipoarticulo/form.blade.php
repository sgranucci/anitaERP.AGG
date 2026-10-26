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
<div class="form-group row">
    <label for="letra_comprobante_compra" class="col-lg-3 col-form-label text-right">Letra en comprobante de compra</label>
    <div class="col-lg-8">
        <input type="text" name="letra_comprobante_compra" id="letra_comprobante_compra" class="form-control" maxlength="1"
               value="{{ old('letra_comprobante_compra', $data->letra_comprobante_compra ?? '') }}"
               placeholder="Ej. A"/>
        <small class="form-text text-muted">
            Tercera letra del tipo de factura de compra (como S en servicios). Vacío: se usa la abreviatura si es B, S, L o U.
        </small>
    </div>
</div>
<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right">IVA compra</label>
    <div class="col-lg-8">
        <div class="custom-control custom-checkbox mt-2">
            <input type="checkbox"
                   class="custom-control-input"
                   id="comprobante_compra_iva_indirecto"
                   name="comprobante_compra_iva_indirecto"
                   value="1"
                   @checked(old('comprobante_compra_iva_indirecto', $data->comprobante_compra_iva_indirecto ?? false))>
            <label class="custom-control-label" for="comprobante_compra_iva_indirecto">
                En AGG, este tipo se imputa siempre como IVA indirecto (segunda letra I), sin mirar el tipo de IVA del centro de costo
            </label>
        </div>
    </div>
</div>
