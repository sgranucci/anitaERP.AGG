@php
    $fmtCant = static function ($v) {
        $v = (float) $v;
        if (abs($v) <= 0.0001) {
            return '';
        }

        return number_format($v, abs($v - round($v)) <= 0.0001 ? 0 : 2, ',', '.');
    };
    $fmtImp = static function ($v) {
        $v = (float) $v;
        if (abs($v) <= 0.0001) {
            return '';
        }

        return number_format($v, 2, ',', '.');
    };
@endphp
<thead>
    <tr>
        <th rowspan="2" class="align-middle">Art&iacute;culo</th>
        <th rowspan="2" class="align-middle">Descripci&oacute;n</th>
        <th rowspan="2" class="align-middle text-right">Costo unit.</th>
        <th rowspan="2" class="align-middle text-right">P.Vta.</th>
        <th rowspan="2" class="align-middle text-right">Cantidad<br>vendida tot.</th>
        <th colspan="2" class="text-center">Venta externa</th>
        <th colspan="2" class="text-center">Consumo interno</th>
        <th colspan="3" class="text-center">Valuaci&oacute;n</th>
    </tr>
    <tr>
        <th class="text-right">Cantidad</th>
        <th class="text-right">Importe total</th>
        <th class="text-right">Invitaciones</th>
        <th class="text-right">Staff</th>
        <th class="text-right">Interna al costo</th>
        <th class="text-right">Interna a P.Vta.</th>
        <th class="text-right">Externa a costo</th>
    </tr>
</thead>
<tbody>
    @forelse ($filas ?? [] as $fila)
        <tr>
            <td class="text-nowrap">
                @if (($puede_ver_articulo ?? false) && (int) ($fila['articulo_id'] ?? 0) > 0)
                    <a href="{{ route('editar_articulo', ['id' => $fila['articulo_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                       target="_blank" rel="noopener" class="text-primary">
                        {{ $fila['sku'] ?? '—' }}
                    </a>
                @else
                    {{ $fila['sku'] ?? '—' }}
                @endif
            </td>
            <td>{{ $fila['descripcion'] ?? '—' }}</td>
            <td class="text-right">{{ $fmtImp($fila['costo_unitario'] ?? 0) }}</td>
            <td class="text-right">{{ $fmtImp($fila['precio_venta'] ?? 0) }}</td>
            <td class="text-right">{{ $fmtCant($fila['cant_total'] ?? 0) }}</td>
            <td class="text-right">{{ $fmtCant($fila['cant_externa'] ?? 0) }}</td>
            <td class="text-right">{{ $fmtImp($fila['importe_externa'] ?? 0) }}</td>
            <td class="text-right">{{ $fmtCant($fila['cant_invitacion'] ?? 0) }}</td>
            <td class="text-right">{{ $fmtCant($fila['cant_staff'] ?? 0) }}</td>
            <td class="text-right">{{ $fmtImp($fila['venta_interna_costo'] ?? 0) }}</td>
            <td class="text-right">{{ $fmtImp($fila['venta_interna_precio_vta'] ?? 0) }}</td>
            <td class="text-right">{{ $fmtImp($fila['venta_externa_costo'] ?? 0) }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="12" class="text-center text-muted py-4">Sin ventas para los filtros indicados.</td>
        </tr>
    @endforelse
</tbody>
@if (! empty($totales ?? []))
    <tfoot>
        <tr class="table-active font-weight-bold">
            <td colspan="4" class="text-right">Totales</td>
            <td class="text-right">{{ $fmtCant($totales['cant_total'] ?? 0) }}</td>
            <td class="text-right">{{ $fmtCant($totales['cant_externa'] ?? 0) }}</td>
            <td class="text-right">{{ $fmtImp($totales['importe_externa'] ?? 0) }}</td>
            <td class="text-right">{{ $fmtCant($totales['cant_invitacion'] ?? 0) }}</td>
            <td class="text-right">{{ $fmtCant($totales['cant_staff'] ?? 0) }}</td>
            <td class="text-right">{{ $fmtImp($totales['venta_interna_costo'] ?? 0) }}</td>
            <td class="text-right">{{ $fmtImp($totales['venta_interna_precio_vta'] ?? 0) }}</td>
            <td class="text-right">{{ $fmtImp($totales['venta_externa_costo'] ?? 0) }}</td>
        </tr>
    </tfoot>
@endif
