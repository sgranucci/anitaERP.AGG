<div class="form-group row">
    <label for="fecha" class="col-lg-3 col-form-label requerido">Fecha</label>
    <div class="col-lg-3">
        <input type="date" name="fecha" id="fecha" class="form-control" value="{{ old('fecha', $data->fecha ?? date('Y-m-d')) }}" required>
    </div>
</div>
@include('includes.form-empresa-asignada', [
    'empresa_query' => $empresa_query,
    'empresa_id' => $data->empresa_id ?? null,
    'solo_lectura' => isset($data) && $data->id,
    'col_input' => 'col-lg-8',
])
<div class="form-group row">
    <label for="caja_id" class="col-lg-3 col-form-label requerido">Caja</label>
    <div class="col-lg-8">
        <select name="caja_id" id="caja_id" data-placeholder="Caja" class="form-control" data-fouc required>
            <option value="">— Seleccionar caja —</option>
            @foreach ($caja_query as $value)
                <option value="{{ $value->id }}" @selected((int) old('caja_id', $data->caja_id ?? 0) === (int) $value->id)>
                    {{ $value->id }} {{ $value->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
@php
    $usuarioIdVal = old('usuario_id', optional($data ?? null)->usuario_id ?? (isset($usuario_default) && ! isset($data) ? $usuario_default->id : ''));
    $usuarioCodigoVal = old('usuario_codigo', optional($data ?? null)->usuario_id ?? (isset($usuario_default) && ! isset($data) ? $usuario_default->id : ''));
    $usuarioNombreVal = old('nombreusuario', optional($data ?? null)->usuarios?->nombre ?? (isset($usuario_default) && ! isset($data) ? $usuario_default->nombre : ''));
@endphp
<div class="form-group row">
    <label for="usuario_codigo" class="col-lg-3 col-form-label requerido">Usuario</label>
    <div class="col-lg-8">
        <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
            <input type="hidden" name="usuario_id" id="usuario_id" class="usuario_id" value="{{ $usuarioIdVal }}">
            <input type="text" style="flex: 0 0 110px; width: 110px; height: 38px;" class="usuario_codigo_arbol form-control" id="usuario_codigo" value="{{ $usuarioCodigoVal }}" placeholder="ID usuario" title="ID numérico del usuario; Tab fuera para cargar el nombre" autocomplete="off" autocapitalize="off" spellcheck="false" inputmode="numeric">
            <button type="button" title="Consulta usuarios" style="padding: 1px; flex: 0 0 auto;" class="btn-accion-tabla consultausuario tooltipsC"
                data-ptrusuario_id="#usuario_id" data-ptrnombre="#nombreusuario" data-ptrusuario_codigo="#usuario_codigo">
                <i class="fa fa-search text-primary"></i>
            </button>
            <input type="text" style="flex: 1 1 auto; min-width: 0; height: 38px;" class="nombreusuario form-control" id="nombreusuario" value="{{ $usuarioNombreVal }}" placeholder="Nombre usuario" readonly autocomplete="off">
        </div>
    </div>
</div>
