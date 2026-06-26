@php
    $seleccionados = old('recepcion_proveedor_ids', $recepciones_seleccionadas ?? []);
    $bloqueado = ($esEdicion ?? false) && ($data->estado ?? '') === \App\Support\Compras\ComprobanteProveedorEstados::CONTABILIZADO;
    $modoRecepcion = old('modo_carga', $data->modo_carga ?? '') === \App\Support\Compras\ComprobanteProveedorModoCarga::ASIGNA_RECEPCION;
    $hayRecepciones = ($recepciones_disponibles ?? collect())->isNotEmpty() || count($seleccionados) > 0;
    $comResolucion = $com_resolucion ?? null;
@endphp

@if ($modoRecepcion && $hayRecepciones)
<div id="cp-bloque-recepciones-com" class="mt-4">
    <h4 class="mb-2">Recepciones COM (factura contra recepción)</h4>
    <p class="text-muted small">
        Seleccione las recepciones COM a facturar. Al contabilizar, el asiento de la recepción no se modifica:
        la factura debita la provisión (neto COM), los impuestos y —si el neto supera la COM— la diferencia prorrateada
        en las cuentas de compra de los artículos; el haber va a la cuenta de proveedores.
    </p>
    @if (! empty($comResolucion['importe_comparacion']))
    <p class="text-muted small mb-2">
        Importe de referencia del comprobante ({{ $comResolucion['importe_comparacion_etiqueta'] ?? 'comparación' }}):
        <strong>${{ number_format((float) $comResolucion['importe_comparacion'], 2, ',', '.') }}</strong>
        — debe coincidir con el neto provisionado de la COM (letra A) o con el total si el proveedor es monotributo.
    </p>
    @endif
    @if (! empty($comResolucion['ambigua']) && ! empty($comResolucion['mensaje']))
    <div class="alert alert-warning py-2">
        <i class="fa fa-exclamation-triangle"></i> {{ $comResolucion['mensaje'] }}
    </div>
    @endif
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
                <th>OC</th>
                <th class="text-right">Neto COM</th>
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
                    $importeCom = (float) ($recepcion->importe_provision_com ?? 0);
                    $importeRef = (float) ($comResolucion['importe_comparacion'] ?? 0);
                    $coincide = $importeRef > 0 && abs($importeCom - $importeRef) <= 0.05;
                @endphp
                @if ($recepcion)
                <tr @if ($coincide) class="table-success" @endif>
                    <td>
                        <input type="checkbox" name="recepcion_proveedor_ids[]" value="{{ $recepcion->id }}"
                            @if (in_array((int) $recepcion->id, array_map('intval', $seleccionados), true)) checked @endif>
                    </td>
                    <td>{{ $recepcion->id }}</td>
                    <td><small>{{ $recepcion->fecha ? $recepcion->fecha->format('d/m/Y') : '' }}</small></td>
                    <td><small>{{ $recepcion->numerorecepcion ?? '' }}</small></td>
                    <td><small>{{ optional($recepcion->ordencompras)->numeroordencompra ?? ($recepcion->ordencompra_id ?? '—') }}</small></td>
                    <td class="text-right"><small>${{ number_format($importeCom, 2, ',', '.') }}</small></td>
                    <td><small>#{{ $recepcion->asiento_id ?? '—' }}</small></td>
                </tr>
                @endif
            @empty
                <tr><td colspan="7" class="text-muted">No hay recepciones COM disponibles en el legajo.</td></tr>
            @endforelse
        </tbody>
    </table>
  @endif
</div>
@endif
