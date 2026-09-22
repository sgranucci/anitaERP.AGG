@php
    $codigo = (string) ($slot['codigo'] ?? '');
    $etiqueta = (string) ($slot['etiqueta'] ?? '');
    $pideVencimiento = ! empty($slot['pide_vencimiento']);
    $esLibre = $codigo === '';
    $claseEliminar = $claseEliminar ?? 'ingreso-eliminararchivo';
    $indice = $indice ?? null;
    $vencimientoOld = $indice === null ? '' : old('archivo_vencimiento.'.$indice);
@endphp
<tr class="item-archivo-ingreso" @if (! $esLibre) data-archivo-fijo="1" @endif>
    <td>
        @if (! $esLibre)
            <div class="font-weight-bold mb-1">{{ $etiqueta }}</div>
            <input type="hidden" name="archivo_tipo[]" value="{{ $codigo }}">
        @else
            <input type="hidden" name="archivo_tipo[]" value="">
        @endif
        <input type="file" name="nombrearchivos[]" class="form-control ingreso-nombrearchivos">
        @if ($pideVencimiento)
            <label class="small mb-0 mt-2 d-block">Vencimiento</label>
            <input type="date" name="archivo_vencimiento[]" class="form-control form-control-sm ingreso-archivo-vencimiento"
                   value="{{ $vencimientoOld }}">
        @else
            <input type="hidden" name="archivo_vencimiento[]" value="">
        @endif
    </td>
    <td class="text-center align-middle">
        @if ($esLibre)
            <button type="button" title="Quitar este rengl&oacute;n" class="btn-accion-tabla {{ $claseEliminar }} tooltipsC">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        @endif
    </td>
</tr>
