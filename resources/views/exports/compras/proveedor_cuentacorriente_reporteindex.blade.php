@php
    use App\Support\Compras\ProveedorCuentacorrienteReporteFiltros;
    $filaLogo = $reservarFilaLogoExcel ?? false;
    $modoDeuda = ! empty($modoDeuda)
        || (($filtros['modo'] ?? ProveedorCuentacorrienteReporteFiltros::MODO_DEUDA)
            !== ProveedorCuentacorrienteReporteFiltros::MODO_FICHA);
    $stats = $resultado['stats'] ?? [];
    $colspan = 12;
@endphp
<table>
    @if ($filaLogo)
        <tr>
            <td colspan="{{ $colspan }}" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $colspan }}" style="font-weight: bold; font-size: 16px;">{{ $titulo ?? 'Cuenta corriente proveedores' }}</td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (! empty($subtitulo))
        <tr>
            <td colspan="{{ $colspan }}">{{ $subtitulo }}</td>
        </tr>
    @endif
    @if (! empty($stats))
        <tr>
            <td colspan="{{ $colspan }}">
                Proveedores: {{ $stats['proveedores'] ?? 0 }}
                · Movimientos: {{ $stats['movimientos'] ?? 0 }}
                @if (($stats['aplicaciones'] ?? 0) > 0)
                    · Aplicaciones: {{ $stats['aplicaciones'] }}
                @endif
            </td>
        </tr>
    @endif
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
                <th>Importe</th>
                <th>Aplicado</th>
                <th>Saldo pend.</th>
                <th></th>
                <th></th>
            @else
                <th>Debe</th>
                <th>Haber</th>
                <th>Saldo</th>
                <th></th>
                <th></th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            @php $tipo = $fila['tipo'] ?? ''; @endphp
            @if ($tipo === 'header_empresa')
                <tr>
                    <td></td>
                    <td>Empresa: {{ $fila['nombreempresa'] ?? $fila['empresa_nombre'] ?? '' }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                @continue
            @endif
            <tr>
                <td>{{ in_array($tipo, ['header_proveedor', 'total_proveedor'], true) ? ($fila['proveedor_codigo'] ?? '') : '' }}</td>
                <td>
                    @if ($tipo === 'header_proveedor')
                        {{ $fila['proveedor_nombre'] ?? '' }}
                    @elseif ($tipo === 'total_proveedor')
                        Total {{ $fila['proveedor_nombre'] ?? '' }}
                    @elseif ($tipo === 'saldo_anterior')
                        {{ $fila['comprobante'] ?? 'Saldo anterior' }}
                    @endif
                </td>
                <td>{{ in_array($tipo, ['header_proveedor', 'aplicacion', 'saldo_anterior', 'movimiento'], true) ? ($fila['nombreempresa'] ?? '') : '' }}</td>
                <td>{{ $fila['fecha'] ?? '' }}</td>
                <td>{{ $fila['fechavencimiento'] ?? '' }}</td>
                <td>{{ $tipo === 'header_proveedor' ? '' : ($fila['comprobante'] ?? '') }}</td>
                <td>{{ $fila['etiqueta_moneda'] ?? ($fila['abreviatura'] ?? '') }}</td>
                @if ($modoDeuda)
                    <td>{{ isset($fila['importe']) ? number_format((float) $fila['importe'], 2, '.', '') : '' }}</td>
                    <td>{{ isset($fila['aplicado']) ? number_format((float) $fila['aplicado'], 2, '.', '') : '' }}</td>
                    <td>{{ isset($fila['saldo_pendiente']) ? number_format((float) $fila['saldo_pendiente'], 2, '.', '') : '' }}</td>
                    <td></td>
                    <td></td>
                @else
                    <td>{{ isset($fila['debe']) ? number_format((float) $fila['debe'], 2, '.', '') : '' }}</td>
                    <td>{{ isset($fila['haber']) ? number_format((float) $fila['haber'], 2, '.', '') : '' }}</td>
                    <td>{{ isset($fila['saldo']) ? number_format((float) $fila['saldo'], 2, '.', '') : '' }}</td>
                    <td></td>
                    <td></td>
                @endif
            </tr>
        @endforeach
    </tbody>
</table>
