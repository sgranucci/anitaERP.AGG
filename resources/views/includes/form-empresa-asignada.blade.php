{{--
    Empresa del usuario (EmpresaRepository::allFiltrado()).
    Variables: $empresa_query (collection), $empresa_id (valor seleccionado), $solo_lectura (bool opcional, ej. edición).
    Opcionales: $label, $col_label, $col_input, $required, $id, $name.
--}}
@php
    $empresasDisponibles = collect($empresa_query ?? []);
    $empresaUnica = $empresasDisponibles->count() === 1;
    $empresaUnicaRegistro = $empresaUnica ? $empresasDisponibles->first() : null;
    $empresaIdValor = old($name ?? 'empresa_id', $empresa_id ?? ($empresaUnicaRegistro?->id ?? ''));
    $inputId = $id ?? 'empresa_id';
    $inputName = $name ?? 'empresa_id';
    $labelText = $label ?? 'Empresa';
    $colLabel = $col_label ?? 'col-lg-3';
    $colInput = $col_input ?? 'col-lg-5';
    $esRequerido = $required ?? true;
    $bloqueado = ($solo_lectura ?? false) || $empresaUnica;
@endphp

<div class="form-group row">
    <label for="{{ $inputId }}" class="{{ $colLabel }} control-label {{ $esRequerido ? 'requerido' : '' }}">{{ $labelText }}</label>
    <div class="{{ $colInput }}">
        @if ($bloqueado && $empresaUnicaRegistro)
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" value="{{ $empresaUnicaRegistro->id }}"/>
            <input type="text" class="form-control" readonly value="{{ $empresaUnicaRegistro->nombre }}"/>
        @elseif ($bloqueado && ! $empresaUnicaRegistro)
            @php
                $empresaNombre = $empresasDisponibles->firstWhere('id', (int) $empresaIdValor)?->nombre ?? '—';
            @endphp
            <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" value="{{ $empresaIdValor }}"/>
            <input type="text" class="form-control" readonly value="{{ $empresaNombre }}"/>
        @else
            <select name="{{ $inputName }}" id="{{ $inputId }}" class="form-control" @if($esRequerido) required @endif>
                <option value="">— Seleccionar —</option>
                @foreach ($empresasDisponibles as $emp)
                    <option value="{{ $emp->id }}" @selected((string) $empresaIdValor === (string) $emp->id)>{{ $emp->nombre }}</option>
                @endforeach
            </select>
        @endif
    </div>
</div>
