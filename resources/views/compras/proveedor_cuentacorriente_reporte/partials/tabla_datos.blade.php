@php
    use App\Support\Compras\ProveedorCuentacorrienteReporteFiltros;
    $modoDeuda = ($filtros['modo'] ?? ProveedorCuentacorrienteReporteFiltros::MODO_DEUDA)
        !== ProveedorCuentacorrienteReporteFiltros::MODO_FICHA;
    $mostrarLinks = ! empty($mostrarLinks);
    $paraPdf = ! empty($para_pdf);
    $paraExcel = ! empty($para_excel);
    $fmt = static function ($valor) use ($paraExcel) {
        if ($valor === null || $valor === '') {
            return '';
        }
        $n = (float) $valor;
        if ($paraExcel) {
            return number_format($n, 2, '.', '');
        }

        return number_format($n, 2, ',', '.');
    };
@endphp
<thead>
    <tr>
        <th>Código</th>
        <th>Proveedor</th>
        <th>Empresa</th>
        <th>Fecha</th>
        <th>Vencimiento</th>
        <th>Comprobante</th>
        <th>Moneda</th>
        @if ($modoDeuda)
            <th class="text-right">Importe</th>
            <th class="text-right">Aplicado</th>
            <th class="text-right">Saldo pend.</th>
        @else
            <th class="text-right">Debe</th>
            <th class="text-right">Haber</th>
            <th class="text-right">Saldo</th>
        @endif
        @if ($mostrarLinks && ! $paraPdf && ! $paraExcel)
            <th></th>
        @endif
    </tr>
</thead>
<tbody>
@forelse ($filas as $fila)
    @php
        $tipo = $fila['tipo'] ?? 'movimiento';
        $esHeader = $tipo === 'header_proveedor';
        $esTotal = $tipo === 'total_proveedor';
        $esApl = $tipo === 'aplicacion';
        $esSaldoAnt = $tipo === 'saldo_anterior';
        $trClass = $esHeader ? 'cc-rep-header' : ($esTotal ? 'cc-rep-total' : ($esApl ? 'cc-rep-apl' : ($esSaldoAnt ? 'cc-rep-saldo-ant' : '')));
    @endphp
    <tr class="{{ $trClass }}">
        <td>
            @if ($esHeader || $esTotal)
                @if ($mostrarLinks && ! empty($puede_ver_proveedor) && ! empty($fila['proveedor_id']))
                    <a class="text-primary" target="_blank" rel="noopener"
                        href="{{ route('editar_proveedor', ['id' => $fila['proveedor_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                        {{ $fila['proveedor_codigo'] ?? '' }}
                    </a>
                @else
                    {{ $fila['proveedor_codigo'] ?? '' }}
                @endif
            @endif
        </td>
        <td>
            @if ($esHeader)
                <strong>{{ $fila['proveedor_nombre'] ?? '' }}</strong>
            @elseif ($esTotal)
                <strong>Total {{ $fila['proveedor_nombre'] ?? '' }}</strong>
            @elseif ($esSaldoAnt)
                <em>{{ $fila['comprobante'] ?? 'Saldo anterior' }}</em>
            @endif
        </td>
        <td>{{ $esHeader ? ($fila['nombreempresa'] ?? '') : '' }}</td>
        <td>{{ $fila['fecha'] ?? '' }}</td>
        <td>{{ $fila['fechavencimiento'] ?? '' }}</td>
        <td>
            @if (! $esHeader)
                {{ $fila['comprobante'] ?? '' }}
            @endif
        </td>
        <td>{{ $fila['etiqueta_moneda'] ?? ($fila['abreviatura'] ?? '') }}</td>
        @if ($modoDeuda)
            <td class="text-right">
                @if ($esTotal)
                    <strong>{{ $fmt($fila['importe'] ?? null) }}</strong>
                @else
                    {{ $fmt($fila['importe'] ?? null) }}
                @endif
            </td>
            <td class="text-right">
                @if ($esTotal)
                    <strong>{{ $fmt($fila['aplicado'] ?? null) }}</strong>
                @else
                    {{ $fmt($fila['aplicado'] ?? null) }}
                @endif
            </td>
            <td class="text-right">
                @if ($esTotal)
                    <strong>{{ $fmt($fila['saldo_pendiente'] ?? null) }}</strong>
                @else
                    {{ $fmt($fila['saldo_pendiente'] ?? null) }}
                @endif
            </td>
        @else
            <td class="text-right">
                @if ($esTotal)
                    <strong>{{ $fmt($fila['debe'] ?? null) }}</strong>
                @else
                    {{ $fmt($fila['debe'] ?? null) }}
                @endif
            </td>
            <td class="text-right">
                @if ($esTotal)
                    <strong>{{ $fmt($fila['haber'] ?? null) }}</strong>
                @else
                    {{ $fmt($fila['haber'] ?? null) }}
                @endif
            </td>
            <td class="text-right">
                @if ($esTotal)
                    <strong>{{ $fmt($fila['saldo'] ?? null) }}</strong>
                @else
                    {{ $fmt($fila['saldo'] ?? null) }}
                @endif
            </td>
        @endif
        @if ($mostrarLinks && ! $paraPdf && ! $paraExcel)
            <td class="text-nowrap">
                @if (($fila['comprobante_proveedor_id'] ?? 0) > 0 && ! empty($puede_ver_comprobante))
                    <a class="btn-accion-tabla tooltipsC text-primary" target="_blank" rel="noopener"
                        href="{{ route('editar_comprobante_proveedor', ['id' => $fila['comprobante_proveedor_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                        title="Ver comprobante">
                        <i class="fas fa-file-invoice"></i>
                    </a>
                @endif
                @if (($fila['pagoproveedor_id'] ?? 0) > 0 && ! empty($puede_ver_pago))
                    <a class="btn-accion-tabla tooltipsC text-primary" target="_blank" rel="noopener"
                        href="{{ route('editar_pagoproveedor', ['id' => $fila['pagoproveedor_id']]) }}"
                        title="Ver orden de pago">
                        <i class="fa fa-money-bill"></i>
                    </a>
                @endif
                @if (($fila['proveedor_id'] ?? 0) > 0 && $esHeader && ! empty($puede_ver_proveedor))
                    <a class="btn-accion-tabla tooltipsC text-primary" target="_blank" rel="noopener"
                        href="{{ route('listar_cuentacorriente_proveedor', ['id' => $fila['proveedor_id']]) }}"
                        title="Abrir ficha CC del proveedor">
                        <i class="fa fa-folder-open"></i>
                    </a>
                @endif
            </td>
        @endif
    </tr>
@empty
    <tr>
        <td colspan="12" class="text-muted text-center">Sin datos.</td>
    </tr>
@endforelse
</tbody>
