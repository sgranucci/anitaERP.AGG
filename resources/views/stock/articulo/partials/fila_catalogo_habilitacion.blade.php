<tr class="acl-hab-fila" data-idx="{{ $i }}">
    <td>
        <select name="acl_hab_alcance[]" class="form-control form-control-sm acl-hab-alcance">
            <option value="rol" @selected(($hab['alcance'] ?? '') === 'rol')>Rol</option>
            <option value="usuario" @selected(($hab['alcance'] ?? '') === 'usuario')>Usuario</option>
        </select>
    </td>
    <td>
        <div class="acl-hab-rol align-items-center" style="display:flex;gap:4px;">
            <input type="hidden" name="acl_hab_rol_id[]" class="acl-rol-id" value="{{ (int) ($hab['rol_id'] ?? 0) ?: '' }}">
            <button type="button" class="btn-accion-tabla consultalogisticarol" title="Consulta roles"><i class="fa fa-search text-primary"></i></button>
            <input type="text" name="acl_hab_rol_nombre[]" class="form-control form-control-sm acl-rol-nombre" value="{{ $hab['rol_nombre'] ?? '' }}" placeholder="Nombre del rol" autocomplete="off">
        </div>
        <div class="acl-hab-usuario align-items-center mt-1" style="display:flex;gap:4px;">
            <input type="hidden" name="acl_hab_usuario_id[]" id="acl_hab_usuario_{{ $i }}" class="usuario_id" value="{{ (int) ($hab['usuario_id'] ?? 0) ?: '' }}">
            <button type="button" class="btn-accion-tabla consultausuario" title="Consulta usuarios (F1)"
                data-ptrusuario_id="#acl_hab_usuario_{{ $i }}"
                data-ptrusuario_codigo="#acl_hab_usuario_codigo_{{ $i }}"
                data-ptrnombre="#acl_hab_usuario_nombre_{{ $i }}"
                data-omitir_filtro_empresa="1">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" name="acl_hab_usuario_codigo[]" id="acl_hab_usuario_codigo_{{ $i }}" class="form-control form-control-sm" value="{{ $hab['usuario_codigo'] ?? '' }}" placeholder="Usuario" autocomplete="off" style="width:8rem;">
            <input type="text" name="acl_hab_usuario_nombre[]" id="acl_hab_usuario_nombre_{{ $i }}" class="form-control form-control-sm" value="{{ $hab['usuario_nombre'] ?? '' }}" placeholder="Nombre" readonly>
        </div>
    </td>
    <td>
        <select name="acl_hab_habilitado[]" class="form-control form-control-sm">
            <option value="1" @selected(($hab['habilitado'] ?? '1') === '1' || ($hab['habilitado'] ?? '') === true)>Sí</option>
            <option value="0" @selected(($hab['habilitado'] ?? '1') === '0' || ($hab['habilitado'] ?? '') === false)>No</option>
        </select>
    </td>
    <td>
        <button type="button" class="btn-accion-tabla acl-quitar-hab" title="Quitar"><i class="fa fa-times-circle text-danger"></i></button>
    </td>
</tr>
