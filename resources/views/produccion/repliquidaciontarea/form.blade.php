@php
    $colLabel = 'col-lg-2 control-label text-right pr-2';
    $colInput = 'col-lg-4';
    $valores = $valores ?? [];
@endphp

<div class="form-group row">
    <label for="desdefecha" class="{{ $colLabel }} requerido">Desde fecha</label>
    <div class="{{ $colInput }}">
        <input type="date" name="desdefecha" id="desdefecha" class="form-control"
            style="max-width: 11.5rem;"
            value="{{ $valores['desdefecha'] ?? date('Y-m-01') }}" required>
    </div>
    <label for="hastafecha" class="{{ $colLabel }} requerido">Hasta fecha</label>
    <div class="{{ $colInput }}">
        <input type="date" name="hastafecha" id="hastafecha" class="form-control"
            style="max-width: 11.5rem;"
            value="{{ $valores['hastafecha'] ?? date('Y-m-d') }}" required>
    </div>
</div>

@include('produccion.partials.campo_consulta_cliente', [
    'prefix' => 'desde',
    'label' => 'Desde cliente',
    'inputName' => 'desdecliente_id',
    'inputId' => 'desdecliente_id',
    'clienteId' => $valores['desdecliente_id'] ?? '',
    'codigo' => $valores['desdecliente_codigo'] ?? '',
    'descripcion' => $valores['desdecliente_nombre'] ?? '',
    'col_label' => $colLabel,
    'col_input' => $colInput,
    'next_focus' => '#hastacliente_id_codigo',
    'help' => 'Vac&iacute;o = primero. F1 / lupa; Enter avanza a Hasta.',
])
@include('produccion.partials.campo_consulta_cliente', [
    'prefix' => 'hasta',
    'label' => 'Hasta cliente',
    'inputName' => 'hastacliente_id',
    'inputId' => 'hastacliente_id',
    'clienteId' => $valores['hastacliente_id'] ?? '',
    'codigo' => $valores['hastacliente_codigo'] ?? '',
    'descripcion' => $valores['hastacliente_nombre'] ?? '',
    'col_label' => $colLabel,
    'col_input' => $colInput,
    'next_focus' => '#desdetarea_id_codigo',
    'help' => 'Vac&iacute;o = &uacute;ltimo.',
])

@include('produccion.partials.campo_consulta_tarea', [
    'prefix' => 'desde',
    'label' => 'Desde tarea',
    'inputName' => 'desdetarea_id',
    'inputId' => 'desdetarea_id',
    'tareaId' => $valores['desdetarea_id'] ?? '',
    'codigo' => $valores['desdetarea_codigo'] ?? '',
    'descripcion' => $valores['desdetarea_nombre'] ?? '',
    'col_label' => $colLabel,
    'col_input' => $colInput,
    'next_focus' => '#hastatarea_id_codigo',
    'help' => 'Vac&iacute;o = primera. F1 / lupa; Enter avanza a Hasta.',
])
@include('produccion.partials.campo_consulta_tarea', [
    'prefix' => 'hasta',
    'label' => 'Hasta tarea',
    'inputName' => 'hastatarea_id',
    'inputId' => 'hastatarea_id',
    'tareaId' => $valores['hastatarea_id'] ?? '',
    'codigo' => $valores['hastatarea_codigo'] ?? '',
    'descripcion' => $valores['hastatarea_nombre'] ?? '',
    'col_label' => $colLabel,
    'col_input' => $colInput,
    'next_focus' => '#desdeempleado_id_codigo',
    'help' => 'Vac&iacute;o = &uacute;ltima.',
])

@include('produccion.partials.campo_consulta_empleado', [
    'prefix' => 'desde',
    'label' => 'Desde empleado',
    'inputName' => 'desdeempleado_id',
    'inputId' => 'desdeempleado_id',
    'empleadoId' => $valores['desdeempleado_id'] ?? '',
    'codigo' => $valores['desdeempleado_codigo'] ?? '',
    'descripcion' => $valores['desdeempleado_nombre'] ?? '',
    'col_label' => $colLabel,
    'col_input' => $colInput,
    'next_focus' => '#hastaempleado_id_codigo',
    'help' => 'Vac&iacute;o = primero. F1 / lupa; Enter avanza a Hasta.',
])
@include('produccion.partials.campo_consulta_empleado', [
    'prefix' => 'hasta',
    'label' => 'Hasta empleado',
    'inputName' => 'hastaempleado_id',
    'inputId' => 'hastaempleado_id',
    'empleadoId' => $valores['hastaempleado_id'] ?? '',
    'codigo' => $valores['hastaempleado_codigo'] ?? '',
    'descripcion' => $valores['hastaempleado_nombre'] ?? '',
    'col_label' => $colLabel,
    'col_input' => $colInput,
    'next_focus' => '#desdearticulo_id_codigo',
    'help' => 'Vac&iacute;o = &uacute;ltimo.',
])

@include('produccion.partials.campo_consulta_articulo', [
    'prefix' => 'desde',
    'label' => 'Desde art&iacute;culo',
    'inputName' => 'desdearticulo_id',
    'inputId' => 'desdearticulo_id',
    'articuloId' => $valores['desdearticulo_id'] ?? '',
    'codigo' => $valores['desdearticulo_codigo'] ?? '',
    'descripcion' => $valores['desdearticulo_nombre'] ?? '',
    'col_label' => $colLabel,
    'col_input' => $colInput,
    'next_focus' => '#hastaarticulo_id_codigo',
    'help' => 'Vac&iacute;o = primero. F1 / lupa; Enter avanza a Hasta.',
])
@include('produccion.partials.campo_consulta_articulo', [
    'prefix' => 'hasta',
    'label' => 'Hasta art&iacute;culo',
    'inputName' => 'hastaarticulo_id',
    'inputId' => 'hastaarticulo_id',
    'articuloId' => $valores['hastaarticulo_id'] ?? '',
    'codigo' => $valores['hastaarticulo_codigo'] ?? '',
    'descripcion' => $valores['hastaarticulo_nombre'] ?? '',
    'col_label' => $colLabel,
    'col_input' => $colInput,
    'next_focus' => '#estadoot',
    'help' => 'Vac&iacute;o = &uacute;ltimo.',
])

<div class="form-group row mb-0">
    <label for="estadoot" class="{{ $colLabel }} requerido">Estado OT</label>
    <div class="{{ $colInput }}">
        <select name="estadoot" id="estadoot" class="form-control" required>
            <option value="">-- Elija estado de OT --</option>
            @foreach ($estadoOt_enum as $value => $etiqueta)
                <option value="{{ $value }}" @selected(($valores['estadoot'] ?? 'CUMPLIDA') === $value)>
                    {{ $etiqueta }}
                </option>
            @endforeach
        </select>
        <small class="form-text text-muted">
            <strong>Todas</strong> incluye tareas iniciadas en el rango a&uacute;n abiertas (en secci&oacute;n)
            y las que cerraron en el rango de fechas.
        </small>
    </div>
</div>
