@php
    $colLabel = 'col-lg-3 control-label text-right pr-2';
    $colInput = 'col-lg-7';
    $tareaId = $tareaId ?? '';
    $empleadoId = $empleadoId ?? '';
    $operacionId = (int) ($operacionId ?? 0);
@endphp
<style>
    .movot-etiquetas { display: flex; flex-wrap: wrap; gap: 0.4rem; }
    .movot-etiqueta { position: relative; margin: 0; cursor: pointer; font-weight: 600; min-width: 7.5rem; }
    .movot-etiqueta input {
        position: absolute;
        opacity: 0;
        width: 1px;
        height: 1px;
        margin: 0;
    }
    .movot-etiqueta:focus-within {
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.35);
    }
</style>
<div class="form-group row">
    <label for="fecha" class="{{ $colLabel }} requerido">Fecha</label>
    <div class="{{ $colInput }}">
        <input type="date" name="fecha" id="fecha" class="form-control required" style="max-width: 11.5rem;"
            value="{{ substr(old('fecha', $data->fecha ?? date('Y-m-d')), 0, 10) }}" required>
    </div>
</div>

@include('produccion.partials.campo_consulta_tarea', [
    'prefix' => 'movot',
    'label' => 'Tarea',
    'inputName' => 'tarea_id',
    'inputId' => 'tarea_movot_id',
    'tareaId' => $tareaId,
    'codigo' => $tareaCodigo ?? '',
    'descripcion' => $tareaNombre ?? '',
    'required' => true,
    'col_label' => $colLabel,
    'col_input' => $colInput,
    'next_focus' => '#movot-operacion-foco',
    'help' => 'F1 o la lupa abren la consulta. Enter resuelve el id y pasa a operación.',
])

<div class="form-group row">
    <label class="{{ $colLabel }} requerido" id="movot-operacion-label">Operación</label>
    <div class="{{ $colInput }}">
        <span id="movot-operacion-foco" tabindex="-1" class="sr-only">Operación</span>
        <div class="movot-etiquetas" id="movot-operacion" role="radiogroup" aria-labelledby="movot-operacion-label">
            @foreach ($operacion_query as $operacion)
                @php $elegida = (int) $operacion->id === $operacionId; @endphp
                <label class="btn btn-sm movot-etiqueta {{ $elegida ? 'btn-primary is-active' : 'btn-outline-secondary' }}">
                    <input type="radio" name="operacion_id" value="{{ $operacion->id }}"
                        class="{{ $loop->first ? 'required' : '' }}"
                        @if ($elegida) checked @endif
                        @if ($loop->first) required @endif>
                    {{ $operacion->nombre }}
                </label>
            @endforeach
        </div>
        <small class="form-text text-muted">Clic en la etiqueta, o flechas y Enter para pasar al empleado.</small>
    </div>
</div>

@include('produccion.partials.campo_consulta_empleado', [
    'prefix' => 'movot',
    'label' => 'Empleado',
    'inputName' => 'empleado_id',
    'inputId' => 'empleado_movot_id',
    'empleadoId' => $empleadoId,
    'codigo' => $empleadoCodigo ?? '',
    'descripcion' => $empleadoNombre ?? '',
    'required' => true,
    'col_label' => $colLabel,
    'col_input' => $colInput,
    'next_focus' => '#ordenestrabajo',
    'help' => 'F1 o la lupa abren la consulta. Enter resuelve el legajo y pasa a las órdenes.',
])

<div class="form-group row">
    <label for="ordenestrabajo" class="{{ $colLabel }} requerido">Órdenes de trabajo</label>
    <div class="{{ $colInput }}">
        <input type="text" name="ordenestrabajo" id="ordenestrabajo" class="form-control required"
            value="{{ old('ordenestrabajo', $data->ordenestrabajo ?? '') }}"
            placeholder="Códigos separados por coma" autocomplete="off" required>
        <small class="form-text text-muted">Enter pasa al botón de guardar. Varias órdenes, separadas por coma.</small>
    </div>
</div>
