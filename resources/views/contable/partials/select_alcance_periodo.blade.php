@php
    $jerarquia = $jerarquia_alcances
        ?? \App\Support\Contable\PeriodoContableCierreSupport::jerarquiaAgenda();
    $valorSeleccionado = old($name ?? 'alcance', $selected ?? 'general');
    $incluirGeneral = $incluir_general ?? true;
    $esRequerido = $required ?? true;
    $claseSelect = $class ?? 'form-control';
    $nombreCampo = $name ?? 'alcance';
@endphp
<select name="{{ $nombreCampo }}" class="{{ $claseSelect }}"
    @if ($esRequerido)
        required
    @endif
>
    @if ($incluirGeneral)
        <option value="general" @selected($valorSeleccionado === 'general')>
            General (todos los módulos)
        </option>
    @endif
    @foreach ($jerarquia as $modulo)
        <optgroup label="{{ $modulo['etiqueta'] }}">
            <option value="{{ $modulo['codigo'] }}" @selected($valorSeleccionado === $modulo['codigo'])>
                Todo el módulo {{ $modulo['etiqueta'] }}
            </option>
            @foreach ($modulo['hijos'] as $hijo)
                <option value="{{ $hijo['codigo'] }}" @selected($valorSeleccionado === $hijo['codigo'])>
                    {{ $hijo['etiqueta'] }}
                </option>
            @endforeach
        </optgroup>
    @endforeach
</select>
