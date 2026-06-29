<div class="form1">
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
                @isset($data)
                    <input type="hidden" name="quitar_foto" id="quitar_foto" value="{{ old('quitar_foto', '0') }}">
                @endisset
                <input type="file" name="foto_up" id="foto"
                    data-tiene-foto="{{ ! empty($data->foto ?? null) ? '1' : '0' }}"
                    data-initial-preview="{{ ! empty($data->foto ?? null) ? asset('storage/imagenes/fotos_usuarios/'.$data->foto) : '' }}"
                    accept="image/*" />
                <small class="form-text text-muted">Imagen cuadrada recomendada; se redimensiona al guardar.@isset($data) Use el bot&oacute;n de quitar para volver al avatar predeterminado.@endisset</small>
            </div>
        </div>
        @isset($data)
            @php
                $suspendidoActual = (bool) old('suspendido', $data->suspendido ?? false);
                $esPropioUsuario = (int) $data->id === (int) session('usuario_id');
            @endphp
            <div class="form-group row mb-0 mt-3 align-items-center">
                <label class="col-lg-3 col-form-label mb-lg-0" for="estado_usuario_activo">Estado</label>
                <div class="col-lg-8">
                    <div class="d-flex flex-wrap align-items-center">
                        <div class="mr-4 mb-2 mb-md-0" id="grp-estado-usuario" role="radiogroup" aria-label="Estado del usuario">
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" name="suspendido" id="estado_usuario_activo" class="custom-control-input estado-usuario-radio" value="0"
                                    {{ ! $suspendidoActual ? 'checked' : '' }}
                                    @if ($esPropioUsuario) disabled @endif>
                                <label class="custom-control-label font-weight-bold text-success" for="estado_usuario_activo">
                                    Activo
                                </label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline ml-md-3">
                                <input type="radio" name="suspendido" id="estado_usuario_suspendido" class="custom-control-input estado-usuario-radio" value="1"
                                    {{ $suspendidoActual ? 'checked' : '' }}
                                    @if ($esPropioUsuario) disabled @endif>
                                <label class="custom-control-label font-weight-bold text-dark" for="estado_usuario_suspendido">
                                    Suspendido
                                </label>
                            </div>
                        </div>
                        <span id="estado-usuario-mensaje" class="badge mb-2 mb-md-0 {{ $suspendidoActual ? 'badge-danger' : 'badge-success' }}">
                            @if ($suspendidoActual)
                                <i class="fas fa-ban mr-1"></i> No puede iniciar sesi&oacute;n
                            @else
                                <i class="fas fa-sign-in-alt mr-1"></i> Puede iniciar sesi&oacute;n
                            @endif
                        </span>
                    </div>
                    @if ($esPropioUsuario)
                        <small class="form-text text-muted mt-2 mb-0">
                            No puede cambiar su propio estado mientras tiene la sesi&oacute;n activa.
                        </small>
                    @else
                        <small class="form-text text-muted mt-2 mb-0">
                            Un usuario suspendido no podr&aacute; ingresar al sistema hasta reactivarlo.
                        </small>
                    @endif
                    @error('suspendido')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var mensaje = document.getElementById('estado-usuario-mensaje');
                    var radios = document.querySelectorAll('.estado-usuario-radio');
                    if (!mensaje || !radios.length) {
                        return;
                    }
                    radios.forEach(function (radio) {
                        radio.addEventListener('change', function () {
                            var suspendido = document.getElementById('estado_usuario_suspendido').checked;
                            mensaje.classList.remove('badge-success', 'badge-danger');
                            if (suspendido) {
                                mensaje.classList.add('badge-danger');
                                mensaje.innerHTML = '<i class="fas fa-ban mr-1"></i> No puede iniciar sesi&oacute;n';
                            } else {
                                mensaje.classList.add('badge-success');
                                mensaje.innerHTML = '<i class="fas fa-sign-in-alt mr-1"></i> Puede iniciar sesi&oacute;n';
                            }
                        });
                    });
                });
            </script>
        @endisset
    </div>
</div>

<div class="card card-outline card-secondary mb-3">
    <div class="card-header py-2">
        <strong class="text-dark"><i class="far fa-building mr-1"></i> Empresa y roles</strong>
    </div>
    <div class="card-body">
        @include('admin.usuario.partials.asignacion_empresas_roles', [
            'empresa_query' => $empresa_query,
            'rols' => $rols,
        ])
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
                <div class="form-group row mb-lg-0 tm-vendedor-campo">
                    <label for="vendedor_id" class="col-lg-4 col-form-label">Vendedor</label>
                    <div class="col-lg-8">
                        @php
                            $vendedorIdUsuario = old('vendedor_id', isset($data) ? ($data->vendedor_id ?? '') : '');
                            $vendedorCodigoUsuario = old('codigovendedor');
                            $vendedorNombreUsuario = old('nombrevendedor');
                            if ($vendedorCodigoUsuario === null && $vendedorIdUsuario && isset($data) && $data->vendedores) {
                                $vendedorCodigoUsuario = $data->vendedores->codigo ?? '';
                                $vendedorNombreUsuario = $data->vendedores->nombre ?? '';
                            } elseif ($vendedorCodigoUsuario === null && $vendedorIdUsuario) {
                                $vendedorResuelto = \App\Models\Ventas\Vendedor::query()->find($vendedorIdUsuario);
                                $vendedorCodigoUsuario = $vendedorResuelto->codigo ?? '';
                                $vendedorNombreUsuario = $vendedorResuelto->nombre ?? '';
                            }
                            $vendedorCodigoUsuario = $vendedorCodigoUsuario ?? '';
                            $vendedorNombreUsuario = $vendedorNombreUsuario ?? '';
                        @endphp
                        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
                            <input type="hidden" class="vendedor_id" name="vendedor_id" id="vendedor_id"
                                value="{{ $vendedorIdUsuario }}">
                            <button type="button" title="Consulta vendedores" class="btn-accion-tabla consultavendedor tooltipsC flex-shrink-0">
                                <i class="fa fa-search text-primary"></i>
                            </button>
                            @if (can('editar-vendedores', false) || can('listar-vendedores', false))
                                <a href="{{ ((int) $vendedorIdUsuario > 0) ? route('editar_vendedor', ['id' => (int) $vendedorIdUsuario, 'origen' => 'modal_consulta', 'vista' => 'consulta']) : '#' }}"
                                    target="_blank" rel="noopener"
                                    class="btn-accion-tabla btn-link-editar-vendedor tooltipsC flex-shrink-0 {{ ((int) $vendedorIdUsuario > 0) ? '' : 'd-none' }}"
                                    title="Consultar vendedor en ABM">
                                    <i class="fa fa-edit"></i>
                                </a>
                            @endif
                            <input type="text" class="form-control codigovendedor flex-shrink-0" id="codigovendedor"
                                value="{{ $vendedorCodigoUsuario }}"
                                placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem;">
                            <input type="text" class="form-control nombrevendedor" id="nombrevendedor"
                                value="{{ $vendedorNombreUsuario }}"
                                placeholder="Descripci&oacute;n" readonly style="min-width: 0; flex: 1 1 auto;">
                        </div>
                        <small class="form-text text-muted">C&oacute;digo Anita y nombre del vendedor asociado al usuario.</small>
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
</div>
