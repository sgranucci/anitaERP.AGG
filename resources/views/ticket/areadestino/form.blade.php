<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-4">
       <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
@php
    $suspendidoMarcado = (string) old('suspendido', ! empty($data->suspendido) ? '1' : '0') === '1';
@endphp
<div class="form-group row">
    <label for="suspendido" class="col-lg-3 col-form-label">Estado</label>
    <div class="col-lg-6">
        <input type="hidden" name="suspendido" value="0">
        <div class="custom-control custom-checkbox mt-2">
            <input type="checkbox" class="custom-control-input" name="suspendido" id="suspendido" value="1"
                   @checked($suspendidoMarcado)>
            <label class="custom-control-label" for="suspendido">Suspendida</label>
        </div>
        <small class="form-text text-muted">Queda en el listado y se puede reactivar. No se ofrece al cargar un ticket nuevo.</small>
    </div>
</div>
