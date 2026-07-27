@php
    use App\Support\Admin\UsuarioImportColumnasSupport;
@endphp

<div class="card card-outline card-secondary mb-3">
    <div class="card-header py-2">
        <strong class="text-dark"><i class="far fa-building mr-1"></i> Asignaciones comunes</strong>
        <small class="text-muted d-block d-md-inline d-md-ml-2">Se aplican a todos los usuarios del Excel</small>
    </div>
    <div class="card-body">
        @include('admin.usuario.partials.asignacion_empresas_roles', [
            'empresa_query' => $empresa_query,
            'rols' => $rols,
        ])

        <div class="form-group row">
            <label for="password" class="col-lg-3 col-form-label requerido">Contraseña</label>
            <div class="col-lg-4">
                <input type="password" name="password" id="password" class="form-control" value="" required minlength="5" autocomplete="new-password" />
            </div>
            <div class="col-lg-5 col-form-label text-muted small">
                Misma contraseña inicial para todos los usuarios importados (mínimo 5 caracteres).
            </div>
        </div>
        <div class="form-group row mb-0">
            <label for="re_password" class="col-lg-3 col-form-label requerido">Repita contraseña</label>
            <div class="col-lg-4">
                <input type="password" name="re_password" id="re_password" class="form-control" value="" required minlength="5" autocomplete="new-password" />
            </div>
        </div>
    </div>
</div>

