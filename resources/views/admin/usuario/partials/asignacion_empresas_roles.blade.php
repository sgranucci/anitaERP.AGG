{{--
    Asignación dual de empresas y roles (ABM usuario admin).
    Variables: $empresa_query, $rols; opcional $data para edición.
--}}
@php
    $empresasRaw = $empresa_query ?? [];
    $empresasTodas = collect($empresasRaw);
    if ($empresasTodas->isNotEmpty() && ! is_object($empresasTodas->first())) {
        $empresasTodas = collect($empresasRaw)->map(fn ($nombre, $id) => (object) [
            'id' => (int) $id,
            'nombre' => $nombre,
        ])->values();
    }

    $rolesTodas = collect($rols ?? [])->map(fn ($nombre, $id) => (object) [
        'id' => (int) $id,
        'nombre' => $nombre,
    ])->values();

    $empresasAsignadasIds = collect(old('empresa_ids', isset($data) ? $data->usuario_empresas->pluck('id')->all() : []))
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => $id > 0)
        ->unique()
        ->values()
        ->all();

    $rolesAsignadosIds = collect(old('rol_id', isset($data) ? $data->roles->pluck('id')->all() : []))
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => $id > 0)
        ->unique()
        ->values()
        ->all();

    $empresaUnicaSistema = $empresasTodas->count() === 1;
    $empresaUnicaRegistro = $empresaUnicaSistema ? $empresasTodas->first() : null;

    if ($empresaUnicaSistema && $empresaUnicaRegistro && empty($empresasAsignadasIds)) {
        $empresasAsignadasIds = [(int) $empresaUnicaRegistro->id];
    }

    $empresasAsignadas = $empresasTodas->whereIn('id', $empresasAsignadasIds)->values();
    $empresasDisponibles = $empresasTodas->whereNotIn('id', $empresasAsignadasIds)->values();

    $rolesAsignados = $rolesTodas->whereIn('id', $rolesAsignadosIds)->values();
    $rolesDisponibles = $rolesTodas->whereNotIn('id', $rolesAsignadosIds)->values();
@endphp

<div class="usuario-asignacion-dual" id="usuario-asignacion-dual" data-empresa-unica="{{ $empresaUnicaSistema ? '1' : '0' }}">
    @if ($empresaUnicaSistema && $empresaUnicaRegistro)
        <input type="hidden" name="empresa_ids[]" id="empresa_id" value="{{ $empresaUnicaRegistro->id }}">
        <div class="form-group row mb-3">
            <label class="col-lg-3 col-form-label requerido">Empresa</label>
            <div class="col-lg-8">
                <input type="text" class="form-control" readonly value="{{ $empresaUnicaRegistro->nombre }}">
                <small class="form-text text-muted">Única empresa del sistema; se asigna automáticamente.</small>
            </div>
        </div>
    @endif

    <div class="usuario-dual-encabezados row d-none d-md-flex mb-2 {{ $empresaUnicaSistema ? 'mt-2' : '' }}">
        <div class="col-md-5 text-muted small font-weight-bold pl-md-3">
            <i class="fas fa-list mr-1"></i> Disponibles
        </div>
        <div class="col-md-2"></div>
        <div class="col-md-5 text-muted small font-weight-bold pl-md-3">
            <i class="fas fa-check-circle mr-1"></i> Asignados
        </div>
    </div>

    @unless ($empresaUnicaSistema)
        <div class="usuario-dual-grupo mb-4">
            <div class="row usuario-dual-etiquetas">
                <div class="col-md-5">
                    <label for="empresas_disponibles" class="usuario-dual-etiqueta mb-1">Empresas asignables</label>
                </div>
                <div class="col-md-2 d-none d-md-block"></div>
                <div class="col-md-5">
                    <label for="empresas_asignadas_list" class="usuario-dual-etiqueta mb-1 requerido">Empresas</label>
                </div>
            </div>
            <div class="row align-items-center usuario-dual-listas">
                <div class="col-md-5">
                    <select id="empresas_disponibles" class="form-control usuario-dual-list" multiple size="7">
                        @foreach ($empresasDisponibles as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 usuario-dual-acciones">
                    <div class="btn-group-vertical mx-auto">
                        <button type="button" class="btn btn-outline-primary btn-sm mb-1 btn-dual-asignar" data-grupo="empresa" title="Asignar empresas seleccionadas">
                            <i class="fas fa-angle-double-right"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm btn-dual-quitar" data-grupo="empresa" title="Quitar empresas seleccionadas">
                            <i class="fas fa-angle-double-left"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-5" id="grupo-empresas-asignadas">
                    <select id="empresas_asignadas_list" class="form-control usuario-dual-list" multiple size="7">
                        @foreach ($empresasAsignadas as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->nombre }}</option>
                        @endforeach
                    </select>
                    <div id="empresas_asignadas_hidden" class="d-none" data-error-container="#grupo-empresas-asignadas">
                        @foreach ($empresasAsignadas as $emp)
                            <input type="hidden" name="empresa_ids[]" value="{{ $emp->id }}">
                        @endforeach
                    </div>
                    <input type="hidden" id="empresa_ids_validacion" name="empresa_ids_validacion" value="{{ count($empresasAsignadasIds) ? '1' : '' }}">
                </div>
            </div>
        </div>
    @endunless

    <div class="usuario-dual-grupo">
        <div class="row usuario-dual-etiquetas">
            <div class="col-md-5">
                <label for="roles_disponibles" class="usuario-dual-etiqueta mb-1">Roles asignables</label>
            </div>
            <div class="col-md-2 d-none d-md-block"></div>
            <div class="col-md-5">
                <label for="roles_asignados_list" class="usuario-dual-etiqueta mb-1 requerido">Roles</label>
            </div>
        </div>
        <div class="row align-items-center usuario-dual-listas">
            <div class="col-md-5">
                <select id="roles_disponibles" class="form-control usuario-dual-list" multiple size="7">
                    @foreach ($rolesDisponibles as $rol)
                        <option value="{{ $rol->id }}">{{ $rol->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 usuario-dual-acciones">
                <div class="btn-group-vertical mx-auto">
                    <button type="button" class="btn btn-outline-primary btn-sm mb-1 btn-dual-asignar" data-grupo="rol" title="Asignar roles seleccionados">
                        <i class="fas fa-angle-double-right"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm btn-dual-quitar" data-grupo="rol" title="Quitar roles seleccionados">
                        <i class="fas fa-angle-double-left"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-5" id="grupo-roles-asignados">
                <select id="roles_asignados_list" class="form-control usuario-dual-list" multiple size="7">
                    @foreach ($rolesAsignados as $rol)
                        <option value="{{ $rol->id }}">{{ $rol->nombre }}</option>
                    @endforeach
                </select>
                <div id="roles_asignados_hidden" class="d-none" data-error-container="#grupo-roles-asignados">
                    @foreach ($rolesAsignados as $rol)
                        <input type="hidden" name="rol_id[]" value="{{ $rol->id }}">
                    @endforeach
                </div>
                <input type="hidden" id="rol_id_validacion" name="rol_id_validacion" value="{{ count($rolesAsignadosIds) ? '1' : '' }}">
            </div>
        </div>
    </div>

    <small class="form-text text-muted mt-2">
        Seleccione ítems en la lista izquierda y use
        <i class="fas fa-angle-double-right"></i>
        para asignarlos; use
        <i class="fas fa-angle-double-left"></i>
        para quitarlos. También puede hacer doble clic sobre un ítem.
    </small>
</div>
