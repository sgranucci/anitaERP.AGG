@php
    $empresaIdVal = (int) old('empresa_id', $nivel
        ? ($nivel->arbolaprobaciones->empresa_id ?? $empresa_id)
        : $empresa_id);
    $cc = $nivel?->centrocosto_ids;
    $usuario = $nivel?->usuarios;
    $ccId = (int) old('centrocosto_id', $nivel->centrocosto_id ?? 0);
    $ccCodigo = old('centrocosto_codigo', $cc->codigo ?? '');
    $ccNombre = old('centrocosto_nombre', $cc->nombre ?? '');
    $usuarioId = (int) old('usuario_id', $nivel->usuario_id ?? 0);
    $usuarioCodigo = old('usuario_codigo', $usuario->usuario ?? ($usuario->id ?? ''));
    $usuarioNombre = old('usuario_nombre', $usuario->nombre ?? '');
@endphp

<div class="form-group row">
    <label for="empresa_id" class="col-lg-3 col-form-label text-right requerido">Empresa</label>
    <div class="col-lg-6">
        @if ($nivel)
            <input type="text" class="form-control" value="{{ optional($nivel->arbolaprobaciones->empresas)->nombre }}" readonly>
            <input type="hidden" name="empresa_id" value="{{ $empresaIdVal }}">
            <small class="form-text text-muted">La empresa del circuito no se cambia al editar.</small>
        @else
            <select name="empresa_id" id="empresa_id" class="form-control" required>
                @foreach ($empresa_query as $emp)
                    <option value="{{ $emp->id }}" @selected($empresaIdVal === (int) $emp->id)>{{ $emp->nombre }}</option>
                @endforeach
            </select>
        @endif
    </div>
</div>

<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right requerido">Centro de costo</label>
    <div class="col-lg-7">
        <div class="tm-centrocosto-campo d-flex flex-nowrap align-items-center" style="gap:4px;">
            <input type="hidden" name="centrocosto_id" id="centrocosto_id" class="centrocosto_id" value="{{ $ccId ?: '' }}" required>
            <button type="button" title="Consulta centros de costo (F1)" class="btn-accion-tabla consultacentrocosto flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" name="centrocosto_codigo" id="centrocosto_codigo"
                   class="form-control codigocentrocosto" value="{{ $ccCodigo }}"
                   placeholder="Cód." autocomplete="off" style="width:5.5rem;flex-shrink:0;"
                   title="Código + Enter; F1 o lupa">
            <input type="text" name="centrocosto_nombre" id="centrocosto_descripcion"
                   class="form-control descripcioncentrocosto" value="{{ $ccNombre }}"
                   placeholder="Descripción" readonly style="min-width:0;flex:1 1 auto;">
        </div>
        <small class="text-muted">Código + Enter · <kbd>F1</kbd> o lupa</small>
    </div>
</div>

<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right requerido">Gerente</label>
    <div class="col-lg-7">
        <div class="tm-usuario-campo d-flex flex-nowrap align-items-center" style="gap:4px;">
            <input type="hidden" name="usuario_id" id="usuario_id_aprobador" class="usuario_id" value="{{ $usuarioId ?: '' }}" required>
            <input type="text" name="usuario_codigo" id="usuario_codigo"
                   class="usuario_codigo_arbol form-control" value="{{ $usuarioCodigo }}"
                   placeholder="Cód." autocomplete="off" style="width:5.5rem;flex-shrink:0;"
                   title="Login o ID; Enter valida; F1 consulta">
            <button type="button" title="Consulta usuarios (F1)" class="btn-accion-tabla consultausuario tooltipsC"
                    data-omitir_filtro_empresa="1">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" name="usuario_nombre" id="usuario_nombre"
                   class="nombreusuario form-control" value="{{ $usuarioNombre }}"
                   placeholder="Nombre del gerente" readonly style="min-width:0;flex:1 1 auto;">
        </div>
        <small class="text-muted">Usuario AnitaERP · código + Enter · <kbd>F1</kbd> o lupa</small>
    </div>
</div>
