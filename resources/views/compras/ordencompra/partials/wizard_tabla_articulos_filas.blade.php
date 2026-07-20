@php
    $articulosWizard = $wizardPlantilla['articulos'] ?? [];
    $centrocostoDefaultFilas = (int) ($centrocostoDefaultDestino ?? auth()->user()->centrocosto_id ?? 1);
@endphp
@forelse ($articulosWizard as $idx => $a)
    @php
        $fechaEntrega = substr((string) ($a['fechaentrega'] ?? ''), 0, 10);
        $ccDestino = (int) ($a['centrocostodestino_id'] ?? $centrocostoDefaultFilas);
        $partidaCodigo = (string) ($a['codigopartidagasto'] ?? '');
        $partidaDesc = (string) ($a['descripcionpartidagasto'] ?? '');
        $capexCodigo = (string) ($a['codigocapex'] ?? '');
        $capexDesc = (string) ($a['descripcioncapex'] ?? '');
        $colorNombre = (string) ($a['color_nombre'] ?? '');
        $talleNombre = (string) ($a['talle_nombre'] ?? '');
    @endphp
    <tr class="wizard-oc-fila-item" data-lin-idx="{{ $idx }}"
        data-requisicion-articulo-id="{{ (int) ($a['requisicion_articulo_id'] ?? 0) }}"
        data-articulo-id="{{ (int) ($a['articulo_id'] ?? 0) }}"
        data-sku="{{ e($a['sku'] ?? '') }}"
        data-descripcion="{{ e($a['descripcion_articulo'] ?? '') }}"
        data-color-id="{{ (int) ($a['color_id'] ?? 0) }}"
        data-talle-id="{{ (int) ($a['talle_id'] ?? 0) }}"
        data-color-nombre="{{ e($colorNombre) }}"
        data-talle-nombre="{{ e($talleNombre) }}">
        <td class="text-center">{{ $idx + 1 }}</td>
        <td>{{ $a['sku'] ?? '' }}</td>
        <td>{{ $a['descripcion_articulo'] ?? '' }}</td>
        <td>{{ $colorNombre !== '' ? $colorNombre : '—' }}</td>
        <td>{{ $talleNombre !== '' ? $talleNombre : '—' }}</td>
        <td>
            <input type="number" step="0.0001" class="form-control form-control-sm wz-lin-cantidad" value="{{ $a['cantidad'] ?? '' }}">
        </td>
        <td>
            <input type="number" step="0.0001" class="form-control form-control-sm wz-lin-precio" value="{{ $a['precio'] ?? '' }}">
        </td>
        <td>
            <select class="form-control form-control-sm wz-lin-moneda">
                @foreach ($moneda_query as $m)
                    <option value="{{ $m->id }}" @selected((int) $m->id === (int) ($a['moneda_id'] ?? 0))>{{ $m->abreviatura }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <input type="number" step="0.0001" min="0" class="form-control form-control-sm wz-lin-cotiz" value="{{ $a['cotizacion'] ?? 1 }}">
        </td>
        <td>
            <input type="date" class="form-control form-control-sm wz-lin-fechaentrega" value="{{ $fechaEntrega }}">
        </td>
        <td>
            <select class="form-control form-control-sm wz-lin-cc-destino">
                @foreach ($centrocosto_query as $cc)
                    <option value="{{ $cc->id }}" @selected((int) $cc->id === $ccDestino)>{{ $cc->codigo }} — {{ $cc->nombre }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <span class="small">
                {{ $partidaCodigo !== '' ? $partidaCodigo : '—' }}
                @if ($partidaDesc !== '')
                    <br><span class="text-muted small">{{ $partidaDesc }}</span>
                @endif
            </span>
        </td>
        <td>
            <span class="small">
                {{ $capexCodigo !== '' ? $capexCodigo : '—' }}
                @if ($capexDesc !== '')
                    <br><span class="text-muted small">{{ $capexDesc }}</span>
                @endif
            </span>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-primary wz-lin-btn-origen mb-1" title="Elegir origen del precio">
                <i class="fa fa-tags"></i> Origen
            </button><br>
            <button type="button" class="btn btn-sm btn-outline-secondary wz-lin-btn-proveedor mb-1" title="Elegir proveedor manualmente">
                <i class="fa fa-truck"></i> Proveedor
            </button><br>
            <span class="badge badge-pill wizard-oc-origen-pill sin-origen"><em>Sin origen</em></span>
        </td>
    </tr>
@empty
    <tr><td colspan="14" class="text-center text-muted py-3">La requisición no tiene ítems pendientes.</td></tr>
@endforelse
