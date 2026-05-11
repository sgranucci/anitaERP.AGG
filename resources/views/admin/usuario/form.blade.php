<div class="card card-outline card-secondary mb-3">
    <div class="card-header py-2">
        <strong class="text-dark"><i class="far fa-user mr-1"></i> Identidad y acceso</strong>
        <small class="text-muted d-block d-md-inline d-md-ml-2">Datos de inicio de sesión y perfil</small>
    </div>
    <div class="card-body">
        <div class="form-group row">
            <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
            <div class="col-lg-8">
                <input type="text" name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $data->nombre ?? '') }}" required maxlength="50" />
            </div>
        </div>
        <div class="form-group row">
            <label for="usuario" class="col-lg-3 col-form-label requerido">Usuario</label>
            <div class="col-lg-8">
                <input type="text" name="usuario" id="usuario" class="form-control" value="{{ old('usuario', $data->usuario ?? '') }}" required maxlength="50" autocomplete="username" />
            </div>
        </div>
        <div class="form-group row">
            <label for="email" class="col-lg-3 col-form-label requerido">Correo electrónico</label>
            <div class="col-lg-8">
                <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $data->email ?? '') }}" required maxlength="100" autocomplete="email" />
            </div>
        </div>
        <div class="form-group row">
            <label for="password" class="col-lg-3 col-form-label {{ !isset($data) ? 'requerido' : '' }}">Contraseña</label>
            <div class="col-lg-8">
                <input type="password" name="password" id="password" class="form-control" value="" {{ !isset($data) ? 'required' : '' }} minlength="5" autocomplete="new-password" />
                @isset($data)
                    <small class="form-text text-muted">Deje en blanco para no modificar la contraseña actual.</small>
                @endisset
            </div>
        </div>
        <div class="form-group row">
            <label for="re_password" class="col-lg-3 col-form-label {{ !isset($data) ? 'requerido' : '' }}">Repita contraseña</label>
            <div class="col-lg-8">
                <input type="password" name="re_password" id="re_password" class="form-control" value="" {{ !isset($data) ? 'required' : '' }} minlength="5" autocomplete="new-password" />
            </div>
        </div>
        <div class="form-group row mb-0">
            <label for="foto" class="col-lg-3 col-form-label">Foto</label>
            <div class="col-lg-8">
                <input type="file" name="foto_up" id="foto" data-initial-preview="{{ isset($data->foto) ? asset("storage/imagenes/fotos_usuarios/$data->foto") : asset("assets/$theme/dist/img/user2-160x160.jpg") }}" accept="image/*" />
                <small class="form-text text-muted">Imagen cuadrada recomendada; se redimensiona al guardar.</small>
            </div>
        </div>
    </div>
</div>

<div class="card card-outline card-secondary mb-3">
    <div class="card-header py-2">
        <strong class="text-dark"><i class="far fa-building mr-1"></i> Empresa y roles</strong>
    </div>
    <div class="card-body">
        <div class="form-group row">
            <label for="empresa_id" class="col-lg-3 col-form-label requerido">Empresas</label>
            <div class="col-lg-8">
                <select class="form-control select2" id="empresa_id" name="empresa_ids[]" multiple="multiple" required data-placeholder="Seleccione una o más empresas">
                    @foreach ($empresa_query as $id => $nombre)
                        <option value="{{ $id }}" {{ is_array(old('empresa_ids')) ? (in_array($id, old('empresa_ids')) ? 'selected' : '') : (isset($data) ? ($data->usuario_empresas->firstWhere('id', $id) ? 'selected' : '') : '') }}>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row mb-0">
            <label for="rol_id" class="col-lg-3 col-form-label requerido">Roles</label>
            <div class="col-lg-8">
                <select name="rol_id[]" id="rol_id" class="form-control" multiple required>
                    @foreach ($rols as $id => $nombre)
                        <option value="{{ $id }}" {{ is_array(old('rol_id')) ? (in_array($id, old('rol_id')) ? 'selected' : '') : (isset($data) ? ($data->roles->firstWhere('id', $id) ? 'selected' : '') : '') }}>
                            {{ $nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card card-outline card-secondary mb-0">
    <div class="card-header py-2">
        <strong class="text-dark"><i class="fas fa-briefcase mr-1"></i> Asignaciones operativas</strong>
        <small class="text-muted d-block d-md-inline d-md-ml-2">Centro de costo, compras y ventas</small>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-6">
                <div class="form-group row">
                    <label for="centrocosto_id" class="col-lg-4 col-form-label requerido">Centro de costo</label>
                    <div class="col-lg-8">
                        <select class="form-control" id="centrocosto_id" name="centrocosto_id" required>
                            <option value="">Seleccione el centro de costo</option>
                            @foreach ($centrocosto_query as $centrocosto)
                                <option value="{{ $centrocosto['id'] }}" {{ (int) old('centrocosto_id', isset($data) ? $data->centrocosto_id : '') === (int) $centrocosto['id'] ? 'selected' : '' }}>
                                    {{ $centrocosto['nombre'] }} ({{ $centrocosto['codigo'] }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="sector_legajocompra_id" class="col-lg-4 col-form-label">Sector legajo compras</label>
                    <div class="col-lg-8">
                        <select class="form-control" id="sector_legajocompra_id" name="sector_legajocompra_id">
                            <option value="">— Sin asignar (opcional) —</option>
                            @foreach ($sector_legajocompra_query as $sector)
                                <option value="{{ $sector->id }}" {{ (int) old('sector_legajocompra_id', isset($data) ? ($data->sector_legajocompra_id ?? 0) : 0) === (int) $sector->id ? 'selected' : '' }}>
                                    {{ $sector->nombre }}
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Solo aplica si el usuario interviene en el circuito de compras con legajo por sector.</small>
                    </div>
                </div>
                <div class="form-group row mb-lg-0">
                    <label for="vendedor_id" class="col-lg-4 col-form-label">Vendedor</label>
                    <div class="col-lg-8">
                        <select class="form-control" id="vendedor_id" name="vendedor_id">
                            <option value="">Sin vendedor asociado</option>
                            @foreach ($vendedor_query as $id => $nombre)
                                <option value="{{ $id }}" {{ (int) old('vendedor_id', isset($data) ? ($data->vendedor_id ?? 0) : 0) === (int) $id ? 'selected' : '' }}>{{ $nombre }} — {{ $id }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="form-group row">
                    <label for="oficinacompra_id" class="col-lg-4 col-form-label">Oficina de compra</label>
                    <div class="col-lg-8">
                        <select class="form-control select2" id="oficinacompra_id" name="oficinacompra_id" data-placeholder="Seleccione oficina de compra">
                            <option value="">— Sin asignar —</option>
                            @foreach ($oficinacompra_query as $id => $nombre)
                                <option value="{{ $id }}" {{ (int) old('oficinacompra_id', isset($data) ? ($data->oficinacompra_id ?? 0) : 0) === (int) $id ? 'selected' : '' }}>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
