@php
    $vto = $fila->fecha_vencimiento ?? null;
    if ($vto instanceof \Carbon\Carbon || $vto instanceof \DateTimeInterface) {
        $vto = $vto->format('Y-m-d');
    }
    $hija = $fila->hijas ?? null;
@endphp
<tr class="item-sp-cuota">
    <td>
        <input type="number" min="1" name="nro_cuotas[]" class="form-control nro-cuota"
               value="{{ $fila->nro_cuota ?? 1 }}">
    </td>
    <td>
        <input type="date" name="fecha_vencimientos_cuota[]" class="form-control" value="{{ $vto }}">
    </td>
    <td>
        <input type="number" step="0.01" name="montos_cuota[]" class="form-control" value="{{ $fila->monto ?? 0 }}">
    </td>
    <td>
        <input type="hidden" name="solicitudpago_hija_ids[]" value="{{ $fila->solicitudpago_hija_id ?? '' }}">
        @if ($hija)
            <a href="{{ route('editar_solicitudpago', $hija->id) }}" target="_blank" rel="noopener">
                #{{ $hija->codigo }}
            </a>
        @elseif (!empty($fila->solicitudpago_hija_id))
            ID {{ $fila->solicitudpago_hija_id }}
        @else
            <span class="text-muted">Pendiente</span>
        @endif
    </td>
    <td class="text-center">
        <button type="button" class="btn-accion-tabla eliminar_sp_cuota tooltipsC" title="Eliminar cuota">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
