<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="usuario" class="col-lg-3 col-form-label requerido">Usuario</label>
    <div class="col-lg-8">
        <input type="text" name="usuario" id="usuario" class="form-control" value="{{old('usuario', $data->usuario ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="email" class="col-lg-3 col-form-label requerido">E-Mail</label>
    <div class="col-lg-8">
        <input type="email" name="email" id="email" class="form-control" value="{{old('email', $data->email ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="password" class="col-lg-3 col-form-label {{!isset($data) ? 'requerido' : ''}}">Contraseña</label>
    <div class="col-lg-8">
        <input type="password" name="password" id="password" class="form-control" value="" {{!isset($data) ? 'required' : ''}} minlength="5"/>
    </div>
</div>
<div class="form-group row">
    <label for="re_password" class="col-lg-3 col-form-label {{!isset($data) ? 'requerido' : ''}}">Repita Contraseña</label>
    <div class="col-lg-8">
        <input type="password" name="re_password" id="re_password" class="form-control" value="" {{!isset($data) ? 'required' : ''}} minlength="5"/>
    </div>
</div>
<div class="form-group row">
    <label for="foto" class="col-lg-3 col-form-label">Foto</label>
    <div class="col-lg-5">
        <input type="file" name="foto_up" id="foto" data-initial-preview="{{isset($data->foto) ? asset("storage/imagenes/fotos_usuarios/$data->foto") : asset("assets/$theme/dist/img/user2-160x160.jpg")}}" accept="image/*"/>
    </div>
</div>
<div class="form-group row">
    <label for="empresa_id" class="col-lg-3 col-form-label requerido">Empresas</label>
    <div class="col-lg-8">
        <select class="form-control select2" id="empresa_id" name="empresa_ids[]" multiple="multiple" required>
            <option value="">Seleccione la empresa</option>
            @foreach($empresa_query as $id => $nombre)
                <option value="{{$id}}" {{is_array(old('empresa_id')) ? (in_array($id, old('empresa_id')) ? 'selected' : '')  : (isset($data) ? ($data->usuario_empresas->firstWhere('id', $id) ? 'selected' : '') : '')}}>{{$nombre}}</option>
            @endforeach
        </select>  
    </div>
</div>        
<div class="form-group row">
    <label for="centrocosto_id" class="col-lg-3 col-form-label requerido">Centro Costo</label>
    <div class="col-lg-8">
        <select class="form-control" id="centrocosto_id" name="centrocosto_id" required>
            <option value="">Seleccione el centro de costo</option>
            @foreach($centrocosto_query as $id => $nombre)
                <option value="{{$id}}" {{is_array(old('centrocosto_id')) ? (in_array($id, old('centrocosto_id')) ? 'selected' : '')  : (isset($data) ? ($data->centrocosto_id == $id ? 'selected' : '') : '')}}>{{$nombre}}</option>
            @endforeach
        </select>  
    </div>
</div>      
<div class="form-group row">
    <label for="rol_id" class="col-lg-3 col-form-label requerido">Rol</label>
    <div class="col-lg-8">
        <select name="rol_id[]" id="rol_id" class="form-control" multiple required>
            <option value="">Seleccione el rol</option>
            @foreach($rols as $id => $nombre)
                <option
                value="{{$id}}"
                {{is_array(old('rol_id')) ? (in_array($id, old('rol_id')) ? 'selected' : '')  : (isset($data) ? ($data->roles->firstWhere('id', $id) ? 'selected' : '') : '')}}
                >
                {{$nombre}}
                </option>
            @endforeach
        </select>
    </div>
</div>