<div class="card card-outline card-secondary mb-3">
    <div class="card-header py-2">
        <strong class="text-dark"><i class="fas fa-briefcase mr-1"></i> Asignaciones operativas</strong>
    </div>
    <div class="card-body">
        <div class="form-group row">
            <label for="centrocosto_id" class="col-lg-3 col-form-label requerido">Centro de costo</label>
            <div class="col-lg-6">
                <select class="form-control" id="centrocosto_id" name="centrocosto_id" required>
                    <option value="">Seleccione el centro de costo</option>
                    @foreach ($centrocosto_query as $centrocosto)
                        <option value="{{ $centrocosto['id'] }}" @selected((int) old('centrocosto_id') === (int) $centrocosto['id'])>
                            {{ $centrocosto['nombre'] }} ({{ $centrocosto['codigo'] }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group row">
            <label for="sector_legajocompra_id" class="col-lg-3 col-form-label">Sector legajo compras</label>
            <div class="col-lg-6">
                <select class="form-control" id="sector_legajocompra_id" name="sector_legajocompra_id">
                    <option value="">— Sin asignar (opcional) —</option>
                    @foreach ($sector_legajocompra_query as $sector)
                        <option value="{{ $sector->id }}" @selected((int) old('sector_legajocompra_id') === (int) $sector->id)>
                            {{ $sector->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group row tm-vendedor-campo">
            <label for="vendedor_id" class="col-lg-3 col-form-label">Vendedor</label>
            <div class="col-lg-6">
                @php
                    $vendedorIdImport = old('vendedor_id', '');
                    $vendedorCodigoImport = old('codigovendedor', '');
                    $vendedorNombreImport = old('nombrevendedor', '');
                    if ($vendedorIdImport && ($vendedorCodigoImport === '' || $vendedorNombreImport === '')) {
                        $vendedorResuelto = \App\Models\Ventas\Vendedor::query()->find($vendedorIdImport);
                        $vendedorCodigoImport = $vendedorResuelto->codigo ?? $vendedorCodigoImport;
                        $vendedorNombreImport = $vendedorResuelto->nombre ?? $vendedorNombreImport;
                    }
                @endphp
                <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
                    <input type="hidden" class="vendedor_id" name="vendedor_id" id="vendedor_id" value="{{ $vendedorIdImport }}">
                    <button type="button" title="Consulta vendedores" class="btn-accion-tabla consultavendedor tooltipsC flex-shrink-0">
                        <i class="fa fa-search text-primary"></i>
                    </button>
                    <input type="text" class="form-control codigovendedor flex-shrink-0" id="codigovendedor"
                        value="{{ $vendedorCodigoImport }}"
                        placeholder="Cód." autocomplete="off" style="width: 5.5rem;">
                    <input type="text" class="form-control nombrevendedor" id="nombrevendedor"
                        value="{{ $vendedorNombreImport }}"
                        placeholder="Descripción" readonly style="min-width: 0; flex: 1 1 auto;">
                </div>
                <small class="form-text text-muted">Opcional. Se asocia el mismo vendedor a todos los usuarios importados.</small>
            </div>
        </div>

        <div class="form-group row mb-0">
            <label for="oficinacompra_id" class="col-lg-3 col-form-label">Oficina de compras</label>
            <div class="col-lg-6">
                <select class="form-control" id="oficinacompra_id" name="oficinacompra_id">
                    <option value="">— Sin asignar (opcional) —</option>
                    @foreach ($oficinacompra_query as $id => $nombre)
                        <option value="{{ $id }}" @selected((int) old('oficinacompra_id') === (int) $id)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card card-outline card-secondary mb-3">
    <div class="card-header py-2">
        <strong class="text-dark"><i class="fa fa-file-excel mr-1"></i> Configuración del Excel</strong>
        <small class="text-muted d-block d-md-inline d-md-ml-2">Mapeo de columnas y vista previa</small>
    </div>
    <div class="card-body">
        @php
            $dominioEmailForm = old('dominio_email', $dominio_email_default ?? '@grupoagg.com');
            $generarLoginForm = (string) old('generar_login_si_falta', ($generar_login_default ?? true) ? '1' : '0') === '1';
            $generarEmailForm = (string) old('generar_email_si_falta', ($generar_email_default ?? true) ? '1' : '0') === '1';
        @endphp

        <div class="border rounded p-3 mb-3 bg-light">
            <h6 class="mb-3">Generación automática de login y email</h6>
            <div class="form-group row">
                <label for="dominio_email" class="col-lg-3 col-form-label">Dominio de email</label>
                <div class="col-lg-4">
                    <input type="text" name="dominio_email" id="dominio_email" class="form-control"
                        value="{{ $dominioEmailForm }}"
                        placeholder="@grupoagg.com" />
                </div>
                <div class="col-lg-5 col-form-label text-muted small">
                    Configurable (default en <code>.env</code>). Ej.: Juan Pérez → <code>jperez{{ $dominioEmailForm }}</code>
                </div>
            </div>
            <div class="form-group row mb-0">
                <label class="col-lg-3 col-form-label">Si faltan en el Excel</label>
                <div class="col-lg-9">
                    <input type="hidden" name="generar_login_si_falta" value="0">
                    <div class="custom-control custom-checkbox custom-control-inline">
                        <input type="checkbox" class="custom-control-input" id="generar_login_si_falta" name="generar_login_si_falta" value="1"
                            @checked($generarLoginForm)>
                        <label class="custom-control-label" for="generar_login_si_falta">
                            Generar login (inicial del nombre + apellido)
                        </label>
                    </div>
                    <input type="hidden" name="generar_email_si_falta" value="0">
                    <div class="custom-control custom-checkbox custom-control-inline">
                        <input type="checkbox" class="custom-control-input" id="generar_email_si_falta" name="generar_email_si_falta" value="1"
                            @checked($generarEmailForm)>
                        <label class="custom-control-label" for="generar_email_si_falta">
                            Generar email (login + dominio)
                        </label>
                    </div>
                    <small class="form-text text-muted d-block mt-1">
                        Usuario y email en el Excel son opcionales. Si vienen vacíos (o no hay columna), se generan.
                        Ante colisión se agrega sufijo numérico (<code>jperez2</code>).
                    </small>
                </div>
            </div>
        </div>

        <div class="border rounded p-3 mb-3 bg-light">
            <h6 class="mb-3">Columnas del archivo</h6>

            <div class="form-group row">
                <label for="col_nombre" class="col-lg-3 col-form-label requerido">Columna nombre</label>
                <div class="col-lg-3">
                    <input type="text" name="col_nombre" id="col_nombre" class="form-control"
                        value="{{ old('col_nombre', UsuarioImportColumnasSupport::COL_NOMBRE_DEFAULT) }}"
                        placeholder="nombre" />
                </div>
                <div class="col-lg-6 col-form-label text-muted small">
                    Obligatoria. Alias: <code>nombre_completo</code>, <code>apellido_nombre</code>, etc.
                </div>
            </div>

            <div class="form-group row">
                <label for="col_usuario" class="col-lg-3 col-form-label">Columna usuario / login</label>
                <div class="col-lg-3">
                    <input type="text" name="col_usuario" id="col_usuario" class="form-control"
                        value="{{ old('col_usuario', UsuarioImportColumnasSupport::COL_USUARIO_DEFAULT) }}"
                        placeholder="usuario" />
                </div>
                <div class="col-lg-6 col-form-label text-muted small">
                    Opcional. Si no existe o la celda está vacía, se genera. Alias: <code>login</code>, <code>username</code>.
                </div>
            </div>

            <div class="form-group row mb-0">
                <label for="col_email" class="col-lg-3 col-form-label">Columna email</label>
                <div class="col-lg-3">
                    <input type="text" name="col_email" id="col_email" class="form-control"
                        value="{{ old('col_email', UsuarioImportColumnasSupport::COL_EMAIL_DEFAULT) }}"
                        placeholder="email" />
                </div>
                <div class="col-lg-6 col-form-label text-muted small">
                    Opcional. Si no existe o la celda está vacía, se genera con el dominio. Alias: <code>mail</code>, <code>correo</code>.
                </div>
            </div>
        </div>

        <div class="form-group row">
            <label for="fila_encabezado" class="col-lg-3 col-form-label">Fila del encabezado</label>
            <div class="col-lg-2">
                <input type="number" name="fila_encabezado" id="fila_encabezado" class="form-control" min="1" max="50"
                    value="{{ old('fila_encabezado', '') }}" placeholder="Auto" />
            </div>
            <div class="col-lg-7 col-form-label text-muted small">
                Vacío = detectar automáticamente en las primeras 15 filas.
            </div>
        </div>

        <div class="form-group row">
            <label for="file" class="col-lg-3 col-form-label requerido">Archivo Excel</label>
            <div class="col-lg-6">
                <input type="file" name="file" id="file" class="form-control" accept=".xlsx,.xls,.csv" required />
            </div>
            <div class="col-lg-3">
                <button type="button" id="btn-preview-import-usuario" class="btn btn-outline-primary btn-sm" disabled>
                    <i class="fa fa-search"></i> Vista previa
                </button>
            </div>
        </div>

        <input type="hidden" name="hoja_indice" id="hoja_indice" value="{{ old('hoja_indice', 1) }}">

        <div class="form-group row mb-2 d-none" id="panel-hoja-excel">
            <label for="hoja_indice_select" class="col-lg-3 col-form-label pt-1">Hoja a importar</label>
            <div class="col-lg-4 col-md-5">
                <select id="hoja_indice_select" class="form-control form-control-sm" aria-label="Elegir hoja del Excel"></select>
            </div>
            <div class="col-lg-5 col-form-label text-muted small pt-1" id="hoja_indice_ayuda">
                Elija la pestaña del Excel con los usuarios a cargar.
            </div>
        </div>

        <div id="panel-preview-import-usuario" class="card border-primary mb-3" style="display:none;">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <strong><i class="fa fa-table"></i> Vista previa del archivo</strong>
                <span id="preview-import-usuario-estado" class="badge badge-secondary">—</span>
            </div>
            <div class="card-body p-2" id="preview-import-usuario-contenido">
                <p class="text-muted small mb-0">Seleccione un archivo para analizar columnas y filas.</p>
            </div>
        </div>

        <div class="alert alert-info small mb-0">
            <strong>Mínimo requerido:</strong> una columna de <code>nombre</code>. Login y email pueden omitirse;
            el sistema usa inicial del primer nombre + apellido (sin tildes), y email = login + dominio.
            <br><br>
            <strong>Ejemplo solo con nombre:</strong>
            <table class="table table-sm table-bordered bg-white mt-2 mb-2" style="max-width: 22rem;">
                <tbody>
                    <tr class="thead-light font-weight-bold"><td>nombre</td></tr>
                    <tr><td>Juan Pérez</td></tr>
                    <tr><td>María Gómez</td></tr>
                </tbody>
            </table>
            Resultado: <code>jperez</code> / <code>jperez{{ $dominioEmailForm }}</code>,
            <code>mgomez</code> / <code>mgomez{{ $dominioEmailForm }}</code>.
        </div>
    </div>
</div>
