@php
    $seleccionados = old('recepcion_proveedor_ids', $recepciones_seleccionadas ?? []);
    $bloqueado = ($esEdicion ?? false) && ($data->estado ?? '') === \App\Support\Compras\ComprobanteProveedorEstados::CONTABILIZADO;
@endphp

@if (($data->ordencompra_id ?? null) && ($recepciones_disponibles ?? collect())->isNotEmpty() || count($seleccionados) > 0)
<div id="cp-bloque-recepciones-com" class="mt-4" style="{{ old('modo_carga', $data->modo_carga ?? '') === \App\Support\Compras\ComprobanteProveedorModoCarga::ASIGNA_RECEPCION ? '' : 'display:none;' }}">
    <h4 class="mb-2">Recepciones COM (factura contra recepción)</h4>
    <p class="text-muted small">
        Seleccione las recepciones COM a facturar. Al contabilizar, el asiento de la recepción no se modifica:
        la factura debita la provisión (neto COM), los impuestos y —si el neto supera la COM— la diferencia prorrateada
        en las cuentas de compra de los artículos; el haber va a la cuenta de proveedores.
    </p>
  @if ($bloqueado)
    <ul class="list-unstyled mb-0">
        @foreach ($data->comprobante_proveedor_recepciones ?? [] as $vinculo)
            <li><small>Recepción #{{ $vinculo->recepcion_proveedor_id }}</small></li>
        @endforeach
    </ul>
  @else
    <table class="table table-sm table-bordered">
        <thead style="background-color:#85C1E9;color:#17202A;">
            <tr>
                <th class="width40"></th>
                <th>ID</th>
                <th>Fecha</th>
                <th>Número</th>
                <th>Asiento provisión</th>
            </tr>
        </thead>
        <tbody>
            @php
                $mostrarIds = collect($seleccionados)
                    ->merge(($recepciones_disponibles ?? collect())->pluck('id'))
                    ->unique()
                    ->values();
            @endphp
            @forelse ($mostrarIds as $recepcionId)
                @php
                    $recepcion = ($recepciones_disponibles ?? collect())->firstWhere('id', $recepcionId)
                        ?? optional($data->comprobante_proveedor_recepciones ?? collect())->firstWhere('recepcion_proveedor_id', $recepcionId)?->recepcion_proveedores;
                @endphp
                @if ($recepcion)
                <tr>
                    <td>
                        <input type="checkbox" name="recepcion_proveedor_ids[]" value="{{ $recepcion->id }}"
                            @if (in_array((int) $recepcion->id, array_map('intval', $seleccionados), true)) checked @endif>
                    </td>
                    <td>{{ $recepcion->id }}</td>
                    <td><small>{{ $recepcion->fecha ? $recepcion->fecha->format('d/m/Y') : '' }}</small></td>
                    <td><small>{{ $recepcion->numerorecepcion ?? '' }}</small></td>
                    <td><small>#{{ $recepcion->asiento_id ?? '—' }}</small></td>
                </tr>
                @endif
            @empty
                <tr><td colspan="5" class="text-muted">No hay recepciones COM disponibles para esta OC.</td></tr>
            @endforelse
        </tbody>
    </table>
  @endif
</div>
@endif
