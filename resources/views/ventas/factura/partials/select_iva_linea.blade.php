@php
    $impuestoIdSeleccionado = (int) ($impuestoIdSeleccionado ?? 0);
    $impuesto_query = $impuesto_query ?? collect();
@endphp
<select name="impuesto_ids[]" class="form-control impuesto_id factura-iva-linea" title="Alícuota IVA">
    <option value="">IVA</option>
    @foreach ($impuesto_query as $impuestoIva)
        <option value="{{ $impuestoIva->id }}" @if ((int) $impuestoIva->id === $impuestoIdSeleccionado) selected @endif>
            {{ $impuestoIva->nombre }}
        </option>
    @endforeach
</select>
