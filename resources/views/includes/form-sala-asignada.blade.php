{{--
    Select de sala (Crown). Variables:
    $sala_query (colección o id=>nombre), $sala_id, opcional $required, $col_label, $col_input, $ayuda
--}}
@php
    $colLabel = $col_label ?? 'col-lg-3 col-form-label';
    $colInput = $col_input ?? 'col-lg-8';
    $required = !empty($required);
    $salas = collect($sala_query ?? []);
    if ($salas->isNotEmpty() && ! is_object($salas->first())) {
        $salas = $salas->map(fn ($nombre, $id) => (object) ['id' => (int) $id, 'nombre' => $nombre, 'codigo' => ''])->values();
    }
    $valor = old('sala_id', $sala_id ?? null);
@endphp
<div class="form-group row">
    <label for="sala_id" class="{{ $colLabel }}{{ $required ? ' requerido' : '' }}">Sala</label>
    <div class="{{ $colInput }}">
        <select name="sala_id" id="sala_id" class="form-control" {{ $required ? 'required' : '' }}>
            <option value="">{{ $required ? 'Seleccione sala' : 'Sin sala' }}</option>
            @foreach ($salas as $sala)
                <option value="{{ $sala->id }}" {{ (int) $valor === (int) $sala->id ? 'selected' : '' }}>
                    {{ $sala->codigo ? $sala->codigo.' — ' : '' }}{{ $sala->nombre }}
                </option>
            @endforeach
        </select>
        @if (!empty($ayuda))
            <small class="form-text text-muted">{{ $ayuda }}</small>
        @endif
    </div>
</div>
