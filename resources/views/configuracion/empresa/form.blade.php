<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="domicilio" class="col-lg-3 col-form-label">Domicilio</label>
    <div class="col-lg-3">
    <input type="text" name="domicilio" id="domicilio" class="form-control" value="{{old('domicilio', $data->domicilio ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="nroinscripcion" class="col-lg-3 col-form-label requerido">Nro. Inscripci&oacute;n</label>
    <div class="col-lg-2">
    <input type="text" name="nroinscripcion" id="nroinscripcion" class="form-control" value="{{old('nroinscripcion', $data->nroinscripcion ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="numeroiibb" class="col-lg-3 col-form-label requerido">Nro. IIBB</label>
    <div class="col-lg-3">
    <input type="text" name="numeroiibb" id="numeroiibb" class="form-control" value="{{old('numeroiibb', $data->numeroiibb ?? '')}}"/>
    </div>
</div>
<div class="form-group row">
    <label for="numeroiibb" class="col-lg-3 col-form-label requerido">Fecha Inicio Actividades</label>
    <div class="col-lg-3">
    <input type="date" name="fechainicioactividad" id="fechainicioactividad" class="form-control requerido" value="{{old('fechainicioactividad', $data->fechainicioactividad ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">C&oacute;digo</label>
    <div class="col-lg-2">
    <input type="number" name="codigo" id="codigo" class="form-control" value="{{old('codigo', $data->codigo ?? '')}}"/>
    </div>
</div>
