<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="codigopostal" class="col-lg-3 col-form-label">C&oacute;digo postal</label>
    <div class="col-lg-1">
        <input type="text" name="codigopostal" id="codigopostal" class="form-control" value="{{old('codigopostal', $data->codigopostal ?? '')}}">
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">C&oacute;digo anita</label>
    <div class="col-lg-2">
        <input type="text" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}">
    </div>
</div>
<div class="form-group row">
        <label for="provincia_uif" class="col-lg-3 col-form-label">Provincia</label>
        <input type="text" class="col-lg-1" id="provincia_uif_id" name="provincia_uif_id" value="{{$data->provincia_id??''}}" >
        <button type="button" title="Consulta Provincias" style="padding:1;" class="btn-accion-tabla consultaprovincia_uif tooltipsC">
                <i class="fa fa-search text-primary"></i>
        </button>
        <input type="text" class="col-lg-3 form-control" id="nombreprovincia_uif" name="nombreprovincia_uif" value="{{$data->provincias->nombre??''}}" >
</div>
@include('includes.uif.modalconsultaprovincia_uif')
