<div class="form-group row">
    <label for="codigo" class="col-lg-3 control-label text-right pr-2 requerido">C&oacute;digo</label>
    <div class="col-lg-3">
        <input type="text" name="codigo" id="codigo" class="form-control text-left" inputmode="numeric"
               value="{{ old('codigo', $data->codigo ?? '') }}"
               @if(isset($data->id)) readonly @else required @endif/>
        <small class="form-text text-muted">Concepto Anita (concp_concepto).</small>
    </div>
</div>
<div class="form-group row">
    <label for="nombre" class="col-lg-3 control-label text-right pr-2 requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="30" required
               value="{{ old('nombre', $data->nombre ?? '') }}"/>
        <small class="form-text text-muted">M&aacute;ximo 30 caracteres (concp_desc).</small>
    </div>
</div>
