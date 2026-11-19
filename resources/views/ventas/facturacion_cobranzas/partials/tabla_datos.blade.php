@php
    $paraPdf = ! empty($para_pdf);
    $puedeVerVenta = ! empty($puede_ver_venta) && ! $paraPdf;
    $puedeVerCliente = ! empty($puede_ver_cliente) && ! $paraPdf;
    $puedeVerCobranza = ! empty($puede_ver_cobranza) && ! $paraPdf;
    $soloTbody = ! empty($solo_tbody);
@endphp
@if (! $soloTbody)
<thead>
    <tr>
        <th>Tip</th>
        <th>Número</th>
        <th>Fecha</th>
        <th>Cliente</th>
        <th>Nombre</th>
        <th class="text-right">Kilos</th>
        <th class="text-right">Abastos</th>
        <th class="text-right">Gravado</th>
        <th class="text-right">Varios</th>
        <th class="text-right">Total comp.</th>
        <th class="text-right">Efectivo</th>
        <th class="text-right">Cheques</th>
        <th class="text-right">Valores</th>
        <th class="text-right">Varios cobro</th>
        <th class="text-right">Total cobro</th>
        <th>Fuente</th>
    </tr>
</thead>
@endif
<tbody>
@forelse ($filas ?? [] as $fila)
    @php
        $tipo = (string) ($fila['tipo_fila'] ?? 'factura');
        $esCorte = in_array($tipo, ['subtotal_reparto', 'total_final'], true);
        $trClass = $esCorte ? 'font-weight-bold' : '';
        if ($tipo === 'total_final') {
            $trClass .= ' table-info';
        } elseif ($tipo === 'subtotal_reparto') {
            $trClass .= ' table-secondary';
        }
        $fmt = static function ($v) {
            $n = (float) $v;
            return abs($n) < 0.0001 ? '' : number_format($n, 2, ',', '.');
        };
        $fmtExcel = static function ($v) {
            $n = (float) $v;
            return abs($n) < 0.0001 ? '' : number_format($n, 2, '.', '');
        };
        $usarExcel = ! empty($para_excel);
        $num = $usarExcel ? $fmtExcel : $fmt;
    @endphp
    <tr class="{{ $trClass }}">
        @if ($esCorte)
            <td colspan="5">{{ $fila['cliente_nombre'] ?? '' }}</td>
        @else
            <td>{{ $fila['tip'] ?? '' }}</td>
            <td>
                @if ($tipo === 'factura' && $puedeVerVenta && (int) ($fila['venta_id'] ?? 0) > 0)
                    <a class="text-primary" target="_blank" rel="noopener"
                        href="{{ route('editar_factura', ['id' => (int) $fila['venta_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                        {{ $fila['numero'] ?? '' }}
                    </a>
                @elseif ($tipo === 'cobranza' && $puedeVerCobranza && (int) ($fila['cobranza_id'] ?? 0) > 0)
                    <a class="text-primary" target="_blank" rel="noopener"
                        href="{{ route('editar_cobranza', ['id' => (int) $fila['cobranza_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                        {{ $fila['numero'] ?? '' }}
                    </a>
                @else
                    {{ $fila['numero'] ?? '' }}
                @endif
            </td>
            <td>{{ $fila['fecha'] ?? '' }}</td>
            <td>
                @if ($puedeVerCliente && (int) ($fila['cliente_id'] ?? 0) > 0)
                    <a class="text-primary" target="_blank" rel="noopener"
                        href="{{ route('editar_cliente', ['id' => (int) $fila['cliente_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                        {{ $fila['cliente_codigo'] ?? '' }}
                    </a>
                @else
                    {{ $fila['cliente_codigo'] ?? '' }}
                @endif
            </td>
            <td>{{ $fila['cliente_nombre'] ?? '' }}</td>
        @endif
        <td class="text-right">{{ $num($fila['kilos'] ?? 0) }}</td>
        <td class="text-right">{{ $num($fila['abastos'] ?? 0) }}</td>
        <td class="text-right">{{ $num($fila['gravado'] ?? 0) }}</td>
        <td class="text-right">{{ $num($fila['varios_comp'] ?? 0) }}</td>
        <td class="text-right">{{ $num($fila['total_comp'] ?? 0) }}</td>
        <td class="text-right">{{ $num($fila['efectivo'] ?? 0) }}</td>
        <td class="text-right">{{ $num($fila['cheques'] ?? 0) }}</td>
        <td class="text-right">{{ $num($fila['valores'] ?? 0) }}</td>
        <td class="text-right">{{ $num($fila['varios_cobro'] ?? 0) }}</td>
        <td class="text-right">{{ $num($fila['total_cobro'] ?? 0) }}</td>
        <td>{{ $esCorte ? '' : strtoupper((string) ($fila['fuente'] ?? '')) }}</td>
    </tr>
@empty
    <tr>
        <td colspan="16" class="text-center text-muted">Sin movimientos para el filtro.</td>
    </tr>
@endforelse
</tbody>
