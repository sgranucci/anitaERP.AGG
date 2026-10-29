<tr class="item-cliente-aviso">
    <td>
        <input type="hidden" name="aviso_ids[]" value="{{ $fila['id'] ?? '' }}">
        <input type="hidden" name="aviso_creousuario_ids[]" value="{{ $fila['creousuario_id'] ?? auth()->id() }}">
        <select name="aviso_disparadores[]" class="form-control form-control-sm aviso-disparador">
            <option value="">-- Disparador --</option>
            @foreach ($disparadoresAviso as $codigo => $etiqueta)
                <option value="{{ $codigo }}"
                    @if (($fila['disparador'] ?? '') === $codigo)
                        selected
                    @endif
                >{{ $etiqueta }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <select name="aviso_modos[]" class="form-control form-control-sm aviso-modo">
            @foreach ($modosAviso as $codigo => $etiqueta)
                <option value="{{ $codigo }}"
                    @if (($fila['modo'] ?? 'aviso') === $codigo)
                        selected
                    @endif
                >{{ $etiqueta }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <textarea name="aviso_textos[]" class="form-control form-control-sm aviso-texto" rows="2" maxlength="2000" placeholder="Texto que se muestra">{{ $fila['texto'] ?? '' }}</textarea>
    </td>
    <td>
        <select name="aviso_acciones[]" class="form-control form-control-sm aviso-accion">
            <option value="">-- Acción --</option>
            @foreach ($accionesAviso as $codigo => $etiqueta)
                <option value="{{ $codigo }}"
                    @if (($fila['accion'] ?? '') === $codigo)
                        selected
                    @endif
                >{{ $etiqueta }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <input type="date" class="form-control form-control-sm aviso-desde" name="aviso_desdefechas[]" value="{{ $fila['desdefecha'] ?? '' }}">
    </td>
    <td>
        <input type="date" class="form-control form-control-sm aviso-hasta" name="aviso_hastafechas[]" value="{{ $fila['hastafecha'] ?? '' }}">
        <div class="custom-control custom-checkbox mt-1">
            <input type="checkbox" class="custom-control-input aviso-sin-vencimiento-check" id="{{ $checkId }}"
                @if (! empty($fila['sin_vencimiento']))
                    checked
                @endif
            >
            <input type="hidden" name="aviso_sin_vencimiento[]" class="aviso-sin-vencimiento" value="{{ ! empty($fila['sin_vencimiento']) ? '1' : '0' }}">
            <label class="custom-control-label" for="{{ $checkId }}">Sin vencimiento</label>
        </div>
    </td>
    <td class="text-center align-middle">
        <button type="button" title="Quitar este aviso" class="btn-accion-tabla eliminar_cliente_aviso tooltipsC">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
