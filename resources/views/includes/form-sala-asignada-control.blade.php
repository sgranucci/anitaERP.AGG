{{--
    Solo el control (hidden/readonly/select) de sala Crown, sin form-group.
    Variables: $sala_query, $sala_id, $solo_lectura (opcional).
    Opcionales: $required, $id, $name, $permite_vacio, $opcion_vacia, $select_class,
    $empresa_input_id (default empresa_id: al cambiar sala se sincroniza empresa).
--}}
@php
    $salasDisponibles = collect($sala_query ?? []);
    $salaUnica = $salasDisponibles->count() === 1;
    $salaUnicaRegistro = $salaUnica ? $salasDisponibles->first() : null;
    $inputName = $name ?? 'sala_id';
    $inputId = $id ?? $inputName;
    $salaIdValor = $sala_id ?? ($salaUnicaRegistro?->id ?? '');
    if (! str_contains($inputName, '[]')) {
        $salaIdValor = old($inputName, $salaIdValor);
    }
    $esRequerido = $required ?? true;
    $permiteVacio = $permite_vacio ?? false;
    $opcionVacia = $opcion_vacia ?? '— Seleccionar —';
    $mostrarOpcionVacia = $mostrar_opcion_vacia ?? ($permiteVacio || ($esRequerido && ! $salaUnica));
    $selectClass = trim(($select_class ?? '').' form-control');
    $bloqueado = ($solo_lectura ?? false) || ($salaUnica && ! $permiteVacio);
    $salaIdValorInt = (int) $salaIdValor;
    $salaRegistro = $salaIdValorInt > 0
        ? $salasDisponibles->first(static function ($sala) use ($salaIdValorInt) {
            return (int) ($sala->id ?? 0) === $salaIdValorInt;
        })
        : null;
    $salaFueraAsignacion = $salaIdValorInt > 0 && $salaRegistro === null;
    if ($salaFueraAsignacion) {
        $salaFuera = \App\Models\Configuracion\Sala::query()
            ->whereKey($salaIdValorInt)
            ->first(['id', 'codigo', 'nombre', 'empresa_id']);
        $salaFueraEtiqueta = $salaFuera
            ? trim(($salaFuera->codigo ?? '').' — '.($salaFuera->nombre ?? ''))
            : ('Sala #'.$salaIdValorInt);
        $salaFueraEmpresaId = (int) ($salaFuera->empresa_id ?? 0);
    }
    $etiquetaSala = static function ($sala): string {
        return trim((string) ($sala->codigo ?? '').' — '.(string) ($sala->nombre ?? ''));
    };
    $empresaInputId = $empresa_input_id ?? 'empresa_id';
@endphp
@if ($salaFueraAsignacion)
    <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="{{ $select_class ?? '' }}" value="{{ $salaIdValorInt }}"
           data-empresa="{{ $salaFueraEmpresaId }}"/>
    <input type="text" class="form-control" readonly value="{{ $salaFueraEtiqueta }}" title="Sala no asignada a tu usuario; se conserva al guardar"/>
@elseif ($bloqueado && $salaIdValorInt > 0)
    <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="{{ $select_class ?? '' }}" value="{{ $salaIdValorInt }}"
           data-empresa="{{ (int) ($salaRegistro->empresa_id ?? 0) }}"/>
    <input type="text" class="form-control" readonly value="{{ $salaRegistro ? $etiquetaSala($salaRegistro) : '—' }}"/>
@elseif ($bloqueado && $salaUnicaRegistro)
    <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="{{ $select_class ?? '' }}" value="{{ $salaUnicaRegistro->id }}"
           data-empresa="{{ (int) ($salaUnicaRegistro->empresa_id ?? 0) }}"/>
    <input type="text" class="form-control" readonly value="{{ $etiquetaSala($salaUnicaRegistro) }}"/>
@elseif ($bloqueado && ! $salaUnicaRegistro)
    <input type="hidden" name="{{ $inputName }}" id="{{ $inputId }}" class="{{ $select_class ?? '' }}" value="{{ $salaIdValor }}"/>
    <input type="text" class="form-control" readonly value="—"/>
@else
    <select name="{{ $inputName }}" id="{{ $inputId }}" class="{{ $selectClass }}"
        @if ($esRequerido)
            required
        @endif
        onchange="var o=this.options[this.selectedIndex]; var e=document.getElementById(@json($empresaInputId)); if(e&&o&&o.dataset.empresa){ e.value=o.dataset.empresa; }"
    >
        @if ($mostrarOpcionVacia)
            <option value="">{{ $opcionVacia }}</option>
        @endif
        @foreach ($salasDisponibles as $sala)
            <option value="{{ $sala->id }}"
                    data-empresa="{{ $sala->empresa_id }}"
                    @selected((string) $salaIdValor === (string) $sala->id)>{{ $etiquetaSala($sala) }}</option>
        @endforeach
    </select>
@endif
