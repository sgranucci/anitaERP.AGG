@php
    $medidas = $medidas ?? [];
    $filasLista = $filas ?? [];
    if ($filasLista instanceof \Illuminate\Pagination\LengthAwarePaginator) {
        $filasLista = $filasLista->items();
    }
    $tableClass = $table_class ?? 'table table-sm table-bordered table-striped mb-0';
    $tableId = empty($solo_thead_tbody) ? 'tabla-paginada' : '';
    $modoApertura = false;
    foreach ($filasLista as $f) {
        if (($f['tipo_fila'] ?? '') === 'apertura') {
            $modoApertura = true;
            break;
        }
    }
@endphp
@if (empty($solo_thead_tbody))
    <table class="{{ $tableClass }}" @if ($tableId) id="{{ $tableId }}" @endif>
@endif
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th>SKU</th>
            <th>Descripci&oacute;n</th>
            <th>Color</th>
            <th>Color desc.</th>
            @if ($modoApertura)
                <th>Concepto</th>
            @endif
            @foreach ($medidas as $med)
                <th class="text-right">
                    @if ((string) $med === '48')
                        UN
                    @elseif ((int) $med === 0)
                        —
                    @else
                        {{ $med }}
                    @endif
                </th>
            @endforeach
            <th class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filasLista as $fila)
            @php
                $articuloId = (int) ($fila['articulo_id'] ?? 0);
                $cants = $fila['cantidades'] ?? [];
                $total = (float) ($fila['total'] ?? 0);
                $concepto = (string) ($fila['concepto'] ?? '');
                $esStock = $concepto === 'Stock' || ($fila['tipo_fila'] ?? '') === 'saldo';
            @endphp
            <tr @if ($esStock) class="font-weight-bold" @endif>
                <td>
                    @if (($puede_ver_articulo ?? false) && $articuloId > 0)
                        <a href="{{ route('editar_articulo', ['id' => $articuloId, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                            class="text-primary" target="_blank" rel="noopener">
                            {{ $fila['sku'] ?? '' }}
                        </a>
                    @else
                        {{ $fila['sku'] ?? '' }}
                    @endif
                </td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td>{{ $fila['color'] ?? '' }}</td>
                <td>{{ $fila['color_desc'] ?? '' }}</td>
                @if ($modoApertura)
                    <td>{{ $concepto }}</td>
                @endif
                @foreach ($medidas as $med)
                    @php
                        $val = (float) ($cants[(string) $med] ?? 0);
                    @endphp
                    <td class="text-right {{ abs($val) < 0.000001 ? 'text-muted' : ($val < 0 ? 'text-danger' : '') }}">
                        @if (abs($val) >= 0.000001)
                            {{ number_format($val, 0, ',', '.') }}
                        @endif
                    </td>
                @endforeach
                <td class="text-right {{ $total < 0 ? 'text-danger' : '' }}">
                    @if (abs($total) >= 0.000001)
                        {{ number_format($total, 0, ',', '.') }}
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ 4 + ($modoApertura ? 1 : 0) + count($medidas) + 1 }}" class="text-center text-muted">
                    Sin movimientos / saldos para los filtros.
                </td>
            </tr>
        @endforelse
    </tbody>
@if (empty($solo_thead_tbody))
    </table>
@endif
