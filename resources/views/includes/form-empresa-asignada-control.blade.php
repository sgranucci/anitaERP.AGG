{{--
    Solo el control (hidden/readonly/select), sin form-group. Para tablas o layouts custom.
    Mismas variables que form-empresa-asignada + $select_class (clases extra del select).
--}}
@php
    $empresasDisponibles = collect($empresa_query ?? []);
    $empresaUnica = $empresasDisponibles->count() === 1;
    $empresaUnicaRegistro = $empresaUnica ? $empresasDisponibles->first() : null;
    $inputName = $name ?? 'empresa_id';
    $inputId = $id ?? $inputName;
    $empresaIdValor = $empresa_id ?? ($empresaUnicaRegistro?->id ?? '');
    if (! str_contains($inputName, '[]')) {
        $empresaIdValor = old($inputName, $empresaIdValor);
    }
    $esRequerido = $required ?? true;
    $permiteVacio = $permite_vacio ?? false;
    $opcionVacia = $opcion_vacia ?? '— Seleccionar —';
    $mostrarId = $mostrar_id ?? false;
    $mostrarOpcionVacia = $mostrar_opcion_vacia ?? ($permiteVacio || $esRequerido);
    $selectClass = trim(($select_class ?? '').' form-control');
    $bloqueado = ($solo_lectura ?? false) || ($empresaUnica && ! $permiteVacio);
@endphp
@if ($bloqueado && $empresaUnicaRegistro)
    <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="{{ $select_class ?? '' }}" value="{{ $empresaUnicaRegistro->id }}"/>
    <input type="text" class="form-control" readonly value="{{ $empresaUnicaRegistro->nombre }}"/>
@elseif ($bloqueado && ! $empresaUnicaRegistro)
    @php
        $empresaNombre = $empresasDisponibles->firstWhere('id', (int) $empresaIdValor)?->nombre ?? '—';
    @endphp
    <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="{{ $select_class ?? '' }}" value="{{ $empresaIdValor }}"/>
    <input type="text" class="form-control" readonly value="{{ $empresaNombre }}"/>
@else
    <select name="{{ $inputName }}" id="{{ $inputId }}" class="{{ $selectClass }}" @if($esRequerido) required @endif @if($data_fouc ?? false) data-fouc @endif>
        @if ($mostrarOpcionVacia)
            <option value="">{{ $opcionVacia }}</option>
        @endif
        @foreach ($empresasDisponibles as $emp)
            @php
                $etiquetaEmp = $mostrarId ? trim($emp->id.' '.$emp->nombre) : $emp->nombre;
            @endphp
            <option value="{{ $emp->id }}"
                    @if(isset($emp->codigo)) data-codigo="{{ $emp->codigo }}" @endif
                    @selected((string) $empresaIdValor === (string) $emp->id)>{{ $etiquetaEmp }}</option>
        @endforeach
    </select>
@endif
