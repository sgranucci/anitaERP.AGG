<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" maxlength="30" required/>
    </div>
</div>
<div class="form-group row">
    <label for="comision" class="col-lg-3 col-form-label">Comisi&oacute;n</label>
    <div class="col-lg-3">
        <input type="number" step="0.01" name="comision" id="comision" class="form-control" value="{{old('comision', $data->comision ?? '')}}"/>
    </div>
</div>
@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $data->empresa_id ?? null,
    'required' => false,
    'permite_vacio' => true,
    'opcion_vacia' => '— Sin empresa —',
    'mostrar_id' => true,
    'col_input' => 'col-lg-8',
])
<div class="form-group row">
    <label for="legajo_id" class="col-lg-3 col-form-label">Legajo</label>
    <div class="col-lg-2">
        <input type="number" name="legajo_id" id="legajo_id" class="form-control" value="{{old('legajo_id', $data->legajo_id ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">C&oacute;digo Anita</label>
    <div class="col-lg-3">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}" readonly/>
    </div>
</div>
